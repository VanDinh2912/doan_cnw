<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChiTietPhieuTra extends Model
{
    use HasFactory;

    protected $table = 'return_items';

    protected $fillable = [
        'return_id', 'invoice_detail_id', 'product_id', 'soluong',
        'don_gia', 'gia_von', 'thanh_tien', 'tinh_trang',
    ];

    public function phieuTra(): BelongsTo
    {
        return $this->belongsTo(PhieuTra::class, 'return_id');
    }

    public function sanPham(): BelongsTo
    {
        return $this->belongsTo(SanPham::class, 'product_id');
    }
}
