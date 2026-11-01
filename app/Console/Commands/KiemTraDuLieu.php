<?php

namespace App\Console\Commands;

use App\Models\HoaDonNhap;
use App\Models\SanPham;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class KiemTraDuLieu extends Command
{
    protected $signature = 'demo:kiemtra-du-lieu';
    protected $description = 'Kiểm tra tính nhất quán bộ dữ liệu nhập mẫu';

    public function handle(): int
    {
        $expected = ['categories' => 12, 'products' => 300, 'suppliers' => 81, 'customers' => 100, 'purchase_invoices' => 1, 'purchase_invoice_items' => 300];
        foreach ($expected as $table => $count) {
            $actual = DB::table($table)->count();
            $this->line("{$table}: {$actual}");
            if ($actual !== $count) {
                $this->error("Số lượng {$table} không đúng.");
                return self::FAILURE;
            }
        }
        $invoice = HoaDonNhap::firstOrFail();
        $lineTotal = DB::table('purchase_invoice_items')->where('purchase_invoice_id', $invoice->id)->sum('thanh_tien');
        $stockTotal = SanPham::sum('soluong');
        $movementTotal = DB::table('stock_movements')->sum('soluong');
        if ($invoice->tong_tien_hang != $lineTotal || $stockTotal != $movementTotal || $invoice->trang_thai !== 'da_nhap_kho') {
            $this->error('Tổng hóa đơn, trạng thái hoặc tồn kho không nhất quán.');
            return self::FAILURE;
        }
        $duplicates = DB::select("SELECT sku FROM products GROUP BY sku HAVING COUNT(*) > 1");
        if ($duplicates !== []) {
            $this->error('Có SKU trùng.');
            return self::FAILURE;
        }
        $this->info('Dữ liệu hợp lệ.');
        return self::SUCCESS;
    }
}
