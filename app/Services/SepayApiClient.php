<?php

namespace App\Services;

use App\Data\SepayTransaction;
use App\Exceptions\SepayApiException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Client CHỈ ĐỌC SePay API v2. Không gọi bất kỳ endpoint ghi nào.
 * Tài liệu: GET /v2/transactions, header Authorization: Bearer <SEPAY_API_TOKEN>.
 */
class SepayApiClient
{
    private const DUONG_DAN_GIAO_DICH = '/transactions';

    private const MUI_GIO_SEPAY = 'Asia/Ho_Chi_Minh';

    private const SO_TRANG_TOI_DA = 1000;

    private ?float $lanGuiCuoi = null;

    /**
     * Lấy toàn bộ giao dịch TIỀN VÀO trong khoảng thời gian, tự phân trang.
     *
     * @return Generator<int, SepayTransaction>
     */
    public function layGiaoDichVao(CarbonInterface $tu, CarbonInterface $den): Generator
    {
        $this->kiemTraCauHinh();

        $tuVn = CarbonImmutable::instance($tu)->setTimezone(self::MUI_GIO_SEPAY)->format('Y-m-d H:i:s');
        $denVn = CarbonImmutable::instance($den)->setTimezone(self::MUI_GIO_SEPAY)->format('Y-m-d H:i:s');

        $perPage = max(1, min(100, (int) config('pos.sepay_api_per_page', 100)));
        $trang = 1;
        $soTrang = 0;

        while (true) {
            $response = $this->goiApi(self::DUONG_DAN_GIAO_DICH, [
                'transaction_date_from' => $tuVn,
                'transaction_date_to' => $denVn,
                'transfer_type' => 'in',
                'page' => $trang,
                'per_page' => $perPage,
            ]);

            $duLieu = $response->json('data');
            if (! is_array($duLieu)) {
                throw SepayApiException::phanHoiSaiCauTruc();
            }

            foreach ($duLieu as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $giaoDich = SepayTransaction::tuApi($item);
                if ($giaoDich->laTienVao()) {
                    yield $giaoDich;
                }
            }

            $soTrang++;
            $trangHienTai = (int) $response->json('meta.pagination.current_page', $trang);
            $trangCuoi = (int) $response->json('meta.pagination.last_page', 0);
            $tongSo = (int) $response->json('meta.pagination.total', 0);

            $conTrang = count($duLieu) >= $perPage && $soTrang < self::SO_TRANG_TOI_DA;
            if ($conTrang && $trangCuoi > 0) {
                $conTrang = $trang + 1 <= $trangCuoi;
            }
            if ($conTrang && $trangCuoi === 0 && $tongSo > 0) {
                $conTrang = $trangHienTai * $perPage < $tongSo;
            }

            if (! $conTrang) {
                break;
            }

            $trang++;
        }
    }

    /**
     * Gọi một request nhỏ để kiểm tra token/kết nối. Không trả hay in dữ liệu giao dịch.
     */
    public function kiemTraKetNoi(): void
    {
        $this->goiApi(self::DUONG_DAN_GIAO_DICH, [
            'page' => 1,
            'per_page' => 1,
        ]);
    }

    private function goiApi(string $duongDan, array $thamSo): Response
    {
        $this->kiemTraCauHinh();

        $goc = rtrim((string) config('pos.sepay_api_base_url', 'https://userapi.sepay.vn/v2'), '/');
        $url = $goc.$duongDan;
        $token = trim((string) config('pos.sepay_api_token'));
        $timeout = max(1, (int) config('pos.sepay_api_timeout', 15));
        $soLanThuLai = max(0, (int) config('pos.sepay_api_retries', 3));
        $lan = 0;

        while (true) {
            $this->choPhepGui();

            try {
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->timeout($timeout)
                    ->connectTimeout($timeout)
                    ->get($url, $thamSo);
            } catch (ConnectionException $e) {
                if ($lan < $soLanThuLai) {
                    $lan++;
                    $this->ngu(min(2 ** $lan, 5));

                    continue;
                }

                throw SepayApiException::khongKetNoi($this->cheBiMat($e->getMessage()));
            }

            if ($response->status() === 401) {
                throw SepayApiException::chuaXacThuc();
            }

            if ($response->status() === 429) {
                if ($lan >= $soLanThuLai) {
                    throw SepayApiException::quaGioiHan();
                }

                $lan++;
                $this->ngu($this->docRetryAfter($response->header('Retry-After')));

                continue;
            }

            if ($response->serverError()) {
                if ($lan >= $soLanThuLai) {
                    throw SepayApiException::loiMayChu($response->status());
                }

                $lan++;
                $this->ngu(min(2 ** ($lan + 1), 5));

                continue;
            }

            if (! $response->successful()) {
                throw SepayApiException::phanHoiKhongHopLe($response->status());
            }

            return $response;
        }
    }

    private function kiemTraCauHinh(): void
    {
        if (trim((string) config('pos.sepay_api_token')) === '') {
            throw SepayApiException::thieuToken();
        }

        $phienBan = strtolower(trim((string) config('pos.sepay_api_version', 'v2')));
        if ($phienBan !== 'v2') {
            throw SepayApiException::phienBanKhongHoTro($phienBan);
        }
    }

    /**
     * SePay giới hạn 3 request/giây; giữ khoảng cách tối thiểu giữa hai lần gọi.
     */
    private function choPhepGui(): void
    {
        $khoangGiay = max(0, (int) config('pos.sepay_api_min_interval_ms', 350)) / 1000;
        if ($khoangGiay <= 0) {
            return;
        }

        if ($this->lanGuiCuoi !== null) {
            $conLai = $khoangGiay - (microtime(true) - $this->lanGuiCuoi);
            if ($conLai > 0) {
                $this->ngu($conLai);
            }
        }

        $this->lanGuiCuoi = microtime(true);
    }

    private function docRetryAfter(?string $header): float
    {
        $giaTri = trim((string) $header);
        if ($giaTri === '') {
            return 1.0;
        }

        if (is_numeric($giaTri)) {
            $giay = (float) $giaTri;
        } else {
            $thoiDiem = strtotime($giaTri);
            $giay = $thoiDiem === false ? 1.0 : max(0, $thoiDiem - time());
        }

        $toiDa = max(0, (int) config('pos.sepay_api_max_retry_after', 30));

        return $toiDa > 0 ? min($giay, (float) $toiDa) : 0.0;
    }

    private function ngu(float $giay): void
    {
        if ($giay <= 0) {
            return;
        }

        usleep((int) round($giay * 1_000_000));
    }

    private function cheBiMat(string $vanBan): string
    {
        $token = trim((string) config('pos.sepay_api_token'));
        if ($token !== '') {
            $vanBan = str_replace($token, '***', $vanBan);
        }

        return preg_replace('/Bearer\s+\S+/i', 'Bearer ***', $vanBan) ?? $vanBan;
    }
}
