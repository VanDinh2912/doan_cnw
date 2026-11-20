<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * PIN xác nhận thao tác nhạy cảm (step-up authentication).
 *
 * - PIN 6 số, lưu hash bcrypt; KHÔNG bao giờ lưu/trả PIN dạng rõ.
 * - Bộ đếm sai + khóa dùng CHUNG cho mọi điểm kiểm (nhập PIN, đổi PIN,
 *   đặt PIN tạm) theo user: sai 5 lần -> khóa 15 phút.
 * - Cửa sổ "thuong" 5 phút và mã một lần "nghiemtrong" chỉ nằm trong
 *   session (KHÔNG chứa PIN).
 */
class PinService
{
    public const MUC_THUONG = 'thuong';
    public const MUC_NGHIEMTRONG = 'nghiemtrong';

    private const SO_SAI_TOI_DA = 5;
    private const KHOA_PHUT = 15;
    private const CUA_SO_THUONG_GIAY = 300;   // 5 phút
    private const TOKEN_NT_GIAY = 120;        // mã một lần sống 2 phút

    public static function bat(): bool
    {
        return (bool) config('pos.pin_bat', false);
    }

    /** Cửa sổ 5 phút cho mức THUONG: chỉ khi cờ bật và user có PIN. */
    public static function daXacNhanThuong(User $user): bool
    {
        if (! self::bat() || ! $user->pin_hash) {
            return false;
        }
        $w = session('pin_ok_thuong');
        return is_array($w)
            && ($w['user_id'] ?? null) === $user->id
            && (time() - ($w['luc'] ?? 0)) <= self::CUA_SO_THUONG_GIAY;
    }

    /** Xóa mọi dấu vết xác nhận PIN trong session (logout/đổi MK/đổi PIN). */
    public static function xoaSessionPin(): void
    {
        session()->forget(['pin_ok_thuong', 'pin_token_nt']);
    }

    /** user đang bị khóa PIN? Trả về timestamp hết khóa hoặc null. */
    public static function dangKhoa(User $user): ?int
    {
        if (! $user->pin_khoa_den) {
            return null;
        }
        $den = $user->pin_khoa_den instanceof \DateTimeInterface
            ? $user->pin_khoa_den->getTimestamp()
            : (int) strtotime((string) $user->pin_khoa_den);

        return $den > time() ? $den : null;
    }

    /** PIN đúng format 6 chữ số. */
    public static function dungFormat(string $pin): bool
    {
        return preg_match('/^\d{6}$/', $pin) === 1;
    }

    /**
     * PIN yếu: dãy lặp, dãy tăng/giảm liên tiếp, 000000/123456/654321,
     * hoặc trùng mật khẩu đăng nhập (so bằng Hash::check, không lộ MK).
     */
    public static function pinYeu(string $pin, User $user): bool
    {
        if (! self::dungFormat($pin)) {
            return true;
        }
        $ds = str_split($pin);
        $tang = true;
        $giam = true;
        for ($i = 1; $i < 6; $i++) {
            $tang = $tang && ((int) $ds[$i] === ((int) $ds[$i - 1] + 1) % 10);
            $giam = $giam && ((int) $ds[$i] === ((int) $ds[$i - 1] + 9) % 10);
        }
        if ($tang || $giam || count(array_unique($ds)) === 1) {
            return true;
        }
        // Lặp chu kỳ 2-3 ký tự: 121212, 123123, 112233...
        if ($pin === str_repeat(substr($pin, 0, 2), 3) || $pin === str_repeat(substr($pin, 0, 3), 2)) {
            return true;
        }
        if (Hash::check($pin, (string) $user->password)) {
            return true;
        }

        return false;
    }

    /**
     * Đặt/đổi PIN. Ném InvalidArgumentException với thông báo an toàn
     * (không chứa PIN). Trả về true khi thành công.
     */
    public static function datPin(User $user, string $pin, ?string $matKhau = null): void
    {
        if (! self::dungFormat($pin)) {
            throw new \InvalidArgumentException('PIN phải gồm đúng 6 chữ số.');
        }
        if ($matKhau !== null && ! Hash::check($matKhau, (string) $user->password)) {
            throw new \InvalidArgumentException('Mật khẩu đăng nhập hiện tại không đúng.');
        }
        if (self::pinYeu($pin, $user)) {
            throw new \InvalidArgumentException('PIN quá yếu hoặc trùng mật khẩu. Vui lòng chọn PIN khác.');
        }
        $user->forceFill([
            'pin_hash' => Hash::make($pin),
            'pin_sai_lien_tiep' => 0,
            'pin_khoa_den' => null,
            'pin_doi_luc' => now(),
            'bat_buoc_doi_pin' => false,
        ])->save();
        self::xoaSessionPin();
    }

    /**
     * Kiểm tra PIN cho một hành động.
     * Trả: ['ok'=>bool,'ma'=>'OK'|'SAI'|'KHOA'|'CHUA_CO_PIN'|'PIN_YEU',
     *        'token'=>?string,'khoa_den'=>?int,'con_lai_phut'=>?int]
     * 'hanh_dong' = "METHOD /duong-dan" dùng bind mã một lần.
     */
    public static function kiemTra(User $user, string $pin, string $muc, string $hanhDong, Request $request): array
    {
        if (! $user->pin_hash) {
            return ['ok' => false, 'ma' => 'CHUA_CO_PIN'];
        }
        if ($khoaDen = self::dangKhoa($user)) {
            return [
                'ok' => false, 'ma' => 'KHOA', 'khoa_den' => $khoaDen,
                'con_lai_phut' => max(1, (int) ceil(($khoaDen - time()) / 60)),
            ];
        }
        if (! Hash::check($pin, (string) $user->pin_hash)) {
            return self::ghiNhanSai($user, $request);
        }

        // Đúng PIN: reset bộ đếm, audit, cấp quyền theo mức.
        if ($user->pin_sai_lien_tiep > 0 || $user->pin_khoa_den) {
            $user->forceFill(['pin_sai_lien_tiep' => 0, 'pin_khoa_den' => null])->save();
        }
        self::audit($request, 'pin_dung', $user, 'nhap_pin_dung', 'Nhập PIN đúng');

        if ($muc === self::MUC_NGHIEMTRONG) {
            $token = Str::random(48);
            session(['pin_token_nt' => [
                'hash' => hash('sha256', $token . '|' . $user->id),
                'user_id' => $user->id,
                'hanh_dong' => $hanhDong,
                'luc' => time(),
            ]]);

            return ['ok' => true, 'ma' => 'OK', 'token' => $token];
        }

        session(['pin_ok_thuong' => ['user_id' => $user->id, 'luc' => time()]]);

        return ['ok' => true, 'ma' => 'OK'];
    }

    /** Kiểm tra mã một lần NGHIÊMTRỌNG từ request; đốt mã ngay khi dùng. */
    public static function kiemTraTokenNt(User $user, Request $request): bool
    {
        $token = (string) $request->input('_pin_token', '');
        $save = session('pin_token_nt');
        session()->forget('pin_token_nt'); // đốt dù đúng hay sai
        if ($token === '' || ! is_array($save)) {
            return false;
        }
        $hd = strtoupper($request->method()) . ' ' . '/' . ltrim($request->path(), '/');
        $conHan = (time() - ($save['luc'] ?? 0)) <= self::TOKEN_NT_GIAY;

        return $conHan
            && ($save['user_id'] ?? null) === $user->id
            && ($save['hanh_dong'] ?? '') === $hd
            && hash_equals((string) ($save['hash'] ?? ''), hash('sha256', $token . '|' . $user->id));
    }

    private static function ghiNhanSai(User $user, Request $request): array
    {
        $soSai = ((int) $user->pin_sai_lien_tiep) + 1;
        $capNhat = ['pin_sai_lien_tiep' => $soSai];
        $khoa = false;
        if ($soSai >= self::SO_SAI_TOI_DA) {
            $capNhat['pin_khoa_den'] = now()->addMinutes(self::KHOA_PHUT);
            $capNhat['pin_sai_lien_tiep'] = 0;
            $khoa = true;
        }
        $user->forceFill($capNhat)->save();
        self::audit($request, 'pin_sai', $user, 'nhap_pin_sai', 'Nhập PIN sai');
        if ($khoa) {
            self::audit($request, 'pin_khoa', $user, 'pin_bi_khoa', 'PIN bị khóa do sai nhiều lần');
            try {
                app(ThongBaoService::class)->guiTelegram("Tài khoản #{$user->id} nhập sai PIN nhiều lần, PIN đã bị khóa 15 phút.");
            } catch (\Throwable) {
                // không để Telegram làm hỏng luồng
            }

            return ['ok' => false, 'ma' => 'KHOA', 'khoa_den' => $capNhat['pin_khoa_den'], 'con_lai_phut' => self::KHOA_PHUT];
        }

        return ['ok' => false, 'ma' => 'SAI'];
    }

    private static function audit(Request $request, string $hanhDong, User $user, string $hanhDongCon, string $moTa): void
    {
        try {
            NhatKyService::ghi($request, $hanhDong, 'user', $user->id, $moTa, null, null, null, 'pin');
        } catch (\Throwable) {
            // audit không được làm hỏng luồng PIN
        }
    }
}
