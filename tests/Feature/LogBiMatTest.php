<?php

namespace Tests\Feature;

use App\Services\ThongBaoService;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LogBiMatTest extends TestCase
{
    public function test_loi_ket_noi_telegram_khong_ghi_token_vao_log(): void
    {
        $token = '123456789:AAHfake-token-khong-duoc-lo-ra';

        config([
            'pos.telegram_bot_token' => $token,
            'pos.telegram_chat_id' => '-100123456',
        ]);

        // Guzzle nhét nguyên URL (kèm token) vào message của ConnectException.
        Http::fake(function () use ($token) {
            throw new ConnectException(
                "cURL error 28: Connection timed out for https://api.telegram.org/bot{$token}/sendMessage",
                new Request('POST', "https://api.telegram.org/bot{$token}/sendMessage")
            );
        });

        Log::spy();

        $ketQua = app(ThongBaoService::class)->guiTelegram('Xin chào');

        $this->assertFalse($ketQua);

        Log::shouldHaveReceived('warning')->withArgs(function (string $thongDiep, array $nguCanh) use ($token): bool {
            $this->assertSame('Telegram notification exception', $thongDiep);
            $this->assertStringNotContainsString($token, (string) ($nguCanh['message'] ?? ''));
            $this->assertStringNotContainsString($token, json_encode($nguCanh));

            return true;
        })->once();
    }

    public function test_phan_hoi_loi_tu_telegram_khong_ghi_token(): void
    {
        $token = '987654321:BBHfake-token-phan-hoi';

        config([
            'pos.telegram_bot_token' => $token,
            'pos.telegram_chat_id' => '-100123456',
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Bad Request'], 400),
        ]);

        Log::spy();

        $this->assertFalse(app(ThongBaoService::class)->guiTelegram('Xin chào'));

        Log::shouldHaveReceived('warning')->withArgs(function (string $thongDiep, array $nguCanh) use ($token): bool {
            $this->assertSame('Telegram notification failed', $thongDiep);
            $this->assertStringNotContainsString($token, json_encode($nguCanh));

            return true;
        })->once();
    }

    public function test_cau_hinh_log_xoay_vong_theo_ngay_giu_14_ngay(): void
    {
        $this->assertSame('daily', config('logging.channels.daily.driver'));
        $this->assertSame(14, (int) config('logging.channels.daily.days'));
    }

    public function test_env_example_dat_log_xoay_vong_va_du_ngay(): void
    {
        $noiDung = file_get_contents(base_path('.env.example'));
        $this->assertIsString($noiDung);
        $this->assertMatchesRegularExpression('/^LOG_STACK=daily$/m', $noiDung);
        $this->assertMatchesRegularExpression('/^LOG_DAILY_DAYS=14$/m', $noiDung);
    }

    public function test_khong_co_lenh_log_nao_ghi_thang_bi_mat(): void
    {
        $mauNguyHiem = '/\b(api_key|api_secret|bot_token|secret_key|password|mat_khau|card_number|so_the|cvv)\b/i';
        $viPham = [];

        $tepPhp = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($tepPhp as $tep) {
            if (! $tep->isFile() || $tep->getExtension() !== 'php') {
                continue;
            }

            foreach (file($tep->getPathname()) ?: [] as $soDong => $dong) {
                if (! preg_match('/Log::(debug|info|notice|warning|error|critical|alert|emergency)\s*\(/', $dong)) {
                    continue;
                }
                if (preg_match($mauNguyHiem, $dong)) {
                    $viPham[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $tep->getPathname()).':'.($soDong + 1).' '.trim($dong);
                }
            }
        }

        $this->assertSame([], $viPham, "Có lệnh log ghi thẳng tên trường nhạy cảm:\n".implode("\n", $viPham));
    }
}
