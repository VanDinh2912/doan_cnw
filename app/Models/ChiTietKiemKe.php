<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChiTietKiemKe extends Model
{
    use HasFactory;
    protected $table = 'stocktake_items';
    protected $fillable = ['stocktake_id', 'product_id', 'soluong_he_thong', 'soluong_thuc', 'chenh_lech', 'thoi_diem_dem'];
    public function sanPham(): BelongsTo { return $this->belongsTo(SanPham::class, 'product_id'); }
}
