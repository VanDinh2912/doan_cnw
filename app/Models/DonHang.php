<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DonHang extends Model
{
    use HasFactory;

    protected $table = 'invoices';

    protected $fillable = [
        'ma_hoadon', 'user_id', 'tong_tien', 'giam_gia_phantram',
        'tien_giam', 'tong_thanh_toan', 'tien_khach_dua',
        'tien_thua', 'phuong_thuc', 'ma_giao_dich', 'ma_giaodich', 'customer_id', 'diem_su_dung',
        'tien_giam_diem', 'diem_tichluy_nhan', 'trang_thai',
        'thoi_gian_thanh_toan', 'ghi_chu',
        'kenh', 'ma_tra_cuu', 'ten_khach_dat', 'so_dienthoai_dat', 'ghi_chu_khach', 'han_giu',
    ];

    protected function casts(): array
    {
        return [
            'thoi_gian_thanh_toan' => 'datetime',
            'han_giu' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function khachHang(): BelongsTo
    {
        return $this->belongsTo(KhachHang::class, 'customer_id');
    }

    public function chiTietDonHangs(): HasMany
    {
        return $this->hasMany(ChiTietDonHang::class, 'invoice_id');
    }

    public function bienDongKhos(): HasMany
    {
        return $this->hasMany(BienDongKho::class, 'invoice_id');
    }

    public function phieuTras(): HasMany
    {
        return $this->hasMany(PhieuTra::class, 'invoice_id');
    }

    public function paymentWebhooks(): HasMany
    {
        return $this->hasMany(PaymentWebhook::class, 'invoice_id');
    }

    /**
     * Đơn chờ thanh toán đã quá hạn cần hủy.
     * Pickup online (kenh=online + phuong_thuc=tienmat + han_giu có giá trị):
     * hủy theo han_giu, không áp hạn 15 phút. Mọi đơn còn lại (POS, QR, đơn cũ
     * han_giu null): created_at + cho_thanh_toan_phut.
     */
    public function scopeQuaHanCho($query)
    {
        $phut = (int) config('pos.cho_thanh_toan_phut', 15);
        $now = now();
        $hanChot = $now->copy()->subMinutes($phut);

        return $query->where('trang_thai', 'cho_thanh_toan')
            ->where(function ($q) use ($now, $hanChot): void {
                $q->where(function ($pickup) use ($now): void {
                    $pickup->where('kenh', 'online')
                        ->where('phuong_thuc', 'tienmat')
                        ->whereNotNull('han_giu')
                        ->where('han_giu', '<', $now);
                })->orWhere(function ($khac) use ($hanChot): void {
                    $khac->where('created_at', '<', $hanChot)
                        ->where(function ($q2): void {
                            $q2->whereNull('han_giu')
                                ->orWhere('kenh', '!=', 'online')
                                ->orWhere('phuong_thuc', '!=', 'tienmat');
                        });
                });
            });
    }
}
