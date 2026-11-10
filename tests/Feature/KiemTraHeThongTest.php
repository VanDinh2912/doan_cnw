<?php

namespace Tests\Feature;

use App\Console\Commands\KiemTraHeThong;
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
            'pos.telegram_bot_token' => 'token-test',
            'pos.telegram_chat_id' => 'chat-test',
            'logging.default' => 'daily',
        ]);
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

    public function test_khong_bao_gio_in_gia_tri_bi_mat(): void
    {
        $biMat = [
            'bank_code' => 'MA-NGAN-HANG-BI-MAT',
            'bank_account' => 'SO-TAI-KHOAN-BI-MAT',
            'sepay_webhook_api_key' => 'KHOA-SEPAY-BI-MAT',
            'telegram_bot_token' => 'TOKEN-TELEGRAM-BI-MAT',
            'telegram_chat_id' => 'CHAT-ID-BI-MAT',
        ];
        config([
            'pos.bank_code' => $biMat['bank_code'],
            'pos.bank_account' => $biMat['bank_account'],
            'pos.sepay_webhook_api_key' => $biMat['sepay_webhook_api_key'],
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
            ->assertExitCode(0);
    }

    public function test_schedule_run_ghi_dau_heartbeat_vao_cache(): void
    {
        Cache::forget(KiemTraHeThong::KHOA_SCHEDULER);

        $this->artisan('schedule:run')->assertExitCode(0);

        $this->assertNotNull(Cache::get(KiemTraHeThong::KHOA_SCHEDULER));
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