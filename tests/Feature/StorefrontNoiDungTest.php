<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\DonHang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Nội dung storefront đúng ngành phụ kiện điện thoại:
 * tên cửa hàng/tagline đọc từ config, email dùng tên cửa hàng,
 * brand-strip đọc từ bảng brands, hero/trust-bar đổi theo cờ giao tận nơi.
 */
class StorefrontNoiDungTest extends TestCase
{
    use RefreshDatabase;

    public function test_ten_cua_hang_doi_theo_config_o_tieu_de_va_header(): void
    {
        Config::set('storefront.ten_cua_hang', 'TestShop');
        Config::set('storefront.tieu_de', 'Phụ kiện chính hãng');

        $this->get(route('storefront.home'))
            ->assertOk()
            ->assertSee('TestShop — Phụ kiện chính hãng', false)
            ->assertSee('TestShop');
    }

    public function test_email_dung_ten_cua_hang_khong_dung_app_name(): void
    {
        Config::set('storefront.ten_cua_hang', 'TestShop');
        Config::set('app.name', 'Laravel');

        $don = new DonHang();
        $don->ma_hoadon = 'HD20260101-0001';
        $don->ten_khach_dat = 'Nguyen Van A';
        $don->ma_tra_cuu = 'TRA123';
        $don->hinh_thuc_nhan = 'pickup';
        $don->phuong_thuc = 'tienmat';
        $don->han_giu = null;
        $don->dia_chi_giao = null;
        $don->tong_thanh_toan = 100000;
        $don->so_dienthoai_dat = '0901234567';
        $don->setRelation('chiTietDonHangs', collect());

        $html = view('emails.dat-hang-online-xac-nhan', ['donHang' => $don])->render();

        $this->assertStringContainsString('TestShop', $html);
        $this->assertStringNotContainsString('Laravel', $html);
    }

    public function test_brand_strip_chi_hien_hang_kich_hoat_va_an_khi_khong_co(): void
    {
        Brand::create(['ten' => 'Hoco', 'slug' => 'hoco', 'kich_hoat' => true]);
        Brand::create(['ten' => 'Remax', 'slug' => 'remax', 'kich_hoat' => true]);
        Brand::create(['ten' => 'HangAn', 'slug' => 'hangan', 'kich_hoat' => false]);

        $this->get(route('storefront.home'))
            ->assertOk()
            ->assertSee('sf-brand-strip', false)
            ->assertSee('Hoco — sắp ra mắt')
            ->assertSee('Remax — sắp ra mắt')
            ->assertDontSee('HangAn — sắp ra mắt');

        Brand::query()->delete();

        $this->get(route('storefront.home'))
            ->assertOk()
            ->assertDontSee('sf-brand-strip', false);
    }

    public function test_giao_tan_noi_tat_hien_nhan_tai_cua_hang_khong_freeship(): void
    {
        Config::set('pos.giao_tan_noi_bat', false);

        $this->get(route('storefront.home'))
            ->assertOk()
            ->assertSee('Nhận tại cửa hàng')
            ->assertSee('Đặt online, ghé lấy hàng')
            ->assertSee('Kiểm tra hàng trước khi thanh toán')
            ->assertSee('Hỗ trợ '.config('storefront.gio_lam_viec'))
            ->assertDontSee('Freeship')
            ->assertDontSee('500.000')
            ->assertDontSee('Giao 24h')
            ->assertDontSee('24/7');
    }

    public function test_giao_tan_noi_bat_hien_freeship_theo_nguong_mien_phi(): void
    {
        Config::set('pos.giao_tan_noi_bat', true);
        Config::set('pos.mien_phi_giao_tu', 500000);

        $this->get(route('storefront.home'))
            ->assertOk()
            ->assertSee('Freeship từ 500.000 ₫')
            ->assertSee('Giao trong 24h nội thành')
            ->assertSee('Giao 24h nội thành')
            ->assertDontSee('24/7');
    }

    public function test_giao_tan_noi_bat_nhung_chua_co_nguong_mien_phi(): void
    {
        Config::set('pos.giao_tan_noi_bat', true);
        Config::set('pos.mien_phi_giao_tu', 0);

        $this->get(route('storefront.home'))
            ->assertOk()
            ->assertSee('Giao tận nơi')
            ->assertDontSee('Freeship');
    }
}
