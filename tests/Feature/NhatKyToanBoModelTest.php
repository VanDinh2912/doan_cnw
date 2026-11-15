<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\LoaiSanPham;
use App\Models\NhatKyHoatDong;
use App\Models\SanPham;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NhatKyToanBoModelTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::create([
            'name' => 'Admin Test',
            'email' => 'admin.nhatky@test.local',
            'password' => bcrypt('12345678'),
            'role' => 'admin',
        ]);
    }

    private function sanPham(): SanPham
    {
        $loai = LoaiSanPham::create(['ten_loai' => 'Sap '.uniqid()]);

        return SanPham::create([
            'category_id' => $loai->id,
            'sku' => 'SKU-NK-'.uniqid(),
            'ten_sanpham' => 'San pham nhat ky',
            'don_vi' => 'Cái',
            'gia_nhap' => 50000,
            'gia_ban' => 100000,
            'soluong' => 10,
            'soluong_toithieu' => 1,
            'trang_thai' => true,
        ]);
    }

    public function test_san_pham_ghi_nhat_ky_khi_tao_sua_xoa(): void
    {
        $sanPham = $this->sanPham();

        $this->assertDatabaseHas('activity_logs', [
            'hanh_dong' => 'tao',
            'doi_tuong' => 'products',
            'doi_tuong_id' => $sanPham->id,
        ]);

        $sanPham->update(['ten_sanpham' => 'Ten moi']);
        $logSua = NhatKyHoatDong::where('hanh_dong', 'sua')
            ->where('doi_tuong', 'products')
            ->where('doi_tuong_id', $sanPham->id)
            ->latest('id')->firstOrFail();
        $this->assertSame('San pham nhat ky', $logSua->truoc['ten_sanpham']);
        $this->assertSame('Ten moi', $logSua->sau['ten_sanpham']);

        $sanPham->delete();
        $this->assertDatabaseHas('activity_logs', [
            'hanh_dong' => 'xoa',
            'doi_tuong' => 'products',
            'doi_tuong_id' => $sanPham->id,
        ]);
    }

    public function test_don_hang_va_khach_hang_ghi_nhat_ky(): void
    {
        $staff = $this->staff();
        $khach = KhachHang::create([
            'ho_ten' => 'Khach NK',
            'so_dienthoai' => '0912345678',
            'diem_tichluy' => 0,
        ]);

        $don = DonHang::create([
            'ma_hoadon' => 'HD-NK-'.uniqid(),
            'user_id' => $staff->id,
            'customer_id' => $khach->id,
            'tong_tien' => 100000,
            'tong_thanh_toan' => 100000,
            'tien_khach_dua' => 100000,
            'tien_thua' => 0,
            'trang_thai' => 'da_thanh_toan',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'hanh_dong' => 'tao',
            'doi_tuong' => 'invoices',
            'doi_tuong_id' => $don->id,
        ]);

        $don->update(['ghi_chu' => 'Ghi chu test']);
        $this->assertDatabaseHas('activity_logs', [
            'hanh_dong' => 'sua',
            'doi_tuong' => 'invoices',
            'doi_tuong_id' => $don->id,
        ]);
    }

    public function test_loai_san_pham_va_nha_cung_cap_ghi_nhat_ky(): void
    {
        $loai = LoaiSanPham::create(['ten_loai' => 'Sap cap '.uniqid()]);
        $this->assertDatabaseHas('activity_logs', [
            'hanh_dong' => 'tao',
            'doi_tuong' => 'categories',
            'doi_tuong_id' => $loai->id,
        ]);
    }
}
