<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HoaDonNhap extends Model
{
    use HasFactory;
    protected $table = 'purchase_invoices';
    protected $fillable = [
        'ma_phieu', 'so_hoadon', 'ky_hieu', 'mau_so', 'ngay_hoadon', 'supplier_id',
        'user_id', 'trang_thai', 'nguoi_nhap_id', 'ngay_nhap_kho', 'hinh_thuc_thanh_toan',
        'tong_tien_hang', 'thue_suat', 'tien_thue', 'tong_thanh_toan', 'file_goc', 'ghi_chu',
    ];
    protected function casts(): array
    {
        return ['ngay_hoadon' => 'date', 'ngay_nhap_kho' => 'datetime', 'tong_tien_hang' => 'integer', 'thue_suat' => 'decimal:2', 'tien_thue' => 'integer', 'tong_thanh_toan' => 'integer'];
    }
    public function nhaCungCap(): BelongsTo { return $this->belongsTo(NhaCungCap::class, 'supplier_id'); }
    public function nguoiTao(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function nguoiNhap(): BelongsTo { return $this->belongsTo(User::class, 'nguoi_nhap_id'); }
    public function chiTiets(): HasMany { return $this->hasMany(ChiTietHoaDonNhap::class, 'purchase_invoice_id'); }
    public function bienDongs(): HasMany { return $this->hasMany(BienDongKho::class, 'purchase_invoice_id'); }
}
