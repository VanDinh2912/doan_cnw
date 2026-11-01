<?php

namespace App\Services;

use App\Models\NhatKyHoatDong;
use Illuminate\Http\Request;

class NhatKyService
{
    public static function ghi(
        Request $request,
        string $hanhDong,
        string $doiTuong,
        ?int $doiTuongId,
        string $moTa
    ): void {
        NhatKyHoatDong::create([
            'user_id' => $request->user()?->id,
            'hanh_dong' => $hanhDong,
            'doi_tuong' => $doiTuong,
            'doi_tuong_id' => $doiTuongId,
            'mo_ta' => $moTa,
            'ip' => $request->ip(),
        ]);
    }
}
