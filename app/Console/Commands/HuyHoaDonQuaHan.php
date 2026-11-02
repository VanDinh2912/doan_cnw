<?php

namespace App\Console\Commands;

use App\Models\DonHang;
use App\Services\ThanhToanChuyenKhoanService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class HuyHoaDonQuaHan extends Command
{
    protected $signature = 'donhang:huy-qua-han';

    protected $description = 'Tự động hủy các hóa đơn chờ thanh toán đã quá hạn';

    public function handle(ThanhToanChuyenKhoanService $service): int
    {
        $soPhut = (int) config('pos.cho_thanh_toan_phut', 15);
        $hanChot = now()->subMinutes($soPhut);

        $danhSach = DonHang::query()
            ->where('trang_thai', 'cho_thanh_toan')
            ->where('created_at', '<', $hanChot)
            ->get();

        if ($danhSach->isEmpty()) {
            $this->info('Không có hóa đơn nào quá hạn chờ thanh toán.');
            return self::SUCCESS;
        }

        $daHuy = 0;
        foreach ($danhSach as $donHang) {
            $res = $service->huyChoThanhToan($donHang, 'Quá hạn chờ thanh toán (Artisan Scheduler)');
            if ($res['success']) {
                $daHuy++;
                $this->line("Đã hủy hóa đơn: {$donHang->ma_hoadon}");
            }
        }

        $this->info("Đã xử lý hủy {$daHuy}/{$danhSach->count()} hóa đơn quá hạn.");
        Log::info('Scheduler hủy hóa đơn quá hạn hoàn tất.', ['tong_so' => $danhSach->count(), 'da_huy' => $daHuy]);

        return self::SUCCESS;
    }
}
