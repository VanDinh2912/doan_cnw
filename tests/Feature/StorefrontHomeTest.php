<?php

namespace Tests\Feature;

use App\Models\LoaiSanPham;
use App\Models\SanPham;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontHomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_visit_homepage_and_see_branding_sections(): void
    {
        $response = $this->get(route('storefront.home'));

        $response->assertStatus(200);
        $response->assertSee('PhoneShop');
        $response->assertSee('Điện thoại nổi bật');
        $response->assertSee('sfQuickViewModal', false);
    }

    public function test_homepage_renders_real_products_when_present_in_database(): void
    {
        $category = LoaiSanPham::create([
            'ten_loai' => 'iPhone Series',
            'mota' => 'Các dòng điện thoại Apple iPhone',
        ]);

        $product = SanPham::create([
            'sku' => 'IPHONE-15-PRO',
            'ten_sanpham' => 'iPhone 15 Pro 128GB',
            'gia_ban' => 25990000,
            'gia_nhap' => 22000000,
            'soluong' => 15,
            'soluong_toithieu' => 5,
            'trang_thai' => true,
            'category_id' => $category->id,
            'hinh_anh' => 'img/sanpham/test.jpg',
        ]);

        $response = $this->get(route('storefront.home'));

        $response->assertStatus(200);
        $response->assertSee('iPhone 15 Pro 128GB');
        $response->assertSee('25.990.000 ₫');
        $response->assertSee('Còn hàng');
        $response->assertSee('IPHONE-15-PRO');
        $response->assertSee('data-price="25.990.000 ₫"', false);
        $response->assertSee('Flash Sale');
    }

    public function test_homepage_orders_categories_by_product_count(): void
    {
        $catA = LoaiSanPham::create(['ten_loai' => 'Category A']);
        $catB = LoaiSanPham::create(['ten_loai' => 'Category B']);

        SanPham::create([
            'sku' => 'PROD-A1',
            'ten_sanpham' => 'Phone A1',
            'gia_ban' => 10000000,
            'gia_nhap' => 8000000,
            'soluong' => 5,
            'soluong_toithieu' => 1,
            'trang_thai' => true,
            'category_id' => $catA->id,
        ]);

        SanPham::create([
            'sku' => 'PROD-B1',
            'ten_sanpham' => 'Phone B1',
            'gia_ban' => 12000000,
            'gia_nhap' => 9000000,
            'soluong' => 5,
            'soluong_toithieu' => 1,
            'trang_thai' => true,
            'category_id' => $catB->id,
        ]);
        SanPham::create([
            'sku' => 'PROD-B2',
            'ten_sanpham' => 'Phone B2',
            'gia_ban' => 13000000,
            'gia_nhap' => 9000000,
            'soluong' => 5,
            'soluong_toithieu' => 1,
            'trang_thai' => true,
            'category_id' => $catB->id,
        ]);

        $response = $this->get(route('storefront.home'));

        $response->assertStatus(200);
        $response->assertSeeInOrder(['Category B', 'Category A']);
    }

    public function test_homepage_renders_empty_state_cleanly_when_no_active_products(): void
    {
        $response = $this->get(route('storefront.home'));

        $response->assertStatus(200);
        $response->assertSee('Sản phẩm đang được cập nhật');
    }

    public function test_logged_in_khachhang_sees_name_and_no_admin_dashboard_link(): void
    {
        $user = User::create([
            'role' => 'khachhang',
            'name' => 'Nguyễn Khách Hàng',
            'email' => 'khach@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->actingAs($user)->get(route('storefront.home'));

        $response->assertStatus(200);
        $response->assertSee('Nguyễn Khách Hàng');
        $response->assertSee('Đăng xuất');
        $response->assertDontSee('Trang quản trị');
    }

    public function test_logged_in_staff_sees_admin_dashboard_link_in_header(): void
    {
        $admin = User::create([
            'role' => 'admin',
            'name' => 'Admin Boss',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->actingAs($admin)->get(route('storefront.home'));

        $response->assertStatus(200);
        $response->assertSee('Admin Boss');
        $response->assertSee('Trang quản trị');
    }
}

