<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class SaoLuuCsdl extends Command
{
    protected $signature = 'sao-luu:csdl {--giu= : Số bản sao lưu giữ lại, mặc định BACKUP_GIU_BAN}';

    protected $description = 'Sao lưu cơ sở dữ liệu đang dùng vào storage/app/backups';

    public function handle(): int
    {
        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");
        $tenDb = (string) config("database.connections.{$connection}.database");

        if (! in_array($driver, ['mysql', 'sqlite'], true)) {
            $this->error("Chưa hỗ trợ sao lưu driver \"{$driver}\". Chỉ hỗ trợ mysql và sqlite.");

            return self::FAILURE;
        }

        if ($driver === 'sqlite') {
            if ($tenDb === ':memory:' || $tenDb === '') {
                $this->error('Không thể sao lưu SQLite :memory: — cần một tệp database thật.');

                return self::FAILURE;
            }
            if (! is_file($tenDb)) {
                $this->error("Không tìm thấy tệp SQLite: {$tenDb}");

                return self::FAILURE;
            }
        }

        $disk = Storage::disk((string) config('backup.disk'));
        File::ensureDirectoryExists($disk->path(''));

        $tienTo = $driver === 'sqlite' ? pathinfo($tenDb, PATHINFO_FILENAME) : $tenDb;
        $duoi = $driver === 'sqlite' ? 'sqlite' : 'sql';
        $tenTep = $tienTo.'-'.now()->format('Ymd-His').'.'.$duoi;

        if ($disk->exists($tenTep)) {
            $this->error("Đã có bản sao lưu {$tenTep} trong cùng giây. Chạy lại sau.");

            return self::FAILURE;
        }

        $duongDan = $disk->path($tenTep);

        try {
            if ($driver === 'sqlite') {
                $this->saoLuuSqlite($duongDan);
            } else {
                $this->saoLuuMysql($connection, $duongDan);
            }
        } catch (Throwable $e) {
            File::delete($duongDan);
            $this->error('Sao lưu thất bại: '.$e->getMessage());
            Log::error('Sao lưu CSDL thất bại', ['driver' => $driver, 'message' => $e->getMessage()]);

            return self::FAILURE;
        }

        $kichThuoc = (int) File::size($duongDan);
        if ($kichThuoc === 0) {
            File::delete($duongDan);
            $this->error('Sao lưu thất bại: tệp sinh ra rỗng.');

            return self::FAILURE;
        }

        $this->info(sprintf('Đã sao lưu %s (%s KB) vào %s', $tenDb, number_format($kichThuoc / 1024, 1), $tenTep));

        $this->donBanCu($disk, $tienTo, $duoi);

        return self::SUCCESS;
    }

    /**
     * VACUUM INTO cho một bản sao nhất quán ngay cả khi cửa hàng đang bán hàng;
     * copy() tệp SQLite đang mở có thể bắt gặp trang ghi dở và cho bản sao hỏng.
     */
    private function saoLuuSqlite(string $dich): void
    {
        DB::statement('VACUUM INTO ?', [$dich]);
    }

    private function saoLuuMysql(string $connection, string $dich): void
    {
        $binary = (string) config('backup.mysqldump_binary');
        $timThay = is_file($binary) ? $binary : (new ExecutableFinder)->find($binary);

        if ($timThay === null) {
            throw new \RuntimeException(
                "Không tìm thấy mysqldump (\"{$binary}\"). Thêm nó vào PATH hoặc đặt DB_DUMP_BINARY "
                .'bằng đường dẫn đầy đủ, ví dụ DB_DUMP_BINARY="D:\\Study\\xampp\\bin\\mysql\\mysql8.4.7\\bin\\mysqldump.exe".'
            );
        }

        $cauHinh = config("database.connections.{$connection}");
        $matKhau = (string) ($cauHinh['password'] ?? '');

        // MYSQL_PWD thay vì --password để mật khẩu không lộ trong danh sách tiến trình.
        $process = new Process([
            $timThay,
            '--host='.(string) ($cauHinh['host'] ?? '127.0.0.1'),
            '--port='.(string) ($cauHinh['port'] ?? '3306'),
            '--user='.(string) ($cauHinh['username'] ?? 'root'),
            '--single-transaction',
            '--routines',
            '--triggers',
            '--events',
            '--default-character-set=utf8mb4',
            '--result-file='.$dich,
            (string) $cauHinh['database'],
        ], base_path(), ['MYSQL_PWD' => $matKhau]);
        $process->setTimeout((float) config('backup.timeout', 300));
        $process->run();

        if (! $process->isSuccessful()) {
            $loi = $this->cheMatKhau($process->getErrorOutput(), $matKhau);
            throw new \RuntimeException('mysqldump thoát với mã '.$process->getExitCode().': '.trim(mb_substr($loi, 0, 500)));
        }
    }

    private function cheMatKhau(string $vanBan, string $matKhau): string
    {
        return $matKhau === '' ? $vanBan : str_replace($matKhau, '***', $vanBan);
    }

    /**
     * Chỉ xóa tệp do chính lệnh này sinh ra (đúng tiền tố + dấu thời gian),
     * không đụng tới bản sao lưu thủ công hay tệp khác trong cùng thư mục.
     */
    private function donBanCu(Filesystem $disk, string $tienTo, string $duoi): void
    {
        $giu = (int) ($this->option('giu') ?: config('backup.giu_ban', 7));
        if ($giu < 1) {
            $this->warn('Số bản giữ lại phải >= 1, bỏ qua bước dọn bản cũ.');

            return;
        }

        $mau = '/^'.preg_quote($tienTo, '/').'-\d{8}-\d{6}\.'.preg_quote($duoi, '/').'$/';
        $tep = collect($disk->files())
            ->filter(fn (string $ten): bool => preg_match($mau, basename($ten)) === 1)
            ->sortByDesc(fn (string $ten): string => basename($ten))
            ->values();

        $canXoa = $tep->slice($giu);
        if ($canXoa->isEmpty()) {
            $this->line('Giữ lại '.$tep->count().' bản sao lưu (tối đa '.$giu.').');

            return;
        }

        foreach ($canXoa as $ten) {
            $disk->delete($ten);
        }
        $this->line('Đã dọn '.count($canXoa).' bản cũ, giữ lại '.$giu.' bản mới nhất.');
    }
}