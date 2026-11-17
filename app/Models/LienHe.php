<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LienHe extends Model
{
    public const LOAI = ['lien_he', 'gop_y', 'khieu_nai', 'ung_tuyen'];

    public const TRANG_THAI = ['moi', 'dang_xu_ly', 'da_xu_ly'];

    protected $table = 'lien_he';

    protected $fillable = [
        'ho_ten', 'so_dien_thoai', 'email', 'loai', 'vi_tri',
        'chu_de', 'noi_dung', 'trang_thai', 'ghi_chu_noibo', 'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function nhanLoai(): string
    {
        return match ($this->loai) {
            'gop_y' => 'Góp ý',
            'khieu_nai' => 'Khiếu nại',
            'ung_tuyen' => 'Ứng tuyển',
            default => 'Liên hệ',
        };
    }

    public function nhanTrangThai(): string
    {
        return match ($this->trang_thai) {
            'dang_xu_ly' => 'Đang xử lý',
            'da_xu_ly' => 'Đã xử lý',
            default => 'Mới',
        };
    }

    public function nhanViTri(): ?string
    {
        if (! $this->vi_tri) {
            return null;
        }

        return config('trang_tinh.tuyen_dung.vi_tri.'.$this->vi_tri, $this->vi_tri);
    }
}
