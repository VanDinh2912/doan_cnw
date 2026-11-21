<?php

namespace App\Models;

use App\Models\Concerns\GhiNhatKy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class DonHang extends Model
{
    use GhiNhatKy, HasFactory;

    protected $table = 'invoices';

    protected $fillable = [
        'ma_hoadon', 'user_id', 'tong_tien', 'giam_gia_phantram',
        'tien_giam', 'tong_thanh_toan', 'tien_khach_dua',
        'tien_thua', 'phuong_thuc', 'ma_giao_dich', 'ma_giaodich', 'customer_id', 'diem_su_dung',
        'tien_giam_diem', 'diem_tichluy_nhan', 'trang_thai',
        'thoi_gian_thanh_toan', 'ghi_chu',
        'kenh', 'ma_tra_cuu', 'ten_khach_dat', 'so_dienthoai_dat', 'ghi_chu_khach', 'han_giu', 'email_dat',
        'hinh_thuc_nhan', 'ten_nguoi_nhan', 'sdt_nguoi_nhan', 'dia_chi_giao', 'phi_giao_hang', 'trang_thai_giao',
        'can_hoan_tien', 'ghi_chu_noi_bo',
        'nhan_hang_luc', 'nhan_hang_boi', 'ghi_chu_nhan',
    ];

    protected function casts(): array
    {
        return [
            'thoi_gian_thanh_toan' => 'datetime',
            'han_giu' => 'datetime',
            'nhan_hang_luc' => 'datetime',
        ];
    }

    public function getTrangThaiTiengVietAttribute(): string
    {
        return match ($this->trang_thai) {
            'cho_thanh_toan' => 'Chờ thanh toán',
            'da_thanh_toan' => 'Đã thanh toán',
            'da_huy' => 'Đã hủy',
            default => str_replace('_', ' ', $this->trang_thai),
        };
    }

    public function getHinhThucNhanTiengVietAttribute(): string
    {
        return match ($this->hinh_thuc_nhan) {
            'giao_tan_noi' => 'Giao tận nơi',
            'tai_cua_hang' => 'Nhận tại cửa hàng',
            default => '—',
        };
    }

    public function getTrangThaiGiaoTiengVietAttribute(): string
    {
        return match ($this->trang_thai_giao) {
            'cho_xac_nhan' => 'Chờ xác nhận',
            'da_xac_nhan' => 'Đã xác nhận',
            'dang_giao' => 'Đang giao',
            'da_giao' => 'Đã giao',
            'da_huy_giao' => 'Đã hủy giao',
            'da_nhan' => 'Đã nhận hàng',
            default => '—',
        };
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function khachHang(): BelongsTo
    {
        return $this->belongsTo(KhachHang::class, 'customer_id');
    }

    public function nhanHangBoi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nhan_hang_boi');
    }

    /** Đơn pickup đã được bàn giao cho khách chưa (an toàn khi chưa có cột). */
    public function daNhanHang(): bool
    {
        return $this->nhan_hang_luc !== null || $this->trang_thai_giao === 'da_nhan';
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
     * Đơn online (kenh=online, mọi phương thức) có han_giu: hủy theo han_giu
     * (D17: QR online 30 phút, pickup 4 giờ), không áp hạn 15 phút. Mọi đơn
     * còn lại (POS, đơn cũ han_giu null): created_at + cho_thanh_toan_phut.
     */
    public function scopeQuaHanCho($query)
    {
        $phut = (int) config('pos.cho_thanh_toan_phut', 15);
        $now = now();
        $hanChot = $now->copy()->subMinutes($phut);

        if (! Schema::hasColumn('invoices', 'kenh')) {
            return $query->where('trang_thai', 'cho_thanh_toan')
                ->where('created_at', '<', $hanChot);
        }

        return $query->where('trang_thai', 'cho_thanh_toan')
            ->where(function ($q) use ($now, $hanChot): void {
                $q->where(function ($online) use ($now): void {
                    $online->where('kenh', 'online')
                        ->whereNotNull('han_giu')
                        ->where('han_giu', '<', $now);
                })->orWhere(function ($khac) use ($hanChot): void {
                    $khac->where('created_at', '<', $hanChot)
                        ->where(function ($q2): void {
                            $q2->whereNull('han_giu')
                                ->orWhere('kenh', '!=', 'online');
                        });
                });
            });
    }
}
