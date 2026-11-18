<?php

namespace App\Console\Commands;

use App\Exceptions\SepayApiException;
use App\Models\DonHang;
use App\Models\PaymentWebhook;
use App\Services\SepayApiClient;
use App\Services\Payment\SepayWebhook;
use App\Services\ThongBaoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SepayDoiSoat extends Command
{
    public const KHOA_LAN_CUOI = 'he_thong:sepay_doi_soat_last_run';

    protected $signature = 'sepay:doi-soat
        {--gio=24 : Số giờ gần nhất cần đối soát}
        {--chi-don-hang : Chỉ ghi giao dịch có mã đơn trong nội dung (bỏ qua giao dịch không liên quan)}
        {--ap-dung : Chỉ dùng khi đã chứng minh được khóa chống trùng chung}';

    protected $description = 'Đối soát giao dịch tiền vào từ SePay API (mặc định chỉ báo cáo)';

    public function __construct(
        private readonly SepayApiClient $client,
        private readonly SepayWebhook $sepayWebhook,
        private readonly ThongBaoService $thongBao,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $soGio = (int) $this->option('gio');
        if ($soGio < 1 || $soGio > 720) {
            $this->error('Tham số --gio phải trong khoảng 1..720 giờ.');

            return self::FAILURE;
        }

        // Tài liệu SePay chưa chứng minh được khóa chung: API v2 dùng id UUID và
        // reference_number, webhook dùng id số và referenceCode. Vì vậy tuyệt đối
        // không tự hoàn tất hóa đơn từ nguồn thứ hai.
        if ($this->option('ap-dung')) {
            $this->error('Không thể chạy --ap-dung: chưa chứng minh được khóa chống trùng chung giữa API v2 và webhook.');
            $this->line('API v2: id UUID / reference_number; webhook: id số / referenceCode. Chỉ chạy chế độ báo cáo.');
            $this->line('Xem docs/BAO_CAO_SEPAY_DOI_SOAT.md để biết điều kiện mở khóa.');

            return self::FAILURE;
        }

        if (trim((string) config('pos.sepay_api_token')) === '') {
            $this->error('Chưa cấu hình SEPAY_API_TOKEN — đối soát chủ động đang tắt.');

            return self::FAILURE;
        }

        if (! \App\Support\KiemTraSchema::coCot('payment_webhooks', 'ma_tham_chieu')) {
            $this->error('Cần áp migration ma_tham_chieu (payment_webhooks) trước khi đối soát.');

            return self::FAILURE;
        }

        $tu = now()->subHours($soGio);
        $den = now();
        $soGiaoDich = 0;
        $soDaCo = 0;
        $soMoi = 0;
        $soBoQua = 0;

        // Giới hạn số tin Telegram chi tiết mỗi lần chạy; phần vượt gộp thành một tin.
        $gioiHanTelegram = max(0, (int) config('pos.sepay_doi_soat_telegram_toi_da', 5));
        $soTelegramChiTiet = 0;
        $soTelegramGop = 0;
        $tongTienGop = 0;

        try {
            foreach ($this->client->layGiaoDichVao($tu, $den) as $giaoDich) {
                $soGiaoDich++;

                $daCo = $this->timBanGhiDaCo($giaoDich->maNgoai(), $giaoDich->khoaThamChieu());
                if ($daCo !== null) {
                    $soDaCo++;

                    continue;
                }

                [$lyDo, $donHang, $maHoaDon] = $this->phanLoai($giaoDich->content, $giaoDich->amountIn);

                // Chế độ chỉ-đơn-hàng (dùng cho lịch chạy): giao dịch không chứa
                // mã đơn nào thì bỏ qua, tránh /doisoat đầy giao dịch không liên quan.
                // Mã đơn có nhưng hóa đơn không tồn tại vẫn được ghi để đối soát tay.
                if ($this->option('chi-don-hang') && $maHoaDon === null) {
                    $soBoQua++;

                    continue;
                }

                PaymentWebhook::create([
                    'nha_cung_cap' => 'sepay_api',
                    'ma_giao_dich_ngoai' => $giaoDich->maNgoai(),
                    'ma_tham_chieu' => $giaoDich->khoaThamChieu(),
                    'invoice_id' => $donHang?->id,
                    'so_tien' => $giaoDich->amountIn,
                    'ket_qua' => 'can_doi_soat',
                    'ly_do' => $lyDo,
                    'ma_hoadon' => $maHoaDon,
                    'noi_dung' => mb_substr($giaoDich->content, 0, 255),
                    'payload' => $giaoDich->payloadLuuTru(),
                    'trang_thai_xu_ly' => 'chua_xu_ly',
                ]);

                $soMoi++;
                $this->line(sprintf(
                    '  + %s | %s đ | %s | %s',
                    $giaoDich->maNgoai(),
                    number_format($giaoDich->amountIn, 0, ',', '.'),
                    $maHoaDon ?? 'không rõ hóa đơn',
                    PaymentWebhook::LY_DO[$lyDo] ?? $lyDo
                ));

                // Chỉ báo Telegram khi tìm được hóa đơn; giao dịch lạ (không có mã,
                // hóa đơn không tồn tại) chỉ ghi /doisoat để tránh nhiễu.
                // Chỉ gửi khi bản ghi vừa được tạo, nên chạy lại không lặp thông báo.
                if ($donHang === null) {
                    continue;
                }

                if ($soTelegramChiTiet < $gioiHanTelegram) {
                    $this->thongBao->canhBaoGiaoDichThieuWebhook(
                        $giaoDich->amountIn,
                        $maHoaDon,
                        $giaoDich->khoaThamChieu() ?? $giaoDich->maNgoai(),
                        PaymentWebhook::LY_DO[$lyDo] ?? $lyDo
                    );
                    $soTelegramChiTiet++;
                } else {
                    $soTelegramGop++;
                    $tongTienGop += $giaoDich->amountIn;
                }
            }
        } catch (SepayApiException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('Lỗi đối soát: '.$e::class);

            return self::FAILURE;
        }

        if ($soTelegramGop > 0) {
            $this->thongBao->canhBaoGopGiaoDichThieuWebhook($soTelegramGop, $tongTienGop);
        }

        Cache::put(self::KHOA_LAN_CUOI, now()->getTimestamp(), now()->addDay());

        $this->info(sprintf(
            'Đối soát SePay API (%d giờ, chế độ báo cáo): %d giao dịch tiền vào, %d đã có, %d thiếu mới.',
            $soGio,
            $soGiaoDich,
            $soDaCo,
            $soMoi
        ));
        if ($soBoQua > 0) {
            $this->line(sprintf('  (%d giao dịch không có mã đơn đã bỏ qua do --chi-don-hang)', $soBoQua));
        }

        return self::SUCCESS;
    }

    private function timBanGhiDaCo(string $maNgoai, ?string $maThamChieu): ?PaymentWebhook
    {
        $banGhi = PaymentWebhook::query()
            ->where('ma_giao_dich_ngoai', $maNgoai)
            ->when($maThamChieu !== null, fn ($q) => $q->orWhere('ma_tham_chieu', $maThamChieu))
            ->first();

        if ($banGhi !== null || $maThamChieu === null) {
            return $banGhi;
        }

        // Bản ghi webhook cũ chưa có cột ma_tham_chieu: đọc dự phòng từ payload.
        return PaymentWebhook::query()
            ->whereNotNull('payload')
            ->where('created_at', '>=', now()->subDays(30))
            ->get()
            ->first(fn (PaymentWebhook $webhook): bool => $webhook->khoaThamChieu() === $maThamChieu);
    }

    /**
     * @return array{0: string, 1: ?DonHang, 2: ?string}
     */
    private function phanLoai(string $noiDung, int $soTien): array
    {
        $maHoaDon = $this->sepayWebhook->timMaHoaDon($noiDung);
        if ($maHoaDon === null) {
            return ['khong_tim_thay_ma_hoadon', null, null];
        }

        $donHang = DonHang::where('ma_hoadon', $maHoaDon)->first();
        if ($donHang === null) {
            return ['hoadon_khong_ton_tai', null, $maHoaDon];
        }

        if ($donHang->trang_thai === 'da_huy') {
            return ['don_da_huy', $donHang, $maHoaDon];
        }

        if ($donHang->trang_thai === 'da_thanh_toan') {
            return ['hoadon_da_thanh_toan', $donHang, $maHoaDon];
        }

        if ($soTien < (int) $donHang->tong_thanh_toan) {
            return ['thieu_tien', $donHang, $maHoaDon];
        }

        return ['api_doi_soat', $donHang, $maHoaDon];
    }
}
