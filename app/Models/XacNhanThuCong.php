<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class XacNhanThuCong extends Model
{
    protected $table = 'xac_nhan_thu_cong';

    protected $fillable = [
        'invoice_id', 'so_tien', 'bang_chung', 'nguoi_xac_nhan_id',
        'yeu_cau_duyet_id', 'admin_duyet_id', 'xac_nhan_luc',
        'soat_boi_id', 'soat_luc', 'ghi_chu_soat',
    ];

    protected function casts(): array
    {
        return [
            'so_tien' => 'integer',
            'xac_nhan_luc' => 'datetime',
            'soat_luc' => 'datetime',
        ];
    }

    public function donHang(): BelongsTo
    {
        return $this->belongsTo(DonHang::class, 'invoice_id');
    }

    public function nguoiXacNhan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nguoi_xac_nhan_id');
    }

    public function adminDuyet(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_duyet_id');
    }

    public function yeuCauDuyet(): BelongsTo
    {
        return $this->belongsTo(YeuCauDuyet::class, 'yeu_cau_duyet_id');
    }

    public function soatBoi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'soat_boi_id');
    }
}
