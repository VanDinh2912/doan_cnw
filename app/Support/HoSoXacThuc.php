<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

/**
 * Cửa sổ xác thực lại hồ sơ khách hàng.
 * Session chỉ lưu user_id + thời điểm — KHÔNG lưu mật khẩu/SĐT/email.
 */
class HoSoXacThuc
{
    public const KHOA = 'ho_so_khach_xac_thuc';

    public static function cuaSoPhut(): int
    {
        $phut = (int) config('pos.ho_so_cua_so_phut', 10);

        return max(2, min(30, $phut));
    }

    public static function conHieuLuc(Request $request): bool
    {
        $user = $request->user();
        if ($user === null) {
            return false;
        }

        $duLieu = $request->session()->get(self::KHOA);
        if (! is_array($duLieu)) {
            return false;
        }

        // Phải gắn đúng user đang đăng nhập — user A không dùng được cửa sổ của user B.
        if (($duLieu['user_id'] ?? null) !== $user->id) {
            return false;
        }

        $luc = (int) ($duLieu['luc'] ?? 0);
        if ($luc <= 0 || (time() - $luc) > self::cuaSoPhut() * 60) {
            self::xoa($request);

            return false;
        }

        return true;
    }

    public static function bat(Request $request): void
    {
        $request->session()->put(self::KHOA, [
            'user_id' => $request->user()->id,
            'luc' => time(),
        ]);
    }

    public static function xoa(Request $request): void
    {
        Session::forget(self::KHOA);
    }
}
