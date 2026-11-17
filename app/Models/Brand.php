<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Brand extends Model
{
    protected $table = 'brands';

    protected $fillable = ['ten', 'slug', 'logo', 'kich_hoat'];

    protected function casts(): array
    {
        return ['kich_hoat' => 'boolean'];
    }

    public function sanPhams(): HasMany
    {
        return $this->hasMany(SanPham::class, 'brand_id');
    }

    /**
     * Tạo slug duy nhất từ tên hãng; giữ nguyên nếu slug đã thuộc hãng này.
     */
    public static function slugDuyNhat(string $ten, ?int $boQuaId = null): string
    {
        $slug = Str::slug($ten) ?: 'hang-'.substr(md5($ten), 0, 6);
        $goc = $slug;
        $i = 2;
        while (static::query()
            ->where('slug', $slug)
            ->when($boQuaId, fn ($q) => $q->where('id', '!=', $boQuaId))
            ->exists()) {
            $slug = $goc.'-'.$i++;
        }

        return $slug;
    }
}
