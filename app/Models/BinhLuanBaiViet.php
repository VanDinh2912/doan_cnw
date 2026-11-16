<?php

namespace App\Models;

use App\Models\Concerns\GhiNhatKy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BinhLuanBaiViet extends Model
{
    use GhiNhatKy, HasFactory;

    protected $table = 'news_comments';

    protected $fillable = ['post_id', 'user_id', 'noi_dung', 'kiem_duyet', 'kich_hoat'];

    protected function casts(): array
    {
        return [
            'kiem_duyet' => 'boolean',
            'kich_hoat' => 'boolean',
        ];
    }

    public function baiViet(): BelongsTo
    {
        return $this->belongsTo(BaiViet::class, 'post_id');
    }

    public function nguoiDung(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeHienThi(Builder $query): Builder
    {
        return $query->where('kiem_duyet', true)->where('kich_hoat', true);
    }
}