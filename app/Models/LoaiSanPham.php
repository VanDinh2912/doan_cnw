<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoaiSanPham extends Model
{
    use HasFactory;

    protected $table = 'categories';

    protected $fillable = ['ma_loai', 'ten_loai', 'mo_ta', 'tu_khoa', 'he_so_lai'];

    public function sanPhams(): HasMany
    {
        return $this->hasMany(SanPham::class, 'category_id');
    }
}
