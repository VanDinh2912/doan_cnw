<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDO;
use Tests\TestCase;

class SaoLuuCsdlTest extends TestCase
{
    use RefreshDatabase;

    private ?string $tepSqlite = null;

    private bool $daDoiKetNoi = false;

    protected function tearDown(): void
    {
        $this->khoiPhucKetNoi();
        parent::tearDown();
    }

    public function test_sao_luu_sqlite_tao_ban_sao_co_du_lieu(): void
    {
        Storage::fake('backups');
        $this->dungSqliteThat();

        $maThoat = Artisan::call('sao-luu:csdl');
        $output = Artisan::output();

        $this->assertSame(0, $maThoat, $output);
        $this->assertStringContainsString('Đã sao lưu', $output);

        $tep = Storage::disk('backups')->files();
        $this->assertCount(1, $tep);
        $this->assertMatchesRegularExpression('/-\d{8}-\d{6}\.sqlite$/', basename($tep[0]));
        $this->assertGreaterThan(0, Storage::disk('backups')->size($tep[0]));

        // Mở bản sao lưu bằng PDO độc lập để chứng minh dữ liệu thật sự nằm trong đó.
        $pdo = new PDO('sqlite:'.Storage::disk('backups')->path($tep[0]));
        $this->assertSame('xin chào', $pdo->query('SELECT v FROM t WHERE id = 1')->fetchColumn());
    }

    public function test_chi_giu_lai_n_ban_moi_nhat_va_khong_dung_tep_la(): void
    {
        Storage::fake('backups');
        $this->dungSqliteThat();
        $tienTo = pathinfo((string) $this->tepSqlite, PATHINFO_FILENAME);

        $disk = Storage::disk('backups');
        $disk->put("{$tienTo}-20200101-000000.sqlite", 'cu-1');
        $disk->put("{$tienTo}-20200102-000000.sqlite", 'cu-2');
        $disk->put("{$tienTo}-20200103-000000.sqlite", 'cu-3');
        $disk->put('ghi-chu-thu-cong.txt', 'khong dung mau');
        $disk->put('ten-khac-20200101-000000.sqlite', 'tien to khac');

        $maThoat = Artisan::call('sao-luu:csdl', ['--giu' => 2]);
        $this->assertSame(0, $maThoat, Artisan::output());

        $conLai = collect($disk->files())->map(fn (string $tep): string => basename($tep));

        $this->assertCount(4, $conLai, $conLai->implode(', '));
        $this->assertTrue($conLai->contains(fn (string $ten): bool => str_starts_with($ten, $tienTo.'-') && str_ends_with($ten, '.sqlite') && ! str_contains($ten, '202001')));
        $this->assertContains("{$tienTo}-20200103-000000.sqlite", $conLai);
        $this->assertNotContains("{$tienTo}-20200101-000000.sqlite", $conLai);
        $this->assertNotContains("{$tienTo}-20200102-000000.sqlite", $conLai);
        $this->assertContains('ghi-chu-thu-cong.txt', $conLai);
        $this->assertContains('ten-khac-20200101-000000.sqlite', $conLai);
    }

    public function test_tu_choi_sao_luu_sqlite_memory(): void
    {
        Storage::fake('backups');
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));

        $maThoat = Artisan::call('sao-luu:csdl');
        $output = Artisan::output();

        $this->assertSame(1, $maThoat);
        $this->assertStringContainsString('Không thể sao lưu SQLite :memory:', $output);
        $this->assertCount(0, Storage::disk('backups')->files());
    }

    public function test_bao_loi_ro_khi_thieu_mysqldump(): void
    {
        Storage::fake('backups');
        config([
            'backup.mysqldump_binary' => 'mysqldump-khong-ton-tai-xyz',
            'database.connections.thu_mysql' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => '3306',
                'database' => 'khong_bao_gio_ket_noi',
                'username' => 'root',
                'password' => '',
            ],
        ]);
        config(['database.default' => 'thu_mysql']);
        $this->daDoiKetNoi = true;

        $maThoat = Artisan::call('sao-luu:csdl');
        $output = Artisan::output();

        $this->assertSame(1, $maThoat);
        $this->assertStringContainsString('Không tìm thấy mysqldump', $output);
        $this->assertStringContainsString('DB_DUMP_BINARY', $output);
        $this->assertCount(0, Storage::disk('backups')->files());
    }

    /**
     * Tạo một database SQLite thật trên đĩa và tạm chuyển kết nối mặc định sang
     * đó, vì :memory: không thể VACUUM INTO và không đại diện cho cấu hình cửa hàng.
     */
    private function dungSqliteThat(): void
    {
        $tep = tempnam(sys_get_temp_dir(), 'pos-saoluu-');
        $this->assertNotFalse($tep);
        $this->tepSqlite = $tep;

        config(['database.connections.thu_sqlite' => [
            'driver' => 'sqlite',
            'database' => $tep,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
        config(['database.default' => 'thu_sqlite']);
        $this->daDoiKetNoi = true;

        DB::connection('thu_sqlite')->statement('CREATE TABLE t (id INTEGER PRIMARY KEY, v TEXT)');
        DB::connection('thu_sqlite')->insert("INSERT INTO t (id, v) VALUES (1, 'xin chào')");
    }

    private function khoiPhucKetNoi(): void
    {
        if (! $this->daDoiKetNoi) {
            return;
        }

        // Trả default về lại để RefreshDatabase rollback đúng kết nối đang mở transaction.
        config(['database.default' => 'sqlite']);
        DB::purge('thu_sqlite');
        DB::purge('thu_mysql');

        if ($this->tepSqlite !== null && is_file($this->tepSqlite)) {
            @unlink($this->tepSqlite);
        }

        $this->daDoiKetNoi = false;
    }
}