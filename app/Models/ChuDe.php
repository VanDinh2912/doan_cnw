<?php

namespace App\Models;

use App\Models\Concerns\GhiNhatKy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChuDe extends Model
{
    use GhiNhatKy, HasFactory;

    protected $table = 'news_topics';

    protected $fillable = ['ten_chude', 'slug', 'mo_ta'];

    public function baiViets(): HasMany
    {
        return $this->hasMany(BaiViet::class, 'topic_id');
    }
}