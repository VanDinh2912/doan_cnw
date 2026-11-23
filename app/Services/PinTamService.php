<?php

namespace App\Services;

use App\Models\NhatKyHoatDong;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Cap PIN tam cho nhan vien — dung chung giua:
 *  - admin chu dong dat lai (/taikhoan/dat-lai-pin/{user})
 *  - admin duyet yeu cau xin cap PIN (/yeu-cau-duyet, M4)
 *
 * PIN tam 6 so ngau nhien, hash vao users.pin_hash, ban ma hoa trong cache
 * 30' de CHINH NV do xem 1 lan tai /pin/tam-cua-toi. Admin khong bao gio thay PIN.
 */
class PinTamService
{
    /** Khoa cache giu PIN tam cho tung user (ma hoa bang APP_KEY, TTL 30'). */
    public static function khoaPinTam(int $userId): string
    {
        return 'pin_tam_user_'.$userId;
    }

    /**
     * @param  string  $nguon  'web' (admin chu dong) | 'duyet' (duyet yeu cau)
     */
    public static function capTamCho(User $user, User $admin, string $lyDo, ?Request $request = null, string $nguon = 'web'): void
    {
        // PIN tam 6 so ngau nhien, tranh day yeu hien nhien
        do {
            $pinTam = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (PinService::pinYeu($pinTam, $user));

        $user->forceFill([
            'pin_hash' => Hash::make($pinTam),
            'pin_sai_lien_tiep' => 0,
            'pin_khoa_den' => null,
            'pin_doi_luc' => now(),
            'bat_buoc_doi_pin' => false, // PIN tam dung luon lam PIN thuong, khong ep doi
        ])->save();
        PinService::xoaSessionPin();

        Cache::put(self::khoaPinTam($user->id), encrypt($pinTam), now()->addMinutes(30));

        $moTa = "Admin #{$admin->id} dat lai PIN cho user #{$user->id}"
            .($nguon === 'duyet' ? ' (duyet yeu cau)' : '')
            ." — {$lyDo}";

        if ($request) {
            NhatKyService::ghi($request, 'pin_dat_lai', 'user', $user->id, $moTa, null, null, $nguon, 'pin');
        } else {
            NhatKyHoatDong::create([
                'user_id' => $admin->id,
                'hanh_dong' => 'pin_dat_lai',
                'doi_tuong' => 'user',
                'doi_tuong_id' => $user->id,
                'mo_ta' => $moTa,
                'nguon' => $nguon,
            ]);
        }
    }
}
