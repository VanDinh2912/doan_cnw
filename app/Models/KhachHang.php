<?php

namespace App\Models;

use App\Support\SoDienThoai;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Models\Concerns\GhiNhatKy;

class KhachHang extends Model
{
    use GhiNhatKy;
    use HasFactory;

    protected $table = 'customers';

    protected $fillable = ['user_id', 'ho_ten', 'so_dienthoai', 'dia_chi', 'diem_tichluy'];

    protected function casts(): array
    {
        return ['diem_tichluy' => 'integer'];
    }

    protected function soDienThoai(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => is_string($value) ? SoDienThoai::chuanHoa($value) : $value,
        );
    }

    public function donHangs(): HasMany
    {
        return $this->hasMany(DonHang::class, 'customer_id');
    }
}
