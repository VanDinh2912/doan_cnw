<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChiTietDonNhap extends Model
{
    use HasFactory;

    protected $table = 'purchase_order_items';

    protected $fillable = ['purchase_order_id', 'product_id', 'soluong_dat', 'soluong_nhan', 'don_gia_nhap', 'thanh_tien'];

    protected function casts(): array
    {
        return ['don_gia_nhap' => 'decimal:2', 'thanh_tien' => 'decimal:2'];
    }

    public function donNhap(): BelongsTo
    {
        return $this->belongsTo(DonNhap::class, 'purchase_order_id');
    }

    public function sanPham(): BelongsTo
    {
        return $this->belongsTo(SanPham::class, 'product_id');
    }
}
