<?php

namespace App\Console\Commands;

use App\Models\DonHang;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class KiemTraHeThong extends Command
{
    protected $signature = 'he-thong:kiem-tra';

    protected $description = 'Kiểm tra nhanh cấu hình vận hành (chỉ đọc, không in giá trị bí mật)';

    public const KHOA_SCHEDULER = 'he_thong:scheduler_last_run';

    private const NGUONG_SCHEDULER_PHUT = 3;

    private int $soLoi = 0;

    private int $soCanhBao = 0;

    public function handle(): int
    {
        $this->line('=== Kiểm tra vận hành Web POS ===');

        $this->kiemTraScheduler();
        $this->kiemTraDonQuaHan();
        $this->kiemTraCauHinh();
        $this->kiemTraMoiTruong();
        $this->kiemTraQuyenGhi();
        $this->kiemTraLog();
        $this->kiemTraEngine();
        $this->kiemTraMigration();

        $this->line(sprintf('Kết quả: %d lỗi, %d cảnh báo.', $this->soLoi, $this->soCanhBao));

        return $this->soLoi > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function kiemTraScheduler(): void
    {
        $lanCuoi = Cache::get(self::KHOA_SCHEDULER);
        if (! is_numeric($lanCuoi)) {
            $this->bao('FAIL', 'Scheduler', 'chưa ghi nhận lần chạy nào — cần cài Task Scheduler gọi scripts/windows/chay-schedule.bat mỗi phút');

            return;
        }

        $giay = max(0, now()->getTimestamp() - (int) $lanCuoi);
        $phut = intdiv($giay, 60);
        $luc = date('H:i:s d/m/Y', (int) $lanCuoi);
        if ($giay > self::NGUONG_SCHEDULER_PHUT * 60) {
            $this->bao('FAIL', 'Scheduler', "lần cuối {$luc} ({$phut} phút trước) — lệnh hủy đơn quá hạn không còn chạy");

            return;
        }

        $this->bao('OK', 'Scheduler', "lần cuối {$luc} ({$phut} phút trước)");
    }

    private function kiemTraDonQuaHan(): void
    {
        $tonDong = DonHang::query()->quaHanCho()->count();

        if ($tonDong > 0) {
            $this->bao('WARN', 'Đơn chờ quá hạn', "{$tonDong} đơn chờ thanh toán đã quá hạn giữ chưa được hủy");

            return;
        }

        $this->bao('OK', 'Đơn chờ quá hạn', 'không có đơn chờ thanh toán quá hạn giữ');
    }

    /**
     * Chỉ báo thiếu/đủ, tuyệt đối không in giá trị khóa hay số tài khoản.
     */
    private function kiemTraCauHinh(): void
    {
        $bankCode = (string) config('pos.bank_code');
        $bankAccount = (string) config('pos.bank_account');
        if ($bankCode === '' || $bankAccount === '') {
            $this->bao('FAIL', 'Ngân hàng (VietQR)', 'thiếu POS_BANK_CODE hoặc POS_BANK_ACCOUNT — không tạo được mã QR');
        } else {
            $this->bao('OK', 'Ngân hàng (VietQR)', 'đã cấu hình đủ POS_BANK_CODE và POS_BANK_ACCOUNT');
        }

        if ((string) config('pos.sepay_webhook_api_key') === '') {
            $this->bao('FAIL', 'SePay webhook', 'thiếu SEPAY_WEBHOOK_API_KEY — mọi webhook sẽ bị từ chối 401');
        } else {
            $this->bao('OK', 'SePay webhook', 'đã cấu hình SEPAY_WEBHOOK_API_KEY');
        }

        $coToken = (string) config('pos.telegram_bot_token') !== '';
        $coChatId = (string) config('pos.telegram_chat_id') !== '';
        if ($coToken && $coChatId) {
            $this->bao('OK', 'Telegram', 'đã cấu hình đủ TELEGRAM_BOT_TOKEN và TELEGRAM_CHAT_ID');
        } else {
            $this->bao('WARN', 'Telegram', 'thiếu TELEGRAM_BOT_TOKEN hoặc TELEGRAM_CHAT_ID — thông báo/cảnh báo bị tắt');
        }

        // Cờ đặt hàng online (P2): báo trạng thái; WARN khi bật mà thiếu biến cần thiết.
        $datHangBat = (bool) config('pos.dat_hang_online_bat');
        $giaoTanNoiBat = (bool) config('pos.giao_tan_noi_bat');
        $this->bao($datHangBat ? 'OK' : 'INFO', 'Đặt hàng online', $datHangBat ? 'POS_DAT_HANG_ONLINE_BAT đang bật' : 'POS_DAT_HANG_ONLINE_BAT đang tắt — mọi route đặt hàng online trả 404');

        if (! $datHangBat) {
            $this->bao('INFO', 'Giao tận nơi', 'POS_GIAO_TAN_NOI_BAT không có ý nghĩa khi đặt hàng online đang tắt');
        } elseif (! $giaoTanNoiBat) {
            $this->bao('INFO', 'Giao tận nơi', 'POS_GIAO_TAN_NOI_BAT đang tắt — chỉ nhận tại cửa hàng');
        } else {
            $this->bao('OK', 'Giao tận nơi', 'POS_GIAO_TAN_NOI_BAT đang bật');
        }

        if ($datHangBat && ($bankCode === '' || $bankAccount === '')) {
            $this->bao('WARN', 'Đặt hàng online', 'đang bật nhưng thiếu POS_BANK_CODE/POS_BANK_ACCOUNT — khách không quét được QR');
        }
        if ($giaoTanNoiBat && ! $datHangBat) {
            $this->bao('WARN', 'Giao tận nơi', 'POS_GIAO_TAN_NOI_BAT bật nhưng POS_DAT_HANG_ONLINE_BAT tắt — cờ giao hàng không có tác dụng');
        }
    }

    private function kiemTraMoiTruong(): void
    {
        $env = (string) app()->environment();
        $debug = (bool) config('app.debug');

        if ($env !== 'production') {
            $this->bao('WARN', 'APP_ENV', "đang là \"{$env}\", cửa hàng chạy thật cần \"production\"");
        } else {
            $this->bao('OK', 'APP_ENV', 'production');
        }

        if ($debug) {
            $this->bao($env === 'production' ? 'FAIL' : 'WARN', 'APP_DEBUG', 'đang bật — trang lỗi sẽ lộ cấu hình và truy vấn');
        } else {
            $this->bao('OK', 'APP_DEBUG', 'đã tắt');
        }

        $this->bao('OK', 'APP_URL', (string) config('app.url'));

        if (file_exists(base_path('bootstrap/cache/config.php'))) {
            $this->bao('OK', 'Cache cấu hình', 'đã chạy config:cache');
        } else {
            $this->bao('WARN', 'Cache cấu hình', 'chưa cache — chạy config:cache, route:cache, view:cache khi triển khai');
        }
    }

    private function kiemTraQuyenGhi(): void
    {
        $thuMuc = [
            'storage' => storage_path(),
            'storage/logs' => storage_path('logs'),
            'storage/framework/views' => storage_path('framework/views'),
            'storage/framework/sessions' => storage_path('framework/sessions'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ];

        $khongGhiDuoc = [];
        foreach ($thuMuc as $nhan => $duongDan) {
            if (! is_dir($duongDan) || ! is_writable($duongDan)) {
                $khongGhiDuoc[] = $nhan;
            }
        }

        if ($khongGhiDuoc !== []) {
            $this->bao('FAIL', 'Quyền ghi', 'không ghi được: '.implode(', ', $khongGhiDuoc));

            return;
        }

        $this->bao('OK', 'Quyền ghi', 'storage và bootstrap/cache ghi được');
    }

    private function kiemTraLog(): void
    {
        $kenh = (string) config('logging.default');
        $danhSach = $kenh === 'stack'
            ? (array) config('logging.channels.stack.channels', [])
            : [$kenh];
        $soNgay = (int) config('logging.channels.daily.days', 14);

        if (in_array('daily', $danhSach, true)) {
            $this->bao('OK', 'Log', 'ghi theo ngày, giữ '.$soNgay.' ngày');

            return;
        }

        $this->bao('WARN', 'Log', 'kênh "'.implode(',', $danhSach).'" không phải daily — đặt LOG_STACK=daily để log xoay vòng '.$soNgay.' ngày');
    }

    /**
     * MyISAM không hỗ trợ transaction và khóa dòng, khiến các luồng tiền/tồn kho
     * mất tính nguyên tử. Chỉ kiểm tra trên MySQL/MariaDB.
     */
    private function kiemTraEngine(): void
    {
        $driver = config('database.default');
        $loai = (string) config("database.connections.{$driver}.driver", $driver);

        if (! in_array($loai, ['mysql', 'mariadb'], true)) {
            $this->bao('OK', 'Engine lưu trữ', 'không áp dụng cho '.$loai);

            return;
        }

        $khongInnodb = collect(Schema::getTables())
            ->filter(fn (array $bang): bool => strcasecmp((string) ($bang['engine'] ?? ''), 'InnoDB') !== 0)
            ->pluck('name')
            ->all();

        if ($khongInnodb !== []) {
            $this->bao('FAIL', 'Engine lưu trữ', count($khongInnodb).' bảng không phải InnoDB ('.implode(', ', array_slice($khongInnodb, 0, 5)).') — transaction và khóa dòng không có tác dụng');

            return;
        }

        $this->bao('OK', 'Engine lưu trữ', 'toàn bộ bảng dùng InnoDB');
    }

    private function kiemTraMigration(): void
    {
        $migrator = app('migrator');
        if (! $migrator->repositoryExists()) {
            $this->bao('FAIL', 'Migration', 'chưa có bảng migrations — cần chạy php artisan migrate');

            return;
        }

        $daChay = $migrator->getRepository()->getRan();
        $trenDia = array_keys($migrator->getMigrationFiles(database_path('migrations')));
        $chuaApDung = array_values(array_diff($trenDia, $daChay));

        if ($chuaApDung !== []) {
            $this->bao('WARN', 'Migration', count($chuaApDung).' migration chưa áp dụng: '.implode(', ', $chuaApDung));

            return;
        }

        $this->bao('OK', 'Migration', 'database đã áp dụng đủ '.count($trenDia).' migration');
    }

    private function bao(string $muc, string $nhan, string $chiTiet): void
    {
        if ($muc === 'FAIL') {
            $this->soLoi++;
        } elseif ($muc === 'WARN') {
            $this->soCanhBao++;
        }

        $this->line(sprintf('[%s] %s: %s', $muc, $nhan, $chiTiet));
    }
}