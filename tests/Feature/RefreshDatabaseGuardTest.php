<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * T3 — chứng minh chốt chặn RefreshDatabase trong Tests\TestCase:
 * mô phỏng cấu hình MySQL (KHÔNG kết nối thật — port 9 không có MySQL lắng nghe,
 * tên DB giả) và khẳng định guard ném ngoại lệ tiếng Việt trước khi kịp kết nối.
 */
class RefreshDatabaseGuardTest extends TestCase
{
    use RefreshDatabase;

    private ?Throwable $loiSetUp = null;

    public function createApplication()
    {
        $app = parent::createApplication();

        // Cấu hình mysql mô phỏng đặt trước khi RefreshDatabase chạy. Port 9 +
        // tên DB giả: nếu guard hỏng thì kết nối cũng refused ngay, không thể
        // chạm CSDL thật hay bản sao.
        $app['config']->set('database.default', 'mysql_mo_phong');
        $app['config']->set('database.connections.mysql_mo_phong', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 9,
            'database' => 'doan_cnw_mo_phong',
            'username' => 'khong_dung',
            'password' => '',
        ]);

        // Dưới paratest, hook ParallelTesting::setUpTestCase chạy TRƯỚC guard
        // và tự kết nối DB khác :memory: → QueryException (port 9 refused)
        // thay vì RuntimeException của guard. Tắt auto-provision cho tiến
        // trình này: các test khác đều dùng sqlite :memory: nên hook vốn
        // là no-op với chúng.
        $_SERVER['LARAVEL_PARALLEL_TESTING_WITHOUT_DATABASES'] = '1';

        return $app;
    }

    protected function setUp(): void
    {
        try {
            parent::setUp();
        } catch (Throwable $e) {
            $this->loiSetUp = $e;
        }
    }

    public function test_guard_nem_ngoai_le_tieng_viet_khi_mo_phong_mysql(): void
    {
        $this->assertNotNull($this->loiSetUp, 'Guard phải ném ngoại lệ trong setUp, trước khi RefreshDatabase kết nối/migrate.');
        // Đúng RuntimeException thuần, không phải QueryException (con của PDOException
        // cũng kế thừa RuntimeException) — chứng minh guard chặn trước tầng kết nối.
        $this->assertSame(RuntimeException::class, get_class($this->loiSetUp));
        $this->assertStringContainsString('RefreshDatabase bị chặn', $this->loiSetUp->getMessage());
        $this->assertStringContainsString('sqlite :memory:', $this->loiSetUp->getMessage());
        $this->assertStringContainsString('mysql/doan_cnw_mo_phong', $this->loiSetUp->getMessage());
    }

    public function test_guard_khong_chan_sqlite_memory(): void
    {
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:']]);

        $this->chanRefreshDatabase();

        $this->assertTrue(true, 'sqlite :memory: phải đi qua guard bình thường.');
    }

    public function test_guard_chan_ca_sqlite_file(): void
    {
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite' => ['driver' => 'sqlite', 'database' => database_path('database.sqlite')]]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('RefreshDatabase bị chặn');

        $this->chanRefreshDatabase();
    }
}