<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhieuTra extends Model
{
    use HasFactory;

    protected $table = 'returns';

    protected $fillable = [
        'ma_phieu', 'invoice_id', 'user_id', 'customer_id', 'tong_hoan',
        'diem_thu_hoi', 'hinh_thuc_hoan', 'trang_thai', 'ly_do',
    ];

    protected function casts(): array
    {
        return ['tong_hoan' => 'decimal:0'];
    }

    public function donHang(): BelongsTo
    {
        return $this->belongsTo(DonHang::class, 'invoice_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chiTiets(): HasMany
    {
        return $this->hasMany(ChiTietPhieuTra::class, 'return_id');
    }
}
