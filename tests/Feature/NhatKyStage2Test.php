<?php

namespace Tests\Feature;

use App\Models\KhachHang;
use App\Models\NhatKyHoatDong;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NhatKyStage2Test extends TestCase
{
    use RefreshDatabase;

    public function test_migration_them_cac_truong_kiem_toan(): void
    {
        foreach (['doi_tuong_loai', 'truoc', 'sau', 'user_agent', 'nguon', 'nhom_id'] as $column) {
            $this->assertTrue(Schema::hasColumn('activity_logs', $column), $column);
        }
    }

    public function test_model_khach_hang_tao_mot_dong_nhat_ky_co_diff_da_che(): void
    {
        $khach = KhachHang::create(['ho_ten' => 'Nguyễn Test', 'so_dienthoai' => '0912345678', 'dia_chi' => 'A']);
        $log = NhatKyHoatDong::where('doi_tuong', 'customers')->latest('id')->firstOrFail();
        $this->assertSame('tao', $log->hanh_dong);
        $this->assertSame('[đã thay đổi]', $log->sau['so_dienthoai']);
        $this->assertSame($khach->id, $log->doi_tuong_id);
    }

    public function test_nhat_ky_chan_update_va_delete_qua_model(): void
    {
        $log = NhatKyHoatDong::create(['hanh_dong' => 'test', 'doi_tuong' => 'test', 'mo_ta' => 'test']);
        $this->expectException(\LogicException::class);
        $log->update(['mo_ta' => 'sua']);
    }

    public function test_lenh_don_cu_mac_dinh_la_dry_run(): void
    {
        NhatKyHoatDong::create(['hanh_dong' => 'test', 'doi_tuong' => 'test', 'mo_ta' => 'test', 'created_at' => now()->subYears(2), 'updated_at' => now()->subYears(2)]);
        $this->artisan('nhatky:don-cu')->expectsOutputToContain('Dry-run')->assertSuccessful();
        $this->assertDatabaseCount('activity_logs', 1);
    }
}
