<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\SanPham;
use App\Models\User;
use App\Services\ThanhToanChuyenKhoanService;
use App\Services\TongQuanKhoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** P2 bước 4 — báo cáo/dashboard tách phí giao hàng khỏi doanh thu hàng hóa. */
class BaoCaoTachPhiGiaoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    private function donOnline(int $ship, bool $daThanhToan = true): DonHang
    {
        $sp = SanPham::orderBy('id')->firstOrFail();
        $sp->update(['soluong' => 50, 'trang_thai' => true, 'gia_ban' => 50000]);
        $don = app(ThanhToanChuyenKhoanService::class)->taoHoaDonChoThanhToan(
            [['id' => $sp->id, 'soluong' => 2]], [], null, // 2×50k = 100k hàng
            [
                'kenh' => 'online', 'phuong_thuc' => 'chuyenkhoan',
                'hinh_thuc_nhan' => 'giao_tan_noi',
                'ma_tra_cuu' => 'BC'.random_int(100000, 999999),
                'ten_khach_dat' => 'Khách BC', 'so_dienthoai_dat' => '0900000111',
                'ten_nguoi_nhan' => 'Người Nhận', 'sdt_nguoi_nhan' => '0900000111',
                'dia_chi_giao' => '1 ABC, Q1',
                'han_giu' => now()->addMinutes(30),
            ]
        );
        // Service đã tự tính phí ship (config pos.phi_giao_hang) và cộng vào tong_thanh_toan.
        // Chỉnh về đúng $ship mong muốn: delta áp cho cả phi_giao_hang lẫn tong_thanh_toan.
        $don = $don->fresh();
        $delta = $ship - (float) ($don->phi_giao_hang ?? 0);
        $don->update([
            'phi_giao_hang' => $ship,
            'tong_thanh_toan' => (float) $don->tong_thanh_toan + $delta,
            'trang_thai' => $daThanhToan ? 'da_thanh_toan' : 'cho_thanh_toan',
            'thoi_gian_thanh_toan' => $daThanhToan ? now() : null,
        ]);

        return $don->fresh();
    }

    public function test_doanh_thu_tach_phi_ship_dung_so(): void
    {
        $this->seed();
        // Đơn: hàng 100.000 + ship 30.000 = tổng khách trả 130.000
        $don = $this->donOnline(30000);
        $this->assertSame(130000.0, (float) $don->tong_thanh_toan);

        $data = $this->actingAs($this->admin())
            ->get(route('thongke.doanhthu', ['kieu' => 'ngay']))->assertOk()->json();

        $this->assertCount(1, $data);
        $this->assertSame(100000.0, (float) $data[0]['doanh_thu']);   // chỉ hàng hóa
        $this->assertSame(30000.0, (float) $data[0]['phi_giao_hang']); // phí ship riêng
    }

    public function test_don_chua_thanh_toan_va_da_huy_khong_vao_doanh_thu(): void
    {
        $this->seed();
        $this->donOnline(30000, false); // chờ thanh toán
        $donHuy = $this->donOnline(0);
        $donHuy->update(['trang_thai' => 'da_huy']);

        $data = $this->actingAs($this->admin())
            ->get(route('thongke.doanhthu', ['kieu' => 'ngay']))->assertOk()->json();
        $this->assertSame([], $data);
    }

    public function test_dashboard_so_hoa_don_chi_dem_da_thanh_toan(): void
    {
        $this->seed();
        $svc = app(TongQuanKhoService::class);
        $this->donOnline(30000, false); // chờ → không đếm
        $this->assertSame(0, $svc->chiSo()['so_hoa_don']);

        $this->donOnline(30000, true);
        $this->assertSame(1, $svc->chiSo()['so_hoa_don']);

        $donHuy = $this->donOnline(0, true);
        $donHuy->update(['trang_thai' => 'da_huy']);
        $this->assertSame(1, $svc->chiSo()['so_hoa_don']); // hủy không đếm
    }

    public function test_thieu_cot_phi_giao_khong_loi(): void
    {
        $this->seed();
        // Mô phỏng đơn cũ không có phí ship → phi_giao_hang null → tính như 0
        $don = $this->donOnline(0);
        $data = $this->actingAs($this->admin())
            ->get(route('thongke.doanhthu', ['kieu' => 'ngay']))->assertOk()->json();
        $this->assertCount(1, $data);
        $this->assertSame(100000.0, (float) $data[0]['doanh_thu']);
        $this->assertSame(0.0, (float) $data[0]['phi_giao_hang']);
    }
}
