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
 * T-DH-M3 (P2 — docs/THIET_KE_DAT_HANG_ONLINE.md mục 9): migration M3 up/down trên
 * bản sao MySQL strict mode; không đổi enum, không FK mới, dữ liệu POS cũ nguyên
 * vẹn, down() chặn khi còn đơn giao_tan_noi. Không RefreshDatabase để giữ schema
 * thật của bản sao. Không chạm doan_cnw. Chạy:
 *   DB_CONNECTION=mysql DB_DATABASE=doan_cnw_copy DB_USERNAME=root DB_PASSWORD=''
 *   php artisan test --filter=DatHangP2MysqlCopyTest
 * Test tự dọn đơn và bản ghi migration nó tạo; chạy lại được nhiều lần.
 */
class DatHangP2MysqlCopyTest extends TestCase
{
    private const MIGRATION = 'database/migrations/2026_11_14_000003_add_delivery_fields_to_invoices_table.php';
    private const M1 = 'database/migrations/2026_11_12_000001_add_online_order_fields_to_invoices_table.php';

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
                'Cần MySQL bản sao: DB_CONNECTION=mysql DB_DATABASE=doan_cnw_copy php artisan test --filter=DatHangP2MysqlCopyTest'
            );
        }
    }

    public function test_t_dh_m3_up_down_tren_mysql_copy_giu_nguyen_enum_va_du_lieu_pos(): void
    {
        $this->chiChayTrenBanSaoMysql();

        $tenMigration = basename(self::MIGRATION, '.php');
        $soDonGoc = DB::table('invoices')->count();
        $enumTruoc = $this->dinhNghiaEnum();
        $fkTruoc = $this->soForeignKey('invoices');
        $maDonTest = 'P2-'.uniqid();
        // M3 đặt cột after('han_giu') và đơn test cần user_id nullable → phải có
        // M1 trước. Bản sao đang ở snapshot trước M1 thì test tự áp và tự gỡ.
        $tuApDungM1 = ! Schema::hasColumn('invoices', 'kenh');
        if ($tuApDungM1) {
            $this->assertSame(0, Artisan::call('migrate', ['--path' => self::M1, '--force' => true]));
        }

        try {
            // Dọn tàn dư lần chạy trước (nếu có) để test chạy lại được
            if (Schema::hasColumn('invoices', 'hinh_thuc_nhan')) {
                DB::table('invoices')->where('ma_hoadon', 'like', 'P2-%')->delete();
                (require base_path(self::MIGRATION))->down();
                DB::table('migrations')->where('migration', $tenMigration)->delete();
            }

            $this->assertSame(0, Artisan::call('migrate', ['--path' => self::MIGRATION, '--force' => true]));

            $cot = $this->cotInvoices();
            $this->assertArrayHasKey('hinh_thuc_nhan', $cot, 'Migration chưa thêm cột hinh_thuc_nhan');
            $this->assertSame('varchar(20)', $cot['hinh_thuc_nhan']['COLUMN_TYPE']);
            $this->assertSame('NO', $cot['hinh_thuc_nhan']['IS_NULLABLE']);
            $this->assertSame('tai_cua_hang', $cot['hinh_thuc_nhan']['COLUMN_DEFAULT']);
            $this->assertSame('varchar(100)', $cot['ten_nguoi_nhan']['COLUMN_TYPE']);
            $this->assertSame('YES', $cot['ten_nguoi_nhan']['IS_NULLABLE']);
            $this->assertSame('varchar(15)', $cot['sdt_nguoi_nhan']['COLUMN_TYPE']);
            $this->assertSame('YES', $cot['sdt_nguoi_nhan']['IS_NULLABLE']);
            $this->assertSame('varchar(255)', $cot['dia_chi_giao']['COLUMN_TYPE']);
            $this->assertSame('YES', $cot['dia_chi_giao']['IS_NULLABLE']);
            $this->assertSame('decimal(10,0)', $cot['phi_giao_hang']['COLUMN_TYPE']);
            $this->assertSame('NO', $cot['phi_giao_hang']['IS_NULLABLE']);
            $this->assertSame('0', $cot['phi_giao_hang']['COLUMN_DEFAULT']);
            $this->assertSame('varchar(50)', $cot['trang_thai_giao']['COLUMN_TYPE']);
            $this->assertSame('YES', $cot['trang_thai_giao']['IS_NULLABLE']);

            $indexes = collect(DB::select('SHOW INDEX FROM invoices'))->groupBy('Key_name');
            $this->assertTrue($indexes->has('invoices_trang_thai_giao_index'), 'Thiếu index trang_thai_giao');

            $this->assertSame($enumTruoc, $this->dinhNghiaEnum(), 'Enum phải giữ nguyên sau up()');
            $this->assertSame($fkTruoc, $this->soForeignKey('invoices'), 'Không được tạo FK mới');

            $this->assertSame($soDonGoc, DB::table('invoices')->where('hinh_thuc_nhan', 'tai_cua_hang')->count(), 'Đơn POS cũ phải backfill hinh_thuc_nhan=tai_cua_hang');
            $this->assertCount($soDonGoc, DonHang::with('user')->get(), 'Đơn POS cũ vẫn đọc được qua model');

            DB::table('invoices')->insert([
                'ma_hoadon' => $maDonTest,
                'user_id' => null,
                'tong_tien' => 20000,
                'tong_thanh_toan' => 50000,
                'phuong_thuc' => 'chuyenkhoan',
                'trang_thai' => 'cho_thanh_toan',
                'kenh' => 'online',
                'ma_tra_cuu' => 'TC-'.uniqid(),
                'ten_khach_dat' => 'Khách P2',
                'so_dienthoai_dat' => '0901234567',
                'ghi_chu_khach' => 'Ghi chú P2',
                'han_giu' => now()->addHours(24),
                'hinh_thuc_nhan' => 'giao_tan_noi',
                'ten_nguoi_nhan' => 'Người nhận P2',
                'sdt_nguoi_nhan' => '0908765432',
                'dia_chi_giao' => '123 Đường ABC, Phường XYZ, Quận 1, TP.HCM',
                'phi_giao_hang' => 30000,
                'trang_thai_giao' => 'cho_xac_nhan',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->assertSame(1, DB::table('invoices')->where('ma_hoadon', $maDonTest)->where('hinh_thuc_nhan', 'giao_tan_noi')->count());

            $migration = require base_path(self::MIGRATION);
            try {
                $migration->down();
                $this->fail('down() phải chặn khi còn đơn giao_tan_noi');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('giao tận nơi', $e->getMessage());
            }
            $this->assertTrue(Schema::hasColumn('invoices', 'hinh_thuc_nhan'), 'down() bị chặn thì không được drop cột');

            DB::table('invoices')->where('ma_hoadon', $maDonTest)->delete();
            $migration->down();
            DB::table('migrations')->where('migration', $tenMigration)->delete();

            $this->assertFalse(Schema::hasColumn('invoices', 'hinh_thuc_nhan'));
            $this->assertFalse(Schema::hasColumn('invoices', 'ten_nguoi_nhan'));
            $this->assertFalse(Schema::hasColumn('invoices', 'sdt_nguoi_nhan'));
            $this->assertFalse(Schema::hasColumn('invoices', 'dia_chi_giao'));
            $this->assertFalse(Schema::hasColumn('invoices', 'phi_giao_hang'));
            $this->assertFalse(Schema::hasColumn('invoices', 'trang_thai_giao'));
            $this->assertSame($soDonGoc, DB::table('invoices')->count(), 'Dữ liệu POS cũ phải nguyên vẹn');
            $this->assertSame($enumTruoc, $this->dinhNghiaEnum(), 'Enum phải giữ nguyên sau down()');
            $this->assertSame($fkTruoc, $this->soForeignKey('invoices'));
        } finally {
            DB::table('invoices')->where('ma_hoadon', 'like', 'P2-%')->delete();
            if (Schema::hasColumn('invoices', 'hinh_thuc_nhan')) {
                (require base_path(self::MIGRATION))->down();
                DB::table('migrations')->where('migration', $tenMigration)->delete();
            }
            if ($tuApDungM1 && Schema::hasColumn('invoices', 'kenh')) {
                (require base_path(self::M1))->down();
                DB::table('migrations')->where('migration', basename(self::M1, '.php'))->delete();
            }
        }
    }

    /**
     * P2 BƯỚC 3 — đường hoàn tất thanh toán trên MySQL strict: đơn online
     * giao_tan_noi → da_thanh_toan + trang_thai_giao theo POS_ONLINE_TU_DONG_XAC_NHAN.
     * Tự áp M1/M3 nếu bản sao chưa có, tự gỡ sau test.
     */
    public function test_hoan_tat_thanh_toan_don_giao_dat_trang_thai_giao(): void
    {
        $this->chiChayTrenBanSaoMysql();

        $tuApDung = [];
        if (! Schema::hasColumn('invoices', 'kenh')) {
            $this->assertSame(0, Artisan::call('migrate', ['--path' => self::M1, '--force' => true]));
            $tuApDung[] = self::M1;
        }
        if (! Schema::hasColumn('invoices', 'trang_thai_giao')) {
            $this->assertSame(0, Artisan::call('migrate', ['--path' => self::MIGRATION, '--force' => true]));
            $tuApDung[] = self::MIGRATION;
        }

        $maDon = 'HD'.now()->format('Ymd').'-'.random_int(8000, 8999);
        try {
            \Illuminate\Support\Facades\Config::set('pos.online_tu_dong_xac_nhan', false);

            DB::table('invoices')->insert([
                'ma_hoadon' => $maDon,
                'user_id' => null,
                'tong_tien' => 20000,
                'tong_thanh_toan' => 50000,
                'phuong_thuc' => 'chuyenkhoan',
                'trang_thai' => 'cho_thanh_toan',
                'kenh' => 'online',
                'ma_tra_cuu' => 'TC-'.uniqid(),
                'ten_khach_dat' => 'Khách P2B3',
                'so_dienthoai_dat' => '0901234567',
                'hinh_thuc_nhan' => 'giao_tan_noi',
                'phi_giao_hang' => 30000,
                'trang_thai_giao' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $don = DonHang::where('ma_hoadon', $maDon)->firstOrFail();
            $res = app(\App\Services\ThanhToanChuyenKhoanService::class)
                ->hoanTatThanhToan($don, null, null, null);

            $this->assertTrue($res['success'], $res['message'] ?? '');
            $don->refresh();
            $this->assertSame('da_thanh_toan', $don->trang_thai);
            $this->assertSame('cho_xac_nhan', $don->trang_thai_giao);

            // Gọi lại lần nữa → idempotent, không đổi trạng thái giao.
            $don->update(['trang_thai_giao' => 'dang_giao']);
            $res2 = app(\App\Services\ThanhToanChuyenKhoanService::class)
                ->hoanTatThanhToan($don->fresh(), null, null, null);
            $this->assertFalse($res2['success']);
            $this->assertTrue($res2['already_processed']);
            $this->assertSame('dang_giao', $don->fresh()->trang_thai_giao);
        } finally {
            DB::table('invoices')->where('ma_hoadon', $maDon)->delete();
            foreach (array_reverse($tuApDung) as $migration) {
                (require base_path($migration))->down();
                DB::table('migrations')->where('migration', basename($migration, '.php'))->delete();
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
