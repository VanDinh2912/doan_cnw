<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentWebhook extends Model
{
    public const LY_DO = [
        'khong_tim_thay_ma_hoadon' => 'Không tìm thấy mã hóa đơn trong nội dung',
        'hoadon_khong_ton_tai' => 'Hóa đơn không tồn tại',
        'don_da_huy' => 'Tiền về hóa đơn đã hủy',
        'thieu_tien' => 'Khách chuyển thiếu tiền',
        'don_khong_hoan_tat_duoc' => 'Đơn đổi trạng thái trước khi hoàn tất',
        'loi_he_thong' => 'Lỗi hệ thống khi xử lý',
    ];

    public $timestamps = false;

    protected $fillable = [
        'nha_cung_cap',
        'ma_giao_dich_ngoai',
        'invoice_id',
        'so_tien',
        'ket_qua',
        'ly_do',
        'ma_hoadon',
        'noi_dung',
        'payload',
        'trang_thai_xu_ly',
        'ghi_chu_xu_ly',
        'nguoi_xu_ly_id',
        'thoi_gian_xu_ly',
    ];

    protected function casts(): array
    {
        return [
            'so_tien' => 'integer',
            'created_at' => 'datetime',
            'thoi_gian_xu_ly' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function donHang(): BelongsTo
    {
        return $this->belongsTo(DonHang::class, 'invoice_id');
    }

    public function nguoiXuLy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nguoi_xu_ly_id');
    }
}
