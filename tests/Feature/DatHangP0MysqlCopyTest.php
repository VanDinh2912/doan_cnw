<?php

namespace Tests\Feature;

use App\Models\DonHang;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * T-DH02 (P0 — docs/THIET_KE_DAT_HANG_ONLINE.md mục 9): migration M1 up/down trên
 * bản sao MySQL strict mode; enum không đổi, không FK mới, dữ liệu POS cũ nguyên
 * vẹn, down() chặn khi còn đơn user_id NULL. Không RefreshDatabase để giữ schema
 * thật của bản sao. Không chạm doan_cnw. Chạy:
 *   DB_CONNECTION=mysql DB_DATABASE=doan_cnw_copy DB_USERNAME=root DB_PASSWORD=''
 *   php artisan test --filter=DatHangP0MysqlCopyTest
 * Test tự dọn đơn và bản ghi migration nó tạo; chạy lại được nhiều lần.
 */
class DatHangP0MysqlCopyTest extends TestCase
{
    private const MIGRATION = 'database/migrations/2026_11_12_000001_add_online_order_fields_to_invoices_table.php';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function chiChayTrenBanSaoMysql(): void
    {
        $ketNoi = DB::connection();
        $database = (string) $ketNoi->getDatabaseName();

        if ($ketNoi->getDriverName() !== 'mysql' || ! str_ends_with($database, '_copy')) {
            $this->markTestSkipped(
                'Cần MySQL bản sao: DB_CONNECTION=mysql DB_DATABASE=doan_cnw_copy php artisan test --filter=DatHangP0MysqlCopyTest'
            );
        }
    }

    public function test_t_dh02_up_down_tren_mysql_copy_giu_nguyen_enum_va_du_lieu_pos(): void
    {
        $this->chiChayTrenBanSaoMysql();

        $tenMigration = basename(self::MIGRATION, '.php');
        $soDonGoc = DB::table('invoices')->count();
        $enumTruoc = $this->dinhNghiaEnum();
        $fkTruoc = $this->soForeignKey('invoices');
        $maDonTest = 'P0-'.uniqid();

        try {
            // Dọn tàn dư lần chạy trước (nếu có) để test chạy lại được
            if (Schema::hasColumn('invoices', 'kenh')) {
                DB::table('invoices')->where('ma_hoadon', 'like', 'P0-%')->delete();
                (require base_path(self::MIGRATION))->down();
                DB::table('migrations')->where('migration', $tenMigration)->delete();
            }

            $this->assertSame(0, Artisan::call('migrate', ['--path' => self::MIGRATION, '--force' => true]));

            $cot = $this->cotInvoices();
            $this->assertArrayHasKey('kenh', $cot, 'Migration chưa thêm cột kenh');
            $this->assertSame('varchar(10)', $cot['kenh']['COLUMN_TYPE']);
            $this->assertSame('NO', $cot['kenh']['IS_NULLABLE']);
            $this->assertSame('pos', $cot['kenh']['COLUMN_DEFAULT']);
            $this->assertSame('varchar(20)', $cot['ma_tra_cuu']['COLUMN_TYPE']);
            $this->assertSame('YES', $cot['ma_tra_cuu']['IS_NULLABLE']);
            $this->assertSame('varchar(100)', $cot['ten_khach_dat']['COLUMN_TYPE']);
            $this->assertSame('YES', $cot['ten_khach_dat']['IS_NULLABLE']);
            $this->assertSame('varchar(15)', $cot['so_dienthoai_dat']['COLUMN_TYPE']);
            $this->assertSame('YES', $cot['so_dienthoai_dat']['IS_NULLABLE']);
            $this->assertSame('varchar(500)', $cot['ghi_chu_khach']['COLUMN_TYPE']);
            $this->assertSame('YES', $cot['ghi_chu_khach']['IS_NULLABLE']);
            $this->assertSame('timestamp', $cot['han_giu']['DATA_TYPE']);
            $this->assertSame('YES', $cot['han_giu']['IS_NULLABLE']);
            $this->assertSame('YES', $cot['user_id']['IS_NULLABLE'], 'user_id phải nullable sau up()');

            $indexes = collect(DB::select('SHOW INDEX FROM invoices'))->groupBy('Key_name');
            $this->assertTrue($indexes->has('invoices_kenh_index'), 'Thiếu index kenh');
            $this->assertTrue($indexes->has('invoices_so_dienthoai_dat_index'), 'Thiếu index so_dienthoai_dat');
            $this->assertTrue($indexes->has('invoices_ma_tra_cuu_unique'), 'Thiếu unique ma_tra_cuu');
            $this->assertSame('0', (string) $indexes['invoices_ma_tra_cuu_unique']->first()->Non_unique);

            $this->assertSame($enumTruoc, $this->dinhNghiaEnum(), 'Enum phải giữ nguyên sau up()');
            $this->assertSame($fkTruoc, $this->soForeignKey('invoices'), 'Không được tạo FK mới');

            $this->assertSame($soDonGoc, DB::table('invoices')->where('kenh', 'pos')->count(), 'Đơn POS cũ phải backfill kenh=pos');
            $this->assertCount($soDonGoc, DonHang::with('user')->get(), 'Đơn POS cũ vẫn đọc được qua model');

            DB::table('invoices')->insert([
                'ma_hoadon' => $maDonTest,
                'user_id' => null,
                'tong_tien' => 1000,
                'tong_thanh_toan' => 1000,
                'phuong_thuc' => 'tienmat',
                'trang_thai' => 'cho_thanh_toan',
                'kenh' => 'online',
                'ma_tra_cuu' => 'TC-'.uniqid(),
                'ten_khach_dat' => 'Khách P0',
                'so_dienthoai_dat' => '0901234567',
                'ghi_chu_khach' => 'Ghi chú P0',
                'han_giu' => now()->addHours(24),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->assertSame(1, DB::table('invoices')->where('ma_hoadon', $maDonTest)->whereNull('user_id')->count());

            $migration = require base_path(self::MIGRATION);
            try {
                $migration->down();
                $this->fail('down() phải chặn khi còn đơn user_id NULL');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('user_id', $e->getMessage());
            }
            $this->assertTrue(Schema::hasColumn('invoices', 'kenh'), 'down() bị chặn thì không được drop cột');

            DB::table('invoices')->where('ma_hoadon', $maDonTest)->delete();
            $migration->down();
            DB::table('migrations')->where('migration', $tenMigration)->delete();

            $this->assertFalse(Schema::hasColumn('invoices', 'kenh'));
            $this->assertFalse(Schema::hasColumn('invoices', 'ma_tra_cuu'));
            $cotSau = $this->cotInvoices();
            $this->assertSame('NO', $cotSau['user_id']['IS_NULLABLE'], 'user_id phải về NOT NULL sau down()');
            $this->assertSame($soDonGoc, DB::table('invoices')->count(), 'Dữ liệu POS cũ phải nguyên vẹn');
            $this->assertSame($enumTruoc, $this->dinhNghiaEnum(), 'Enum phải giữ nguyên sau down()');
            $this->assertSame($fkTruoc, $this->soForeignKey('invoices'));
        } finally {
            DB::table('invoices')->where('ma_hoadon', 'like', 'P0-%')->delete();
            if (Schema::hasColumn('invoices', 'kenh')) {
                (require base_path(self::MIGRATION))->down();
                DB::table('migrations')->where('migration', $tenMigration)->delete();
            }
        }
    }

    private function cotInvoices(): array
    {
        return collect(DB::select(
            "SELECT COLUMN_NAME, COLUMN_TYPE, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices'"
        ))->map(fn ($c) => (array) $c)->keyBy('COLUMN_NAME')->all();
    }

    private function dinhNghiaEnum(): array
    {
        $ketQua = [];
        foreach ([['invoices', 'trang_thai'], ['invoices', 'phuong_thuc'], ['users', 'role'], ['stock_movements', 'loai']] as [$bang, $cot]) {
            $ketQua["{$bang}.{$cot}"] = DB::selectOne(
                'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$bang, $cot]
            )->COLUMN_TYPE;
        }

        return $ketQua;
    }

    private function soForeignKey(string $bang): int
    {
        return (int) DB::selectOne(
            'SELECT COUNT(*) AS n FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$bang]
        )->n;
    }
}