<?php

namespace App\Models;

use App\Models\Concerns\GhiNhatKy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NhaCungCap extends Model
{
    use GhiNhatKy, HasFactory;

    protected $table = 'suppliers';

    protected $fillable = ['ma_so_thue', 'ten_nhacungcap', 'nguoi_lienhe', 'so_dienthoai', 'dia_chi', 'ghi_chu'];

    public function donNhaps(): HasMany
    {
        return $this->hasMany(DonNhap::class, 'supplier_id');
    }
}
