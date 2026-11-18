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
        'api_doi_soat' => 'Có trên SePay API nhưng chưa nhận webhook',
        'hoadon_da_thanh_toan' => 'Hóa đơn đã thanh toán nhưng chưa có webhook',
        'tai_khoan_khong_khop' => 'Tiền vào tài khoản không khớp POS_BANK_ACCOUNT',
    ];

    public $timestamps = false;

    protected $fillable = [
        'nha_cung_cap',
        'ma_giao_dich_ngoai',
        'ma_tham_chieu',
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

    /**
     * Mã tham chiếu dùng để đối chiếu webhook với SePay API. Bản ghi cũ
     * (trước migration) chưa có cột nên đọc dự phòng từ payload.
     */
    public function khoaThamChieu(): ?string
    {
        $ma = trim((string) ($this->ma_tham_chieu ?? ''));
        if ($ma !== '') {
            return $ma;
        }

        $payload = $this->payload ?? [];
        foreach (['referenceCode', 'reference_number', 'reference_code'] as $khoa) {
            $ma = trim((string) ($payload[$khoa] ?? ''));
            if ($ma !== '') {
                return $ma;
            }
        }

        return null;
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
