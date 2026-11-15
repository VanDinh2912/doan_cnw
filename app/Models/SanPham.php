<?php

namespace App\Models;

use App\Models\Concerns\GhiNhatKy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class SanPham extends Model
{
    use GhiNhatKy, HasFactory;

    protected $table = 'products';

    protected $fillable = [
        'category_id', 'sku', 'barcode', 'ten_sanpham', 'don_vi',
        'gia_nhap', 'gia_ban', 'soluong', 'soluong_hong', 'soluong_toithieu',
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

    public function getAnhUrlAttribute(): string
    {
        $value = $this->hinh_anh;

        if ($value === null || $value === '') {
            return asset('img/no-image.svg');
        }
        if (str_starts_with($value, 'https://')) {
            return $value;
        }
        if (str_starts_with($value, 'upload/') || str_starts_with($value, 'storage/')) {
            return asset($value);
        }

        return Storage::disk('public')->url($value);
    }
}
