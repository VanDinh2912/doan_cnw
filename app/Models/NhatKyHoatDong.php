<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NhatKyHoatDong extends Model
{
    use HasFactory;

    protected $table = 'activity_logs';

    protected $fillable = [
        'user_id', 'hanh_dong', 'doi_tuong', 'doi_tuong_loai', 'doi_tuong_id', 'mo_ta',
        'truoc', 'sau', 'ip', 'user_agent', 'nguon', 'nhom_id',
    ];

    protected function casts(): array
    {
        return ['truoc' => 'array', 'sau' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): bool => throw new \LogicException('Nhật ký kiểm toán chỉ được ghi thêm.'));
        static::deleting(fn (): bool => throw new \LogicException('Nhật ký kiểm toán chỉ được ghi thêm.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
