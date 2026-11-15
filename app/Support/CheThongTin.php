<?php

namespace App\Support;

class CheThongTin
{
    // Che SĐT cho log/nhật ký/Telegram: 3 đầu + **** + 3 cuối (0901234567 → 090****567).
    public static function soDienThoai(?string $sdt): string
    {
        $sdt = (string) $sdt;
        $doDai = strlen($sdt);

        if ($doDai < 7) {
            return str_repeat('*', $doDai);
        }

        return substr($sdt, 0, 3).'****'.substr($sdt, -3);
    }
}