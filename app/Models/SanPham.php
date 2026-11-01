<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SanPham extends Model
{
    use HasFactory;

    protected $table = 'products';

    protected $fillable = [
        'category_id', 'sku', 'barcode', 'ten_sanpham', 'don_vi',
        'gia_nhap', 'gia_ban', 'soluong', 'soluong_toithieu',
        'hinh_anh', 'trang_thai',
    ];

    protected function casts(): array
    {
        return [
            'gia_nhap' => 'decimal:0',
            'gia_ban' => 'decimal:0',
            'trang_thai' => 'boolean',
        ];
    }

    public function loaiSanPham(): BelongsTo
    {
        return $this->belongsTo(LoaiSanPham::class, 'category_id');
    }

    public function chiTietDonHangs(): HasMany
    {
        return $this->hasMany(ChiTietDonHang::class, 'product_id');
    }

    public function bienDongKhos(): HasMany
    {
        return $this->hasMany(BienDongKho::class, 'product_id');
    }
}
