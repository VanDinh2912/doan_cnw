<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhieuKiemKe extends Model
{
    use HasFactory;
    protected $table = 'stocktakes';
    protected $fillable = ['ma_phieu', 'user_id', 'trang_thai', 'ghi_chu'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function chiTiets(): HasMany { return $this->hasMany(ChiTietKiemKe::class, 'stocktake_id'); }
}
