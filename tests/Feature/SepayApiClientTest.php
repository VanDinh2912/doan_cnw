<?php

namespace Tests\Feature;

use App\Exceptions\SepayApiException;
use App\Services\SepayApiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SepayApiClientTest extends TestCase
{
    private const TOKEN = 'TOKEN-SEPAY-BI-MAT';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'pos.sepay_api_token' => self::TOKEN,
            'pos.sepay_api_base_url' => 'https://userapi.sepay.vn/v2',
            'pos.sepay_api_version' => 'v2',
            'pos.sepay_api_timeout' => 5,
            'pos.sepay_api_retries' => 2,
            'pos.sepay_api_per_page' => 100,
            'pos.sepay_api_min_interval_ms' => 0,
            'pos.sepay_api_max_retry_after' => 1,
        ]);

        Http::preventStrayRequests();
    }

    private function giaoDich(array $ghiDe = []): array
    {
        return array_merge([
            'id' => 'uuid-0001',
            'transaction_date' => '2026-11-11 09:00:00',
            'account_number' => '1017588888',
            'transfer_type' => 'in',
            'amount_in' => 150000,
            'amount_out' => 0,
            'transaction_content' => 'THANH TOAN HD20261111-0001',
            'reference_number' => 'FT001',
            'code' => 'SEVN1',
            'bank_brand_name' => 'VCB',
        ], $ghiDe);
    }

    private function phanHoi(array $data, array $pagination = []): array
    {
        return [
            'status' => 'success',
            'data' => $data,
            'meta' => [
                'pagination' => array_merge([
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 100,
                    'total' => count($data),
                ], $pagination),
            ],
        ];
    }

    public function test_chuan_hoa_dto_chi_lay_tien_vao_va_che_so_tai_khoan(): void
    {
        Http::fake([
            'https://userapi.sepay.vn/v2/*' => Http::response($this->phanHoi([
                $this->giaoDich(),
                $this->giaoDich([
                    'id' => 'uuid-0002',
                    'transfer_type' => 'out',
                    'amount_in' => 0,
                    'amount_out' => 50000,
                    'reference_number' => 'FT002',
                ]),
            ]), 200),
        ]);

        $ketQua = iterator_to_array((new SepayApiClient)->layGiaoDichVao(now()->subHour(), now()));

        $this->assertCount(1, $ketQua);
        $giaoDich = $ketQua[0];
        $this->assertSame('uuid-0001', $giaoDich->id);
        $this->assertSame('FT001', $giaoDich->referenceNumber);
        $this->assertSame(150000, $giaoDich->amountIn);
        $this->assertSame('in', $giaoDich->transferType);
        $this->assertSame(str_repeat('*', 6).'8888', $giaoDich->accountNumberMasked);
        $this->assertSame('api:uuid-0001', $giaoDich->maNgoai());
        $this->assertSame('FT001', $giaoDich->khoaThamChieu());

        $payload = $giaoDich->payloadLuuTru();
        $this->assertArrayNotHasKey('account_number', $payload);
        $this->assertSame(str_repeat('*', 6).'8888', $payload['account_number_masked']);
        $this->assertArrayNotHasKey('accountNumber', $payload);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'userapi.sepay.vn/v2/transactions')
                && $request->hasHeader('Authorization', 'Bearer '.self::TOKEN)
                && (string) $request['transfer_type'] === 'in';
        });
    }

    public function test_401_tra_ngoai_le_khong_lo_token(): void
    {
        Http::fake([
            'https://userapi.sepay.vn/v2/*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        try {
            iterator_to_array((new SepayApiClient)->layGiaoDichVao(now()->subHour(), now()));
            $this->fail('Phải ném SepayApiException khi gặp 401.');
        } catch (SepayApiException $e) {
            $this->assertStringContainsString('401', $e->getMessage());
            $this->assertStringContainsString('SEPAY_API_TOKEN', $e->getMessage());
            $this->assertStringNotContainsString(self::TOKEN, $e->getMessage());
        }

        Http::assertSentCount(1);
    }

    public function test_429_doc_retry_after_roi_thanh_cong(): void
    {
        Http::fake([
            'https://userapi.sepay.vn/v2/*' => Http::sequence()
                ->push('', 429, ['Retry-After' => '0'])
                ->push($this->phanHoi([$this->giaoDich()]), 200),
        ]);

        $ketQua = iterator_to_array((new SepayApiClient)->layGiaoDichVao(now()->subHour(), now()));

        $this->assertCount(1, $ketQua);
        Http::assertSentCount(2);
    }

    public function test_phan_trang_lay_du_cac_trang(): void
    {
        config(['pos.sepay_api_per_page' => 2]);

        Http::fake([
            'https://userapi.sepay.vn/v2/*' => Http::sequence()
                ->push($this->phanHoi([
                    $this->giaoDich(['id' => 'uuid-1', 'reference_number' => 'FT1']),
                    $this->giaoDich(['id' => 'uuid-2', 'reference_number' => 'FT2']),
                ], ['current_page' => 1, 'last_page' => 2, 'per_page' => 2, 'total' => 3]), 200)
                ->push($this->phanHoi([
                    $this->giaoDich(['id' => 'uuid-3', 'reference_number' => 'FT3']),
                ], ['current_page' => 2, 'last_page' => 2, 'per_page' => 2, 'total' => 3]), 200),
        ]);

        $ketQua = iterator_to_array((new SepayApiClient)->layGiaoDichVao(now()->subHour(), now()));

        $this->assertCount(3, $ketQua);
        $this->assertSame(['FT1', 'FT2', 'FT3'], array_map(fn ($tx) => $tx->referenceNumber, $ketQua));
        Http::assertSentCount(2);

        $soTrang = collect(Http::recorded())
            ->map(fn (array $cap): int => (int) $cap[0]['page'])
            ->all();
        $this->assertSame([1, 2], $soTrang);
    }

    public function test_5xx_tra_ngoai_le_sau_khi_thu_lai(): void
    {
        config(['pos.sepay_api_retries' => 0]);

        Http::fake([
            'https://userapi.sepay.vn/v2/*' => Http::response(['error' => 'server'], 500),
        ]);

        try {
            iterator_to_array((new SepayApiClient)->layGiaoDichVao(now()->subHour(), now()));
            $this->fail('Phải ném SepayApiException khi gặp 5xx.');
        } catch (SepayApiException $e) {
            $this->assertStringContainsString('HTTP 500', $e->getMessage());
            $this->assertStringNotContainsString(self::TOKEN, $e->getMessage());
        }
    }

    public function test_loi_ket_noi_khong_lo_token(): void
    {
        config(['pos.sepay_api_retries' => 0]);

        Http::fake(fn () => throw new ConnectionException('Máy chủ giả lập không phản hồi'));

        try {
            iterator_to_array((new SepayApiClient)->layGiaoDichVao(now()->subHour(), now()));
            $this->fail('Phải ném SepayApiException khi không kết nối được.');
        } catch (SepayApiException $e) {
            $this->assertStringContainsString('Không kết nối được SePay API', $e->getMessage());
            $this->assertStringNotContainsString(self::TOKEN, $e->getMessage());
        }
    }

    public function test_v1_khong_duoc_ho_tro(): void
    {
        config(['pos.sepay_api_version' => 'v1']);

        try {
            iterator_to_array((new SepayApiClient)->layGiaoDichVao(now()->subHour(), now()));
            $this->fail('v1 phải bị từ chối rõ ràng.');
        } catch (SepayApiException $e) {
            $this->assertStringContainsString('v1', $e->getMessage());
            $this->assertStringContainsString('không được hỗ trợ', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_thieu_token_khong_goi_mang(): void
    {
        config(['pos.sepay_api_token' => '']);

        $this->expectException(SepayApiException::class);
        $this->expectExceptionMessage('SEPAY_API_TOKEN');

        iterator_to_array((new SepayApiClient)->layGiaoDichVao(now()->subHour(), now()));
    }
}
