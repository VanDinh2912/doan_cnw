<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentWebhook extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'nha_cung_cap',
        'ma_giao_dich_ngoai',
        'invoice_id',
        'so_tien',
        'ket_qua',
    ];

    protected function casts(): array
    {
        return [
            'so_tien' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function donHang(): BelongsTo
    {
        return $this->belongsTo(DonHang::class, 'invoice_id');
    }
}
