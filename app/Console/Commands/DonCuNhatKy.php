<?php

namespace App\Console\Commands;

use App\Models\NhatKyHoatDong;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DonCuNhatKy extends Command
{
    protected $signature = 'nhatky:don-cu {--ngay= : Giữ lại số ngày, mặc định từ POS_NHATKY_GIU_NGAY hoặc 365} {--ap-dung : Thực sự xóa dữ liệu}';
    protected $description = 'Liệt kê và tùy chọn xóa nhật ký quá thời hạn lưu trữ';

    public function handle(): int
    {
        $days = max(1, (int) ($this->option('ngay') ?: env('POS_NHATKY_GIU_NGAY', 365)));
        $before = now()->subDays($days);
        $query = NhatKyHoatDong::where('created_at', '<', $before);
        $count = (clone $query)->count();
        $this->line("Có {$count} dòng trước ".$before->format('d/m/Y H:i:s').'.');
        if (! $this->option('ap-dung')) {
            $this->info('Dry-run: chưa xóa dữ liệu. Dùng --ap-dung để thực hiện.');
            return self::SUCCESS;
        }
        // Dọn theo query builder để không mở đường cập nhật/xóa qua model append-only.
        $deleted = DB::table('activity_logs')->where('created_at', '<', $before)->delete();
        $this->info("Đã xóa {$deleted} dòng nhật ký.");
        return self::SUCCESS;
    }
}
