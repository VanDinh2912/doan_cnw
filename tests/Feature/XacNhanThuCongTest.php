<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\SanPham;
use App\Models\User;
use App\Models\XacNhanThuCong;
use App\Services\ThanhToanChuyenKhoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** M5 — xác nhận thanh toán thủ công có bằng chứng + trang soát + thẻ tổng quan. */
class XacNhanThuCongTest extends TestCase
{
    use RefreshDatabase;

    private function donCho(array $overrides = []): DonHang
    {
        $sp = SanPham::orderBy('id')->firstOrFail();
        $sp->update(['soluong' => 10, 'trang_thai' => true]);

        return app(ThanhToanChuyenKhoanService::class)->taoHoaDonChoThanhToan(
            [['id' => $sp->id, 'soluong' => 1]], [], null,
            array_merge([
                'kenh' => 'online', 'phuong_thuc' => 'chuyenkhoan',
                'hinh_thuc_nhan' => 'tai_cua_hang',
                'ma_tra_cuu' => 'XN'.random_int(100000, 999999),
                'ten_khach_dat' => 'Khách XN', 'so_dienthoai_dat' => '0905555666',
            ], $overrides)
        );
    }

    public function test_thieu_bang_chung_bi_tu_choi_don_khong_doi(): void
    {
        $this->seed();
        $don = $this->donCho();
        $thuNgan = User::where('role', 'thungan')->firstOrFail();

        $this->actingAs($thuNgan)->postJson(route('donhang.xacnhan', $don), ['bang_chung' => 'ab'])
            ->assertStatus(422);
        $this->actingAs($thuNgan)->postJson(route('donhang.xacnhan', $don), [])
            ->assertStatus(422);
        $this->assertSame('cho_thanh_toan', $don->fresh()->trang_thai);
        $this->assertSame(0, XacNhanThuCong::count());
    }

    public function test_xac_nhan_ghi_day_du_truong(): void
    {
        $this->seed();
        $don = $this->donCho();
        $thuNgan = User::where('role', 'thungan')->firstOrFail();

        $this->actingAs($thuNgan)->postJson(route('donhang.xacnhan', $don), [
            'bang_chung' => '4 so cuoi: 8899',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertSame('da_thanh_toan', $don->fresh()->trang_thai);
        $xn = XacNhanThuCong::sole();
        $this->assertSame($don->id, $xn->invoice_id);
        $this->assertSame((int) $don->tong_thanh_toan, $xn->so_tien);
        $this->assertSame('4 so cuoi: 8899', $xn->bang_chung);
        $this->assertSame($thuNgan->id, $xn->nguoi_xac_nhan_id);
        $this->assertNotNull($xn->xac_nhan_luc);
        $this->assertNull($xn->soat_luc);
    }

    public function test_bam_dup_chi_mot_dong(): void
    {
        $this->seed();
        $don = $this->donCho();
        $thuNgan = User::where('role', 'thungan')->firstOrFail();

        $this->actingAs($thuNgan)->postJson(route('donhang.xacnhan', $don), ['bang_chung' => 'GD-0001'])
            ->assertOk();
        // Lần 2: đơn đã thanh toán → service báo already_processed, không tạo dòng mới
        $this->actingAs($thuNgan)->postJson(route('donhang.xacnhan', $don), ['bang_chung' => 'GD-0002'])
            ->assertStatus(422);
        $this->assertSame(1, XacNhanThuCong::count());
    }

    public function test_trang_soat_quyen_va_danh_dau_da_soat(): void
    {
        $this->seed();
        $don = $this->donCho();
        $thuNgan = User::where('role', 'thungan')->firstOrFail();
        $admin = User::where('role', 'admin')->firstOrFail();

        $this->actingAs($thuNgan)->postJson(route('donhang.xacnhan', $don), ['bang_chung' => 'GD-0001'])->assertOk();
        $xn = XacNhanThuCong::sole();

        // Thu ngân KHÔNG vào được trang soát (route nhóm admin)
        $this->actingAs($thuNgan)->get(route('doisoat.thucu'))->assertForbidden();

        // Admin thấy trong danh sách "chưa đối chiếu"
        $this->actingAs($admin)->get(route('doisoat.thucu'))
            ->assertOk()->assertSee($don->ma_hoadon)->assertSee('GD-0001');

        // Thẻ tổng quan admin hiện số đếm
        $this->actingAs($admin)->get(route('tongquan'))
            ->assertOk()->assertSee('Xác nhận thủ công chưa đối chiếu');

        // Đánh dấu đã soát (PIN tắt trong test env)
        $this->actingAs($admin)->postJson(route('doisoat.thucu.soat', $xn), ['ghi_chu_soat' => 'Khớp sao kê 23/11'])
            ->assertRedirect();
        $xn->refresh();
        $this->assertNotNull($xn->soat_luc);
        $this->assertSame($admin->id, $xn->soat_boi_id);
        $this->assertSame('Khớp sao kê 23/11', $xn->ghi_chu_soat);

        // Sau khi soát: biến mất khỏi "chưa đối chiếu", hiện ở "đã soát"
        $this->actingAs($admin)->get(route('doisoat.thucu', ['trang_thai_soat' => 'chua']))
            ->assertOk()->assertDontSee($don->ma_hoadon);
        $this->actingAs($admin)->get(route('doisoat.thucu', ['trang_thai_soat' => 'daxong']))
            ->assertOk()->assertSee($don->ma_hoadon);
    }

    public function test_lenh_khoi_tao_dry_run_va_apply_idempotent(): void
    {
        $this->seed();
        $don = $this->donCho();
        $thuNgan = User::where('role', 'thungan')->firstOrFail();
        $this->actingAs($thuNgan)->postJson(route('donhang.xacnhan', $don), ['bang_chung' => 'GD-0001'])->assertOk();

        // Xóa dòng để mô phỏng dữ liệu cũ chỉ có ghi_chu
        XacNhanThuCong::query()->delete();
        $don->update(['ghi_chu' => 'Thu ngân xác nhận thủ công']);

        Artisan::call('thu-cong:khoi-tao-tu-ghi-chu');
        $this->assertSame(0, XacNhanThuCong::count(), 'dry-run không được ghi');

        Artisan::call('thu-cong:khoi-tao-tu-ghi-chu', ['--apply' => true]);
        $this->assertSame(1, XacNhanThuCong::count());
        $xn = XacNhanThuCong::sole();
        $this->assertSame('Nhập lại từ ghi chú cũ', $xn->bang_chung);
        $this->assertNull($xn->nguoi_xac_nhan_id);

        // Idempotent: chạy lại không tạo thêm
        Artisan::call('thu-cong:khoi-tao-tu-ghi-chu', ['--apply' => true]);
        $this->assertSame(1, XacNhanThuCong::count());
    }

    public function test_thieu_bang_thi_luong_cu_chay(): void
    {
        $this->seed();
        Schema::drop('xac_nhan_thu_cong');
        \App\Support\KiemTraSchema::datLai();
        $don = $this->donCho();
        $thuNgan = User::where('role', 'thungan')->firstOrFail();

        $this->actingAs($thuNgan)->postJson(route('donhang.xacnhan', $don))
            ->assertOk()->assertJson(['success' => true]);
        $this->assertSame('da_thanh_toan', $don->fresh()->trang_thai);
    }
}
