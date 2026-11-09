<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KhachHang extends Model
{
    use HasFactory;

    protected $table = 'customers';

    protected $fillable = ['user_id', 'ho_ten', 'so_dienthoai', 'dia_chi', 'diem_tichluy'];

    protected function casts(): array
    {
        return ['diem_tichluy' => 'integer'];
    }

    public function donHangs(): HasMany
    {
        return $this->hasMany(DonHang::class, 'customer_id');
    }
}
