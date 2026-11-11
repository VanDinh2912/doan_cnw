<?php

namespace Tests\Feature;

use App\Models\ChiTietHoaDonNhap;
use App\Models\HoaDonNhap;
use App\Models\NhaCungCap;
use App\Models\SanPham;
use App\Models\User;
use App\Services\HoaDonNhapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bước 5B mục 1: cảnh báo lệch giá nhập > 10% và giá bán < giá nhập
 * (HoaDonNhapService.php:35-39).
 */
class NhapKhoCanhBaoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('role', 'admin')->firstOrFail();
    }

    public function test_canh_bao_khi_gia_nhap_lech_tren_10_phan_tram(): void
    {
        $sanPham = SanPham::where('sku', 'CS-0001')->firstOrFail();
        $sanPham->update(['gia_nhap' => 100000, 'gia_ban' => 300000]);

        $hoaDon = $this->taoHoaDonNhap($sanPham, 2, 120000);

        $canhBao = app(HoaDonNhapService::class)->xacNhanNhapKho($hoaDon, $this->admin);

        $this->assertSame(['Giá nhập CS-0001 lệch trên 10%.'], $canhBao);
        $this->assertSame(120000, (int) $sanPham->fresh()->gia_nhap);
    }

    public function test_canh_bao_khi_gia_ban_thap_hon_gia_nhap_moi(): void
    {
        $sanPham = SanPham::where('sku', 'CS-0001')->firstOrFail();
        $sanPham->update(['gia_nhap' => 100000, 'gia_ban' => 90000]);

        $hoaDon = $this->taoHoaDonNhap($sanPham, 1, 100000);

        $canhBao = app(HoaDonNhapService::class)->xacNhanNhapKho($hoaDon, $this->admin);

        $this->assertSame(['Giá bán CS-0001 thấp hơn giá nhập mới.'], $canhBao);
    }

    public function test_khong_canh_bao_khi_gia_trong_bien_va_gia_ban_du_cao(): void
    {
        $sanPham = SanPham::where('sku', 'CS-0001')->firstOrFail();
        $sanPham->update(['gia_nhap' => 100000, 'gia_ban' => 150000]);

        // Lệch đúng 10% vẫn chưa vượt ngưỡng (> 0.1 mới cảnh báo).
        $hoaDon = $this->taoHoaDonNhap($sanPham, 1, 110000);

        $canhBao = app(HoaDonNhapService::class)->xacNhanNhapKho($hoaDon, $this->admin);

        $this->assertSame([], $canhBao);
        $this->assertSame('da_nhap_kho', $hoaDon->fresh()->trang_thai);
    }

    private function taoHoaDonNhap(SanPham $sanPham, int $soLuong, int $donGia): HoaDonNhap
    {
        $tienHang = $soLuong * $donGia;

        $hoaDon = HoaDonNhap::create([
            'ma_phieu' => 'PN-CANHBAO-'.uniqid(),
            'so_hoadon' => '0009922',
            'ky_hieu' => '1C26TAA',
            'ngay_hoadon' => now()->toDateString(),
            'supplier_id' => NhaCungCap::firstOrFail()->id,
            'user_id' => $this->admin->id,
            'trang_thai' => 'nhap_tam',
            'hinh_thuc_thanh_toan' => 'tienmat',
            'tong_tien_hang' => $tienHang,
            'thue_suat' => 0,
            'tien_thue' => 0,
            'tong_thanh_toan' => $tienHang,
        ]);

        ChiTietHoaDonNhap::create([
            'purchase_invoice_id' => $hoaDon->id,
            'stt' => 1,
            'product_id' => $sanPham->id,
            'ten_hang' => $sanPham->ten_sanpham,
            'don_vi' => $sanPham->don_vi,
            'soluong' => $soLuong,
            'don_gia' => $donGia,
            'thanh_tien' => $tienHang,
        ]);

        return $hoaDon;
    }
}
