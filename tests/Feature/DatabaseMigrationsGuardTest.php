<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * BƯỚC B (P2) — mở rộng chốt chặn T3 sang DatabaseMigrations: trait này cũng
 * migrate đầu bài test nên phải chịu cùng chốt chặn sqlite :memory:.
 * Mô phỏng mysql port 9 + tên DB giả (không kết nối thật, không chạm CSDL nào).
 */
class DatabaseMigrationsGuardTest extends TestCase
{
    use DatabaseMigrations;

    private ?Throwable $loiSetUp = null;

    public function createApplication()
    {
        $app = parent::createApplication();

        $app['config']->set('database.default', 'mysql_mo_phong');
        $app['config']->set('database.connections.mysql_mo_phong', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 9,
            'database' => 'doan_cnw_mo_phong',
            'username' => 'khong_dung',
            'password' => '',
        ]);

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

    public function test_guard_chan_database_migrations_tren_mysql_mo_phong(): void
    {
        $this->assertNotNull($this->loiSetUp, 'Guard phải ném ngoại lệ trong setUp trước khi trait kịp kết nối/migrate.');
        $this->assertSame(RuntimeException::class, get_class($this->loiSetUp));
        $this->assertStringContainsString('RefreshDatabase bị chặn', $this->loiSetUp->getMessage());
        $this->assertStringContainsString('mysql/doan_cnw_mo_phong', $this->loiSetUp->getMessage());
    }
}