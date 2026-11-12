<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AnhSanPhamUrl implements ValidationRule
{
    // Cột products.hinh_anh là VARCHAR(255) — không đổi schema nên URL bị giới hạn theo cột.
    public const MAX_DO_DAI = 255;

    public const THONG_BAO = 'URL ảnh không hợp lệ. Chỉ chấp nhận https, tối đa 255 ký tự, không chứa khoảng trắng hay thông tin đăng nhập.';

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

        if (mb_strlen($value) > self::MAX_DO_DAI
            || preg_match('/\s/u', $value) === 1
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