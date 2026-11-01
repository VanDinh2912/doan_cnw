<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BienDongKho extends Model
{
    use HasFactory;

    protected $table = 'stock_movements';

    protected $fillable = [
        'product_id', 'user_id', 'invoice_id', 'purchase_order_id', 'purchase_invoice_id', 'return_id', 'stocktake_id', 'loai', 'soluong', 'ghi_chu',
    ];

    public function sanPham(): BelongsTo
    {
        return $this->belongsTo(SanPham::class, 'product_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function donHang(): BelongsTo
    {
        return $this->belongsTo(DonHang::class, 'invoice_id');
    }

    public function donNhap(): BelongsTo
    {
        return $this->belongsTo(DonNhap::class, 'purchase_order_id');
    }

    public function hoaDonNhap(): BelongsTo
    {
        return $this->belongsTo(HoaDonNhap::class, 'purchase_invoice_id');
    }

    public function phieuTra(): BelongsTo
    {
        return $this->belongsTo(PhieuTra::class, 'return_id');
    }

    public function phieuKiemKe(): BelongsTo
    {
        return $this->belongsTo(PhieuKiemKe::class, 'stocktake_id');
    }
}
