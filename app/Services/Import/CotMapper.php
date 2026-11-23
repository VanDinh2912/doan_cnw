<?php

namespace App\Services\Import;

use Illuminate\Support\Str;

/**
 * M2 — Khung khop cot Excel theo TEN TIEU DE, dung chung cho moi doi tuong import.
 *
 * Chuan hoa ten cot: chu thuong, bo dau tieng Viet, bo moi ky tu khong phai
 * a-z0-9 (vd "Mã loại!" -> "maloai"). Sau do khop chinh xac voi aliases
 * trong config; cot la duoc bo qua + gan co the kem goi y "y ban la cot X?"
 * (levenshtein nho) — KHONG tu ap dung.
 */
class CotMapper
{
    /** Chuan hoa ten cot: lower + bo dau + chi giu a-z0-9. */
    public static function chuanHoa(string $ten): string
    {
        $ascii = Str::ascii(mb_strtolower(trim($ten)));

        return (string) preg_replace('/[^a-z0-9]/', '', $ascii);
    }

    /**
     * Dung bang khop: chuanHoa(alias) => field.
     *
     * @param  array<string,array{nhan?:string,aliases?:array<int,string>}>  $truong
     * @return array<string,string> chuanHoa(alias) => field
     */
    public static function bangKhacPhuc(array $truong): array
    {
        $bang = [];
        foreach ($truong as $field => $cfg) {
            $names = array_merge([$cfg['nhan'] ?? $field], $cfg['aliases'] ?? []);
            foreach ($names as $name) {
                $key = self::chuanHoa((string) $name);
                if ($key !== '' && ! isset($bang[$key])) {
                    $bang[$key] = $field;
                }
            }
        }

        return $bang;
    }

    /**
     * Khop 1 ten cot tho -> field | 'he_thong:<key>' | 'chi_doc:<key>' | null (la).
     *
     * @param  array<string,array<int,string>>  $bangPhu  chuanHoa(alias) => 'he_thong:soluong' | 'chi_doc:ten_loai'
     */
    public static function khop(string $tenCotTho, array $bangTruong, array $bangPhu): ?string
    {
        $k = self::chuanHoa($tenCotTho);
        if ($k === '') {
            return null;
        }
        if (isset($bangTruong[$k])) {
            return $bangTruong[$k];
        }

        return $bangPhu[$k] ?? null;
    }

    /**
     * Goi y cot gan giong cho cot la — chi goi y, khong tu ap dung.
     * Nguong: khoang cach chinh sua <= 2 va <= 1/3 do dai ten chuan.
     *
     * @param  array<string,string>  $ungVien  chuanHoa => ten hien thi
     */
    public static function goiY(string $tenCotLa, array $ungVien): ?string
    {
        $k = self::chuanHoa($tenCotLa);
        if ($k === '') {
            return null;
        }
        $totNhat = null;
        $totNhatD = PHP_INT_MAX;
        foreach ($ungVien as $chuan => $hienThi) {
            $d = levenshtein($k, $chuan);
            $nguong = max(1, intdiv(strlen($chuan), 3));
            if ($d <= min(2, $nguong) && $d < $totNhatD) {
                $totNhat = $hienThi;
                $totNhatD = $d;
            }
        }

        return $totNhat;
    }
}
