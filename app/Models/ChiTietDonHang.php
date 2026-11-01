<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChiTietDonHang extends Model
{
    use HasFactory;

    protected $table = 'invoice_details';

    protected $fillable = ['invoice_id', 'product_id', 'soluong', 'don_gia', 'gia_von', 'thanh_tien'];

    public function donHang(): BelongsTo
    {
        return $this->belongsTo(DonHang::class, 'invoice_id');
    }

    public function sanPham(): BelongsTo
    {
        return $this->belongsTo(SanPham::class, 'product_id');
    }
}
