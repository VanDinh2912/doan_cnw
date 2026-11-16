<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\NhatKyHoatDong;
use App\Models\SanPham;
use App\Models\User;
use App\Services\ThanhToanChuyenKhoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** HM5 — phiếu giao hàng in: quyền, nội dung, no-store, không lộ giá vốn, nhật ký. */
class PhieuGiaoHangTest extends TestCase
{
    use RefreshDatabase;

    private function donGiao(): DonHang
    {
        $sp = SanPham::orderBy('id')->firstOrFail();
        $sp->update(['soluong' => 10, 'trang_thai' => true]);
        $don = app(ThanhToanChuyenKhoanService::class)->taoHoaDonChoThanhToan(
            [['id' => $sp->id, 'soluong' => 1]], [], null,
            [
                'kenh' => 'online', 'phuong_thuc' => 'chuyenkhoan',
                'hinh_thuc_nhan' => 'giao_tan_noi',
                'ma_tra_cuu' => 'PG'.random_int(100000, 999999),
                'ten_khach_dat' => 'Khách Giao', 'so_dienthoai_dat' => '0901212121',
                'ten_nguoi_nhan' => 'Chị Lan', 'sdt_nguoi_nhan' => '0903434343',
                'dia_chi_giao' => '45 Hai Bà Trưng, Q1, TP.HCM',
                'ghi_chu_khach' => 'Giao giờ hành chính',
                'han_giu' => now()->addMinutes(30),
            ]
        );
        $don->update(['trang_thai' => 'da_thanh_toan', 'thoi_gian_thanh_toan' => now()]);

        return $don->fresh();
    }

    public function test_phieu_giao_noi_dung_dung_va_quyen(): void
    {
        $this->seed();
        $don = $this->donGiao();
        $thuNgan = User::where('role', 'thungan')->firstOrFail();

        // Khách/guest không xem được (kiểm tra trước khi actingAs nhân viên)
        $this->get(route('donhangonline.phieugiaohang', $don))->assertRedirect();
        $khach = User::where('role', 'khachhang')->first();
        if ($khach) {
            $this->actingAs($khach)->get(route('donhangonline.phieugiaohang', $don))->assertRedirect();
        }

        $res = $this->actingAs($thuNgan)->get(route('donhangonline.phieugiaohang', $don));
        $res->assertOk()
            ->assertSee('PHIẾU GIAO HÀNG')
            ->assertSee($don->ma_hoadon)
            ->assertSee('Chị Lan')->assertSee('0903434343')
            ->assertSee('Hai Bà Trưng')->assertSee('Giao giờ hành chính')
            ->assertSee('ĐÃ THANH TOÁN')
            ->assertSee('Phí giao hàng');
        $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'));

        // Không lộ giá vốn / lợi nhuận / giá nhập
        $content = $res->getContent();
        $this->assertStringNotContainsString('giá vốn', strtolower($content));
        $this->assertStringNotContainsString('gia_von', $content);
        $this->assertStringNotContainsString('lợi nhuận', strtolower($content));
        $this->assertStringNotContainsString('loi_nhuan', $content);

        // Nhật ký lần in, không PII
        $log = NhatKyHoatDong::where('hanh_dong', 'in_phieu_giao')->where('doi_tuong_id', $don->id)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString('0903434343', json_encode([$log->mo_ta, $log->truoc, $log->sau]));

        // Đơn pickup không có phiếu giao → 404
        $sp = SanPham::orderBy('id')->firstOrFail();
        $donPickup = app(ThanhToanChuyenKhoanService::class)->taoHoaDonChoThanhToan(
            [['id' => $sp->id, 'soluong' => 1]], [], null,
            ['kenh' => 'online', 'phuong_thuc' => 'tienmat', 'hinh_thuc_nhan' => 'tai_cua_hang',
             'ma_tra_cuu' => 'PK'.random_int(100000, 999999), 'han_giu' => now()->addHours(4)]
        );
        $this->actingAs($thuNgan)->get(route('donhangonline.phieugiaohang', $donPickup))->assertNotFound();
    }
}
