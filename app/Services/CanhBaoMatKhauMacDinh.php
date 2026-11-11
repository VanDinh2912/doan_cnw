<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class CanhBaoMatKhauMacDinh
{
    public const MAT_KHAU_SEEDER = '123456';

    private const KHOA_CACHE = 'canh_bao:mat_khau_mac_dinh';

    private const TTL_GIAY = 300;

    /**
     * So hash thay vì so chuỗi để vẫn đúng nếu seeder đổi cách băm.
     * Kết quả được cache vì Hash::check (bcrypt) khá đắt cho mỗi lần tải trang.
     *
     * @return list<string>
     */
    public function danhSachEmail(): array
    {
        return Cache::remember(self::KHOA_CACHE, self::TTL_GIAY, function (): array {
            return User::query()
                ->whereIn('role', ['admin', 'thungan', 'thukho'])
                ->get(['id', 'email', 'password'])
                ->filter(fn (User $user): bool => Hash::check(self::MAT_KHAU_SEEDER, (string) $user->password))
                ->pluck('email')
                ->values()
                ->all();
        });
    }

    public function conHieuLuc(): bool
    {
        return $this->danhSachEmail() !== [];
    }

    public static function quenCache(): void
    {
        Cache::forget(self::KHOA_CACHE);
    }
}