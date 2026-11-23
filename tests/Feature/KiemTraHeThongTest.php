<?php

namespace Tests\Feature;

use App\Console\Commands\KiemTraHeThong;
use App\Console\Commands\SepayDoiSoat;
use App\Models\DonHang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KiemTraHeThongTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        // Mặc định coi như cấu hình đủ và log đã xoay vòng để mỗi test chỉ
        // kiểm chứng đúng phần mình quan tâm.
        config([
            'pos.bank_code' => 'VCB',
            'pos.bank_account' => '0123456789',
            'pos.sepay_webhook_api_key' => 'khoa-test',
            'pos.sepay_api_token' => 'token-sepay-api-test',
            'pos.telegram_bot_token' => 'token-test',
            'pos.telegram_chat_id' => 'chat-test',
            'logging.default' => 'daily',
        ]);
        Cache::forget(SepayDoiSoat::KHOA_LAN_CUOI);
    }

    public function test_bao_loi_khi_scheduler_chua_tung_chay(): void
    {
        Cache::forget(KiemTraHeThong::KHOA_SCHEDULER);

        $this->artisan('he-thong:kiem-tra')
            ->expectsOutputToContain('[FAIL] Scheduler: chưa ghi nhận lần chạy nào')
            ->assertExitCode(1);
    }

    public function test_bao_ok_khi_scheduler_vua_chay(): void
    {
        Cache::put(KiemTraHeThong::KHOA_SCHEDULER, now()->getTimestamp(), now()->addDay());

        $this->artisan('he-thong:kiem-tra')
            ->expectsOutputToContain('[OK] Scheduler: lần cuối')
            ->assertExitCode(0);
    }

    public function test_bao_loi_khi_scheduler_da_ngung_qua_nguong(): void
    {
        Cache::put(KiemTraHeThong::KHOA_SCHEDULER, now()->subMinutes(10)->getTimestamp(), now()->addDay());

        $maThoat = Artisan::call('he-thong:kiem-tra');
        $output = Artisan::output();

        $this->assertSame(1, $maThoat);
        $this->assertStringContainsString('[FAIL] Scheduler: lần cuối', $output);
        $this->assertStringContainsString('10 phút trước', $output);
        $this->assertStringContainsString('không còn chạy', $output);
    }

    public function test_canh_bao_don_cho_thanh_toan_qua_han(): void
    {
        $thuNgan = User::where('role', 'thungan')->firstOrFail();

        $donHang = DonHang::create([
            'ma_hoadon' => 'HD-QUA-HAN-01',
            'user_id' => $thuNgan->id,
            'customer_id' => null,
            'tong_tien' => 100000,
            'tong_thanh_toan' => 100000,
            'phuong_thuc' => 'chuyenkhoan',
            'trang_thai' => 'cho_thanh_toan',
        ]);
        $donHang->created_at = now()->subMinutes(40);
        $donHang->save();

        $this->artisan('he-thong:kiem-tra')
            ->expectsOutputToContain('[WARN] Đơn chờ quá hạn: 1 đơn');
    }

    public function test_bao_thieu_cau_hinh_bat_buoc(): void
    {
        config([
            'pos.bank_code' => '',
            'pos.sepay_webhook_api_key' => '',
            'pos.telegram_bot_token' => '',
        ]);

        $this->artisan('he-thong:kiem-tra')
            ->expectsOutputToContain('[FAIL] Ngân hàng (VietQR): thiếu')
            ->expectsOutputToContain('[FAIL] SePay webhook: thiếu')
            ->expectsOutputToContain('[WARN] Telegram: thiếu')
            ->assertExitCode(1);
    }

    public function test_canh_bao_thieu_sepay_api_token_chi_la_warn(): void
    {
        config(['pos.sepay_api_token' => '']);
        Cache::put(KiemTraHeThong::KHOA_SCHEDULER, now()->getTimestamp(), now()->addDay());

        $this->artisan('he-thong:kiem-tra')
            ->expectsOutputToContain('[WARN] SePay API: thiếu SEPAY_API_TOKEN')
            ->assertExitCode(0);
    }

    public function test_bao_ok_khi_co_token_va_lan_doi_soat_moi(): void
    {
        Cache::put(KiemTraHeThong::KHOA_SCHEDULER, now()->getTimestamp(), now()->addDay());
        Cache::put(SepayDoiSoat::KHOA_LAN_CUOI, now()->getTimestamp(), now()->addDay());

        $this->artisan('he-thong:kiem-tra')
            ->expectsOutputToContain('[OK] SePay API: đã cấu hình SEPAY_API_TOKEN')
            ->expectsOutputToContain('[OK] Đối soát SePay: lần cuối')
            ->assertExitCode(0);
    }

    public function test_canh_bao_lan_doi_soat_da_cu(): void
    {
        Cache::put(KiemTraHeThong::KHOA_SCHEDULER, now()->getTimestamp(), now()->addDay());
        Cache::put(SepayDoiSoat::KHOA_LAN_CUOI, now()->subHour()->getTimestamp(), now()->addDay());

        $output = $this->chayKiemTra();

        $this->assertStringContainsString('[WARN] Đối soát SePay: lần cuối', $output);
        $this->assertStringContainsString('lịch 10 phút có thể đã ngừng', $output);
    }

    public function test_khong_bao_gio_in_gia_tri_bi_mat(): void
    {
        $biMat = [
            'bank_code' => 'MA-NGAN-HANG-BI-MAT',
            'bank_account' => 'SO-TAI-KHOAN-BI-MAT',
            'sepay_webhook_api_key' => 'KHOA-SEPAY-BI-MAT',
            'sepay_api_token' => 'TOKEN-SEPAY-API-BI-MAT',
            'telegram_bot_token' => 'TOKEN-TELEGRAM-BI-MAT',
            'telegram_chat_id' => 'CHAT-ID-BI-MAT',
        ];
        config([
            'pos.bank_code' => $biMat['bank_code'],
            'pos.bank_account' => $biMat['bank_account'],
            'pos.sepay_webhook_api_key' => $biMat['sepay_webhook_api_key'],
            'pos.sepay_api_token' => $biMat['sepay_api_token'],
            'pos.telegram_bot_token' => $biMat['telegram_bot_token'],
            'pos.telegram_chat_id' => $biMat['telegram_chat_id'],
        ]);
        Cache::put(KiemTraHeThong::KHOA_SCHEDULER, now()->getTimestamp(), now()->addDay());

        $maThoat = Artisan::call('he-thong:kiem-tra');
        $output = Artisan::output();

        $this->assertSame(0, $maThoat);
        $this->assertStringContainsString('[OK] Ngân hàng (VietQR): đã cấu hình đủ', $output);
        $this->assertStringContainsString('[OK] SePay webhook: đã cấu hình', $output);
        foreach ($biMat as $giaTri) {
            $this->assertStringNotContainsString($giaTri, $output);
        }
    }

    public function test_canh_bao_debug_bat_va_env_khong_phai_production(): void
    {
        Cache::put(KiemTraHeThong::KHOA_SCHEDULER, now()->getTimestamp(), now()->addDay());
        config(['app.debug' => true]);

        $this->artisan('he-thong:kiem-tra')
            ->expectsOutputToContain('[WARN] APP_ENV: đang là "testing"')
            ->expectsOutputToContain('[WARN] APP_DEBUG: đang bật');
    }

    public function test_canh_bao_log_chua_phai_daily(): void
    {
        Cache::put(KiemTraHeThong::KHOA_SCHEDULER, now()->getTimestamp(), now()->addDay());
        config([
            'logging.default' => 'stack',
            'logging.channels.stack.channels' => ['single'],
        ]);

        $output = $this->chayKiemTra();

        $this->assertStringContainsString('kênh "single" không phải daily', $output);
        $this->assertStringContainsString('LOG_STACK=daily', $output);
    }

    public function test_bao_ok_khi_log_daily_giu_14_ngay(): void
    {
        Cache::put(KiemTraHeThong::KHOA_SCHEDULER, now()->getTimestamp(), now()->addDay());

        $this->artisan('he-thong:kiem-tra')
            ->expectsOutputToContain('[OK] Log: ghi theo ngày, giữ 14 ngày')
            ->assertExitCode(0);
    }

    public function test_engine_luu_tru_khong_ap_dung_cho_sqlite(): void
    {
        Cache::put(KiemTraHeThong::KHOA_SCHEDULER, now()->getTimestamp(), now()->addDay());

        $this->artisan('he-thong:kiem-tra')
            ->expectsOutputToContain('[OK] Engine lưu trữ: không áp dụng cho sqlite')
            ->assertExitCode(0);
    }

    public function test_canh_bao_migration_chua_ap_dung(): void
    {
        Cache::put(KiemTraHeThong::KHOA_SCHEDULER, now()->getTimestamp(), now()->addDay());
        DB::table('migrations')->where('migration', '0001_01_01_000000_create_users_table')->delete();

        $output = $this->chayKiemTra();

        $this->assertStringContainsString('1 migration chưa áp dụng: 0001_01_01_000000_create_users_table', $output);
    }

    public function test_schedule_da_dang_ky_cac_lenh_van_hanh(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('donhang:huy-qua-han')
            ->expectsOutputToContain('sao-luu:csdl')
            ->expectsOutputToContain('hold:clean')
            ->expectsOutputToContain('sepay:doi-soat')
            ->assertExitCode(0);
    }

    public function test_schedule_run_ghi_dau_heartbeat_vao_cache(): void
    {
        Cache::forget(KiemTraHeThong::KHOA_SCHEDULER);

        $this->artisan('schedule:run')->assertExitCode(0);

        $this->assertNotNull(Cache::get(KiemTraHeThong::KHOA_SCHEDULER));
    }

    public function test_admin_dang_hoat_dong_thieu_pin_la_fail(): void
    {
        config(['pos.pin_bat' => true]);
        // Moi user deu co PIN tru admin -> chi con admin thieu.
        foreach (User::all() as $u) {
            $u->forceFill(['pin_hash' => bcrypt('123456')])->save();
        }
        User::where('role', 'admin')->firstOrFail()
            ->forceFill(['pin_hash' => null])->save();

        $out = $this->chayKiemTra();

        $this->assertStringContainsString('[FAIL] PIN thao tác nhạy cảm', $out);
        $this->assertStringContainsString('admin', $out);
    }

    public function test_nv_thieu_pin_la_warn_kem_so_luong(): void
    {
        config(['pos.pin_bat' => true]);
        foreach (User::all() as $u) {
            $u->forceFill(['pin_hash' => bcrypt('123456')])->save();
        }
        // Thu kho dang hoat dong khong co PIN -> WARN, khong FAIL.
        User::where('role', 'thukho')->firstOrFail()
            ->forceFill(['pin_hash' => null])->save();

        $out = $this->chayKiemTra();

        $this->assertStringContainsString('[OK] PIN thao tác nhạy cảm', $out);
        $this->assertStringContainsString('[WARN] PIN nhân viên', $out);
        $this->assertStringContainsString('1 thu ngân/thủ kho', $out);
    }

    public function test_nv_bi_khoa_khong_tinh_thieu_pin(): void
    {
        config(['pos.pin_bat' => true]);
        foreach (User::all() as $u) {
            $u->forceFill(['pin_hash' => bcrypt('123456')])->save();
        }
        User::where('role', 'thungan')->firstOrFail()
            ->forceFill(['pin_hash' => null, 'khoa_luc' => now()->addDay()])->save();

        $out = $this->chayKiemTra();

        $this->assertStringContainsString('[OK] PIN nhân viên', $out);
    }

    public function test_tai_khoan_dung_mat_khau_demo_la_fail(): void
    {
        // Seeder tao MK '123456' cho ca 3 vai tro -> phai FAIL, in id+role.
        $out = $this->chayKiemTra();

        $this->assertStringContainsString('[FAIL] Mật khẩu demo', $out);
        $this->assertStringContainsString('(admin)', $out);
        $this->assertStringNotContainsString('123456', $out);
    }

    public function test_doi_mat_khau_xong_thi_ok(): void
    {
        foreach (User::whereIn('role', ['admin', 'thungan', 'thukho'])->get() as $u) {
            $u->forceFill(['password' => bcrypt('MatKhauThatKhongDemo@99')])->save();
        }

        $out = $this->chayKiemTra();

        $this->assertStringContainsString('[OK] Mật khẩu demo', $out);
    }

    /**
     * Artisan::output() trả toàn bộ output của lần gọi gần nhất, cho phép kiểm
     * nhiều đoạn trên cùng một dòng (expectsOutputToContain chỉ khớp một đoạn/dòng).
     */
    private function chayKiemTra(): string
    {
        Artisan::call('he-thong:kiem-tra');

        return Artisan::output();
    }
}