<?php

namespace Tests\Feature;

use App\Models\BienDongKho;
use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\SanPham;
use App\Services\ThanhToanChuyenKhoanService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * P1 trên bản sao MySQL (doan_cnw_copy):
 *  - T-DH02b: migration M2 (stock_movements.user_id nullable) up/down, không FK mới,
 *    enum loai giữ nguyên, down() chặn khi còn movement user_id NULL.
 *  - T-DH13 (bàn giao điểm 1): khóa dòng sản phẩm THẬT bằng hai kết nối MySQL. Khi
 *    kết nối A giữ khóa sản phẩm trong transaction, kết nối B gọi đường tạo đơn
 *    online (R1 + lockForUpdate) phải CHỜ rồi timeout (innodb_lock_wait_timeout=1)
 *    thay vì cùng vượt trần. Đây là tranh chấp khóa dòng InnoDB thật qua hai kết
 *    nối, không phải mô phỏng đa luồng đồng thời; nó chứng minh khóa tuần tự hóa
 *    người tạo đơn. SQLite :memory: một kết nối không kiểm chứng được nên skip.
 *
 * Không RefreshDatabase để giữ schema/dữ liệu bản sao; test tự áp migration và dọn
 * bản ghi nó tạo, chạy lại được nhiều lần. Không chạm doan_cnw. Chạy:
 *   DB_CONNECTION=mysql DB_DATABASE=doan_cnw_copy DB_USERNAME=root DB_PASSWORD=''
 *   php artisan test --filter=DatHangP1MysqlCopyTest
 */
class DatHangP1MysqlCopyTest extends TestCase
{
    private const M1 = 'database/migrations/2026_11_12_000001_add_online_order_fields_to_invoices_table.php';
    private const M2 = 'database/migrations/2026_11_13_000001_make_stock_movements_user_id_nullable.php';
    private const M3 = 'database/migrations/2026_11_14_000003_add_delivery_fields_to_invoices_table.php';

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
                'Cần MySQL bản sao: DB_CONNECTION=mysql DB_DATABASE=doan_cnw_copy php artisan test --filter=DatHangP1MysqlCopyTest'
            );
        }
    }

    public function test_t_dh02b_m2_up_down_tren_mysql_copy_giu_nguyen_enum_va_du_lieu(): void
    {
        $this->chiChayTrenBanSaoMysql();

        $soGoc = DB::table('stock_movements')->count();
        $fkTruoc = $this->soForeignKey('stock_movements');
        $enumTruoc = $this->enumLoai();
        $sp = SanPham::orderBy('id')->firstOrFail();
        $tenM2 = basename(self::M2, '.php');

        try {
            // Dọn tàn dư lần chạy trước để test chạy lại được
            BienDongKho::where('ghi_chu', 'P1-M2-TEST')->delete();
            if ($this->m2DaApDung()) {
                (require base_path(self::M2))->down();
                DB::table('migrations')->where('migration', $tenM2)->delete();
            }

            $this->assertSame(0, Artisan::call('migrate', ['--path' => self::M2, '--force' => true]));

            $cot = $this->cotStockMovements();
            $this->assertSame('YES', $cot['user_id']['IS_NULLABLE'], 'M2 phải làm user_id nullable');
            $this->assertSame($fkTruoc, $this->soForeignKey('stock_movements'), 'Không được tạo FK mới');
            $this->assertSame($enumTruoc, $this->enumLoai(), 'Enum loai phải giữ nguyên sau up()');

            $id = DB::table('stock_movements')->insertGetId([
                'product_id' => $sp->id,
                'user_id' => null,
                'invoice_id' => null,
                'loai' => 'ban',
                'soluong' => -1,
                'ghi_chu' => 'P1-M2-TEST',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->assertSame(1, DB::table('stock_movements')->where('id', $id)->whereNull('user_id')->count());

            try {
                (require base_path(self::M2))->down();
                $this->fail('down() phải chặn khi còn movement user_id NULL');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('user_id', $e->getMessage());
            }
            $this->assertSame('YES', $this->cotStockMovements()['user_id']['IS_NULLABLE'], 'down() bị chặn thì không được đổi cột');

            DB::table('stock_movements')->where('id', $id)->delete();
            (require base_path(self::M2))->down();
            DB::table('migrations')->where('migration', $tenM2)->delete();

            $this->assertSame('NO', $this->cotStockMovements()['user_id']['IS_NULLABLE'], 'user_id phải về NOT NULL sau down()');
            $this->assertSame($soGoc, DB::table('stock_movements')->count(), 'Dữ liệu movement cũ phải nguyên vẹn');
            $this->assertSame($fkTruoc, $this->soForeignKey('stock_movements'));
            $this->assertSame($enumTruoc, $this->enumLoai(), 'Enum loai phải giữ nguyên sau down()');
        } finally {
            BienDongKho::where('ghi_chu', 'P1-M2-TEST')->delete();
            if ($this->m2DaApDung()) {
                (require base_path(self::M2))->down();
                DB::table('migrations')->where('migration', $tenM2)->delete();
            }
        }
    }

    public function test_t_dh13_khoa_san_pham_chan_tao_don_online_dong_thoi(): void
    {
        $this->chiChayTrenBanSaoMysql();

        $sp = SanPham::orderBy('id')->firstOrFail();
        $tonGoc = (int) $sp->soluong;
        $activeGoc = (bool) $sp->trang_thai;
        $maTraCuu = 'P1CONC'.strtoupper(substr(uniqid(), -8));
        $gioHang = [['id' => $sp->id, 'soluong' => 1]];
        $online = [
            'kenh' => 'online',
            'phuong_thuc' => 'tienmat',
            'ma_tra_cuu' => $maTraCuu,
            'ten_khach_dat' => 'Khách Concurrent',
            'so_dienthoai_dat' => '0901234567',
            'ghi_chu_khach' => null,
            'han_giu' => now()->addHours(4),
        ];
        $phienKhac = null;

        try {
            $this->apDungM1M2M3();
            SanPham::whereKey($sp->id)->update(['soluong' => 10, 'trang_thai' => true]);

            config(['database.connections.khoa_phu' => config('database.connections.'.config('database.default'))]);
            $phienKhac = DB::connection('khoa_phu');
            // Kết nối mặc định là bên sẽ chờ khóa; hạ timeout của chính nó.
            DB::connection()->statement('SET SESSION innodb_lock_wait_timeout = 1');

            $phienKhac->beginTransaction();
            $phienKhac->table('products')->where('id', $sp->id)->lockForUpdate()->first();

            $batDau = microtime(true);
            try {
                app(ThanhToanChuyenKhoanService::class)->taoHoaDonChoThanhToan($gioHang, [], null, $online);
                $this->fail('Tạo đơn online phải chờ khóa sản phẩm và timeout khi sản phẩm đang bị kết nối khác khóa.');
            } catch (QueryException $e) {
                $this->assertStringContainsString('Lock wait timeout', $e->getMessage());
            }
            $this->assertGreaterThan(1.0, microtime(true) - $batDau, 'Lệnh phải thực sự chờ khóa dòng.');

            // Bị chặn thì không đơn nào được tạo, kho nguyên vẹn (không cùng vượt trần).
            $this->assertSame(0, DonHang::where('ma_tra_cuu', $maTraCuu)->count());
            $this->assertSame(10, (int) SanPham::whereKey($sp->id)->value('soluong'));

            $phienKhac->rollBack();

            // Nhả khóa xong thì tạo đơn thành công, trừ kho đúng một lần.
            $don = app(ThanhToanChuyenKhoanService::class)->taoHoaDonChoThanhToan($gioHang, [], null, $online);
            $this->assertSame('online', $don->kenh);
            $this->assertSame('tienmat', $don->phuong_thuc);
            $this->assertSame('cho_thanh_toan', $don->trang_thai);
            $this->assertNull($don->user_id);
            $this->assertSame(9, (int) SanPham::whereKey($sp->id)->value('soluong'));
            $this->assertSame(1, BienDongKho::where('invoice_id', $don->id)->whereNull('user_id')->where('loai', 'ban')->count());
        } finally {
            if ($phienKhac !== null && $phienKhac->transactionLevel() > 0) {
                $phienKhac->rollBack();
            }
            DB::purge('khoa_phu');
            DB::connection()->statement('SET SESSION innodb_lock_wait_timeout = 50');

            $ids = $this->m1DaApDung()
                ? DonHang::where('ma_tra_cuu', $maTraCuu)->pluck('id')
                : collect();
            if ($ids->isNotEmpty()) {
                BienDongKho::whereIn('invoice_id', $ids)->delete();
                ChiTietDonHang::whereIn('invoice_id', $ids)->delete();
                DonHang::whereIn('id', $ids)->delete();
            }
            SanPham::whereKey($sp->id)->update(['soluong' => $tonGoc, 'trang_thai' => $activeGoc]);
            $this->goM1M2M3();
        }
    }

    private function apDungM1M2M3(): void
    {
        $this->goM1M2M3();
        $this->assertSame(0, Artisan::call('migrate', ['--path' => self::M1, '--force' => true]));
        $this->assertSame(0, Artisan::call('migrate', ['--path' => self::M2, '--force' => true]));
        // Sau merge P2, R1 ghi cả 6 cột M3 khi tạo đơn online nên bản sao phải có M3.
        $this->assertSame(0, Artisan::call('migrate', ['--path' => self::M3, '--force' => true]));
    }

    private function goM1M2M3(): void
    {
        // Dọn bản ghi test trước vì down() chặn khi còn user_id NULL. Chỉ truy vấn
        // ma_tra_cuu khi M1 đã áp (cột tồn tại) để chạy lại được lúc bản sao đang
        // ở trạng thái trước M1.
        if ($this->m1DaApDung()) {
            $ids = DonHang::where('ma_tra_cuu', 'like', 'P1CONC%')->pluck('id');
            if ($ids->isNotEmpty()) {
                BienDongKho::whereIn('invoice_id', $ids)->delete();
                ChiTietDonHang::whereIn('invoice_id', $ids)->delete();
                DonHang::whereIn('id', $ids)->delete();
            }
        }
        BienDongKho::where('ghi_chu', 'P1-M2-TEST')->delete();

        if ($this->m3DaApDung()) {
            (require base_path(self::M3))->down();
            DB::table('migrations')->where('migration', basename(self::M3, '.php'))->delete();
        }
        if ($this->m2DaApDung()) {
            (require base_path(self::M2))->down();
            DB::table('migrations')->where('migration', basename(self::M2, '.php'))->delete();
        }
        if ($this->m1DaApDung()) {
            (require base_path(self::M1))->down();
            DB::table('migrations')->where('migration', basename(self::M1, '.php'))->delete();
        }
    }

    private function m3DaApDung(): bool
    {
        return Schema::hasColumn('invoices', 'hinh_thuc_nhan');
    }

    private function m1DaApDung(): bool
    {
        return Schema::hasColumn('invoices', 'kenh');
    }

    private function m2DaApDung(): bool
    {
        return $this->cotStockMovements()['user_id']['IS_NULLABLE'] === 'YES';
    }

    private function cotStockMovements(): array
    {
        return collect(DB::select(
            "SELECT COLUMN_NAME, COLUMN_TYPE, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stock_movements'"
        ))->map(fn ($c) => (array) $c)->keyBy('COLUMN_NAME')->all();
    }

    private function enumLoai(): string
    {
        return (string) DB::selectOne(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stock_movements' AND COLUMN_NAME = 'loai'"
        )->COLUMN_TYPE;
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