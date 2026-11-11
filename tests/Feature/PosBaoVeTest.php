<?php

namespace Tests\Feature;

use App\Models\LoaiSanPham;
use App\Models\SanPham;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Bước 5B mục 2, 3, 4:
 * - API key webhook rỗng phải fail-closed (SepayWebhook.php:17-19).
 * - Biên giảm giá 0–100 khi thanh toán (DonHangController.php:197).
 * - Tìm sản phẩm giới hạn 10 kết quả (SanPhamController.php:102).
 */
class PosBaoVeTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->cashier = User::where('role', 'thungan')->firstOrFail();
    }

    public function test_webhook_tu_choi_khi_api_key_chua_cau_hinh(): void
    {
        Config::set('pos.sepay_webhook_api_key', '');

        $this->postJson(route('webhook.thanhtoan'), [
            'id' => 'GD-KHONG-KEY',
            'transferType' => 'in',
            'transferAmount' => 100000,
            'content' => 'THANH TOAN HD20261110-0001',
        ], ['Authorization' => 'Apikey bat-ky'])->assertStatus(401);

        $this->assertDatabaseCount('payment_webhooks', 0);
    }

    public function test_giam_gia_am_bi_tu_choi(): void
    {
        $sanPham = SanPham::where('sku', 'CS-0001')->firstOrFail();

        $this->thanhToanVoiGiamGia($sanPham, -1)
            ->assertSessionHasErrors('giam_gia_phantram');

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_giam_gia_tren_100_phan_tram_bi_tu_choi(): void
    {
        $sanPham = SanPham::where('sku', 'CS-0001')->firstOrFail();

        $this->thanhToanVoiGiamGia($sanPham, 101)
            ->assertSessionHasErrors('giam_gia_phantram');

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_giam_gia_dung_bien_100_van_hop_le(): void
    {
        $sanPham = SanPham::where('sku', 'CS-0001')->firstOrFail();

        $this->thanhToanVoiGiamGia($sanPham, 100)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseHas('invoices', ['tong_thanh_toan' => 0, 'giam_gia_phantram' => 100]);
    }

    public function test_tim_san_pham_chi_tra_toi_da_10_ket_qua(): void
    {
        $loai = LoaiSanPham::firstOrFail();
        for ($i = 1; $i <= 12; $i++) {
            SanPham::create([
                'category_id' => $loai->id,
                'sku' => sprintf('ZZLIMIT-%03d', $i),
                'ten_sanpham' => 'San pham gioi han '.$i,
                'don_vi' => 'Cái',
                'gia_nhap' => 10000,
                'gia_ban' => 20000,
                'soluong' => 5,
                'soluong_toithieu' => 1,
                'trang_thai' => true,
            ]);
        }

        $ketQua = $this->actingAs($this->cashier)
            ->getJson(route('sanpham.timkiem', ['q' => 'gioi han']))
            ->assertOk()
            ->json();

        $this->assertCount(10, $ketQua);
        foreach ($ketQua as $sanPham) {
            $this->assertStringContainsString('gioi han', $sanPham['ten_sanpham']);
        }
    }

    private function thanhToanVoiGiamGia(SanPham $sanPham, float $phanTram): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->cashier)->withSession([
            'gio_hang' => [
                (string) $sanPham->id => [
                    'id' => $sanPham->id,
                    'soluong' => 1,
                    'gia_ban' => (float) $sanPham->gia_ban,
                ],
            ],
        ])->post(route('donhang.thanhtoan'), [
            'giam_gia_phantram' => $phanTram,
            'tien_khach_dua' => 0,
            'phuong_thuc' => 'tienmat',
        ]);
    }
}
