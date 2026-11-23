<?php

namespace App\Console\Commands;

use App\Services\KiemTraPinService;
use Illuminate\Console\Command;

/**
 * pin:trang-thai — xuất trạng thái sẵn sàng PIN/mã duyệt dạng JSON.
 * Dùng bởi scripts/windows/cong-tac-pin-prod.ps1 (không phân tích chuỗi
 * nữa — một nguồn dữ liệu duy nhất, có cấu trúc).
 */
class PinTrangThai extends Command
{
    protected $signature = 'pin:trang-thai';

    protected $description = 'Xuat trang thai PIN/ma duyet dang JSON (cho cong tac prod)';

    public function handle(KiemTraPinService $svc): int
    {
        $this->line(json_encode($svc->kiemTra(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
