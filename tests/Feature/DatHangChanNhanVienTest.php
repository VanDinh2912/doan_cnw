<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\NhatKyHoatDong;
use App\Models\SanPham;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M1 — Tai khoan nhan vien (admin/thungan/thukho) khong dat hang online
 * qua storefront: POST /dat-hang va /don-hang/{ma}/huy bi chan o server,
 * co ghi nhat ky. Van xem gio/form/tra cuu/trang thai. Guest + khachhang
 * khong doi hanh vi.
 */
class DatHangChanNhanVienTest extends TestCase
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

    private function cart(SanPham $sp, int $qty = 1): array
    {
        return [(string) $sp->id => [
            'id' => $sp->id,
            'sku' => $sp->sku,
            'ten_sanpham' => $sp->ten_sanpham,
            'gia_ban' => (float) $sp->gia_ban,
            'soluong' => $qty,
            'ton_kho' => $sp->soluong,
        ]];
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'ten' => 'Khách Test',
            'so_dienthoai' => '0901234567',
            'ghi_chu' => null,
            'phuong_thuc' => 'pickup',
            'checkout_token' => 'TOKEN',
        ], $overrides);
    }

    private function postDat(User $user, array $cart): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)
            ->withSession(['checkout_token' => 'TOKEN', 'storefront_cart' => $cart])
            ->post(route('dat-hang.dat'), $this->payload());
    }

    public function test_nv_dat_hang_bi_chan_server_khong_tao_don(): void
    {
        $this->seed();
        $sp = $this->sp();
        $thuNgan = User::where('role', 'thungan')->firstOrFail();

        $truoc = DonHang::count();
        $res = $this->postDat($thuNgan, $this->cart($sp));

        $res->assertSessionHas('error');
        $this->assertStringContainsString('POS', (string) session('error'));
        $this->assertSame($truoc, DonHang::count(), 'Khong duoc tao don nao');
        $this->assertSame($sp->soluong, $sp->fresh()->soluong, 'Khong duoc tru ton');
    }

    public function test_ca_3_role_nv_deu_bi_chan(): void
    {
        $this->seed();
        $sp = $this->sp();
        foreach (['admin', 'thungan', 'thukho'] as $role) {
            $nv = User::where('role', $role)->firstOrFail();
            $this->postDat($nv, $this->cart($sp))->assertSessionHas('error');
        }
        $this->assertSame(0, DonHang::where('kenh', 'online')->count());
    }

    public function test_chan_co_ghi_nhat_ky_khong_pii(): void
    {
        $this->seed();
        $sp = $this->sp();
        $thuNgan = User::where('role', 'thungan')->firstOrFail();

        $this->postDat($thuNgan, $this->cart($sp));

        $log = NhatKyHoatDong::where('hanh_dong', 'chan_dat_hang_nhan_vien')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($thuNgan->id, $log->user_id);
        $this->assertStringNotContainsString('0901234567', (string) $log->mo_ta);
        $this->assertStringNotContainsString('Khách Test', (string) $log->mo_ta);
    }

    public function test_nv_huy_don_qua_trang_khach_bi_chan(): void
    {
        $this->seed();
        $sp = $this->sp();
        // Don online dang cho thanh toan (tao nhu guest)
        $this->withSession(['checkout_token' => 'T2', 'storefront_cart' => $this->cart($sp)])
            ->post(route('dat-hang.dat'), $this->payload(['checkout_token' => 'T2']));
        $donHang = DonHang::where('kenh', 'online')->latest('id')->firstOrFail();

        $thuNgan = User::where('role', 'thungan')->firstOrFail();
        $res = $this->actingAs($thuNgan)
            ->withSession(['don_hang_xac_thuc' => [$donHang->ma_tra_cuu => true]])
            ->post(route('don-hang.huy', $donHang->ma_tra_cuu));

        $res->assertSessionHas('error');
        $this->assertSame('cho_thanh_toan', $donHang->fresh()->trang_thai, 'Don khong duoc huy');
    }

    public function test_nv_van_xem_gio_va_form_nut_vo_hieu(): void
    {
        $this->seed();
        $sp = $this->sp();
        $thuNgan = User::where('role', 'thungan')->firstOrFail();

        $this->actingAs($thuNgan)->get(route('storefront.cart.index'))->assertOk();

        $html = $this->actingAs($thuNgan)
            ->withSession(['checkout_token' => 'T', 'storefront_cart' => $this->cart($sp)])
            ->get(route('dat-hang.form'))->assertOk()->getContent();
        $this->assertStringContainsString('POS', $html);
        $this->assertStringContainsString('disabled', $html);
    }

    public function test_khachhang_va_guest_dat_binh_thuong(): void
    {
        $this->seed();
        $sp = $this->sp();

        // Guest
        $this->withSession(['checkout_token' => 'TG', 'storefront_cart' => $this->cart($sp)])
            ->post(route('dat-hang.dat'), $this->payload(['checkout_token' => 'TG']))
            ->assertRedirect();
        $this->assertSame(1, DonHang::where('kenh', 'online')->count());

        // Tai khoan khach hang dang nhap
        $khach = User::create([
            'name' => 'KH', 'email' => 'kh'.uniqid().'@test.local',
            'password' => bcrypt('x'), 'role' => 'khachhang',
        ]);
        $this->actingAs($khach)
            ->withSession(['checkout_token' => 'TK', 'storefront_cart' => $this->cart($sp)])
            ->post(route('dat-hang.dat'), $this->payload(['checkout_token' => 'TK']))
            ->assertRedirect();
        $this->assertSame(2, DonHang::where('kenh', 'online')->count());
    }
}
