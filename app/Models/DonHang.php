<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DonHang extends Model
{
    use HasFactory;

    protected $table = 'invoices';

    protected $fillable = [
        'ma_hoadon', 'user_id', 'tong_tien', 'giam_gia_phantram',
        'tien_giam', 'tong_thanh_toan', 'tien_khach_dua',
        'tien_thua', 'phuong_thuc', 'customer_id', 'diem_su_dung',
        'tien_giam_diem', 'diem_tichluy_nhan', 'trang_thai', 'ma_giaodich',
        'thoi_gian_thanh_toan',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function khachHang(): BelongsTo
    {
        return $this->belongsTo(KhachHang::class, 'customer_id');
    }

    public function chiTietDonHangs(): HasMany
    {
        return $this->hasMany(ChiTietDonHang::class, 'invoice_id');
    }

    public function bienDongKhos(): HasMany
    {
        return $this->hasMany(BienDongKho::class, 'invoice_id');
    }
}
