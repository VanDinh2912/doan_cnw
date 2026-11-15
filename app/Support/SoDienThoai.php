<?php

namespace App\Support;

class SoDienThoai
{
    // Chuẩn hóa về dạng 0xxxxxxxxx: bỏ khoảng trắng/dấu chấm/gạch/ngoặc, +84 hoặc 84 đầu → 0.
    public static function chuanHoa(?string $sdt): string
    {
        $sdt = preg_replace('/[\s.\-()]/', '', (string) $sdt);

        if (str_starts_with($sdt, '+84')) {
            return '0'.substr($sdt, 3);
        }

        if (str_starts_with($sdt, '84') && strlen($sdt) >= 11) {
            return '0'.substr($sdt, 2);
        }

        return $sdt;
    }
}