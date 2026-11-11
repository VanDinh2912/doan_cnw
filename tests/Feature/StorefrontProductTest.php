<?php

namespace Tests\Feature;

use App\Models\LoaiSanPham;
use App\Models\SanPham;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_trang_chi_tiet_san_pham_tra_200_va_hien_thi_ten(): void
    {
        $loai = $this->taoLoai('Sạc dự phòng');
        $chinh = $this->taoSanPham($loai, 'SP-CHINH', 'Sạc dự phòng Test 10.000mAh');
        // Cần >= 2 sản phẩm liên quan: Laravel chỉ bật chặn lazy-load khi một
        // truy vấn hydrate nhiều hơn 1 model (Builder.php:472).
        $this->taoSanPham($loai, 'SP-LIEN-QUAN-1', 'Sạc dự phòng Test 20.000mAh');
        $this->taoSanPham($loai, 'SP-LIEN-QUAN-2', 'Sạc dự phòng Test 30.000mAh');

        $response = $this->get(route('storefront.product', $chinh));

        $response->assertOk();
        $response->assertSee('Sạc dự phòng Test 10.000mAh');
    }

    public function test_trang_danh_muc_tra_200_va_hien_thi_ten(): void
    {
        $loai = $this->taoLoai('Cáp sạc');
        $this->taoSanPham($loai, 'SP-CAP-01', 'Cáp sạc Test Type-C');

        $response = $this->get(route('storefront.category', $loai));

        $response->assertOk();
        $response->assertSee('Cáp sạc');
    }

    public function test_san_pham_khong_ton_tai_tra_404_tieng_viet(): void
    {
        $this->get('/san-pham/999999')
            ->assertNotFound()
            ->assertSee('Không tìm thấy trang');
    }

    public function test_danh_muc_khong_ton_tai_tra_404_tieng_viet(): void
    {
        $this->get('/danh-muc/999999')
            ->assertNotFound()
            ->assertSee('Không tìm thấy trang');
    }

    public function test_link_danh_muc_tren_trang_chu_tro_dung_route(): void
    {
        $loai = $this->taoLoai('Tai nghe');
        $this->taoSanPham($loai, 'SP-TAI-NGHE', 'Tai nghe Test Bluetooth');

        $this->get(route('storefront.home'))
            ->assertOk()
            ->assertSee('href="'.route('storefront.category', $loai).'"', false);
    }

    private function taoLoai(string $ten): LoaiSanPham
    {
        return LoaiSanPham::create(['ten_loai' => $ten]);
    }

    private function taoSanPham(LoaiSanPham $loai, string $sku, string $ten): SanPham
    {
        return SanPham::create([
            'sku' => $sku,
            'ten_sanpham' => $ten,
            'gia_ban' => 150000,
            'gia_nhap' => 100000,
            'soluong' => 10,
            'soluong_toithieu' => 2,
            'trang_thai' => true,
            'category_id' => $loai->id,
        ]);
    }
}
