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

    // diem_tichluy cố ý KHÔNG fillable: chỉ đổi qua luồng thanh toán/trả hàng
    // hoặc điều chỉnh có kiểm soát (KhachHangController::postDieuChinhDiem).
    protected $fillable = ['user_id', 'ho_ten', 'so_dienthoai', 'dia_chi', 'khoa_den', 'ly_do_khoa'];

    protected function casts(): array
    {
        return [
            'diem_tichluy' => 'integer',
            'khoa_den' => 'datetime',
        ];
    }

    /** Hồ sơ khách đang bị khóa (khoa_den != null). */
    public function biKhoa(): bool
    {
        return $this->khoa_den !== null;
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
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
