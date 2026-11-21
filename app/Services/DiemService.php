<?php

namespace App\Services;

use App\Models\DiemBienDong;
use App\Support\KiemTraSchema;
use Illuminate\Database\QueryException;

/**
 * Ghi sổ biến động điểm tích lũy. Mọi thay đổi customers.diem_tichluy
 * phải gọi hàm này TRONG CÙNG TRANSACTION, SAU khi số dư đã chốt,
 * truyền so_du_sau = số dư thực tế sau biến động.
 *
 * Guard "code đi trước migration": bảng diem_bien_dong chưa tồn tại thì
 * bỏ qua ghi sổ, không ảnh hưởng nghiệp vụ.
 */
class DiemService
{
    public const LOAI_TICH = 'tich_diem';
    public const LOAI_DUNG = 'dung_diem';
    public const LOAI_HOAN_DIEM_HUY = 'hoan_diem_huy';
    public const LOAI_THU_HOI_HUY = 'thu_hoi_huy';
    public const LOAI_THU_HOI_TRA = 'thu_hoi_tra_hang';
    public const LOAI_HOAN_DIEM_TRA = 'hoan_diem_tra_hang';
    public const LOAI_DIEU_CHINH = 'dieu_chinh';
    public const LOAI_SO_DAU_KY = 'so_du_dau_ky';

    public static function sanSang(): bool
    {
        return KiemTraSchema::coBang('diem_bien_dong');
    }

    /** Nhãn tiếng Việt cho loại biến động — dùng chung admin + trang khách. */
    public static function nhanLoai(string $loai): string
    {
        return match ($loai) {
            self::LOAI_TICH => 'Tích điểm',
            self::LOAI_DUNG => 'Dùng điểm',
            self::LOAI_HOAN_DIEM_HUY => 'Hoàn điểm (hủy đơn chờ)',
            self::LOAI_THU_HOI_HUY => 'Thu hồi điểm (hủy đơn)',
            self::LOAI_THU_HOI_TRA => 'Thu hồi điểm (trả hàng)',
            self::LOAI_HOAN_DIEM_TRA => 'Hoàn điểm (trả hàng)',
            self::LOAI_DIEU_CHINH => 'Điều chỉnh bởi cửa hàng',
            self::LOAI_SO_DAU_KY => 'Số dư đầu kỳ',
            default => $loai,
        };
    }

    /**
     * @param  array{invoice_id?:?int, return_id?:?int, user_id?:?int, ly_do?:?string, su_kien?:?string}  $them
     */
    public static function ghi(
        ?int $customerId,
        string $loai,
        int $diem,
        int $soDuSau,
        array $them = [],
    ): ?DiemBienDong {
        if (! self::sanSang() || $customerId === null) {
            return null;
        }

        $duLieu = array_merge([
            'customer_id' => $customerId,
            'loai' => $loai,
            'diem' => $diem,
            'so_du_sau' => $soDuSau,
            'created_at' => now(),
        ], array_intersect_key($them, array_flip([
            'invoice_id', 'return_id', 'user_id', 'ly_do', 'su_kien',
        ])));

        // su_kien là khóa duy nhất: sự kiện lặp (webhook, bấm đúp) không
        // tạo dòng thứ hai — trả về dòng đã có.
        $suKien = $duLieu['su_kien'] ?? null;
        if ($suKien !== null && ($daCo = DiemBienDong::where('su_kien', $suKien)->first())) {
            return $daCo;
        }

        try {
            return DiemBienDong::create($duLieu);
        } catch (QueryException $e) {
            // Race: request khác vừa ghi cùng su_kien → coi như đã ghi.
            if ($suKien !== null && str_contains($e->getMessage(), 'su_kien')) {
                return DiemBienDong::where('su_kien', $suKien)->first();
            }
            throw $e;
        }
    }
}
