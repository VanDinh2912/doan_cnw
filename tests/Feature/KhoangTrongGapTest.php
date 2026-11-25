<?php

namespace Tests\Feature;

use App\Models\ChiTietHoaDonNhap;
use App\Models\GioTam;
use App\Models\HoaDonNhap;
use App\Models\NhaCungCap;
use App\Models\SanPham;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test bo sung cho 5 route GHI chua co test goi toi (sheet "Khoang trong"):
 * storefront.cart.update / storefront.cart.clear / hold.xoa /
 * hoadonnhap.huy / hoadonnhap.postsua. Chay sqlite :memory:.
 */
class KhoangTrongGapTest extends TestCase
{
    use RefreshDatabase;

    private function cartSession(SanPham $sp, int $qty = 1): array
    {
        return [
            'storefront_cart' => [
                (string) $sp->id => [
                    'id' => $sp->id, 'sku' => $sp->sku, 'ten_sanpham' => $sp->ten_sanpham,
                    'gia_ban' => (float) $sp->gia_ban, 'soluong' => $qty,
                    'ton_kho' => $sp->soluong,
                ],
            ],
        ];
    }

    private function taoHoaDonNhapTam(User $user): HoaDonNhap
    {
        $sp = SanPham::firstOrFail();
        $hd = HoaDonNhap::create([
            'ma_phieu' => 'PN-GAP-'.uniqid(),
            'so_hoadon' => 'GAP-001',
            'ky_hieu' => '1C26GAP',
            'ngay_hoadon' => now()->toDateString(),
            'supplier_id' => NhaCungCap::firstOrFail()->id,
            'user_id' => $user->id,
            'trang_thai' => 'nhap_tam',
            'hinh_thuc_thanh_toan' => 'tienmat',
            'tong_tien_hang' => 100000,
            'thue_suat' => 0,
            'tien_thue' => 0,
            'tong_thanh_toan' => 100000,
        ]);
        ChiTietHoaDonNhap::create([
            'purchase_invoice_id' => $hd->id, 'stt' => 1, 'product_id' => $sp->id,
            'ten_hang' => $sp->ten_sanpham, 'don_vi' => $sp->don_vi,
            'soluong' => 1, 'don_gia' => 100000, 'thanh_tien' => 100000,
        ]);
        return $hd;
    }

    private function payloadSua(HoaDonNhap $hd, SanPham $sp): array
    {
        return [
            'supplier_id' => $hd->supplier_id,
            'so_hoadon' => 'GAP-001-SUA',
            'ky_hieu' => '1C26GAP',
            'ngay_hoadon' => now()->toDateString(),
            'thue_suat' => 0,
            'hinh_thuc_thanh_toan' => 'tienmat',
            'items' => [[
                'product_id' => $sp->id, 'ten_hang' => $sp->ten_sanpham,
                'don_vi' => $sp->don_vi, 'soluong' => 2, 'don_gia' => 50000,
            ]],
        ];
    }

    // ---------- storefront.cart.update ----------

    public function test_cart_update_doi_so_luong_trong_session(): void
    {
        $this->seed();
        $sp = SanPham::where('soluong', '>', 5)->firstOrFail();
        $this->withSession($this->cartSession($sp, 1))
            ->put(route('storefront.cart.update'), ['soluong' => [(string) $sp->id => 3]])
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame(3, session('storefront_cart')[(string) $sp->id]['soluong']);
    }

    public function test_cart_update_tu_choi_khi_vuot_ton_kho(): void
    {
        $this->seed();
        $sp = SanPham::where('soluong', '>', 1)->firstOrFail();
        $this->withSession($this->cartSession($sp, 1))
            ->put(route('storefront.cart.update'), ['soluong' => [(string) $sp->id => $sp->soluong + 1]])
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertSame(1, session('storefront_cart')[(string) $sp->id]['soluong']);
    }

    // ---------- storefront.cart.clear ----------

    public function test_cart_clear_xoa_storefront_cart_nhung_giu_gio_pos(): void
    {
        $this->seed();
        $sp = SanPham::firstOrFail();
        $session = $this->cartSession($sp, 2);
        $session['gio_hang'] = ['99' => ['id' => 99, 'soluong' => 1]];
        $this->withSession($session)
            ->delete(route('storefront.cart.clear'))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertNull(session('storefront_cart'));
        $this->assertNotNull(session('gio_hang')); // khong dung gio POS
    }

    // ---------- hold.xoa ----------

    public function test_hold_xoa_chu_gio_xoa_duoc_gio_tam_cua_minh(): void
    {
        $this->seed();
        $cashier = User::where('role', 'thungan')->firstOrFail();
        $gioTam = GioTam::create(['user_id' => $cashier->id, 'ten_goi' => 'Khách chờ', 'du_lieu' => []]);
        $this->actingAs($cashier)
            ->post(route('hold.xoa', $gioTam))
            ->assertRedirect();
        $this->assertDatabaseMissing('held_carts', ['id' => $gioTam->id]);
    }

    public function test_hold_xoa_chan_thu_kho_va_thu_ngan_khac(): void
    {
        $this->seed();
        $owner = User::where('role', 'thungan')->firstOrFail();
        $other = User::factory()->create(['role' => 'thungan']);
        $thuKho = User::where('role', 'thukho')->firstOrFail();
        $gioTam = GioTam::create(['user_id' => $owner->id, 'ten_goi' => 'Giỏ A', 'du_lieu' => []]);

        // thu kho: sai role -> 403
        $this->actingAs($thuKho)->post(route('hold.xoa', $gioTam))->assertForbidden();
        // thu ngan khac chu gio: dung role nhung khong phai owner -> 403
        $this->actingAs($other)->post(route('hold.xoa', $gioTam))->assertForbidden();
        $this->assertDatabaseHas('held_carts', ['id' => $gioTam->id]);
    }

    // ---------- hoadonnhap.huy ----------

    public function test_hoadonnhap_huy_chuyen_nhap_tam_sang_da_huy(): void
    {
        $this->seed();
        $admin = User::where('role', 'admin')->firstOrFail();
        $hd = $this->taoHoaDonNhapTam($admin);
        $this->actingAs($admin)->post(route('hoadonnhap.huy', $hd))->assertRedirect();
        $this->assertSame('da_huy', $hd->fresh()->trang_thai);
    }

    public function test_hoadonnhap_huy_chan_thu_ngan_va_phieu_khong_nhap_tam(): void
    {
        $this->seed();
        $admin = User::where('role', 'admin')->firstOrFail();
        $thuNgan = User::where('role', 'thungan')->firstOrFail();
        $hd = $this->taoHoaDonNhapTam($admin);

        // thu ngan: role:admin,thukho -> 403
        $this->actingAs($thuNgan)->post(route('hoadonnhap.huy', $hd))->assertForbidden();
        $this->assertSame('nhap_tam', $hd->fresh()->trang_thai);

        // phieu da_huy roi huy lai -> 422
        $hd->update(['trang_thai' => 'da_huy']);
        $this->actingAs($admin)->post(route('hoadonnhap.huy', $hd))->assertStatus(422);
    }

    // ---------- hoadonnhap.postsua ----------

    public function test_hoadonnhap_postsua_cap_nhat_phieu_nhap_tam(): void
    {
        $this->seed();
        $thuKho = User::where('role', 'thukho')->firstOrFail();
        $hd = $this->taoHoaDonNhapTam($thuKho);
        $sp = SanPham::firstOrFail();
        $this->actingAs($thuKho)
            ->post(route('hoadonnhap.postsua', $hd), $this->payloadSua($hd, $sp))
            ->assertRedirect(route('hoadonnhap.xem', $hd));
        $hd->refresh();
        $this->assertSame('GAP-001-SUA', $hd->so_hoadon);
        $this->assertSame(2, $hd->chiTiets()->count() === 1 ? (int) $hd->chiTiets()->first()->soluong : -1);
        $this->assertSame(100000, (int) $hd->tong_thanh_toan); // 2*50000 + thue 0
    }

    public function test_hoadonnhap_postsua_chan_thu_ngan_va_phieu_da_nhap(): void
    {
        $this->seed();
        $admin = User::where('role', 'admin')->firstOrFail();
        $thuNgan = User::where('role', 'thungan')->firstOrFail();
        $hd = $this->taoHoaDonNhapTam($admin);
        $sp = SanPham::firstOrFail();

        $this->actingAs($thuNgan)
            ->post(route('hoadonnhap.postsua', $hd), $this->payloadSua($hd, $sp))
            ->assertForbidden();

        $hd->update(['trang_thai' => 'da_nhap_kho']);
        $this->actingAs($admin)
            ->post(route('hoadonnhap.postsua', $hd), $this->payloadSua($hd, $sp))
            ->assertStatus(422);
    }
}
