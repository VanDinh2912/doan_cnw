<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YeuCauDuyet extends Model
{
    public const TT_CHO_CAP = 'cho_cap';
    public const TT_DA_CAP = 'da_cap';
    public const TT_DA_DUNG = 'da_dung';
    public const TT_HET_HAN = 'het_han';
    public const TT_TU_CHOI = 'tu_choi';

    public $timestamps = false;

    protected $fillable = [
        'nhan_vien_id', 'ten_route', 'method', 'doi_tuong_loai', 'doi_tuong_id',
        'mo_ta_thao_tac', 'tham_so_duyet', 'trang_thai', 'ma_hash', 'khoa_trung',
        'admin_cap_id', 'cap_luc', 'het_han_luc', 'dung_luc', 'tu_choi_luc',
        'ly_do_tu_choi', 'created_at',
    ];

    protected $casts = [
        'tham_so_duyet' => 'array',
        'cap_luc' => 'datetime',
        'het_han_luc' => 'datetime',
        'dung_luc' => 'datetime',
        'tu_choi_luc' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function nhanVien()
    {
        return $this->belongsTo(User::class, 'nhan_vien_id');
    }

    public function adminCap()
    {
        return $this->belongsTo(User::class, 'admin_cap_id');
    }
}
