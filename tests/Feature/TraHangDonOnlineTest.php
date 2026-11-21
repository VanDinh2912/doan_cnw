<?php

namespace Tests\Feature;

use App\Models\ChiTietPhieuTra;
use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\PhieuTra;
use App\Models\SanPham;
use App\Models\User;
use App\Services\ThanhToanChuyenKhoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** HM6 — trả hàng đơn online: không crash khi customer_id/user_id null,
 *  hoàn tiền tỷ lệ trên tiền hàng (quy tắc hoàn ship 2026-11-16 — xem
 *  TraHangPhiGiaoTest), thu hồi điểm đã cộng, không hoàn điểm đã dùng. */
class TraHangDonOnlineTest extends TestCase
{
    use RefreshDatabase;

    private function donOnline(array $overrides = [], int $soLuong = 2): DonHang
    {
        $sp = SanPham::orderBy('id')->firstOrFail();
        $sp->update(['soluong' => 20, 'trang_thai' => true, 'gia_ban' => 50000]);
        $don = app(ThanhToanChuyenKhoanService::class)->taoHoaDonChoThanhToan(
            [['id' => $sp->id, 'soluong' => $soLuong]], [], null,
            array_merge([
                'kenh' => 'online', 'phuong_thuc' => 'chuyenkhoan',
                'hinh_thuc_nhan' => 'giao_tan_noi',
                'ma_tra_cuu' => 'TR'.random_int(100000, 999999),
                'ten_khach_dat' => 'Khách Trả', 'so_dienthoai_dat' => '0909999000',
                'ten_nguoi_nhan' => 'Người Nhận', 'sdt_nguoi_nhan' => '0909999000',
                'dia_chi_giao' => '3 Lê Lợi, Q1',
                'han_giu' => now()->addMinutes(30),
            ], $overrides)
        );
        DonHang::whereKey($don->id)->update(array_intersect_key($overrides, array_flip([
            'customer_id', 'diem_tichluy_nhan', 'phi_giao_hang',
        ])));
        $don->update(['trang_thai' => 'da_thanh_toan', 'thoi_gian_thanh_toan' => now()]);

        return $don->fresh();
    }

    private function thuNgan(): User
    {
        return User::where('role', 'thungan')->firstOrFail();
    }

    public function test_chon_hang_online_khong_loi_va_tra_full_hoan_gom_phi_ship(): void
    {
        $this->seed();
        // Đơn 2×50.000 = 100.000 + ship 30.000 → tổng 130.000
        $don = $this->donOnline(['phi_giao_hang' => 30000]);
        $chiTiet = $don->chiTietDonHangs()->firstOrFail();

        $this->actingAs($this->thuNgan())->get(route('trahang.chon', $don))->assertOk();

        // Trả toàn bộ 2 cái → tiền hoàn = 2*50.000 = 100.000.
        // (Đổi kỳ vọng 130.000→100.000: quy tắc hoàn ship 2026-11-16 — tiền hoàn
        // chỉ trên tiền hàng, phí giao không còn được hoàn tỷ lệ; admin phải tick.)
        $this->actingAs($this->thuNgan())->post(route('trahang.luu'), [
            'invoice_id' => $don->id,
            'hinh_thuc_hoan' => 'chuyenkhoan',
            'ly_do' => 'Khách trả toàn bộ',
            'items' => [
                ['invoice_detail_id' => $chiTiet->id, 'soluong' => 2, 'tinh_trang' => 'con_tot'],
            ],
        ])->assertRedirect();

        $phieu = PhieuTra::where('invoice_id', $don->id)->latest('id')->firstOrFail();
        $this->assertSame(100000, (int) $phieu->tong_hoan); // quy tắc mới: không gồm phí ship
        $this->assertSame('hoan_thanh', $phieu->trang_thai); // dưới ngưỡng duyệt
        $this->assertSame(0, (int) $phieu->diem_hoan); // online không dùng điểm → không hoàn điểm dùng
    }

    public function test_tra_mot_phan_tinh_ty_le_va_thu_hoi_diem_khong_am(): void
    {
        $this->seed();
        $kh = KhachHang::create(['ho_ten' => 'KH Trả', 'so_dienthoai' => '0901111000']);
        $kh->diem_tichluy = 5;
        $kh->save();
        // diem_tichluy_nhan=10 nhưng số dư khách chỉ còn 5 → thu hồi kẹp ở 5
        $don = $this->donOnline([
            'customer_id' => $kh->id, 'diem_tichluy_nhan' => 10, 'phi_giao_hang' => 30000,
        ]);
        $chiTiet = $don->chiTietDonHangs()->firstOrFail();

        // Trả 1/2 số lượng → hoàn = 1*50.000 = 50.000 (không gồm ship — quy tắc mới)
        // (Đổi kỳ vọng 65.000→50.000 cùng lý do trên.)
        $this->actingAs($this->thuNgan())->post(route('trahang.luu'), [
            'invoice_id' => $don->id,
            'hinh_thuc_hoan' => 'tienmat',
            'items' => [
                ['invoice_detail_id' => $chiTiet->id, 'soluong' => 1, 'tinh_trang' => 'con_tot'],
            ],
        ])->assertRedirect();

        $phieu = PhieuTra::where('invoice_id', $don->id)->latest('id')->firstOrFail();
        $this->assertSame(50000, (int) $phieu->tong_hoan);
        // diem_thu_hoi = floor(65000 / diem_moi_dong); số dư không âm sau thu hồi
        $this->assertGreaterThanOrEqual(0, (int) $kh->fresh()->diem_tichluy);
        $this->assertSame('hoan_thanh', $phieu->trang_thai);
    }

    public function test_tra_hang_khong_customer_khong_crash(): void
    {
        $this->seed();
        // customer_id = null (khách guest), user_id nội bộ — phiếu vẫn tạo được
        $don = $this->donOnline();
        $this->assertNull($don->customer_id);
        $chiTiet = $don->chiTietDonHangs()->firstOrFail();

        $this->actingAs($this->thuNgan())->post(route('trahang.luu'), [
            'invoice_id' => $don->id,
            'hinh_thuc_hoan' => 'doi_hang',
            'items' => [
                ['invoice_detail_id' => $chiTiet->id, 'soluong' => 1, 'tinh_trang' => 'loi_hong'],
            ],
        ])->assertRedirect();

        $phieu = PhieuTra::where('invoice_id', $don->id)->latest('id')->firstOrFail();
        $this->assertNull($phieu->customer_id);
        $this->assertSame('hoan_thanh', $phieu->trang_thai);
        // Hàng lỗi → cộng soluong_hong thay vì soluong
        $sp = SanPham::orderBy('id')->firstOrFail();
        $this->assertSame(18, (int) $sp->fresh()->soluong); // bán 2 → còn 18, trả lỗi 1 → hỏng +1, không về bán
        $this->assertSame(1, (int) $sp->fresh()->soluong_hong);
    }
}
