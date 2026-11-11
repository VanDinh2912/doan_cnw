<?php

namespace App\Data;

use Carbon\CarbonImmutable;

/**
 * DTO chuẩn hóa giao dịch tiền vào từ SePay API v2.
 * Không giữ số tài khoản thô: chỉ lưu bản đã che để tránh lộ dữ liệu ra log/DB.
 */
final readonly class SepayTransaction
{
    public function __construct(
        public string $id,
        public string $referenceNumber,
        public string $code,
        public string $transactionDate,
        public string $transferType,
        public int $amountIn,
        public int $amountOut,
        public string $content,
        public string $bankBrandName,
        public string $accountNumberMasked,
    ) {}

    public static function tuApi(array $duLieu): self
    {
        $id = trim((string) ($duLieu['id'] ?? ''));
        $reference = trim((string) ($duLieu['reference_number'] ?? $duLieu['referenceCode'] ?? ''));
        $code = trim((string) ($duLieu['code'] ?? ''));
        $ngay = trim((string) ($duLieu['transaction_date'] ?? $duLieu['transactionDate'] ?? ''));
        if ($ngay === '') {
            $ngay = CarbonImmutable::now('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s');
        }

        return new self(
            id: $id,
            referenceNumber: $reference,
            code: $code,
            transactionDate: $ngay,
            transferType: strtolower(trim((string) ($duLieu['transfer_type'] ?? $duLieu['transferType'] ?? 'in'))),
            amountIn: (int) ($duLieu['amount_in'] ?? $duLieu['transferAmount'] ?? 0),
            amountOut: (int) ($duLieu['amount_out'] ?? 0),
            content: (string) ($duLieu['transaction_content'] ?? $duLieu['content'] ?? ''),
            bankBrandName: (string) ($duLieu['bank_brand_name'] ?? $duLieu['gateway'] ?? ''),
            accountNumberMasked: self::cheSoTaiKhoan((string) ($duLieu['account_number'] ?? $duLieu['accountNumber'] ?? '')),
        );
    }

    public function laTienVao(): bool
    {
        return $this->transferType === 'in' && $this->amountIn > 0;
    }

    public function khoaThamChieu(): ?string
    {
        return $this->referenceNumber !== '' ? $this->referenceNumber : null;
    }

    /**
     * Khóa duy nhất cho bản ghi do API tạo. Có tiền tố `api:` để không bao giờ
     * trùng với id số của webhook SePay.
     */
    public function maNgoai(): string
    {
        if ($this->id !== '') {
            return 'api:'.$this->id;
        }

        if ($this->referenceNumber !== '') {
            return 'api:ref:'.$this->referenceNumber;
        }

        return 'api:khong-dinh-danh-'.substr(sha1($this->transactionDate.'|'.$this->amountIn.'|'.$this->content), 0, 16);
    }

    /**
     * Payload lưu vào payment_webhooks: không có số tài khoản thô, không có toàn bộ
     * phản hồi gốc của SePay.
     */
    public function payloadLuuTru(): array
    {
        return [
            'nguon' => 'api_doi_soat',
            'api_id' => $this->id,
            'reference_number' => $this->referenceNumber,
            'code' => $this->code,
            'transaction_date' => $this->transactionDate,
            'transfer_type' => $this->transferType,
            'amount_in' => $this->amountIn,
            'amount_out' => $this->amountOut,
            'transaction_content' => mb_substr($this->content, 0, 255),
            'bank_brand_name' => $this->bankBrandName,
            'account_number_masked' => $this->accountNumberMasked,
        ];
    }

    public static function cheSoTaiKhoan(string $soTaiKhoan): string
    {
        $so = trim($soTaiKhoan);
        if ($so === '') {
            return '';
        }

        $doDai = mb_strlen($so);
        if ($doDai <= 4) {
            return str_repeat('*', $doDai);
        }

        return str_repeat('*', $doDai - 4).mb_substr($so, -4);
    }
}
