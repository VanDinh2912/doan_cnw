<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\SanPham;
use App\Models\User;
use App\Services\ThanhToanChuyenKhoanService;
use App\Support\KiemTraSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** HM1 — màn hình nhân viên xử lý đơn online: phân quyền, lọc, che SĐT, badge. */
class DonHangOnlineNhanVienTest extends TestCase
{
    use RefreshDatabase;

    private function donOnline(array $overrides = []): DonHang
    {
        $sp = SanPham::orderBy('id')->firstOrFail();
        $sp->update(['soluong' => 10, 'trang_thai' => true]);

        return app(ThanhToanChuyenKhoanService::class)->taoHoaDonChoThanhToan(
            [['id' => $sp->id, 'soluong' => 1]], [], null,
            array_merge([
                'kenh' => 'online', 'phuong_thuc' => 'chuyenkhoan',
                'hinh_thuc_nhan' => 'giao_tan_noi',
                'ma_tra_cuu' => 'NV'.random_int(100000, 999999),
                'ten_khach_dat' => 'Khách Online', 'so_dienthoai_dat' => '0901234567',
                'ten_nguoi_nhan' => 'Người Nhận', 'sdt_nguoi_nhan' => '0907654321',
                'dia_chi_giao' => '12 Nguyễn Trãi, Q1, TP.HCM',
                'han_giu' => now()->addMinutes(30),
            ], $overrides)
        );
    }

    public function test_phan_quyen_danh_sach_don_online(): void
    {
        $this->seed();
        $don = $this->donOnline();

        $this->get(route('donhangonline.danhsach'))->assertRedirect(); // khách → login
        $khach = User::where('role', 'khachhang')->first();
        if ($khach) {
            // staff.only chuyển khách hàng về trang chủ (không lộ dữ liệu nhân viên)
            $this->actingAs($khach)->get(route('donhangonline.danhsach'))->assertRedirect();
        }
        foreach (['admin', 'thungan', 'thukho'] as $role) {
            $u = User::where('role', $role)->firstOrFail();
            $this->actingAs($u)->get(route('donhangonline.danhsach'))->assertOk();
        }
        $this->actingAs(User::where('role', 'thungan')->firstOrFail())
            ->get(route('donhangonline.chitiet', $don))->assertOk();
        // Đơn POS không phải online → 404
        $donPos = DonHang::create([
            'ma_hoadon' => 'HD20261115-8888', 'tong_tien' => 1, 'tong_thanh_toan' => 1,
            'phuong_thuc' => 'tienmat', 'trang_thai' => 'da_thanh_toan',
        ]);
        $this->actingAs(User::where('role', 'admin')->firstOrFail())
            ->get(route('donhangonline.chitiet', $donPos))->assertNotFound();
    }

    public function test_danh_sach_loc_va_che_sdt_khong_lo_gia_nhap(): void
    {
        $this->seed();
        $this->donOnline();
        $thuNgan = User::where('role', 'thungan')->firstOrFail();

        $res = $this->actingAs($thuNgan)->get(route('donhangonline.danhsach', [
            'hinh_thuc_nhan' => 'giao_tan_noi', 'tim' => 'Khách Online',
        ]));
        $res->assertOk()->assertSee('Khách Online')->assertSee('090****567');
        $this->assertStringNotContainsString('0901234567', $res->getContent());
        $this->assertStringNotContainsString('giá nhập', strtolower($res->getContent()));
        // Tìm theo SĐT vẫn hoạt động nhưng danh sách vẫn che
        $res2 = $this->actingAs($thuNgan)->get(route('donhangonline.danhsach', ['tim' => '0901234']));
        $res2->assertOk()->assertSee('090****567');

        // Lọc trạng thái giao
        $this->actingAs($thuNgan)->get(route('donhangonline.danhsach', [
            'trang_thai_giao' => 'cho_xac_nhan',
        ]))->assertOk();
        $this->actingAs($thuNgan)->get(route('donhangonline.danhsach', [
            'trang_thai_giao' => 'da_giao',
        ]))->assertDontSee('Khách Online');
    }

    public function test_chi_tiet_full_sdt_dia_chi_no_store(): void
    {
        $this->seed();
        $don = $this->donOnline();
        $res = $this->actingAs(User::where('role', 'thungan')->firstOrFail())
            ->get(route('donhangonline.chitiet', $don));
        $res->assertOk()
            ->assertSee('0901234567')->assertSee('Nguyễn Trãi')
            ->assertDontSee('giá vốn');
        $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'));
    }

    public function test_badge_dem_don_can_xu_ly(): void
    {
        $this->seed();
        $thuNgan = User::where('role', 'thungan')->firstOrFail();

        $this->assertSame(0, $this->actingAs($thuNgan)->getJson(route('donhangonline.soluong'))->json('so_luong'));

        // Đơn giao chờ thanh toán → chưa tính (chỉ tính da_thanh_toan + cho_xac_nhan)
        $this->donOnline();
        $this->assertSame(0, $this->actingAs($thuNgan)->getJson(route('donhangonline.soluong'))->json('so_luong'));

        // Thanh toán xong → trang_thai_giao = cho_xac_nhan → badge = 1
        $don = DonHang::where('kenh', 'online')->firstOrFail();
        $don->update(['trang_thai' => 'da_thanh_toan', 'trang_thai_giao' => 'cho_xac_nhan']);
        $this->assertSame(1, $this->actingAs($thuNgan)->getJson(route('donhangonline.soluong'))->json('so_luong'));

        // Khách không được gọi
        $khach = User::where('role', 'khachhang')->first();
        if ($khach) {
            $this->actingAs($khach)->getJson(route('donhangonline.soluong'))->assertRedirect();
        }
    }

    public function test_thieu_cot_online_bao_can_ap_migration_khong_500(): void
    {
        $this->seed();
        $thuNgan = User::where('role', 'thungan')->firstOrFail();

        Schema::table('invoices', function ($t): void {
            $t->dropIndex(['kenh']);
        });
        Schema::table('invoices', function ($t): void {
            $t->dropColumn(['kenh', 'hinh_thuc_nhan']);
        });
        KiemTraSchema::datLai();

        $this->actingAs($thuNgan)->get(route('donhangonline.danhsach'))
            ->assertOk()->assertSee('Cần áp migration');
        $this->actingAs($thuNgan)->getJson(route('donhangonline.soluong'))
            ->assertOk()->assertJson(['so_luong' => 0]);
    }
}
