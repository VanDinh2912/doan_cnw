<?php

namespace Tests\Feature;

use App\Models\SanPham;
use App\Models\User;
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
}
