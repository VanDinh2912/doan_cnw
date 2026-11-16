<?php

namespace App\Models;

use App\Models\Concerns\GhiNhatKy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class BaiViet extends Model
{
    use GhiNhatKy, HasFactory;

    protected $table = 'news_posts';

    protected $fillable = [
        'topic_id', 'user_id', 'tieu_de', 'slug', 'tom_tat', 'noi_dung',
        'hinh_anh', 'luot_xem', 'kiem_duyet', 'kich_hoat',
    ];

    protected function casts(): array
    {
        return [
            'kiem_duyet' => 'boolean',
            'kich_hoat' => 'boolean',
            'luot_xem' => 'integer',
        ];
    }

    public function chuDe(): BelongsTo
    {
        return $this->belongsTo(ChuDe::class, 'topic_id');
    }

    public function tacGia(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function binhLuans(): HasMany
    {
        return $this->hasMany(BinhLuanBaiViet::class, 'post_id');
    }

    public function scopeDaDang(Builder $query): Builder
    {
        return $query->where('kiem_duyet', true)->where('kich_hoat', true);
    }

    public function getAnhUrlAttribute(): ?string
    {
        if (empty($this->hinh_anh)) {
            return null;
        }
        if (str_starts_with($this->hinh_anh, 'http://') || str_starts_with($this->hinh_anh, 'https://')) {
            return $this->hinh_anh;
        }

        return Storage::disk('public')->url($this->hinh_anh);
    }
}