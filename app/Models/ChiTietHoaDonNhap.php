<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChiTietHoaDonNhap extends Model
{
    use HasFactory;
    protected $table = 'purchase_invoice_items';
    protected $fillable = ['purchase_invoice_id', 'stt', 'product_id', 'ten_hang', 'don_vi', 'soluong', 'don_gia', 'thanh_tien', 'category_id', 'gia_ban_de_xuat'];
    protected function casts(): array { return ['don_gia' => 'integer', 'thanh_tien' => 'integer', 'gia_ban_de_xuat' => 'integer']; }
    public function hoaDonNhap(): BelongsTo { return $this->belongsTo(HoaDonNhap::class, 'purchase_invoice_id'); }
    public function sanPham(): BelongsTo { return $this->belongsTo(SanPham::class, 'product_id'); }
    public function loai(): BelongsTo { return $this->belongsTo(LoaiSanPham::class, 'category_id'); }
}
