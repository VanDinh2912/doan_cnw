<?php

namespace App\Rules;

use App\Support\SoDienThoai;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Quy tắc số điện thoại di động Việt Nam dùng chung cho mọi điểm nhập khách hàng.
 * Server là nguồn đúng: luôn chuẩn hóa trước rồi mới validate.
 */
class SoDienThoaiHopLe implements ValidationRule
{
    public const REGEX = '/^0(3|5|7|8|9)\d{8}$/';

    public const THONG_BAO = 'Số điện thoại không hợp lệ. Nhập 10 chữ số, bắt đầu bằng 0.';

    /**
     * Chuẩn hóa dùng chung với App\Support\SoDienThoai (một nguồn sự thật).
     * Giữ nguyên giá trị không phải chuỗi để rule `string` tự từ chối.
     */
    public static function chuanHoa(mixed $giaTri): mixed
    {
        if (! is_string($giaTri)) {
            return $giaTri;
        }

        return SoDienThoai::chuanHoa($giaTri);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match(self::REGEX, $value)) {
            $fail(self::THONG_BAO);
        }
    }
}
