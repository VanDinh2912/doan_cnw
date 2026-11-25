<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Liet ke du lieu THU theo quy uoc (CHI DOC, khong xoa/sua gi):
 *  - khach hang: customers.ho_ten bat dau bang "TEST"
 *  - don hang:    invoices.ten_khach_dat bat dau bang "TEST"
 *                hoac invoices.ghi_chu bat dau bang "[thu]"
 *  - san pham:   products.ten_sanpham bat dau bang "TEST"
 * Quy uoc day du: docs/SO_TAY_TRIEN_KHAI.md muc "Quy uoc du lieu thu".
 */
class ThuTestLietKe extends Command
{
    protected $signature = 'thu-test:liet-ke';

    protected $description = 'Liet ke khach/don/san pham du lieu thu theo quy uoc TEST / [thu] (chi doc)';

    public function handle(): int
    {
        $this->info('=== KHACH HANG THU (ho_ten LIKE "TEST%") ===');
        $khach = DB::table('customers')
            ->where('ho_ten', 'like', 'TEST%')
            ->orderBy('id')
            ->get(['id', 'ho_ten', 'so_dienthoai', 'created_at']);
        if ($khach->isEmpty()) {
            $this->line('  (khong co)');
        }
        foreach ($khach as $k) {
            $this->line(sprintf('  id=%d | %s | sdt=%s | tao luc %s',
                $k->id, $k->ho_ten, $k->so_dienthoai, $k->created_at));
        }

        $this->newLine();
        $this->info('=== DON HANG THU (ten_khach_dat LIKE "TEST%" OR ghi_chu LIKE "[thu]%") ===');
        $don = DB::table('invoices')
            ->where(function ($q) {
                $q->where('ten_khach_dat', 'like', 'TEST%')
                  ->orWhere('ghi_chu', 'like', '[thu]%');
            })
            ->orderBy('id')
            ->get(['id', 'ma_tra_cuu', 'ten_khach_dat', 'trang_thai', 'tong_thanh_toan', 'ghi_chu', 'created_at']);
        if ($don->isEmpty()) {
            $this->line('  (khong co)');
        }
        foreach ($don as $d) {
            $this->line(sprintf('  id=%d | ma=%s | %s | %s | %s | tao luc %s',
                $d->id, $d->ma_tra_cuu ?? '-', $d->ten_khach_dat ?? '-',
                $d->trang_thai, number_format((float) $d->tong_thanh_toan, 0, ',', '.'),
                $d->created_at));
        }

        $this->newLine();
        $this->info('=== SAN PHAM THU (ten_sanpham LIKE "TEST%") ===');
        $sp = DB::table('products')
            ->where('ten_sanpham', 'like', 'TEST%')
            ->orderBy('id')
            ->get(['id', 'sku', 'ten_sanpham', 'soluong', 'created_at']);
        if ($sp->isEmpty()) {
            $this->line('  (khong co)');
        }
        foreach ($sp as $p) {
            $this->line(sprintf('  id=%d | sku=%s | %s | ton=%d | tao luc %s',
                $p->id, $p->sku, $p->ten_sanpham, $p->soluong, $p->created_at));
        }

        $this->newLine();
        $this->comment('Lenh chi doc — khong xoa/sua du lieu. Quy uoc: docs/SO_TAY_TRIEN_KHAI.md.');

        return self::SUCCESS;
    }
}
