<?php

namespace Tests\Feature;

use App\Models\SanPham;
use App\Models\User;
use App\Models\DonHang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_complete_payment_and_stock_is_decremented(): void
    {
        $this->seed();
        $cashier = User::where('role', 'thungan')->firstOrFail();
        $product = SanPham::where('sku', 'HC-C109-CL')->firstOrFail();

        $response = $this->actingAs($cashier)->withSession([
            'gio_hang' => [
                (string) $product->id => [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'ten_sanpham' => $product->ten_sanpham,
                    'gia_ban' => (float) $product->gia_ban,
                    'soluong' => 2,
                    'ton_kho' => $product->soluong,
                ],
            ],
        ])->post(route('donhang.thanhtoan'), [
            'giam_gia_phantram' => 0,
            'tien_khach_dua' => 200000,
            'phuong_thuc' => 'tienmat',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'soluong' => 3]);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('invoice_details', 1);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'loai' => 'ban',
            'soluong' => -2,
        ]);
    }

    public function test_payment_over_stock_does_not_create_invoice(): void
    {
        $this->seed();
        $cashier = User::where('role', 'thungan')->firstOrFail();
        $product = SanPham::where('sku', 'HC-C109-CL')->firstOrFail();

        $response = $this->actingAs($cashier)->withSession([
            'gio_hang' => [(string) $product->id => [
                'id' => $product->id,
                'soluong' => 99,
                'gia_ban' => (float) $product->gia_ban,
            ]],
        ])->post(route('donhang.thanhtoan'), [
            'giam_gia_phantram' => 0,
            'tien_khach_dua' => 99999999,
            'phuong_thuc' => 'tienmat',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'soluong' => 5]);
    }

    public function test_cashier_cannot_access_inventory(): void
    {
        $this->seed();
        $cashier = User::where('role', 'thungan')->firstOrFail();

        $this->actingAs($cashier)->get(route('kho.danhsach'))->assertForbidden();
    }

    public function test_transfer_payment_uses_exact_total_and_no_change(): void
    {
        $this->seed();
        $cashier = User::where('role', 'thungan')->firstOrFail();
        $product = SanPham::where('sku', 'HC-C109-CL')->firstOrFail();

        $response = $this->actingAs($cashier)->withSession([
            'gio_hang' => [(string) $product->id => [
                'id' => $product->id,
                'soluong' => 1,
                'gia_ban' => 1,
            ]],
        ])->post(route('donhang.thanhtoan'), [
            'giam_gia_phantram' => 10,
            'tien_khach_dua' => 1,
            'phuong_thuc' => 'chuyenkhoan',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('invoices', [
            'phuong_thuc' => 'chuyenkhoan',
            'tong_thanh_toan' => 62100,
            'tien_khach_dua' => 62100,
            'tien_thua' => 0,
        ]);
    }

    public function test_inactive_product_cannot_be_sold(): void
    {
        $this->seed();
        $cashier = User::where('role', 'thungan')->firstOrFail();
        $product = SanPham::where('sku', 'HC-C109-CL')->firstOrFail();
        $product->update(['trang_thai' => false]);

        $response = $this->actingAs($cashier)->withSession([
            'gio_hang' => [(string) $product->id => [
                'id' => $product->id,
                'soluong' => 1,
            ]],
        ])->post(route('donhang.thanhtoan'), [
            'giam_gia_phantram' => 0,
            'tien_khach_dua' => 100000,
            'phuong_thuc' => 'tienmat',
        ]);

        $response->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_admin_can_filter_invoices_by_date(): void
    {
        $this->seed();
        $admin = User::where('role', 'admin')->firstOrFail();
        $invoiceCu = DonHang::create([
            'ma_hoadon' => 'HD20261030-0001',
            'user_id' => $admin->id,
            'tong_tien' => 100000,
            'tong_thanh_toan' => 100000,
            'tien_khach_dua' => 100000,
            'tien_thua' => 0,
        ]);
        $invoiceCu->forceFill(['created_at' => '2026-10-30 10:00:00'])->saveQuietly();
        $invoiceMoi = DonHang::create([
            'ma_hoadon' => 'HD20261101-0001',
            'user_id' => $admin->id,
            'tong_tien' => 200000,
            'tong_thanh_toan' => 200000,
            'tien_khach_dua' => 200000,
            'tien_thua' => 0,
        ]);
        $invoiceMoi->forceFill(['created_at' => '2026-11-01 10:00:00'])->saveQuietly();

        $this->actingAs($admin)
            ->get(route('donhang.danhsach', ['tu_ngay' => '2026-11-01', 'den_ngay' => '2026-11-01']))
            ->assertOk()
            ->assertSee('HD20261101-0001')
            ->assertDontSee('HD20261030-0001');
    }
}
