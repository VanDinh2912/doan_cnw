<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\SanPham;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * T-DH-P2-01 (P2 — docs/THIET_KE_DAT_HANG_ONLINE.md mục 11.5): Form + Checkout
 * cho giao_tan_noi. Validate người nhận, SĐT, địa chỉ; giao_tan_noi chỉ cho QR;
 * tính phí giao hàng theo config; miễn phí khi đạt ngưỡng; request cố sửa phí
 * bị bỏ qua; ngoài vùng giao bị từ chối.
 */
class DatHangP2FormCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['pos.phi_giao_hang' => 30000]);
        config(['pos.mien_phi_giao_tu' => 0]);
        config(['pos.giao_hang_tinh' => '']);
    }

    private function themSanPhamVaoGio(int $soLuong = 1): SanPham
    {
        $sanPham = SanPham::first();
        $this->withSession(['storefront_cart' => [
            $sanPham->id => ['id' => $sanPham->id, 'ten' => $sanPham->ten, 'gia_ban' => $sanPham->gia_ban, 'soluong' => $soLuong],
        ]])->get('/dat-hang');
        return $sanPham;
    }

    public function test_form_hien_thi_lua_chon_hinh_thuc_nhan(): void
    {
        $this->themSanPhamVaoGio();
        $response = $this->get('/dat-hang');

        $response->assertOk();
        $response->assertSee('tai_cua_hang');
        $response->assertSee('giao_tan_noi');
        $response->assertSee('QR trả trước');
        $response->assertSee('Trả tại quầy');
    }

    public function test_checkout_giao_tan_noi_thieu_nguoi_nhan_bi_tu_choi(): void
    {
        $this->themSanPhamVaoGio();
        $token = $this->withSession(['checkout_token' => 'test-token'])->get('/dat-hang')->viewData('token');

        $response = $this->post('/dat-hang', [
            'ten' => 'Khách Test',
            'so_dienthoai' => '0901234567',
            'ghi_chu' => null,
            'hinh_thuc_nhan' => 'giao_tan_noi',
            'ten_nguoi_nhan' => null,
            'sdt_nguoi_nhan' => null,
            'dia_chi_giao' => null,
            'phuong_thuc' => 'qr',
            'checkout_token' => $token,
        ]);

        $response->assertSessionHasErrors(['ten_nguoi_nhan']);
        $response->assertSessionHasErrors(['sdt_nguoi_nhan']);
        $response->assertSessionHasErrors(['dia_chi_giao']);
    }

    public function test_checkout_giao_tan_noi_thieu_sdt_nguoi_nhan_bi_tu_choi(): void
    {
        $this->themSanPhamVaoGio();
        $token = $this->withSession(['checkout_token' => 'test-token'])->get('/dat-hang')->viewData('token');

        $response = $this->post('/dat-hang', [
            'ten' => 'Khách Test',
            'so_dienthoai' => '0901234567',
            'ghi_chu' => null,
            'hinh_thuc_nhan' => 'giao_tan_noi',
            'ten_nguoi_nhan' => 'Người Nhận',
            'sdt_nguoi_nhan' => null,
            'dia_chi_giao' => '123 Đường ABC',
            'phuong_thuc' => 'qr',
            'checkout_token' => $token,
        ]);

        $response->assertSessionHasErrors(['sdt_nguoi_nhan']);
    }

    public function test_checkout_giao_tan_noi_thieu_dia_chi_bi_tu_choi(): void
    {
        $this->themSanPhamVaoGio();
        $token = $this->withSession(['checkout_token' => 'test-token'])->get('/dat-hang')->viewData('token');

        $response = $this->post('/dat-hang', [
            'ten' => 'Khách Test',
            'so_dienthoai' => '0901234567',
            'ghi_chu' => null,
            'hinh_thuc_nhan' => 'giao_tan_noi',
            'ten_nguoi_nhan' => 'Người Nhận',
            'sdt_nguoi_nhan' => '0901234567',
            'dia_chi_giao' => null,
            'phuong_thuc' => 'qr',
            'checkout_token' => $token,
        ]);

        $response->assertSessionHasErrors(['dia_chi_giao']);
    }

    public function test_checkout_giao_tan_noi_tra_tai_quay_bi_tu_choi(): void
    {
        $this->themSanPhamVaoGio();
        $token = $this->withSession(['checkout_token' => 'test-token'])->get('/dat-hang')->viewData('token');

        $response = $this->post('/dat-hang', [
            'ten' => 'Khách Test',
            'so_dienthoai' => '0901234567',
            'ghi_chu' => null,
            'hinh_thuc_nhan' => 'giao_tan_noi',
            'ten_nguoi_nhan' => 'Người Nhận',
            'sdt_nguoi_nhan' => '0901234567',
            'dia_chi_giao' => '123 Đường ABC',
            'phuong_thuc' => 'pickup',
            'checkout_token' => $token,
        ]);

        $response->assertSessionHasErrors(['phuong_thuc']);
    }

    public function test_checkout_giao_tan_noi_tinh_phi_giao_hang_dung(): void
    {
        $sanPham = $this->themSanPhamVaoGio();
        $token = $this->withSession(['checkout_token' => 'test-token'])->get('/dat-hang')->viewData('token');

        $response = $this->post('/dat-hang', [
            'ten' => 'Khách Test',
            'so_dienthoai' => '0901234567',
            'ghi_chu' => null,
            'hinh_thuc_nhan' => 'giao_tan_noi',
            'ten_nguoi_nhan' => 'Người Nhận',
            'sdt_nguoi_nhan' => '0901234567',
            'dia_chi_giao' => '123 Đường ABC, Phường XYZ, Quận 1, TP.HCM',
            'phuong_thuc' => 'qr',
            'checkout_token' => $token,
        ]);

        $response->assertRedirect();
        $maTraCuu = $response->getTargetUrl() ? basename($response->getTargetUrl()) : null;
        $don = DonHang::where('ma_tra_cuu', $maTraCuu)->first();

        $this->assertNotNull($don);
        $this->assertSame('giao_tan_noi', $don->hinh_thuc_nhan);
        $this->assertSame(30000, $don->phi_giao_hang);
        $this->assertEquals($don->tong_tien + 30000, $don->tong_thanh_toan);
    }

    public function test_checkout_giao_tan_noi_mien_phi_khi_dat_nguong(): void
    {
        // P2 Bước C: phí miễn phí xét theo tiền hàng TÍNH LẠI TỪ DB (không tin
        // giá session). Giữ nguyên ý định ca test (đạt ngưỡng → miễn phí) nhưng
        // dùng giá DB thật của sản phẩm seed (34000) và ngưỡng 30000 thay vì
        // giá session 300000 như bản WIP.
        config(['pos.mien_phi_giao_tu' => 30000]);
        $sanPham = SanPham::first();
        $this->withSession(['storefront_cart' => [
            $sanPham->id => ['id' => $sanPham->id, 'ten' => $sanPham->ten, 'gia_ban' => $sanPham->gia_ban, 'soluong' => 1],
        ]])->get('/dat-hang');
        $token = $this->withSession(['checkout_token' => 'test-token'])->get('/dat-hang')->viewData('token');

        $response = $this->post('/dat-hang', [
            'ten' => 'Khách Test',
            'so_dienthoai' => '0901234567',
            'ghi_chu' => null,
            'hinh_thuc_nhan' => 'giao_tan_noi',
            'ten_nguoi_nhan' => 'Người Nhận',
            'sdt_nguoi_nhan' => '0901234567',
            'dia_chi_giao' => '123 Đường ABC',
            'phuong_thuc' => 'qr',
            'checkout_token' => $token,
        ]);

        $response->assertRedirect();
        $maTraCuu = $response->getTargetUrl() ? basename($response->getTargetUrl()) : null;
        $don = DonHang::where('ma_tra_cuu', $maTraCuu)->first();

        $this->assertNotNull($don);
        $this->assertSame(0, $don->phi_giao_hang);
        $this->assertEquals($don->tong_tien, $don->tong_thanh_toan);
    }

    public function test_checkout_giao_tan_noi_request_co_sua_phi_bi_bo_qua(): void
    {
        $this->themSanPhamVaoGio();
        $token = $this->withSession(['checkout_token' => 'test-token'])->get('/dat-hang')->viewData('token');

        $response = $this->post('/dat-hang', [
            'ten' => 'Khách Test',
            'so_dienthoai' => '0901234567',
            'ghi_chu' => null,
            'hinh_thuc_nhan' => 'giao_tan_noi',
            'ten_nguoi_nhan' => 'Người Nhận',
            'sdt_nguoi_nhan' => '0901234567',
            'dia_chi_giao' => '123 Đường ABC',
            'phuong_thuc' => 'qr',
            'checkout_token' => $token,
            'phi_giao_hang' => 1, // Cố tình gửi phí thấp
            'tong_thanh_toan' => 1, // Cố tình gửi tổng thấp
            'trang_thai' => 'da_thanh_toan', // Cố tình gửi trạng thái hoàn tất
        ]);

        $response->assertRedirect();
        $maTraCuu = $response->getTargetUrl() ? basename($response->getTargetUrl()) : null;
        $don = DonHang::where('ma_tra_cuu', $maTraCuu)->first();

        $this->assertNotNull($don);
        $this->assertSame(30000, $don->phi_giao_hang);
        $this->assertEquals($don->tong_tien + 30000, $don->tong_thanh_toan);
        $this->assertSame('cho_thanh_toan', $don->trang_thai);
    }

    public function test_checkout_giao_tan_noi_ngoai_vung_giao_bi_tu_choi(): void
    {
        config(['pos.giao_hang_tinh' => 'Hồ Chí Minh,Bình Dương']);
        $this->themSanPhamVaoGio();
        $token = $this->withSession(['checkout_token' => 'test-token'])->get('/dat-hang')->viewData('token');

        $response = $this->post('/dat-hang', [
            'ten' => 'Khách Test',
            'so_dienthoai' => '0901234567',
            'ghi_chu' => null,
            'hinh_thuc_nhan' => 'giao_tan_noi',
            'ten_nguoi_nhan' => 'Người Nhận',
            'sdt_nguoi_nhan' => '0901234567',
            'dia_chi_giao' => '123 Đường ABC, Phường XYZ, Hà Nội', // Tỉnh Hà Nội không trong danh sách
            'phuong_thuc' => 'qr',
            'checkout_token' => $token,
        ]);

        $response->assertSessionHasErrors(['dia_chi_giao']);
    }

    public function test_checkout_tai_cua_hang_khong_doi_hanh_vi_p1(): void
    {
        $this->themSanPhamVaoGio();
        $token = $this->withSession(['checkout_token' => 'test-token'])->get('/dat-hang')->viewData('token');

        $response = $this->post('/dat-hang', [
            'ten' => 'Khách Test',
            'so_dienthoai' => '0901234567',
            'ghi_chu' => null,
            'hinh_thuc_nhan' => 'tai_cua_hang',
            'phuong_thuc' => 'pickup',
            'checkout_token' => $token,
        ]);

        $response->assertRedirect();
        $maTraCuu = $response->getTargetUrl() ? basename($response->getTargetUrl()) : null;
        $don = DonHang::where('ma_tra_cuu', $maTraCuu)->first();

        $this->assertNotNull($don);
        $this->assertSame('tai_cua_hang', $don->hinh_thuc_nhan);
        $this->assertNull($don->ten_nguoi_nhan);
        $this->assertNull($don->sdt_nguoi_nhan);
        $this->assertNull($don->dia_chi_giao);
        $this->assertEquals(0, $don->phi_giao_hang);
        $this->assertEquals($don->tong_tien, $don->tong_thanh_toan);
    }
}
