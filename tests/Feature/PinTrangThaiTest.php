<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\KiemTraPinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * KiemTraPinService — nguon du lieu cho pin:trang-thai / cong-tac-pin-prod.ps1.
 * Chay tren sqlite :memory: (RefreshDatabase), khong cham DB that.
 */
class PinTrangThaiTest extends TestCase
{
    use RefreshDatabase;

    private KiemTraPinService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new KiemTraPinService();
        config(['pos.pin_bat' => true, 'pos.ma_duyet_bat' => true]);
    }

    private function user(array $attr): User
    {
        // pin_* khong nam trong $fillable -> forceFill sau khi create.
        $u = User::create([
            'name' => 'Test',
            'email' => 'u'.uniqid().'@test.local',
            'password' => Hash::make('x'),
            'role' => $attr['role'] ?? 'thungan',
        ]);
        $u->forceFill(array_diff_key($attr, ['role' => true]))->save();

        return $u->fresh();
    }

    private function mocLo(string $luc): void
    {
        // Tu GD1fix: moc_lo chi lay config POS_MOC_LO_PIN_LUC hoac activity_logs
        // id=463 — id tu tang trong test khong bao gio la 463 -> dat config.
        config(['pos.moc_lo_pin_luc' => $luc]);
        DB::table('activity_logs')->insert([
            'hanh_dong' => 'pin_dat',
            'doi_tuong' => 'user',
            'doi_tuong_loai' => 'user',
            'doi_tuong_id' => 1,
            'mo_ta' => 'test',
            'ip' => '127.0.0.1',
            'user_agent' => 'test',
            'nguon' => 'console',
            'created_at' => $luc,
            'updated_at' => $luc,
        ]);
    }

    public function test_du_lieu_sach_khong_phat_hien(): void
    {
        $moc = now()->subHour();
        $this->mocLo($moc->toDateTimeString());
        $this->user(['role' => 'admin', 'pin_hash' => Hash::make('1'), 'pin_doi_luc' => now()]);
        $this->user(['role' => 'thungan', 'pin_hash' => Hash::make('2')]);
        $this->user(['role' => 'thukho', 'pin_hash' => Hash::make('3')]);

        $kq = $this->svc->kiemTra();

        $this->assertSame([], $kq['admin_chua_doi']);
        $this->assertSame([], $kq['admin_thieu_pin']);
        $this->assertSame([], $kq['nv_thieu_pin']);
        $this->assertSame(5, $kq['cot_pin']);
        $this->assertTrue($kq['bang_yc']);
        $this->assertNotNull($kq['moc_lo']);
    }

    public function test_admin_bang_moc_van_bi_bat(): void
    {
        // pin_doi_luc == moc_lo -> CHUA doi sau su co -> phai bi bat.
        $moc = now()->subHour();
        $this->mocLo($moc->toDateTimeString());
        $admin = $this->user(['role' => 'admin', 'pin_hash' => Hash::make('1'), 'pin_doi_luc' => $moc]);

        $kq = $this->svc->kiemTra();

        $this->assertContains($admin->id, $kq['admin_chua_doi']);
    }

    public function test_admin_sau_moc_thi_dat(): void
    {
        $moc = now()->subHour();
        $this->mocLo($moc->toDateTimeString());
        $this->user(['role' => 'admin', 'pin_hash' => Hash::make('1'), 'pin_doi_luc' => now()]);

        $this->assertSame([], $this->svc->kiemTra()['admin_chua_doi']);
    }

    public function test_admin_thieu_pin_hash(): void
    {
        $this->mocLo(now()->subHour()->toDateTimeString());
        $admin = $this->user(['role' => 'admin', 'pin_hash' => null]);

        $kq = $this->svc->kiemTra();

        $this->assertContains($admin->id, $kq['admin_thieu_pin']);
    }

    public function test_thu_kho_dang_hoat_dong_thieu_pin(): void
    {
        $this->mocLo(now()->subHour()->toDateTimeString());
        $this->user(['role' => 'thukho', 'email' => 'thukho-that@example.com', 'pin_hash' => null]);

        $kq = $this->svc->kiemTra();

        $this->assertCount(1, $kq['nv_thieu_pin']);
        $this->assertSame('th***@example.com', $kq['nv_thieu_pin'][0]);
        $this->assertStringNotContainsString('thukho-that', json_encode($kq));
    }

    public function test_nv_bi_khoa_khong_tinh_thieu_pin(): void
    {
        $this->mocLo(now()->subHour()->toDateTimeString());
        $this->user(['role' => 'thungan', 'pin_hash' => null, 'khoa_luc' => now()->addDay()]);

        $this->assertSame([], $this->svc->kiemTra()['nv_thieu_pin']);
    }

    public function test_moc_lo_co_dinh_khong_troi_khi_co_pin_dat_moi(): void
    {
        // Mốc cấu hình cố định; thêm pin_dat console MỚI hơn -> mốc KHÔNG đổi.
        $this->mocLo(now()->toDateTimeString()); // su kien console moi hon
        config(['pos.moc_lo_pin_luc' => '2026-11-22 07:34:13']);

        $this->assertSame('2026-11-22 07:34:13', $this->svc->kiemTra()['moc_lo']);
    }

    public function test_moc_lo_uu_tien_config_hon_id_463(): void
    {
        config(['pos.moc_lo_pin_luc' => '2020-01-01 00:00:00']);
        // id 463 co the khong ton tai trong test DB -> van lay config.
        $this->assertSame('2020-01-01 00:00:00', $this->svc->kiemTra()['moc_lo']);
    }

    public function test_khong_co_moc_lo_thi_admin_khong_bi_so(): void
    {
        // Khong co activity_logs -> moc_lo null -> bo qua doi chieu admin.
        $this->user(['role' => 'admin', 'pin_hash' => Hash::make('1'), 'pin_doi_luc' => null]);

        $kq = $this->svc->kiemTra();

        $this->assertNull($kq['moc_lo']);
        $this->assertSame([], $kq['admin_chua_doi']);
        // nhung admin thieu pin_hash? o day co pin_hash, chi pin_doi_luc null
        // -> van nam trong admin_chua_doi? khong: nhanh do chi chay khi co moc.
        $this->assertSame([], $kq['admin_thieu_pin']);
    }

    public function test_lenh_artisan_xuat_json(): void
    {
        $this->mocLo(now()->subHour()->toDateTimeString());
        $this->user(['role' => 'admin', 'pin_hash' => Hash::make('1'), 'pin_doi_luc' => now()]);

        $this->artisan('pin:trang-thai')
            ->expectsOutputToContain('"admin_chua_doi":[]')
            ->assertSuccessful();
    }
}
