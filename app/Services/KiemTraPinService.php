<?php

namespace App\Services;

use App\Models\NhatKyHoatDong;
use App\Models\User;
use App\Support\KiemTraSchema;
use Illuminate\Support\Facades\Schema;

/**
 * Kiểm tra trạng thái sẵn sàng của PIN + mã duyệt hai-người trên DB hiện tại.
 * Trả về MẢNG CÓ CẤU TRÚC — dùng chung cho:
 *  - artisan pin:trang-thai (JSON, gọi từ cong-tac-pin-prod.ps1)
 *  - artisan pin:tu-kiem-tra (self-test trên sqlite tạm)
 *  - test PHPUnit
 *
 * KHÔNG in PIN/mã/mật khẩu/hash/PII: email nhân viên luôn được che.
 */
class KiemTraPinService
{
    /** Che email: 2 ký tự đầu + *** + domain. */
    public static function cheEmail(?string $email): string
    {
        $email = (string) $email;
        if (! str_contains($email, '@')) {
            return '***';
        }
        [$dau, $domain] = explode('@', $email, 2);

        return mb_substr($dau, 0, 2).'***@'.$domain;
    }

    /**
     * @return array{
     *   db:string, pin_bat:bool, ma_duyet_bat:bool,
     *   cot_pin:int, bang_yc:bool, moc_lo:?string,
     *   admin_chua_doi:list<int>, admin_thieu_pin:list<int>,
     *   nv_thieu_pin:list<string>
     * }
     */
    public function kiemTra(): array
    {
        $db = (string) config('database.connections.'.config('database.default').'.database');

        $cotPin = ['pin_hash', 'pin_sai_lien_tiep', 'pin_khoa_den', 'pin_doi_luc', 'bat_buoc_doi_pin'];
        $soCot = count(array_filter($cotPin, fn ($c) => Schema::hasColumn('users', $c)));

        $kq = [
            'db' => $db,
            'pin_bat' => (bool) config('pos.pin_bat'),
            'ma_duyet_bat' => (bool) config('pos.ma_duyet_bat', true),
            'cot_pin' => $soCot,
            'bang_yc' => KiemTraSchema::coBang('yeu_cau_duyet'),
            'moc_lo' => null,
            'admin_chua_doi' => [],
            'admin_thieu_pin' => [],
            'nv_thieu_pin' => [],
        ];

        // Mốc lộ PIN CỐ ĐỊNH — không trôi khi có sự kiện console mới:
        //  1. config pos.moc_lo_pin_luc (env POS_MOC_LO_PIN_LUC) nếu được đặt;
        //  2. activity_logs id=463 (pin_dat console lúc sự cố);
        //  3. null -> caller quyết định (check -Che bat sẽ bị chặn).
        // KHÔNG fallback "dòng pin_dat console mới nhất" — nếu không, mỗi lần
        // dat-pin-prod.ps1 chạy mốc sẽ dịch lên và check admin luôn FAIL.
        $mocCauHinh = config('pos.moc_lo_pin_luc');
        if (is_string($mocCauHinh) && trim($mocCauHinh) !== '') {
            $kq['moc_lo'] = trim($mocCauHinh);
        } else {
            try {
                $kq['moc_lo'] = NhatKyHoatDong::whereKey(463)->first()?->created_at?->toDateTimeString();
            } catch (\Throwable) {
                $kq['moc_lo'] = null;
            }
        }

        // Admin: phải CÓ pin_hash và pin_doi_luc LỚN HƠN mốc lộ
        // (bằng nhau = chưa đổi sau sự cố = chưa đạt).
        $admins = User::where('role', 'admin')->get(['id', 'pin_hash', 'pin_doi_luc']);
        foreach ($admins as $a) {
            if (! $a->pin_hash) {
                $kq['admin_thieu_pin'][] = (int) $a->id;
                continue;
            }
            if ($kq['moc_lo'] !== null) {
                $moc = \Illuminate\Support\Carbon::parse($kq['moc_lo']);
                if (! $a->pin_doi_luc || $a->pin_doi_luc->lte($moc)) {
                    $kq['admin_chua_doi'][] = (int) $a->id;
                }
            }
        }

        // Nhân viên thungan/thukho đang hoạt động (khoa_luc null hoặc đã qua)
        // phải có pin_hash — thiếu thì không qua được mức M1.
        $nvThieu = User::whereIn('role', ['thungan', 'thukho'])
            ->where(function ($q) {
                $q->whereNull('khoa_luc')->orWhere('khoa_luc', '<=', now());
            })
            ->whereNull('pin_hash')
            ->get(['id', 'email']);
        $kq['nv_thieu_pin'] = $nvThieu->map(fn ($u) => self::cheEmail($u->email))->all();

        return $kq;
    }
}
