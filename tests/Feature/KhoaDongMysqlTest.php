<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\User;
use App\Services\ThanhToanChuyenKhoanService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Bước 5B mục 9: kiểm chứng lockForUpdate bằng khóa dòng THẬT.
 *
 * SQLite :memory: chỉ có một kết nối nên không mô phỏng được tranh chấp.
 * Test này chỉ chạy khi trỏ vào bản sao MySQL, ví dụ:
 *   DB_CONNECTION=mysql DB_DATABASE=doan_cnw_copy php artisan test --filter=KhoaDongMysqlTest
 * Không dùng RefreshDatabase để không xóa dữ liệu bản sao; test tự dọn bản ghi nó tạo.
 */
class KhoaDongMysqlTest extends TestCase
{
    public function test_hoa_don_bi_khoa_boi_phien_khac_thi_hoan_tat_phai_cho_va_that_bai(): void
    {
        $this->chiChayTrenBanSaoMysql();

        $nguoi = User::where('role', 'admin')->first() ?? User::query()->firstOrFail();
        $maHoaDon = 'HD-KHOA-'.now()->format('YmdHis').'-'.random_int(1000, 9999);

        $donHang = DonHang::create([
            'ma_hoadon' => $maHoaDon,
            'user_id' => $nguoi->id,
            'tong_tien' => 50000,
            'giam_gia_phantram' => 0,
            'tien_giam' => 0,
            'diem_su_dung' => 0,
            'tien_giam_diem' => 0,
            'diem_tichluy_nhan' => 0,
            'tong_thanh_toan' => 50000,
            'tien_khach_dua' => 0,
            'tien_thua' => 0,
            'phuong_thuc' => 'chuyenkhoan',
            'trang_thai' => 'cho_thanh_toan',
        ]);

        config(['database.connections.khoa_phu' => config('database.connections.'.config('database.default'))]);
        $phienKhac = DB::connection('khoa_phu');
        // Kết nối mặc định là bên sẽ chờ khóa; phải hạ timeout của chính nó.
        DB::connection()->statement('SET SESSION innodb_lock_wait_timeout = 1');

        try {
            $phienKhac->beginTransaction();
            $phienKhac->table('invoices')->where('id', $donHang->id)->lockForUpdate()->first();

            $batDau = microtime(true);
            try {
                app(ThanhToanChuyenKhoanService::class)->hoanTatThanhToan($donHang, 'GD-BI-KHOA');
                $this->fail('Hoàn tất thanh toán phải chờ khóa và thất bại khi hóa đơn đang bị khóa.');
            } catch (QueryException $e) {
                $this->assertStringContainsString('Lock wait timeout', $e->getMessage());
            }
            $this->assertGreaterThan(1.0, microtime(true) - $batDau, 'Lệnh phải thực sự chờ khóa.');

            $donHang->refresh();
            $this->assertSame('cho_thanh_toan', $donHang->trang_thai);
            $this->assertNull($donHang->ma_giaodich);

            $phienKhac->rollBack();

            $ketQua = app(ThanhToanChuyenKhoanService::class)->hoanTatThanhToan($donHang->fresh(), 'GD-SAU-KHOA');
            $this->assertTrue($ketQua['success']);
            $this->assertSame('da_thanh_toan', $donHang->fresh()->trang_thai);
            $this->assertSame('GD-SAU-KHOA', $donHang->fresh()->ma_giaodich);
        } finally {
            if ($phienKhac->transactionLevel() > 0) {
                $phienKhac->rollBack();
            }
            DB::purge('khoa_phu');
            DonHang::whereKey($donHang->id)->delete();
        }
    }

    private function chiChayTrenBanSaoMysql(): void
    {
        $ketNoi = DB::connection();
        $database = (string) $ketNoi->getDatabaseName();

        if ($ketNoi->getDriverName() !== 'mysql' || ! str_ends_with($database, '_copy')) {
            $this->markTestSkipped(
                'Cần MySQL bản sao: DB_CONNECTION=mysql DB_DATABASE=doan_cnw_copy php artisan test --filter=KhoaDongMysqlTest'
            );
        }
    }
}
