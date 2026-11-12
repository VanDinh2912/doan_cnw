<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AnhSanPhamUrl implements ValidationRule
{
    // Cột thật products.hinh_anh là VARCHAR(150) utf8mb4 (migration khai 255 nhưng chưa áp lên DB thật).
    // Không đổi schema nên giới hạn theo cột thật, tránh SQLSTATE 1406 "Data too long" gây 500.
    public const MAX_DO_DAI = 150;

    public const THONG_BAO = 'Liên kết ảnh không hợp lệ. Hãy dán liên kết trực tiếp tới tệp ảnh bắt đầu bằng https://, không chứa khoảng trắng hay thông tin đăng nhập.';

    public const THONG_BAO_QUA_DAI = 'Liên kết ảnh quá dài (tối đa '.self::MAX_DO_DAI.' ký tự). Hãy dùng liên kết trực tiếp tới tệp ảnh, không dùng liên kết tìm kiếm hay quảng cáo.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        if (! is_string($value)) {
            $fail(self::THONG_BAO);
            return;
        }

        $value = trim($value);
        if ($value === '') {
            return;
        }

        if (mb_strlen($value) > self::MAX_DO_DAI) {
            $fail(self::THONG_BAO_QUA_DAI);
            return;
        }

        if (preg_match('/\s/u', $value) === 1
            || ! str_starts_with($value, 'https://')
            || filter_var($value, FILTER_VALIDATE_URL) === false) {
            $fail(self::THONG_BAO);
            return;
        }

        $parts = parse_url($value);
        if (! is_array($parts) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            $fail(self::THONG_BAO);
        }
    }
}