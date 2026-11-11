<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Tests\TestCase;

class SepayKiemTraKetNoiTest extends TestCase
{
    private const TOKEN = 'TOKEN-SEPAY-KIEM-TRA-BI-MAT';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'pos.sepay_api_token' => self::TOKEN,
            'pos.sepay_api_base_url' => 'https://userapi.sepay.vn/v2',
            'pos.sepay_api_version' => 'v2',
            'pos.sepay_api_timeout' => 5,
            'pos.sepay_api_retries' => 0,
            'pos.sepay_api_per_page' => 100,
            'pos.sepay_api_min_interval_ms' => 0,
            'pos.sepay_api_max_retry_after' => 1,
        ]);

        Http::preventStrayRequests();
    }

    private function batDauGhiLog(): TestHandler
    {
        config(['logging.default' => 'single']);
        $handler = new TestHandler;
        Log::channel('single')->getLogger()->pushHandler($handler);

        return $handler;
    }

    private function chayLenh(): array
    {
        $maThoat = Artisan::call('sepay:kiem-tra-ket-noi');

        return [$maThoat, Artisan::output()];
    }

    private function assertLogKhongLoToken(TestHandler $handler): void
    {
        foreach ($handler->getRecords() as $record) {
            $this->assertStringNotContainsString(self::TOKEN, (string) $record['message']);
            $this->assertStringNotContainsString(self::TOKEN, (string) json_encode($record['context']));
        }
    }

    public function test_ket_noi_thanh_cong_in_ok_va_dung_bearer(): void
    {
        Http::fake([
            'https://userapi.sepay.vn/v2/*' => Http::response(['status' => 'success', 'data' => []], 200),
        ]);
        $handler = $this->batDauGhiLog();

        [$maThoat, $output] = $this->chayLenh();

        $this->assertSame(0, $maThoat);
        $this->assertStringContainsString('Kết nối SePay API OK.', $output);
        $this->assertStringNotContainsString(self::TOKEN, $output);
        $this->assertLogKhongLoToken($handler);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/transactions')
                && $request->hasHeader('Authorization', 'Bearer '.self::TOKEN)
                && (int) $request['per_page'] === 1;
        });
        Http::assertSentCount(1);
    }

    public function test_401_bao_loi_ro_rang_khong_lo_token(): void
    {
        Http::fake([
            'https://userapi.sepay.vn/v2/*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);
        $handler = $this->batDauGhiLog();

        [$maThoat, $output] = $this->chayLenh();

        $this->assertSame(1, $maThoat);
        $this->assertStringContainsString('401', $output);
        $this->assertStringContainsString('SEPAY_API_TOKEN', $output);
        $this->assertStringNotContainsString(self::TOKEN, $output);
        $this->assertLogKhongLoToken($handler);
    }

    public function test_loi_ket_noi_co_token_trong_message_van_bi_che(): void
    {
        Http::fake(function (): never {
            throw new ConnectionException(
                'cURL error 28: timed out for https://userapi.sepay.vn/v2/transactions with Bearer '.self::TOKEN
            );
        });
        $handler = $this->batDauGhiLog();

        [$maThoat, $output] = $this->chayLenh();

        $this->assertSame(1, $maThoat);
        $this->assertStringContainsString('Không kết nối được SePay API', $output);
        $this->assertStringNotContainsString(self::TOKEN, $output);
        $this->assertLogKhongLoToken($handler);
    }

    public function test_thieu_token_khong_goi_mang(): void
    {
        config(['pos.sepay_api_token' => '']);
        Http::fake();
        $handler = $this->batDauGhiLog();

        [$maThoat, $output] = $this->chayLenh();

        $this->assertSame(1, $maThoat);
        $this->assertStringContainsString('SEPAY_API_TOKEN', $output);
        Http::assertNothingSent();
        $this->assertLogKhongLoToken($handler);
    }
}
