<?php

namespace Tests\Feature;

use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\SanPham;
use App\Models\User;
use App\Services\TongQuanKhoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Cảnh báo lợi nhuận khi dòng bán có gia_von = 0 — KHÔNG đổi công thức lợi nhuận. */
class CanhBaoGiaVonTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::where('role', $role)->firstOrFail();
    }

    private function hoaDonDaThanhToan(int $giaVon = 0): DonHang
    {
        $don = DonHang::create([
            'ma_hoadon' => 'GV-'.uniqid(),
            'user_id' => null,
            'tong_tien' => 20000,
            'tong_thanh_toan' => 20000,
            'phuong_thuc' => 'tienmat',
            'trang_thai' => 'da_thanh_toan',
        ]);
        ChiTietDonHang::create([
            'invoice_id' => $don->id,
            'product_id' => SanPham::firstOrFail()->id,
            'soluong' => 1,
            'don_gia' => 20000,
            'gia_von' => $giaVon,
            'thanh_tien' => 20000,
        ]);

        return $don;
    }

    public function test_loi_nhuan_json_giu_nguyen_cong_thuc_khi_gia_von_0(): void
    {
        $this->seed();
        $this->hoaDonDaThanhToan(0); // don_gia 20000, gia_von 0 → loi_nhuan = 20000

        $data = $this->actingAs($this->user('admin'))
            ->get(route('thongke.doanhthu', ['kieu' => 'ngay']))->assertOk()->json();

        $this->assertCount(1, $data);
        $this->assertSame(20000.0, (float) $data[0]['loi_nhuan']);
    }

    public function test_trang_thong_ke_hien_canh_bao_khi_co_dong_gia_von_0(): void
    {
        $this->seed();
        $this->hoaDonDaThanhToan(0);

        $this->actingAs($this->user('admin'))
            ->get(route('thongke.index'))
            ->assertOk()
            ->assertSee('dòng bán chưa có giá vốn')
            ->assertSee(route('thongke.chuagivon'), false);
    }

    public function test_trang_thong_ke_sach_khong_hien_canh_bao(): void
    {
        $this->seed();
        $this->hoaDonDaThanhToan(10000);

        $this->actingAs($this->user('admin'))
            ->get(route('thongke.index'))
            ->assertOk()
            ->assertDontSee('dòng bán chưa có giá vốn');
    }

    public function test_hoa_don_chua_thanh_toan_va_da_huy_khong_tinh(): void
    {
        $this->seed();
        $donCho = $this->hoaDonDaThanhToan(0);
        $donCho->update(['trang_thai' => 'cho_thanh_toan']);
        $donHuy = $this->hoaDonDaThanhToan(0);
        $donHuy->update(['trang_thai' => 'da_huy']);

        $this->actingAs($this->user('admin'))
            ->get(route('thongke.index'))
            ->assertOk()
            ->assertDontSee('dòng bán chưa có giá vốn');
    }

    public function test_trang_chua_co_gia_von_liet_ke_dung_dong(): void
    {
        $this->seed();
        $don = $this->hoaDonDaThanhToan(0);
        $ten = SanPham::firstOrFail()->ten_sanpham;
        // Dòng bình thường không được liệt kê
        $don2 = DonHang::create([
            'ma_hoadon' => 'GV-OK-'.uniqid(),
            'user_id' => null,
            'tong_tien' => 20000,
            'tong_thanh_toan' => 20000,
            'phuong_thuc' => 'tienmat',
            'trang_thai' => 'da_thanh_toan',
        ]);
        ChiTietDonHang::create([
            'invoice_id' => $don2->id,
            'product_id' => SanPham::firstOrFail()->id,
            'soluong' => 1,
            'don_gia' => 20000,
            'gia_von' => 10000,
            'thanh_tien' => 20000,
        ]);

        $this->actingAs($this->user('admin'))
            ->get(route('thongke.chuagivon'))
            ->assertOk()
            ->assertSee($ten)
            ->assertSee($don->ma_hoadon)
            ->assertDontSee($don2->ma_hoadon);
    }

    public function test_phan_quyen_chi_admin(): void
    {
        $this->seed();
        $this->hoaDonDaThanhToan(0);

        foreach (['thungan', 'thukho'] as $role) {
            $this->actingAs($this->user($role))
                ->get(route('thongke.index'))->assertForbidden();
            $this->actingAs($this->user($role))
                ->get(route('thongke.chuagivon'))->assertForbidden();
        }
    }

    public function test_chiso_dem_sp_dang_ban_chua_co_gia_nhap(): void
    {
        $this->seed();
        $svc = app(TongQuanKhoService::class);
        $sp = SanPham::firstOrFail();

        $this->assertSame(0, $svc->chiSo()['so_sp_chua_co_gia_nhap']);

        // Đang bán + chưa giá nhập → đếm
        $sp->update(['trang_thai' => true, 'gia_nhap' => 0, 'soluong' => 5]);
        $this->assertSame(1, $svc->chiSo()['so_sp_chua_co_gia_nhap']);

        // Có giá nhập → không đếm
        $sp->update(['gia_nhap' => 10000]);
        $this->assertSame(0, $svc->chiSo()['so_sp_chua_co_gia_nhap']);

        // Ngừng bán + chưa giá nhập → không đếm
        $sp->update(['trang_thai' => false, 'gia_nhap' => 0]);
        $this->assertSame(0, $svc->chiSo()['so_sp_chua_co_gia_nhap']);
    }

    public function test_tongquan_json_co_chi_so_moi(): void
    {
        $this->seed();
        $data = $this->actingAs($this->user('admin'))
            ->getJson(route('tongquan.solieu'))->assertOk()->json();

        $this->assertArrayHasKey('so_sp_chua_co_gia_nhap', $data);
    }
}
