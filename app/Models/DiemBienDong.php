<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sổ cái biến động điểm tích lũy — chỉ thêm, không sửa/xóa.
 * Bảng diem_bien_dong có thể CHƯA tồn tại trên prod (code đi trước
 * migration): mọi nơi ghi phải qua App\Services\DiemService::ghi() có guard.
 */
class DiemBienDong extends Model
{
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = null;

    protected $table = 'diem_bien_dong';

    protected $fillable = [
        'customer_id', 'loai', 'diem', 'so_du_sau',
        'invoice_id', 'return_id', 'user_id', 'ly_do', 'su_kien',
    ];

    protected function casts(): array
    {
        return [
            'diem' => 'integer',
            'so_du_sau' => 'integer',
            'customer_id' => 'integer',
            'invoice_id' => 'integer',
            'return_id' => 'integer',
            'user_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function khachHang(): BelongsTo
    {
        return $this->belongsTo(KhachHang::class, 'customer_id');
    }

    public function donHang(): BelongsTo
    {
        return $this->belongsTo(DonHang::class, 'invoice_id');
    }

    public function phieuTra(): BelongsTo
    {
        return $this->belongsTo(PhieuTra::class, 'return_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
