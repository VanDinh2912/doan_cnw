<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Yeu cau xin cap/ dat lai PIN (M4). Moi NV toi da 1 dong cho_xu_ly
 * (khoa_cho = user_id, unique); het han sau 7 ngay.
 */
class YeuCauPin extends Model
{
    public const TT_CHO_XU_LY = 'cho_xu_ly';
    public const TT_DA_CAP = 'da_cap';
    public const TT_TU_CHOI = 'tu_choi';
    public const TT_HET_HAN = 'het_han';

    /** Yeu cau cho xu ly qua bao lau thi tu het han. */
    public const SO_NGAY_HET_HAN = 7;

    protected $table = 'yeu_cau_pin';

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'ly_do', 'trang_thai',
        'admin_xu_ly_id', 'xu_ly_luc', 'ly_do_tu_choi', 'khoa_cho',
    ];

    protected function casts(): array
    {
        return [
            'xu_ly_luc' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function nhanVien(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function adminXuLy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_xu_ly_id');
    }

    /** Dan cho_xu_ly qua han -> het_han (khoa_cho ve NULL). Tra ve so dong da doi. */
    public static function donHetHan(): int
    {
        return static::where('trang_thai', self::TT_CHO_XU_LY)
            ->where('created_at', '<', now()->subDays(self::SO_NGAY_HET_HAN))
            ->update([
                'trang_thai' => self::TT_HET_HAN,
                'khoa_cho' => null,
            ]);
    }
}
