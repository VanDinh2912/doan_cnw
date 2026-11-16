<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\PhieuTra;
use App\Models\SanPham;
use App\Models\User;
use App\Services\ThanhToanChuyenKhoanService;
use App\Support\KiemTraSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Quy tắc hoàn phí giao (chốt 2026-11-16):
 * - Tiền hoàn = tỷ lệ phần hàng trả trên TIỀN HÀNG sau giảm giá, KHÔNG gồm ship.
 * - Chỉ admin tick "Hoàn cả phí giao" (bắt buộc lý do, một lần/đơn) mới cộng ship.
 * - Tổng hoàn tích lũy ≤ tổng khách đã thanh toán; điểm thu hồi không tính ship.
 * - Đơn không ship / thiếu cột → hành vi cũ.
 */
class TraHangPhiGiaoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    private function thuNgan(): User
    {
        return User::where('role', 'thungan')->firstOrFail();
    }

    /** Đơn online giao tận nơi: mặc định 4×40.000 = 160.000 hàng + 30.000 ship = 190.000. */
    private function donShip(array $overrides = []): DonHang
    {
        $sp = SanPham::orderBy('id')->firstOrFail();
        $sp->update(['soluong' => 20, 'trang_thai' => true, 'gia_ban' => 40000]);
        $don = app(ThanhToanChuyenKhoanService::class)->taoHoaDonChoThanhToan(
            [['id' => $sp->id, 'soluong' => 4]], [], null,
            array_merge([
                'kenh' => 'online', 'phuong_thuc' => 'chuyenkhoan',
                'hinh_thuc_nhan' => 'giao_tan_noi',
                'ma_tra_cuu' => 'PG'.random_int(100000, 999999),
                'ten_khach_dat' => 'Khách Ship', 'so_dienthoai_dat' => '0908888000',
                'ten_nguoi_nhan' => 'Người Nhận', 'sdt_nguoi_nhan' => '0908888000',
                'dia_chi_giao' => '5 Trần Hưng Đạo, Q1',
                'han_giu' => now()->addMinutes(30),
            ], $overrides)
        );
        DonHang::whereKey($don->id)->update(array_intersect_key($overrides, array_flip([
            'customer_id', 'diem_tichluy_nhan', 'phi_giao_hang', 'tien_giam', 'tong_thanh_toan',
        ])));
        $don->update(['trang_thai' => 'da_thanh_toan', 'thoi_gian_thanh_toan' => now()]);

        return $don->fresh();
    }

    private function postTra(DonHang $don, User $user, array $items, array $extra = [])
    {
        return $this->actingAs($user)->post(route('trahang.luu'), array_merge([
            'invoice_id' => $don->id,
            'hinh_thuc_hoan' => 'chuyenkhoan',
            'ly_do' => 'Khách đổi ý',
            'items' => $items,
        ], $extra));
    }

    public function test_tra_hang_khong_tick_khong_hoan_phi_ship(): void
    {
        $this->seed();
        // Hàng 160.000 + ship 30.000 = 190.000; trả 2/4 → hoàn 80.000 (không gồm ship)
        $don = $this->donShip();
        $chiTiet = $don->chiTietDonHangs()->firstOrFail();

        $this->postTra($don, $this->admin(), [
            ['invoice_detail_id' => $chiTiet->id, 'soluong' => 2, 'tinh_trang' => 'con_tot'],
        ])->assertRedirect();

        $phieu = PhieuTra::where('invoice_id', $don->id)->latest('id')->firstOrFail();
        $this->assertSame(80000, (int) $phieu->tong_hoan);
        $this->assertSame(0, (int) ($phieu->tien_hoan_phi_giao ?? 0));
    }

    public function test_admin_tick_hoan_ship_mot_lan_va_tich_luy_khong_vuot_tong(): void
    {
        $this->seed();
        $don = $this->donShip();
        $chiTiet = $don->chiTietDonHangs()->firstOrFail();

        // Lần 1: admin tick hoàn ship → 80.000 hàng + 30.000 ship = 110.000
        $this->postTra($don, $this->admin(), [
            ['invoice_detail_id' => $chiTiet->id, 'soluong' => 2, 'tinh_trang' => 'con_tot'],
        ], ['hoan_phi_giao' => '1', 'ly_do' => 'Lỗi do cửa hàng, hoàn cả phí ship'])->assertRedirect();

        $phieu1 = PhieuTra::where('invoice_id', $don->id)->latest('id')->firstOrFail();
        $this->assertSame(110000, (int) $phieu1->tong_hoan);
        $this->assertSame(30000, (int) $phieu1->tien_hoan_phi_giao);

        // Lần 2: trả nốt 2 cái còn lại, KHÔNG hoàn thêm ship → +80.000
        $this->postTra($don, $this->admin(), [
            ['invoice_detail_id' => $chiTiet->id, 'soluong' => 2, 'tinh_trang' => 'con_tot'],
        ], ['hoan_phi_giao' => '1', 'ly_do' => 'Thử hoàn ship lần hai'])->assertStatus(422);

        $this->postTra($don, $this->admin(), [
            ['invoice_detail_id' => $chiTiet->id, 'soluong' => 2, 'tinh_trang' => 'con_tot'],
        ])->assertRedirect();

        $phieu2 = PhieuTra::where('invoice_id', $don->id)->latest('id')->firstOrFail();
        $this->assertSame(80000, (int) $phieu2->tong_hoan);
        $this->assertSame(0, (int) $phieu2->tien_hoan_phi_giao);
        // Tích lũy: 110.000 + 80.000 = 190.000 = đúng tổng khách trả, không vượt
        $tong = (int) PhieuTra::where('invoice_id', $don->id)
            ->whereIn('trang_thai', ['hoan_thanh', 'cho_duyet'])->sum('tong_hoan');
        $this->assertSame(190000, $tong);
    }

    public function test_thungan_khong_duoc_tick_hoan_ship(): void
    {
        $this->seed();
        $don = $this->donShip();
        $chiTiet = $don->chiTietDonHangs()->firstOrFail();

        $this->postTra($don, $this->thuNgan(), [
            ['invoice_detail_id' => $chiTiet->id, 'soluong' => 1, 'tinh_trang' => 'con_tot'],
        ], ['hoan_phi_giao' => '1', 'ly_do' => 'Thu ngân thử tick'])->assertForbidden();

        $this->assertSame(0, PhieuTra::where('invoice_id', $don->id)->count());
    }

    public function test_admin_tick_bat_buoc_ly_do(): void
    {
        $this->seed();
        $don = $this->donShip();
        $chiTiet = $don->chiTietDonHangs()->firstOrFail();

        $this->postTra($don, $this->admin(), [
            ['invoice_detail_id' => $chiTiet->id, 'soluong' => 1, 'tinh_trang' => 'con_tot'],
        ], ['hoan_phi_giao' => '1', 'ly_do' => ''])->assertStatus(422);
    }

    public function test_giam_gia_tinh_ty_le_tren_tien_hang_sau_giam(): void
    {
        $this->seed();
        // Hàng 160.000, giảm 16.000 → hàng thuần 144.000 + ship 30.000 = 174.000.
        // Trả 1/4 → 40.000 * (144.000/160.000) = 36.000 (không gồm ship).
        $don = $this->donShip(['tien_giam' => 16000, 'tong_thanh_toan' => 174000]);
        $chiTiet = $don->chiTietDonHangs()->firstOrFail();

        $this->postTra($don, $this->admin(), [
            ['invoice_detail_id' => $chiTiet->id, 'soluong' => 1, 'tinh_trang' => 'con_tot'],
        ])->assertRedirect();

        $phieu = PhieuTra::where('invoice_id', $don->id)->latest('id')->firstOrFail();
        $this->assertSame(36000, (int) $phieu->tong_hoan);
    }

    public function test_diem_thu_hoi_khong_tinh_phi_ship_da_hoan(): void
    {
        $this->seed();
        $kh = KhachHang::create(['ho_ten' => 'KH Điểm', 'so_dienthoai' => '0907777000', 'diem_tichluy' => 10]);
        $don = $this->donShip(['customer_id' => $kh->id, 'diem_tichluy_nhan' => 1]);
        $chiTiet = $don->chiTietDonHangs()->firstOrFail();

        // Trả 2/4 = 80.000 hàng hoàn + tick ship 30.000 → tong_hoan 110.000
        // nhưng diem_thu_hoi = floor(80.000/100.000) = 0 (ship không tính điểm).
        $this->postTra($don, $this->admin(), [
            ['invoice_detail_id' => $chiTiet->id, 'soluong' => 2, 'tinh_trang' => 'con_tot'],
        ], ['hoan_phi_giao' => '1', 'ly_do' => 'Hoàn ship do lỗi cửa hàng'])->assertRedirect();

        $phieu = PhieuTra::where('invoice_id', $don->id)->latest('id')->firstOrFail();
        $this->assertSame(110000, (int) $phieu->tong_hoan);
        $this->assertSame(0, (int) $phieu->diem_thu_hoi);
        $this->assertSame(10, (int) $kh->fresh()->diem_tichluy);
    }

    public function test_bao_cao_tach_doanh_thu_hang_va_hoan_phi_ship(): void
    {
        $this->seed();
        $don = $this->donShip();
        $chiTiet = $don->chiTietDonHangs()->firstOrFail();

        // Trả hết hàng 160.000 + admin tick ship 30.000 → tổng hoàn 190.000
        $this->postTra($don, $this->admin(), [
            ['invoice_detail_id' => $chiTiet->id, 'soluong' => 4, 'tinh_trang' => 'con_tot'],
        ], ['hoan_phi_giao' => '1', 'ly_do' => 'Trả toàn bộ, hoàn cả ship'])->assertRedirect();

        $data = $this->actingAs($this->admin())
            ->get(route('thongke.doanhthu', ['kieu' => 'ngay']))->assertOk()->json();

        $this->assertCount(1, $data);
        $this->assertSame(0.0, (float) $data[0]['doanh_thu']);        // hàng đã hoàn hết
        $this->assertSame(0.0, (float) $data[0]['phi_giao_hang']);    // ship đã hoàn
        $this->assertSame(30000.0, (float) $data[0]['hoan_phi_giao_hang']); // dòng riêng
    }

    public function test_bao_cao_phi_ship_khong_hoan_van_la_khoan_da_thu(): void
    {
        $this->seed();
        $don = $this->donShip();
        $chiTiet = $don->chiTietDonHangs()->firstOrFail();

        // Trả hết hàng, KHÔNG tick ship → doanh thu hàng = 0, phí ship 30k vẫn đã thu
        $this->postTra($don, $this->admin(), [
            ['invoice_detail_id' => $chiTiet->id, 'soluong' => 4, 'tinh_trang' => 'con_tot'],
        ])->assertRedirect();

        $data = $this->actingAs($this->admin())
            ->get(route('thongke.doanhthu', ['kieu' => 'ngay']))->assertOk()->json();

        $this->assertSame(0.0, (float) $data[0]['doanh_thu']);
        $this->assertSame(30000.0, (float) $data[0]['phi_giao_hang']);
        $this->assertSame(0.0, (float) $data[0]['hoan_phi_giao_hang']);
    }

    public function test_thieu_cot_khong_loi_hanh_vi_cu(): void
    {
        $this->seed();
        $don = $this->donShip();
        $chiTiet = $don->chiTietDonHangs()->firstOrFail();

        // Giả lập DB chưa áp migration returns.tien_hoan_phi_giao
        Schema::table('returns', function ($t): void {
            $t->dropColumn('tien_hoan_phi_giao');
        });
        KiemTraSchema::datLai();

        // Không tick → trả hàng bình thường (hành vi cũ)
        $this->postTra($don, $this->thuNgan(), [
            ['invoice_detail_id' => $chiTiet->id, 'soluong' => 1, 'tinh_trang' => 'con_tot'],
        ])->assertRedirect();
        $phieu = PhieuTra::where('invoice_id', $don->id)->latest('id')->firstOrFail();
        $this->assertSame(40000, (int) $phieu->tong_hoan);

        // Tick khi thiếu cột → 422 rõ ràng, không 500, không tạo phiếu
        $this->postTra($don, $this->admin(), [
            ['invoice_detail_id' => $chiTiet->id, 'soluong' => 1, 'tinh_trang' => 'con_tot'],
        ], ['hoan_phi_giao' => '1', 'ly_do' => 'Tick khi thiếu cột'])->assertStatus(422);
        $this->assertSame(1, PhieuTra::where('invoice_id', $don->id)->count());
    }

    public function test_don_khong_ship_giu_nguyen_hanh_vi_cu(): void
    {
        $this->seed();
        // Đơn pickup (không giao tận nơi) → phi_giao_hang = 0
        $sp = SanPham::orderBy('id')->firstOrFail();
        $sp->update(['soluong' => 20, 'trang_thai' => true, 'gia_ban' => 50000]);
        $don = app(ThanhToanChuyenKhoanService::class)->taoHoaDonChoThanhToan(
            [['id' => $sp->id, 'soluong' => 2]], [], null,
            [
                'kenh' => 'online', 'phuong_thuc' => 'chuyenkhoan',
                'hinh_thuc_nhan' => 'tai_cua_hang',
                'ma_tra_cuu' => 'PK'.random_int(100000, 999999),
                'ten_khach_dat' => 'Khách Pickup', 'so_dienthoai_dat' => '0906666000',
                'han_giu' => now()->addMinutes(30),
            ]
        );
        $don->update(['trang_thai' => 'da_thanh_toan', 'thoi_gian_thanh_toan' => now()]);
        $chiTiet = $don->chiTietDonHangs()->firstOrFail();

        $this->postTra($don->fresh(), $this->admin(), [
            ['invoice_detail_id' => $chiTiet->id, 'soluong' => 1, 'tinh_trang' => 'con_tot'],
        ])->assertRedirect();

        $phieu = PhieuTra::where('invoice_id', $don->id)->latest('id')->firstOrFail();
        $this->assertSame(50000, (int) $phieu->tong_hoan); // 50.000 * (100.000/100.000)
        // Tick trên đơn không ship → 422
        $this->postTra($don->fresh(), $this->admin(), [
            ['invoice_detail_id' => $chiTiet->id, 'soluong' => 1, 'tinh_trang' => 'con_tot'],
        ], ['hoan_phi_giao' => '1', 'ly_do' => 'Tick trên đơn không ship'])->assertStatus(422);
    }
}
