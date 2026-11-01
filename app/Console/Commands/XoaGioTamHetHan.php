<?php

namespace App\Console\Commands;

use App\Models\GioTam;
use Illuminate\Console\Command;

class XoaGioTamHetHan extends Command
{
    protected $signature = 'hold:clean';
    protected $description = 'Xóa giỏ tạm quá 24 giờ';

    public function handle(): int
    {
        $count = GioTam::where('created_at', '<', now()->subDay())->delete();
        $this->info("Đã xóa {$count} giỏ tạm hết hạn.");
        return self::SUCCESS;
    }
}
