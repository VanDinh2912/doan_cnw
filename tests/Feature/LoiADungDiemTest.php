<?php

namespace Tests\Feature;

use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\LoaiSanPham;
use App\Models\SanPham;
use App\Models\User;
use App\Services\ThanhToanChuyenKhoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Tái hiện và kiểm thử Lỗi A — Dùng điểm khi khách chưa có điểm:
 * - Khách có 0 điểm nhập điểm dùng tùy ý (500) ở POS tiền mặt VÀ QR/chuyển khoản;
 * - Khách chưa chọn (khách lẻ) mà nhập điểm;
 * - Số âm, số thập phân, chữ;
 * - Vượt điểm hiện có;
 * - Vượt tổng tiền;
 * - Server từ chối bằng 422 + thông báo tiếng Việt;
 * - Trừ điểm đúng 1 lần;
 * - Hủy đơn quá hạn hoàn điểm đúng;
 * - Hai hóa đơn tuần tự cùng dùng số điểm còn lại (hóa đơn thứ hai bị từ chối).
 */
class LoiADungDiemTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;
    private SanPham $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->cashier = User::where('role', 'thungan')->firstOrFail();
        $this->product = SanPham::where('sku', 'CS-0001')->firstOrFail();

        Config::set('pos.gia_tri_diem', 1000);
        Config::set('pos.diem_moi_dong', 10000);
        Config::set('pos.bank_code', 'MB');
        Config::set('pos.bank_account', '0987654321');
        Config::set('pos.cho_thanh_toan_phut', 15);
    }

    private function sessionGioHang(int $soLuong = 2): array
    {
        return [
            'gio_hang' => [
                (string) $this->product->id => [
                    'id' => $this->product->id,
                    'sku' => $this->product->sku,
                    'ten_sanpham' => $this->product->ten_sanpham,
                    'gia_ban' => (float) $this->product->gia_ban,
                    'soluong' => $soLuong,
                    'ton_kho' => $this->product->soluong,
                ],
            ],
        ];
    }

    private function khachHangVoiDiem(int $diem = 0): KhachHang
    {
        return KhachHang::create([
            'ho_ten' => 'Khách Test Điểm',
            'so_dienthoai' => '0988'.rand(100000, 999999),
            'diem_tichluy' => $diem,
        ]);
    }

    // 1. Khách có 0 điểm nhập 500 ở tiền mặt bị từ chối 422 + tiếng Việt
    public function test_tienmat_khach_0_diem_nhap_500_bi_tu_choi(): void
    {
        $khach = $this->khachHangVoiDiem(0);

        $res = $this->actingAs($this->cashier)
            ->withSession($this->sessionGioHang())
            ->postJson(route('donhang.thanhtoan'), [
                'customer_id' => $khach->id,
                'diem_su_dung' => 500,
                'giam_gia_phantram' => 0,
                'tien_khach_dua' => 200000,
                'phuong_thuc' => 'tienmat',
            ]);

        $res->assertStatus(422);
        $this->assertStringContainsString('vượt quá điểm hiện có', json_encode($res->json(), JSON_UNESCAPED_UNICODE));
        $this->assertDatabaseCount('invoices', 0);
        $this->assertSame(0, $khach->fresh()->diem_tichluy);
    }

    // 2. Khách có 0 điểm nhập 500 ở QR bị từ chối 422 + tiếng Việt
    public function test_qr_khach_0_diem_nhap_500_bi_tu_choi(): void
    {
        $khach = $this->khachHangVoiDiem(0);

        $res = $this->actingAs($this->cashier)
            ->withSession($this->sessionGioHang())
            ->postJson(route('donhang.taoqr'), [
                'customer_id' => $khach->id,
                'diem_su_dung' => 500,
                'giam_gia_phantram' => 0,
            ]);

        $res->assertStatus(422);
        $this->assertStringContainsString('vượt quá điểm hiện có', json_encode($res->json(), JSON_UNESCAPED_UNICODE));
        $this->assertDatabaseCount('invoices', 0);
        $this->assertSame(0, $khach->fresh()->diem_tichluy);
    }

    // 3. Khách lẻ (chưa chọn khách) mà nhập điểm bị từ chối 422
    public function test_khach_le_nhap_diem_bi_tu_choi_ca_tienmat_va_qr(): void
    {
        // Luồng tiền mặt
        $resTienMat = $this->actingAs($this->cashier)
            ->withSession($this->sessionGioHang())
            ->postJson(route('donhang.thanhtoan'), [
                'customer_id' => null,
                'diem_su_dung' => 50,
                'giam_gia_phantram' => 0,
                'tien_khach_dua' => 200000,
                'phuong_thuc' => 'tienmat',
            ]);

        $resTienMat->assertStatus(422);
        $this->assertStringContainsString('Cần chọn khách hàng trước khi dùng điểm', json_encode($resTienMat->json(), JSON_UNESCAPED_UNICODE));

        // Luồng QR
        $resQr = $this->actingAs($this->cashier)
            ->withSession($this->sessionGioHang())
            ->postJson(route('donhang.taoqr'), [
                'customer_id' => null,
                'diem_su_dung' => 50,
                'giam_gia_phantram' => 0,
            ]);

        $resQr->assertStatus(422);
        $this->assertStringContainsString('Cần chọn khách hàng trước khi dùng điểm', json_encode($resQr->json(), JSON_UNESCAPED_UNICODE));
        $this->assertDatabaseCount('invoices', 0);
    }

    // 4. Số âm bị từ chối 422
    public function test_diem_su_dung_so_am_bi_tu_choi(): void
    {
        $khach = $this->khachHangVoiDiem(100);

        $res = $this->actingAs($this->cashier)
            ->withSession($this->sessionGioHang())
            ->postJson(route('donhang.thanhtoan'), [
                'customer_id' => $khach->id,
                'diem_su_dung' => -10,
                'giam_gia_phantram' => 0,
                'tien_khach_dua' => 200000,
                'phuong_thuc' => 'tienmat',
            ]);

        $res->assertStatus(422);
        $this->assertStringContainsString('không được âm', json_encode($res->json(), JSON_UNESCAPED_UNICODE));
    }

    // 5. Số thập phân / chữ bị từ chối 422
    public function test_diem_su_dung_thap_phan_hoac_chu_bi_tu_choi(): void
    {
        $khach = $this->khachHangVoiDiem(100);

        // Số thập phân
        $resFloat = $this->actingAs($this->cashier)
            ->withSession($this->sessionGioHang())
            ->postJson(route('donhang.thanhtoan'), [
                'customer_id' => $khach->id,
                'diem_su_dung' => 5.5,
                'giam_gia_phantram' => 0,
                'tien_khach_dua' => 200000,
                'phuong_thuc' => 'tienmat',
            ]);

        $resFloat->assertStatus(422);
        $this->assertStringContainsString('số nguyên', json_encode($resFloat->json(), JSON_UNESCAPED_UNICODE));

        // Chuỗi chữ
        $resString = $this->actingAs($this->cashier)
            ->withSession($this->sessionGioHang())
            ->postJson(route('donhang.thanhtoan'), [
                'customer_id' => $khach->id,
                'diem_su_dung' => 'abc',
                'giam_gia_phantram' => 0,
                'tien_khach_dua' => 200000,
                'phuong_thuc' => 'tienmat',
            ]);

        $resString->assertStatus(422);
        $this->assertStringContainsString('số nguyên', json_encode($resString->json(), JSON_UNESCAPED_UNICODE));
    }

    // 6. Vượt điểm hiện có bị từ chối 422
    public function test_diem_su_dung_vuot_diem_hien_co_bi_tu_choi(): void
    {
        $khach = $this->khachHangVoiDiem(20);

        $res = $this->actingAs($this->cashier)
            ->withSession($this->sessionGioHang())
            ->postJson(route('donhang.thanhtoan'), [
                'customer_id' => $khach->id,
                'diem_su_dung' => 25,
                'giam_gia_phantram' => 0,
                'tien_khach_dua' => 200000,
                'phuong_thuc' => 'tienmat',
            ]);

        $res->assertStatus(422);
        $this->assertStringContainsString('vượt quá điểm hiện có', json_encode($res->json(), JSON_UNESCAPED_UNICODE));
    }

    // 7. Vượt tổng tiền hóa đơn bị từ chối 422
    public function test_diem_su_dung_vuot_tong_tien_bi_tu_choi(): void
    {
        $khach = $this->khachHangVoiDiem(500); // 500 điểm = 500,000 đ
        // Giả sử tổng tiền là 2 x gia_ban, ta nhập điểm quy đổi vượt quá tổng tiền
        $tongTien = (float) $this->product->gia_ban * 2;
        $diemVuot = (int) ceil(($tongTien + 10000) / config('pos.gia_tri_diem'));
        $khach->update(['diem_tichluy' => $diemVuot + 10]);

        $res = $this->actingAs($this->cashier)
            ->withSession($this->sessionGioHang(2))
            ->postJson(route('donhang.thanhtoan'), [
                'customer_id' => $khach->id,
                'diem_su_dung' => $diemVuot,
                'giam_gia_phantram' => 0,
                'tien_khach_dua' => 200000,
                'phuong_thuc' => 'tienmat',
            ]);

        $res->assertStatus(422);
        $this->assertStringContainsString('vượt quá giá trị hóa đơn', json_encode($res->json(), JSON_UNESCAPED_UNICODE));
    }

    // 8. Trừ điểm đúng 1 lần khi thanh toán thành công
    public function test_tru_diem_dung_mot_lan_khi_thanh_toan_hop_le(): void
    {
        $khach = $this->khachHangVoiDiem(50);

        $res = $this->actingAs($this->cashier)
            ->withSession($this->sessionGioHang())
            ->post(route('donhang.thanhtoan'), [
                'customer_id' => $khach->id,
                'diem_su_dung' => 20,
                'giam_gia_phantram' => 0,
                'tien_khach_dua' => 200000,
                'phuong_thuc' => 'tienmat',
            ]);

        $res->assertRedirect();
        // 50 - 20 = 30 + điểm nhận từ đơn hàng
        $khachMoi = $khach->fresh();
        $donHang = DonHang::firstOrFail();
        $this->assertSame(20, $donHang->diem_su_dung);
        $this->assertSame(30 + $donHang->diem_tichluy_nhan, $khachMoi->diem_tichluy);
    }

    // 9. Hủy đơn QR hoàn điểm đúng
    public function test_huy_don_cho_thanh_toan_hoan_diem_chinh_xac(): void
    {
        $khach = $this->khachHangVoiDiem(50);

        $res = $this->actingAs($this->cashier)
            ->withSession($this->sessionGioHang())
            ->postJson(route('donhang.taoqr'), [
                'customer_id' => $khach->id,
                'diem_su_dung' => 30,
                'giam_gia_phantram' => 0,
            ]);

        $res->assertOk();
        $this->assertSame(20, $khach->fresh()->diem_tichluy);

        $donHang = DonHang::firstOrFail();
        $service = app(ThanhToanChuyenKhoanService::class);
        $service->huyChoThanhToan($donHang, 'Quá hạn chờ thanh toán');

        $this->assertSame('da_huy', $donHang->fresh()->trang_thai);
        $this->assertSame(50, $khach->fresh()->diem_tichluy);
    }

    // 10. Hai hóa đơn tuần tự cùng dùng số điểm còn lại: hóa đơn 2 bị từ chối
    public function test_hai_hoa_don_tuan_tu_dung_chung_diem_hoa_don_hai_bi_tu_choi(): void
    {
        $khach = $this->khachHangVoiDiem(30);

        // Đơn 1 dùng hết 30 điểm qua QR
        $res1 = $this->actingAs($this->cashier)
            ->withSession($this->sessionGioHang())
            ->postJson(route('donhang.taoqr'), [
                'customer_id' => $khach->id,
                'diem_su_dung' => 30,
                'giam_gia_phantram' => 0,
            ]);

        $res1->assertOk();
        $this->assertSame(0, $khach->fresh()->diem_tichluy);

        // Đơn 2 cố dùng tiếp 30 điểm
        $res2 = $this->actingAs($this->cashier)
            ->withSession($this->sessionGioHang())
            ->postJson(route('donhang.taoqr'), [
                'customer_id' => $khach->id,
                'diem_su_dung' => 30,
                'giam_gia_phantram' => 0,
            ]);

        $res2->assertStatus(422);
        $this->assertStringContainsString('vượt quá điểm hiện có', json_encode($res2->json(), JSON_UNESCAPED_UNICODE));
    }
}
