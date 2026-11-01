<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GioTam extends Model
{
    use HasFactory;

    protected $table = 'held_carts';
    protected $fillable = ['user_id', 'ten_goi', 'du_lieu'];

    protected function casts(): array
    {
        return ['du_lieu' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
