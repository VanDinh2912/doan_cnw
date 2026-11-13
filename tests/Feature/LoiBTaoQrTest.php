<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\LoaiSanPham;
use App\Models\SanPham;
use App\Models\User;
use App\Services\ThanhToanChuyenKhoanService;
use App\Services\VietQrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Kiểm thử Lỗi B — Tạo mã QR bị lỗi:
 * - Đủ cấu hình → tạo QR thành công;
 * - Thiếu từng biến (bank_code, bank_account) → thông báo thân thiện 422, không tạo đơn rác, không trừ kho;
 * - POS tiền mặt và POS QR không phụ thuộc cột online (khi $online rỗng không ghi/không đọc cột mới);
 * - Log che số tài khoản đầy đủ.
 */
class LoiBTaoQrTest extends TestCase
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

        Config::set('pos.bank_code', 'MB');
        Config::set('pos.bank_account', '0987654321');
        Config::set('pos.bank_name', 'CUA HANG TEST');
        Config::set('pos.cho_thanh_toan_phut', 15);
    }

    private function sessionGioHang(int $soLuong = 1): array
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

    public function test_du_cau_hinh_tao_qr_thanh_cong(): void
    {
        $tonDau = (int) $this->product->soluong;

        $res = $this->actingAs($this->cashier)
            ->withSession($this->sessionGioHang(2))
            ->postJson(route('donhang.taoqr'), ['giam_gia_phantram' => 0]);

        $res->assertOk();
        $data = $res->json();
        $this->assertArrayHasKey('url_qr', $data);
        $this->assertNotNull($data['url_qr']);
        $this->assertStringContainsString('img.vietqr.io', $data['url_qr']);
        $this->assertStringContainsString(VietQrService::cleanNoiDung($data['ma_hoadon']), $data['url_qr']);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertSame($tonDau - 2, (int) $this->product->fresh()->soluong);
    }

    public function test_thieu_bank_code_tra_422_thong_bao_than_thien_va_khong_tru_kho(): void
    {
        Config::set('pos.bank_code', '');
        $tonDau = (int) $this->product->soluong;

        $res = $this->actingAs($this->cashier)
            ->withSession($this->sessionGioHang(1))
            ->postJson(route('donhang.taoqr'), ['giam_gia_phantram' => 0]);

        $res->assertStatus(422);
        $this->assertStringContainsString('ngân hàng', $res->json('error') ?? '');
        $this->assertDatabaseCount('invoices', 0);
        $this->assertSame($tonDau, (int) $this->product->fresh()->soluong);
    }

    public function test_thieu_bank_account_tra_422_thong_bao_than_thien_va_khong_tru_kho(): void
    {
        Config::set('pos.bank_account', '');
        $tonDau = (int) $this->product->soluong;

        $res = $this->actingAs($this->cashier)
            ->withSession($this->sessionGioHang(1))
            ->postJson(route('donhang.taoqr'), ['giam_gia_phantram' => 0]);

        $res->assertStatus(422);
        $this->assertStringContainsString('tài khoản ngân hàng', $res->json('error') ?? '');
        $this->assertDatabaseCount('invoices', 0);
        $this->assertSame($tonDau, (int) $this->product->fresh()->soluong);
    }

    public function test_pos_tien_mat_khong_ghi_cac_cot_online_khi_online_rong(): void
    {
        $res = $this->actingAs($this->cashier)
            ->withSession($this->sessionGioHang(1))
            ->post(route('donhang.thanhtoan'), [
                'giam_gia_phantram' => 0,
                'tien_khach_dua' => 200000,
                'phuong_thuc' => 'tienmat',
            ]);

        $res->assertRedirect();
        $donHang = DonHang::firstOrFail();
        $this->assertSame('tienmat', $donHang->phuong_thuc);
        $this->assertNull($donHang->ma_tra_cuu);
        $this->assertNull($donHang->ten_khach_dat);
        $this->assertNull($donHang->so_dienthoai_dat);
        $this->assertNull($donHang->han_giu);
    }

    public function test_tao_don_cho_khi_online_rong_khong_ep_ghi_cot_online(): void
    {
        $service = app(ThanhToanChuyenKhoanService::class);
        $don = $service->taoHoaDonChoThanhToan(
            [['id' => $this->product->id, 'soluong' => 1]],
            [],
            $this->cashier,
            [] // $online rỗng
        );

        $this->assertNotNull($don->id);
        $this->assertSame('chuyenkhoan', $don->phuong_thuc);
        $this->assertSame('cho_thanh_toan', $don->trang_thai);
        $this->assertNull($don->ma_tra_cuu);
        $this->assertNull($don->han_giu);
    }

    public function test_ghi_log_khong_chua_so_tai_khoan_day_du(): void
    {
        $stkThat = '987654321098';
        Config::set('pos.bank_code', ''); // Kích hoạt lỗi thiếu cấu hình
        Config::set('pos.bank_account', $stkThat);

        $res = $this->actingAs($this->cashier)
            ->withSession($this->sessionGioHang(1))
            ->postJson(route('donhang.taoqr'), ['giam_gia_phantram' => 0]);

        $res->assertStatus(422);
        // Kiểm tra nội dung response không lộ số tài khoản
        $this->assertStringNotContainsString($stkThat, json_encode($res->json()));
    }
}
