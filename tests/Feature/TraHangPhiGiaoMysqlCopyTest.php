<?php

namespace Tests\Feature;

use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\PhieuTra;
use App\Models\SanPham;
use App\Models\User;
use App\Support\KiemTraSchema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Quy tắc hoàn phí giao trên MySQL strict (decimal/rounding thật).
 * Chạy: DB_CONNECTION=mysql DB_DATABASE=doan_cnw_copy DB_USERNAME=root DB_PASSWORD=''
 *   php artisan test --filter='MysqlCopyTest|KhoaDongMysqlTest'
 * Tự áp M1/M3 (cột online) + migration returns.tien_hoan_phi_giao nếu bản sao
 * chưa có, tự gỡ sau test. Tự dọn dữ liệu test.
 */
class TraHangPhiGiaoMysqlCopyTest extends TestCase
{
    private const M1 = 'database/migrations/2026_11_12_000001_add_online_order_fields_to_invoices_table.php';
    private const M3 = 'database/migrations/2026_11_14_000003_add_delivery_fields_to_invoices_table.php';
    private const MIGRATION = 'database/migrations/2026_11_16_000002_add_tien_hoan_phi_giao_to_returns_table.php';

    /** @var string[] migration tự áp, cần gỡ ở tearDown (theo thứ tự ngược) */
    private array $tuApDung = [];

    private int $donId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $ketNoi = DB::connection();
        if ($ketNoi->getDriverName() !== 'mysql' || ! str_ends_with((string) $ketNoi->getDatabaseName(), '_copy')) {
            $this->markTestSkipped('Cần MySQL bản sao doan_cnw_copy.');
        }
        // Bản sao là snapshot cũ: tự áp các migration cần thiết (giống DatHangP2MysqlCopyTest).
        if (! Schema::hasColumn('invoices', 'kenh')) {
            $this->assertSame(0, Artisan::call('migrate', ['--path' => self::M1, '--force' => true]));
            $this->tuApDung[] = self::M1;
        }
        if (! Schema::hasColumn('invoices', 'phi_giao_hang')) {
            $this->assertSame(0, Artisan::call('migrate', ['--path' => self::M3, '--force' => true]));
            $this->tuApDung[] = self::M3;
        }
        if (! Schema::hasColumn('returns', 'tien_hoan_phi_giao')) {
            $this->assertSame(0, Artisan::call('migrate', ['--path' => self::MIGRATION, '--force' => true]));
            $this->tuApDung[] = self::MIGRATION;
        }
        KiemTraSchema::datLai();
    }

    protected function tearDown(): void
    {
        try {
            if ($this->donId) {
                $ids = PhieuTra::where('invoice_id', $this->donId)->pluck('id');
                DB::table('return_items')->whereIn('return_id', $ids)->delete();
                PhieuTra::where('invoice_id', $this->donId)->delete();
                ChiTietDonHang::where('invoice_id', $this->donId)->delete();
                DonHang::whereKey($this->donId)->delete();
            }
            foreach (array_reverse($this->tuApDung) as $migration) {
                (require base_path($migration))->down();
                DB::table('migrations')->where('migration', basename($migration, '.php'))->delete();
            }
        } finally {
            KiemTraSchema::datLai();
            parent::tearDown();
        }
    }

    public function test_tinh_hoan_tien_hang_va_phi_ship_tren_mysql_decimal(): void
    {
        $admin = User::where('role', 'admin')->first()
            ?? User::create(['name' => 'Test Admin', 'email' => 'test-admin-'.uniqid().'@local', 'password' => bcrypt('x'), 'role' => 'admin']);
        $sp = SanPham::orderBy('id')->firstOrFail();

        // 2×33.333 = 66.666 hàng; giảm 6.666; ship 30.000 → tổng 90.000.
        // Tỷ lệ tiền hàng = (90.000-30.000)/66.666 = 60.000/66.666 ≈ 0.900009
        // Trả 1 cái: round(33.333 × 0.900009) = 30.000 (kiểm chứng làm tròn decimal MySQL).
        $don = DonHang::create([
            'ma_hoadon' => 'PGCOPY-'.uniqid(),
            'tong_tien' => 66666, 'tien_giam' => 6666, 'tong_thanh_toan' => 90000,
            'phi_giao_hang' => 30000, 'trang_thai' => 'da_thanh_toan',
            'thoi_gian_thanh_toan' => now(), 'kenh' => 'online', 'phuong_thuc' => 'chuyenkhoan',
        ]);
        $this->donId = $don->id;
        $ct = ChiTietDonHang::create([
            'invoice_id' => $don->id, 'product_id' => $sp->id, 'soluong' => 2,
            'don_gia' => 33333, 'gia_von' => 0, 'thanh_tien' => 66666,
        ]);

        $this->actingAs($admin)->post(route('trahang.luu'), [
            'invoice_id' => $don->id, 'hinh_thuc_hoan' => 'chuyenkhoan', 'ly_do' => 'Test copy',
            'items' => [['invoice_detail_id' => $ct->id, 'soluong' => 1, 'tinh_trang' => 'con_tot']],
        ])->assertRedirect();

        $phieu = PhieuTra::where('invoice_id', $don->id)->latest('id')->firstOrFail();
        $this->assertSame(30000, (int) $phieu->tong_hoan);
        $this->assertSame(0, (int) $phieu->tien_hoan_phi_giao);

        // Admin tick hoàn ship → cộng đúng 30.000, cột decimal MySQL giữ nguyên
        $this->actingAs($admin)->post(route('trahang.luu'), [
            'invoice_id' => $don->id, 'hinh_thuc_hoan' => 'chuyenkhoan',
            'ly_do' => 'Lỗi cửa hàng, hoàn ship', 'hoan_phi_giao' => '1',
            'items' => [['invoice_detail_id' => $ct->id, 'soluong' => 1, 'tinh_trang' => 'con_tot']],
        ])->assertRedirect();

        $phieu2 = PhieuTra::where('invoice_id', $don->id)->latest('id')->firstOrFail();
        $this->assertSame(60000, (int) $phieu2->tong_hoan);
        $this->assertSame(30000, (int) $phieu2->tien_hoan_phi_giao);
        // Tích lũy 30.000 + 60.000 = 90.000 = đúng tổng khách trả
        $this->assertSame(90000, (int) PhieuTra::where('invoice_id', $don->id)->sum('tong_hoan'));
    }
}
