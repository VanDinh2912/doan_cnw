<?php

namespace Tests\Feature;

use App\Models\BienDongKho;
use App\Models\DonHang;
use App\Models\SanPham;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P1 — Checkout guest pickup (T-DH03..07) + trần giữ pickup trong đường tạo đơn
 * + movement user_id null (M2). SQLite :memory:, dữ liệu tạo bằng model/seed.
 * Hợp đồng: POST /dat-hang (ten, so_dienthoai, ghi_chu?, phuong_thuc=pickup,
 * checkout_token) + session storefront_cart; thành công 302 → trang thành công.
 */
class DatHangP1Test extends TestCase
{
    use RefreshDatabase;

    private function sp(int $ton, int $chiSo = 0): SanPham
    {
        // Seed một lần mỗi test (RefreshDatabase reset theo test); gọi lại khi đã có
        // dữ liệu để tránh chèn trùng categories.id ở test cần nhiều sản phẩm.
        if (SanPham::query()->count() === 0) {
            $this->seed();
        }
        $sp = SanPham::orderBy('id')->skip($chiSo)->firstOrFail();
        $sp->update(['soluong' => $ton, 'trang_thai' => true]);

        return $sp->fresh();
    }

    private function cart(SanPham $sp, int $qty, ?float $gia = null): array
    {
        return [(string) $sp->id => [
            'id' => $sp->id,
            'sku' => $sp->sku,
            'ten_sanpham' => $sp->ten_sanpham,
            'gia_ban' => $gia ?? (float) $sp->gia_ban,
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

    private function dat(array $cart, array $payload = [], string $token = 'TOKEN'): \Illuminate\Testing\TestResponse
    {
        return $this->withSession([
            'checkout_token' => $token,
            'storefront_cart' => $cart,
        ])->post(route('dat-hang.dat'), $this->payload(array_merge(['checkout_token' => $token], $payload)));
    }

    public function test_guest_checkout_pickup_tao_don_giu_kho_movement_null_user(): void
    {
        $sp = $this->sp(10);

        $res = $this->dat($this->cart($sp, 2));

        $res->assertRedirect();
        $don = DonHang::where('kenh', 'online')->firstOrFail();
        $res->assertRedirect(route('dat-hang.thanhcong', $don->ma_tra_cuu));

        $this->assertNull($don->user_id);
        $this->assertNull($don->customer_id);
        $this->assertSame('tienmat', $don->phuong_thuc);
        $this->assertSame('cho_thanh_toan', $don->trang_thai);
        $this->assertNotNull($don->ma_tra_cuu);
        $this->assertNotNull($don->han_giu);
        $this->assertSame('Khách Test', $don->ten_khach_dat);
        $this->assertSame('0901234567', $don->so_dienthoai_dat);

        $this->assertDatabaseHas('products', ['id' => $sp->id, 'soluong' => 8]);
        $this->assertDatabaseHas('invoice_details', [
            'invoice_id' => $don->id, 'product_id' => $sp->id, 'soluong' => 2,
        ]);
        // M2: movement của đơn guest có user_id null
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $sp->id, 'loai' => 'ban', 'soluong' => -2, 'user_id' => null,
        ]);
        // Giỏ xóa sau đặt
        $this->assertEmpty(session('storefront_cart'));
    }

    public function test_gia_tinh_lai_tu_db_khong_tin_gia_session(): void
    {
        $sp = $this->sp(10);
        $sp->update(['gia_ban' => 99000]);

        // Giỏ session mang giá giả 1đ — server phải tính lại từ DB (99000)
        $this->dat($this->cart($sp, 2, 1.0));

        $don = DonHang::where('kenh', 'online')->firstOrFail();
        $this->assertEquals(198000, (float) $don->tong_tien);
        $this->assertEquals(198000, (float) $don->tong_thanh_toan);
        $this->assertDatabaseHas('invoice_details', [
            'invoice_id' => $don->id, 'don_gia' => 99000, 'thanh_tien' => 198000,
        ]);
    }

    public function test_vuot_ton_khong_tao_don_kho_nguyen_ven(): void
    {
        $sp = $this->sp(7);

        $res = $this->dat($this->cart($sp, 99));

        $res->assertRedirect();
        $res->assertSessionHas('error');
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseHas('products', ['id' => $sp->id, 'soluong' => 7]);
    }

    public function test_double_submit_cung_token_chi_mot_don(): void
    {
        $sp = $this->sp(10);
        $cart = $this->cart($sp, 2);

        $this->withSession(['checkout_token' => 'TOKEN', 'storefront_cart' => $cart])
            ->post(route('dat-hang.dat'), $this->payload());
        // Lần 2 cùng token: token đã bị pull, giỏ đã xóa → không tạo đơn thứ hai
        $this->post(route('dat-hang.dat'), $this->payload());

        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseHas('products', ['id' => $sp->id, 'soluong' => 8]);
    }

    public function test_token_khong_khop_thi_tu_choi(): void
    {
        $sp = $this->sp(10);

        $res = $this->withSession(['checkout_token' => 'TOKEN', 'storefront_cart' => $this->cart($sp, 2)])
            ->post(route('dat-hang.dat'), $this->payload(['checkout_token' => 'SAI']));

        $res->assertRedirect();
        $res->assertSessionHas('error');
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseHas('products', ['id' => $sp->id, 'soluong' => 10]);
    }

    public function test_validate_thieu_ten_sdt_sai_ghi_chu_qua_dai(): void
    {
        $sp = $this->sp(10);
        $cart = $this->cart($sp, 1);

        $this->dat($cart, ['ten' => ''])->assertSessionHasErrors('ten');
        $this->dat($cart, ['so_dienthoai' => '123'])->assertSessionHasErrors('so_dienthoai');
        $this->dat($cart, ['ghi_chu' => str_repeat('a', 501)])->assertSessionHasErrors('ghi_chu');
        $this->dat($cart, ['phuong_thuc' => 'qr'])->assertSessionHasErrors('phuong_thuc');

        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseHas('products', ['id' => $sp->id, 'soluong' => 10]);
    }

    public function test_sp_an_het_hang_xoa_trong_gio_bi_tu_choi_toan_bo(): void
    {
        // SP tạm dừng
        $spAn = $this->sp(10);
        $spAn->update(['trang_thai' => false]);
        $this->dat($this->cart($spAn, 1))->assertSessionHas('error');
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseHas('products', ['id' => $spAn->id, 'soluong' => 10]);

        // SP hết hàng
        $spHet = $this->sp(0, 1);
        $this->dat($this->cart($spHet, 1))->assertSessionHas('error');
        $this->assertDatabaseCount('invoices', 0);

        // SP không tồn tại (đã xóa khỏi DB)
        $cartAo = [999999 => ['id' => 999999, 'soluong' => 1, 'gia_ban' => 1000]];
        $this->dat($cartAo)->assertSessionHas('error');
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_tran_pickup_ap_dung_trong_duong_tao_don(): void
    {
        $sp = $this->sp(10); // trần 50% = 5

        // Đặt 6 > trần 5 → từ chối, không tạo đơn, kho nguyên
        $this->dat($this->cart($sp, 6))->assertSessionHas('error');
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseHas('products', ['id' => $sp->id, 'soluong' => 10]);

        // Đặt 5 = trần → được, kho còn 5
        $this->dat($this->cart($sp, 5), [], 'TOKEN1');
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseHas('products', ['id' => $sp->id, 'soluong' => 5]);

        // Đặt thêm 1: đang giữ 5 + 1 > trần 5 (tồn gốc 10) → từ chối
        $this->dat($this->cart($sp, 1), [], 'TOKEN2')->assertSessionHas('error');
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseHas('products', ['id' => $sp->id, 'soluong' => 5]);
    }

    public function test_nhieu_san_pham_tru_kho_dung_tung_dong(): void
    {
        $sp1 = $this->sp(10, 0);
        $sp2 = $this->sp(10, 1);
        $cart = $this->cart($sp1, 2) + $this->cart($sp2, 3);

        $this->dat($cart);

        $don = DonHang::where('kenh', 'online')->firstOrFail();
        $this->assertDatabaseHas('products', ['id' => $sp1->id, 'soluong' => 8]);
        $this->assertDatabaseHas('products', ['id' => $sp2->id, 'soluong' => 7]);
        $this->assertSame(2, $don->chiTietDonHangs()->count());
        $this->assertSame(2, $don->bienDongKhos()->where('loai', 'ban')->count());
    }

    public function test_m2_stock_movements_user_id_nullable_tren_sqlite(): void
    {
        $this->seed();
        $sp = SanPham::orderBy('id')->firstOrFail();

        // M2: movement của đơn guest không có người thực hiện → user_id NULL phải chèn được.
        $movement = BienDongKho::create([
            'product_id' => $sp->id, 'user_id' => null, 'invoice_id' => null,
            'loai' => 'ban', 'soluong' => -1, 'ghi_chu' => 'Test M2',
        ]);

        $this->assertNull($movement->user_id);
        $this->assertDatabaseHas('stock_movements', ['id' => $movement->id, 'user_id' => null]);
    }
}