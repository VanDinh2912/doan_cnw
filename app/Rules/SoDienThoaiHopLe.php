<?php

namespace App\Rules;

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
     * Trim, bỏ khoảng trắng/dấu chấm/gạch ngang/ngoặc, đổi đầu +84 hoặc 84 thành 0.
     * Giữ nguyên giá trị không phải chuỗi để rule `string` tự từ chối.
     */
    public static function chuanHoa(mixed $giaTri): mixed
    {
        if (! is_string($giaTri)) {
            return $giaTri;
        }

        $so = preg_replace('/[\s.\-()]/', '', trim($giaTri));

        if (str_starts_with($so, '+84')) {
            $so = '0'.substr($so, 3);
        } elseif (str_starts_with($so, '84') && strlen($so) > 10) {
            $so = '0'.substr($so, 2);
        }

        return $so;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match(self::REGEX, $value)) {
            $fail(self::THONG_BAO);
        }
    }
}
