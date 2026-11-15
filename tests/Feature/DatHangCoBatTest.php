<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\SanPham;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Cờ bật/tắt đặt hàng online (POS_DAT_HANG_ONLINE_BAT / POS_GIAO_TAN_NOI_BAT).
 * phpunit.xml mặc định bật cả hai để giữ hành vi P1/P2; test này tắt bằng Config::set.
 * Khi cờ tắt: mọi route đặt hàng online trả 404, giỏ hàng vẫn xem được,
 * nút "Đặt hàng" bị ẩn, giao_tan_noi bị từ chối ở server.
 */
class DatHangCoBatTest extends TestCase
{
    use RefreshDatabase;

    private function sp(int $ton = 10): SanPham
    {
        if (SanPham::query()->count() === 0) {
            $this->seed();
        }
        $sp = SanPham::orderBy('id')->firstOrFail();
        $sp->update(['soluong' => $ton, 'trang_thai' => true]);

        return $sp->fresh();
    }

    private function cart(SanPham $sp): array
    {
        return [(string) $sp->id => [
            'id' => $sp->id,
            'sku' => $sp->sku,
            'ten_sanpham' => $sp->ten_sanpham,
            'gia_ban' => (float) $sp->gia_ban,
            'soluong' => 1,
            'ton_kho' => $sp->soluong,
            'hinh_anh' => null,
        ]];
    }

    private function tatCoDatHang(): void
    {
        Config::set('pos.dat_hang_online_bat', false);
    }

    public function test_co_tat_chan_moi_route_dat_hang_online_khong_500(): void
    {
        $this->tatCoDatHang();

        $this->get(route('dat-hang.form'))->assertNotFound();
        $this->post(route('dat-hang.dat'), [])->assertNotFound();
        $this->get(route('dat-hang.qr', 'MA1234567890'))->assertNotFound();
        $this->get(route('dat-hang.thanhcong', 'MA1234567890'))->assertNotFound();
        $this->get(route('don-hang.tracuu'))->assertNotFound();
        $this->post(route('don-hang.posttracuu'), [])->assertNotFound();
        $this->get(route('don-hang.trangthai', 'MA1234567890'))->assertNotFound();
        $this->post(route('don-hang.poll', 'MA1234567890'), [])->assertNotFound();
        $this->post(route('don-hang.huy', 'MA1234567890'), [])->assertNotFound();
    }

    public function test_co_tat_gio_hang_van_xem_duoc_nhung_an_nut_dat_hang(): void
    {
        $this->tatCoDatHang();
        $sp = $this->sp();

        $res = $this->withSession(['storefront_cart' => $this->cart($sp)])
            ->get(route('storefront.cart.index'));

        $res->assertOk();
        $res->assertDontSee('Đặt hàng');
    }

    public function test_co_bat_hien_nut_dat_hang_va_form_truy_cap_duoc(): void
    {
        $sp = $this->sp();

        $res = $this->withSession(['storefront_cart' => $this->cart($sp)])
            ->get(route('storefront.cart.index'));
        $res->assertOk();
        $res->assertSee('Đặt hàng');

        $this->withSession(['storefront_cart' => $this->cart($sp)])
            ->get(route('dat-hang.form'))
            ->assertOk();
    }

    public function test_giao_tan_noi_bi_tu_choi_khi_co_thu_hai_tat(): void
    {
        Config::set('pos.dat_hang_online_bat', true);
        Config::set('pos.giao_tan_noi_bat', false);

        $sp = $this->sp();
        $token = 'TOKEN-GT';

        $res = $this->withSession([
            'checkout_token' => $token,
            'storefront_cart' => $this->cart($sp),
        ])->post(route('dat-hang.dat'), [
            'ten' => 'Khách Giao',
            'so_dienthoai' => '0901234567',
            'hinh_thuc_nhan' => 'giao_tan_noi',
            'phuong_thuc' => 'qr',
            'checkout_token' => $token,
            'ten_nguoi_nhan' => 'Người Nhận',
            'sdt_nguoi_nhan' => '0907654321',
            'dia_chi_so_nha' => '1 Đường A',
            'dia_chi_phuong_xa' => 'Phường B',
            'dia_chi_quan_huyen' => 'Quận C',
            'dia_chi_tinh_thanh' => 'Hồ Chí Minh',
        ]);

        $res->assertSessionHasErrors('hinh_thuc_nhan');
        $this->assertSame(0, DonHang::where('kenh', 'online')->count());
    }

    public function test_giao_tan_noi_duoc_chap_nhan_khi_ca_hai_co_bat(): void
    {
        Config::set('pos.dat_hang_online_bat', true);
        Config::set('pos.giao_tan_noi_bat', true);

        $sp = $this->sp();
        $token = 'TOKEN-GT2';

        $res = $this->withSession([
            'checkout_token' => $token,
            'storefront_cart' => $this->cart($sp),
        ])->post(route('dat-hang.dat'), [
            'ten' => 'Khách Giao',
            'so_dienthoai' => '0901234567',
            'hinh_thuc_nhan' => 'giao_tan_noi',
            'phuong_thuc' => 'qr',
            'checkout_token' => $token,
            'ten_nguoi_nhan' => 'Người Nhận',
            'sdt_nguoi_nhan' => '0907654321',
            'dia_chi_so_nha' => '1 Đường A',
            'dia_chi_phuong_xa' => 'Phường B',
            'dia_chi_quan_huyen' => 'Quận C',
            'dia_chi_tinh_thanh' => 'Hồ Chí Minh',
        ]);

        $res->assertRedirect();
        $don = DonHang::where('kenh', 'online')->firstOrFail();
        $this->assertSame('giao_tan_noi', $don->hinh_thuc_nhan);
    }

    public function test_form_an_lua_chon_giao_tan_noi_khi_co_thu_hai_tat(): void
    {
        Config::set('pos.dat_hang_online_bat', true);
        Config::set('pos.giao_tan_noi_bat', false);

        $sp = $this->sp();

        $res = $this->withSession(['storefront_cart' => $this->cart($sp)])
            ->get(route('dat-hang.form'));

        $res->assertOk();
        $res->assertDontSee('value="giao_tan_noi"', false);
        $res->assertSee('value="tai_cua_hang"', false);
    }

    public function test_kiem_tra_he_thong_bao_trang_thai_hai_co(): void
    {
        Config::set('pos.dat_hang_online_bat', false);
        Config::set('pos.giao_tan_noi_bat', false);

        // Lệnh có thể FAIL vì các mục khác (bank/sepay/...) trong môi trường test;
        // chỉ cần dòng báo trạng thái hai cờ xuất hiện trong output.
        \Illuminate\Support\Facades\Artisan::call('he-thong:kiem-tra');
        $out = \Illuminate\Support\Facades\Artisan::output();
        $this->assertStringContainsString('Đặt hàng online', $out);
        $this->assertStringContainsString('Giao tận nơi', $out);
    }
}
