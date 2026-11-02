<?php

namespace App\Services\Payment;

use Illuminate\Http\Request;

class SepayWebhook
{
    /**
     * Xác thực Webhook từ SePay thông qua Header Authorization: Apikey KHOA_CUA_BAN
     * Sử dụng hash_equals để chống tấn công timing attack.
     */
    public function xacThuc(Request $request): bool
    {
        $expectedKey = (string) config('pos.sepay_webhook_api_key');

        if ($expectedKey === '') {
            return false;
        }

        $authHeader = trim((string) ($request->header('Authorization') ?: $request->server('HTTP_AUTHORIZATION')));

        // Định dạng SePay: "Apikey YOUR_API_KEY"
        if (preg_match('/^Apikey\s+(.+)$/i', $authHeader, $matches)) {
            $providedKey = trim($matches[1]);

            return hash_equals($expectedKey, $providedKey);
        }

        // Hỗ trợ trường hợp gửi trực tiếp key
        if ($authHeader !== '' && hash_equals($expectedKey, $authHeader)) {
            return true;
        }

        return false;
    }

    /**
     * Kiểm tra tính hợp lệ của các trường bắt buộc trong payload SePay.
     */
    public function validate(array $payload): bool
    {
        return isset($payload['id'], $payload['transferAmount'], $payload['transferType'], $payload['content']);
    }

    /**
     * Chuyển đổi payload của SePay thành mảng giao dịch chuẩn hóa.
     *
     * @return array [ma_giaodich, so_tien, noi_dung, loai, thoi_gian, gateway, reference_code]
     */
    public function chuanHoa(array $payload): array
    {
        return [
            'ma_giaodich' => (string) ($payload['id'] ?? $payload['referenceCode'] ?? ''),
            'id_sepay' => $payload['id'] ?? null,
            'so_tien' => (int) ($payload['transferAmount'] ?? 0),
            'noi_dung' => (string) ($payload['content'] ?? ''),
            'loai' => strtolower((string) ($payload['transferType'] ?? 'in')),
            'thoi_gian' => (string) ($payload['transactionDate'] ?? now()->toDateTimeString()),
            'gateway' => (string) ($payload['gateway'] ?? ''),
            'reference_code' => (string) ($payload['referenceCode'] ?? ''),
        ];
    }

    /**
     * Trích xuất mã hóa đơn từ nội dung chuyển khoản theo quy tắc:
     * Bỏ hết ký tự không phải chữ và số, tìm mẫu HD + 8 chữ số (ngày) + 4 chữ số (thứ tự).
     * Ví dụ: "HD202611020001", "HD20261102-0001", "SEPAY HD 20261102 0001 CHUYEN TIEN"
     * -> Trả về mã chuẩn: "HD20261102-0001"
     */
    public function timMaHoaDon(string $noiDung): ?string
    {
        $clean = preg_replace('/[^A-Za-z0-9]/', '', $noiDung);

        if (preg_match('/HD([0-9]{8})([0-9]{4})/i', $clean, $matches)) {
            return strtoupper('HD'.$matches[1].'-'.$matches[2]);
        }

        return null;
    }
}
