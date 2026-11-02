<?php

namespace App\Services;

use Illuminate\Support\Str;

class VietQrService
{
    public const TEMPLATES = ['compact', 'compact2', 'qr_only', 'print'];

    /**
     * Kiểm tra cấu hình ngân hàng và số tài khoản có hợp lệ để tạo mã VietQR hay không.
     */
    public function enabled(): bool
    {
        $bank = trim((string) config('pos.bank_code'));
        $account = trim((string) config('pos.bank_account'));

        if (! preg_match('/^[A-Za-z0-9]{2,10}$/', $bank)) {
            return false;
        }

        if (! preg_match('/^[0-9]{6,19}$/', $account)) {
            return false;
        }

        return true;
    }

    /**
     * Làm sạch nội dung chuyển khoản: bỏ dấu tiếng Việt, loại ký tự đặc biệt, cắt tối đa 25 ký tự.
     */
    public static function cleanNoiDung(string $text): string
    {
        $clean = Str::ascii($text);
        $clean = preg_replace('/[^A-Za-z0-9 ]/', '', $clean);
        $clean = preg_replace('/\s+/', ' ', trim($clean));

        return mb_substr($clean, 0, 25);
    }

    /**
     * Tạo URL ảnh VietQR động từ cổng img.vietqr.io.
     * Trả về null nếu chưa cấu hình hoặc số tiền <= 0.
     */
    public function url(int $amount, string $noiDung): ?string
    {
        if (! $this->enabled() || $amount <= 0) {
            return null;
        }

        $template = config('pos.qr_template', 'compact2');
        if (! in_array($template, self::TEMPLATES, true)) {
            $template = 'compact2';
        }

        $query = [
            'amount' => $amount,
            'addInfo' => self::cleanNoiDung($noiDung),
        ];

        $name = trim((string) config('pos.bank_name'));
        if ($name !== '') {
            $query['accountName'] = Str::upper(Str::ascii($name));
        }

        return sprintf(
            'https://img.vietqr.io/image/%s-%s-%s.png?%s',
            config('pos.bank_code'),
            config('pos.bank_account'),
            $template,
            http_build_query($query, '', '&', PHP_QUERY_RFC3986)
        );
    }
}
