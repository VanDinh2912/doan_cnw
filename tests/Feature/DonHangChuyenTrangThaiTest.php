<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\NhatKyHoatDong;
use App\Models\SanPham;
use App\Models\User;
use App\Services\ThanhToanChuyenKhoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** HM2 — máy trạng thái giao đơn online: hợp lệ/không hợp lệ, idempotent, quyền, nhật ký. */
class DonHangChuyenTrangThaiTest extends TestCase
{
    use RefreshDatabase;

    private function donGiaoDaThanhToan(array $overrides = []): DonHang
    {
        $sp = SanPham::orderBy('id')->firstOrFail();
        $sp->update(['soluong' => 10, 'trang_thai' => true]);
        $don = app(ThanhToanChuyenKhoanService::class)->taoHoaDonChoThanhToan(
            [['id' => $sp->id, 'soluong' => 1]], [], null,
            array_merge([
                'kenh' => 'online', 'phuong_thuc' => 'chuyenkhoan',
                'hinh_thuc_nhan' => 'giao_tan_noi',
                'ma_tra_cuu' => 'TT'.random_int(100000, 999999),
                'ten_khach_dat' => 'Khách Giao', 'so_dienthoai_dat' => '0901111222',
                'han_giu' => now()->addMinutes(30),
            ], $overrides)
        );
        // Cột online ghi trực tiếp (service chỉ nhận một số trường cố định)
        $don->update(array_intersect_key($overrides, array_flip([
            'trang_thai_giao', 'customer_id', 'diem_tichluy_nhan', 'hinh_thuc_nhan',
        ])));
        $don->update(['trang_thai' => 'da_thanh_toan', 'thoi_gian_thanh_toan' => now()]);

        return $don->fresh();
    }

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    /** Ghi thẳng DB (tránh update() trên model cũ bị bỏ qua khi không dirty). */
    private function datTrangThaiGiao(DonHang $don, ?string $tt): void
    {
        DonHang::whereKey($don->id)->update(['trang_thai_giao' => $tt]);
    }

    public function test_chuyen_trang_thai_hop_le_theo_thu_tu(): void
    {
        $this->seed();
        $don = $this->donGiaoDaThanhToan();
        $this->assertNull($don->trang_thai_giao);

        // chưa có trạng thái giao → không chuyển được (phải chờ webhook/manual set)
        $this->actingAs($this->admin())->post(route('donhangonline.trangthai', $don), [
            'trang_thai_giao_moi' => 'da_xac_nhan',
        ])->assertSessionHas('error');

        // webhook set cho_xac_nhan → chuyển da_xac_nhan
        $don->update(['trang_thai_giao' => 'cho_xac_nhan']);
        $this->actingAs($this->admin())->post(route('donhangonline.trangthai', $don), [
            'trang_thai_giao_moi' => 'da_xac_nhan',
        ])->assertSessionHas('success');
        $this->assertSame('da_xac_nhan', $don->fresh()->trang_thai_giao);

        foreach (['dang_giao' => 'dang_giao', 'da_giao' => 'da_giao'] as $moi => $_) {
            $this->actingAs($this->admin())->post(route('donhangonline.trangthai', $don), [
                'trang_thai_giao_moi' => $moi,
            ])->assertSessionHas('success');
        }
        $this->assertSame('da_giao', $don->fresh()->trang_thai_giao);

        // Nhật ký ghi đủ thao tác, không PII
        $logs = NhatKyHoatDong::where('doi_tuong', 'don_hang')->where('doi_tuong_id', $don->id)
            ->where('hanh_dong', 'chuyen_trang_thai_giao')->get();
        $this->assertCount(3, $logs);
        $this->assertStringNotContainsString('0901111222', json_encode($logs->toArray()));
    }

    public function test_tu_choi_chuyen_sai_thu_tu_va_nhay_bac(): void
    {
        $this->seed();
        $don = $this->donGiaoDaThanhToan(['trang_thai_giao' => 'cho_xac_nhan']);
        $admin = $this->admin();

        // Nhảy bậc thẳng sang da_giao → từ chối
        $this->actingAs($admin)->post(route('donhangonline.trangthai', $don), [
            'trang_thai_giao_moi' => 'da_giao',
        ])->assertSessionHas('error');
        $this->assertSame('cho_xac_nhan', $don->fresh()->trang_thai_giao);

        // Bấm đúp cùng thao tác → lần 2 "đã được xử lý", không lỗi
        $this->actingAs($admin)->post(route('donhangonline.trangthai', $don), [
            'trang_thai_giao_moi' => 'da_xac_nhan',
        ])->assertSessionHas('success');
        $this->actingAs($admin)->post(route('donhangonline.trangthai', $don), [
            'trang_thai_giao_moi' => 'da_xac_nhan',
        ])->assertSessionHas('success');
        $this->assertSame('da_xac_nhan', $don->fresh()->trang_thai_giao);

        // Đã giao xong không chuyển ngược
        $don->update(['trang_thai_giao' => 'da_giao']);
        $this->actingAs($admin)->post(route('donhangonline.trangthai', $don), [
            'trang_thai_giao_moi' => 'dang_giao',
        ])->assertSessionHas('error');
        $this->assertSame('da_giao', $don->fresh()->trang_thai_giao);
    }

    public function test_don_chua_thanh_toan_khong_chuyen_duoc(): void
    {
        $this->seed();
        $sp = SanPham::orderBy('id')->firstOrFail();
        $sp->update(['soluong' => 10, 'trang_thai' => true]);
        $don = app(ThanhToanChuyenKhoanService::class)->taoHoaDonChoThanhToan(
            [['id' => $sp->id, 'soluong' => 1]], [], null,
            [
                'kenh' => 'online', 'phuong_thuc' => 'chuyenkhoan',
                'hinh_thuc_nhan' => 'giao_tan_noi',
                'ma_tra_cuu' => 'UN'.random_int(100000, 999999),
                'han_giu' => now()->addMinutes(30),
            ]
        );
        $this->actingAs($this->admin())->post(route('donhangonline.trangthai', $don), [
            'trang_thai_giao_moi' => 'da_xac_nhan',
        ])->assertSessionHas('error');
        $this->assertNull($don->fresh()->trang_thai_giao);
    }

    public function test_vai_tro_va_khach_khong_chuyen_duoc(): void
    {
        $this->seed();
        $don = $this->donGiaoDaThanhToan(['trang_thai_giao' => 'cho_xac_nhan']);

        // admin/thungan/thukho đều được
        foreach (['thungan', 'thukho'] as $role) {
            $u = User::where('role', $role)->firstOrFail();
            $this->actingAs($u)->post(route('donhangonline.trangthai', $don), [
                'trang_thai_giao_moi' => 'da_xac_nhan',
            ])->assertSessionHas('success');
            $this->datTrangThaiGiao($don, 'cho_xac_nhan'); // reset cho vai trò kế
        }
        $this->datTrangThaiGiao($don, 'cho_xac_nhan'); // trạng thái xác định trước khi test khách

        // khách hàng bị chặn (staff.only redirect), không đổi trạng thái
        $khach = User::where('role', 'khachhang')->first();
        if ($khach) {
            $this->actingAs($khach)->post(route('donhangonline.trangthai', $don), [
                'trang_thai_giao_moi' => 'da_xac_nhan',
            ])->assertRedirect();
        }
        $this->assertSame('cho_xac_nhan', $don->fresh()->trang_thai_giao);

        // Đơn pickup không có trạng thái giao
        $donPickup = $this->donGiaoDaThanhToan([
            'hinh_thuc_nhan' => 'tai_cua_hang',
            'trang_thai_giao' => null,
        ]);
        $this->actingAs($this->admin())->post(route('donhangonline.trangthai', $donPickup), [
            'trang_thai_giao_moi' => 'da_xac_nhan',
        ])->assertSessionHas('error');
    }

    public function test_da_giao_khong_cong_diem_lan_hai(): void
    {
        $this->seed();
        $kh = KhachHang::create([
            'ho_ten' => 'KH Test', 'so_dienthoai' => '0909999888',
        ]);
        $kh->diem_tichluy = 50;
        $kh->save();
        $don = $this->donGiaoDaThanhToan([
            'customer_id' => $kh->id,
            'diem_tichluy_nhan' => 10, // giả lập điểm đã cộng khi thanh toán
        ]);
        $don->update(['trang_thai_giao' => 'dang_giao']);

        $this->actingAs($this->admin())->post(route('donhangonline.trangthai', $don), [
            'trang_thai_giao_moi' => 'da_giao',
        ])->assertSessionHas('success');

        // Điểm giữ nguyên 50 — da_giao KHÔNG cộng lại (đã cộng 1 lần lúc thanh toán)
        $this->assertSame(50, (int) $kh->fresh()->diem_tichluy);
    }
}
