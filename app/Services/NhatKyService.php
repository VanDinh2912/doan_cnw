<?php

namespace App\Services;

use App\Models\NhatKyHoatDong;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class NhatKyService
{
    public static function ghi(
        Request $request,
        string $hanhDong,
        string $doiTuong,
        ?int $doiTuongId,
        string $moTa,
        ?array $truoc = null,
        ?array $sau = null,
        ?string $nguon = null,
        ?string $nhomId = null
    ): void {
        $data = [
            'user_id' => $request->user()?->id,
            'hanh_dong' => $hanhDong,
            'doi_tuong' => $doiTuong,
            'doi_tuong_id' => $doiTuongId,
            'mo_ta' => $moTa,
            'ip' => $request->ip(),
        ];
        $extra = [
            'doi_tuong_loai' => $doiTuong,
            'truoc' => self::anToan($truoc),
            'sau' => self::anToan($sau),
            'user_agent' => substr((string) $request->userAgent(), 0, 500) ?: null,
            'nguon' => $nguon ?: ($request->is('api/*') ? 'webhook' : 'web'),
            'nhom_id' => $nhomId,
        ];
        foreach ($extra as $column => $value) {
            if (Schema::hasColumn('activity_logs', $column)) {
                $data[$column] = $value;
            }
        }
        NhatKyHoatDong::create($data);
    }

    public static function anToan(?array $data): ?array
    {
        if ($data === null) return null;
        $sensitive = ['password', 'remember_token', 'token', 'api_key', 'ma_tra_cuu', 'so_tai_khoan'];
        $result = [];
        foreach ($data as $key => $value) {
            $lower = strtolower((string) $key);
            if (in_array($lower, $sensitive, true) || str_contains($lower, 'mat_khau')) {
                $result[$key] = '[đã ẩn]';
            } elseif (in_array($lower, ['so_dienthoai', 'email', 'dia_chi'], true)) {
                $result[$key] = $value === null || $value === '' ? null : '[đã thay đổi]';
            } elseif (is_scalar($value) || $value === null) {
                $result[$key] = $value;
            }
        }
        return $result;
    }
}
