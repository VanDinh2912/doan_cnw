<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DonNhap extends Model
{
    use HasFactory;

    protected $table = 'purchase_orders';

    protected $fillable = [
        'ma_don', 'supplier_id', 'user_id', 'nguoi_nhan_id', 'trang_thai',
        'tong_tien', 'ngay_dukien', 'ngay_nhan', 'ghi_chu',
    ];

    protected function casts(): array
    {
        return ['tong_tien' => 'decimal:2', 'ngay_dukien' => 'date', 'ngay_nhan' => 'datetime'];
    }

    public function nhaCungCap(): BelongsTo
    {
        return $this->belongsTo(NhaCungCap::class, 'supplier_id');
    }

    public function chiTiets(): HasMany
    {
        return $this->hasMany(ChiTietDonNhap::class, 'purchase_order_id');
    }

    public function nguoiTao(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
