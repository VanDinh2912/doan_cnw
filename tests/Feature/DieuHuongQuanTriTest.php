<?php

namespace Tests\Feature;

use App\Models\KhachHang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * A2: lỗi/redirect trong khu quản trị phải ở lại khu quản trị,
 * không văng ra storefront '/'; có đường "Xem cửa hàng" hai chiều.
 */
class DieuHuongQuanTriTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('pos.pin_bat', true);
        Config::set('pos.ma_duyet_bat', true);
    }

    private function staff(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_post_pin_khong_referer_ve_tongquan_khong_ve_trang_chu(): void
    {
        // POST /trahang (M2) của thu ngân, không _ma_duyet, không Referer.
        $thungan = $this->staff('thungan');

        $response = $this->actingAs($thungan)->post(route('trahang.luu'), []);

        $response->assertRedirect(route('tongquan'));
        $response->assertSessionHas('can_ma_duyet');
    }

    public function test_post_pin_m1_khong_referer_cung_ve_tongquan(): void
    {
        // PUT /khachhang/sua/{khachHang} (M1) — middleware chặn trước controller.
        $khachHang = KhachHang::create([
            'ho_ten' => 'Khách Test', 'so_dienthoai' => '0900000001', 'diem_tichluy' => 0,
        ]);
        $thungan = $this->staff('thungan');

        $response = $this->actingAs($thungan)
            ->put(route('khachhang.postsua', $khachHang), []);

        $response->assertRedirect(route('tongquan'));
        $response->assertSessionHas('loi_pin');
    }

    public function test_referer_hop_le_van_duoc_uu_tien_ghi_de(): void
    {
        $thungan = $this->staff('thungan');
        $trangQuanLy = route('khachhang.danhsach');

        $response = $this->actingAs($thungan)
            ->withHeader('referer', $trangQuanLy)
            ->post(route('trahang.luu'), []);

        $response->assertRedirect($trangQuanLy);
    }

    public function test_loi_403_trong_khu_quan_tri_hien_nut_quan_tri_cho_staff(): void
    {
        // Thủ kho không có quyền nhóm role:admin,thungan -> 403.
        $thukho = $this->staff('thukho');

        $response = $this->actingAs($thukho)->get(route('khachhang.danhsach'));

        $response->assertForbidden();
        $response->assertSee('Về tổng quan');
        $response->assertSee('Xem cửa hàng');
        $response->assertSee(route('storefront.home'), false);
    }

    public function test_loi_404_cho_guest_hien_nut_storefront(): void
    {
        $response = $this->get('/duong-dan-khong-ton-tai-xyz');

        $response->assertNotFound();
        $response->assertSee('Về trang chủ');
        $response->assertDontSee('Về tổng quan');
    }

    public function test_link_xem_cua_hang_hien_cho_ca_3_vai_tro(): void
    {
        foreach (['admin', 'thungan', 'thukho'] as $role) {
            $response = $this->actingAs($this->staff($role))->get(route('tongquan'));
            $response->assertOk();
            $response->assertSee('Xem cửa hàng');
            $response->assertSee(route('storefront.home'), false);
        }
    }

    public function test_khach_hang_khong_thay_link_quan_tri_va_bi_day_ve_storefront(): void
    {
        $khach = $this->staff('khachhang');

        // Khách vào thẳng khu quản trị -> RedirectKhachhangFromStaff về storefront.
        $this->actingAs($khach)->get(route('tongquan'))
            ->assertRedirect(route('storefront.home'));

        // Storefront của khách không có link quản trị.
        $this->actingAs($khach)->get(route('storefront.home'))
            ->assertOk()
            ->assertDontSee('Xem cửa hàng')
            ->assertDontSee('Trang quản trị');
    }

    public function test_staff_vao_duoc_storefront(): void
    {
        foreach (['admin', 'thungan', 'thukho'] as $role) {
            $this->actingAs($this->staff($role))
                ->get(route('storefront.home'))
                ->assertOk();
        }
    }

    public function test_het_phien_post_quan_tri_chuyen_login(): void
    {
        // Guest POST vào route quản trị -> redirect đăng nhập (kèm intended).
        $response = $this->post(route('trahang.luu'), []);

        $response->assertRedirect();
        $this->assertStringContainsString('dangnhap', (string) $response->headers->get('Location'));
    }
}
