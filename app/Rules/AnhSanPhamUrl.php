<?php

namespace App\Rules;

use App\Support\KiemTraSchema;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AnhSanPhamUrl implements ValidationRule
{
    // DB thật doan_cnw đang là VARCHAR(150); migration nới lên 500 đã viết sẵn.
    // Giới hạn hiệu lực = độ dài cột THẬT (đọc qua KiemTraSchema, cache tĩnh);
    // không đọc được thì lùi về 150 — tránh SQLSTATE 1406 "Data too long" gây 500.
    public const MAX_DO_DAI = 150;

    public const THONG_BAO = 'Liên kết ảnh không hợp lệ. Hãy dán liên kết trực tiếp tới tệp ảnh bắt đầu bằng https://, không chứa khoảng trắng hay thông tin đăng nhập.';

    /** Giới hạn độ dài theo cột thật; fallback MAX_DO_DAI khi không đọc được schema. */
    public static function gioiHanDoDai(): int
    {
        try {
            return KiemTraSchema::doDaiCotVarchar('products', 'hinh_anh') ?? self::MAX_DO_DAI;
        } catch (\Throwable) {
            return self::MAX_DO_DAI;
        }
    }

    public static function thongBaoQuaDai(): string
    {
        return 'Liên kết ảnh quá dài (tối đa '.self::gioiHanDoDai().' ký tự). Hãy dùng liên kết trực tiếp tới tệp ảnh, không dùng liên kết tìm kiếm hay quảng cáo.';
    }

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

        if (mb_strlen($value) > self::gioiHanDoDai()) {
            $fail(self::thongBaoQuaDai());
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