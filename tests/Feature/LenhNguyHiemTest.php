<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Chốt chặn production của các lệnh phá hủy. Chỉ kiểm chứng nhánh TỪ CHỐI,
 * vì nhánh cho phép sẽ thật sự xóa bảng và không thể chạy an toàn trong test.
 */
class LenhNguyHiemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['env'] = 'production';
    }

    protected function tearDown(): void
    {
        $this->app['env'] = 'testing';
        parent::tearDown();
    }

    public function test_migrate_fresh_bi_chan_khi_thieu_co_force_dangerous(): void
    {
        $this->artisan('migrate:fresh')
            ->expectsOutputToContain('bị chặn ở production')
            ->assertExitCode(1);

        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('invoices'));
    }

    public function test_migrate_fresh_bi_chan_khi_nhap_sai_ten_database(): void
    {
        $this->artisan('migrate:fresh', ['--force-dangerous' => true, '--ten-db-xac-nhan' => 'doan_cnw'])
            ->expectsOutputToContain('Tên database không khớp')
            ->assertExitCode(1);

        $this->assertTrue(Schema::hasTable('users'));
    }

    public function test_demo_reset_bi_chan_khi_thieu_co_force_dangerous(): void
    {
        $this->artisan('demo:reset', ['--force' => true])
            ->expectsOutputToContain('bị chặn ở production')
            ->assertExitCode(1);

        $this->assertTrue(Schema::hasTable('users'));
    }

    public function test_documents_import_bi_chan_khi_thieu_co_force_dangerous(): void
    {
        $this->artisan('documents:import', ['--customers' => true])
            ->expectsOutputToContain('bị chặn ở production')
            ->assertExitCode(1);
    }
}