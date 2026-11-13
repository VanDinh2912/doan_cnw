<?php

namespace App\Services;

use App\Models\SanPham;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Quy tắc trần giữ hàng cho đơn pickup online (P0/PA1). Chỉ đọc + tính, không ghi.
 *
 * Đơn pickup = invoices.kenh='online' + phuong_thuc='tienmat' (trả tại quầy) +
 * trang_thai='cho_thanh_toan'. Không đếm QR (chuyenkhoan), đơn POS (kenh='pos'),
 * đơn da_thanh_toan hay da_huy.
 *
 * P1 BẮT BUỘC gọi kiemTra() trong cùng DB::transaction với lockForUpdate sản phẩm
 * (khóa theo id tăng dần, như ThanhToanChuyenKhoanService) trước khi trừ kho; nếu
 * không, hai khách đặt đồng thời có thể cùng vượt trần (race). Service này không
 * tự khóa hay trừ kho.
 */
class GiuHangOnlineService
{
    public function dangGiuPickup(int $sanPhamId): int
    {
        return (int) DB::table('invoice_details')
            ->join('invoices', 'invoices.id', '=', 'invoice_details.invoice_id')
            ->where('invoice_details.product_id', $sanPhamId)
            ->where('invoices.kenh', 'online')
            ->where('invoices.phuong_thuc', 'tienmat')
            ->where('invoices.trang_thai', 'cho_thanh_toan')
            ->sum('invoice_details.soluong');
    }

    /**
     * Tồn gốc = tồn hiện tại + lượng pickup đang giữ. Kho bị trừ ngay khi tạo đơn
     * chờ (R1) nên cộng lại phần đang giữ để trần không co lại theo mỗi đơn.
     */
    public function tonGoc(SanPham $sanPham): int
    {
        return (int) $sanPham->soluong + $this->dangGiuPickup($sanPham->id);
    }

    public function tran(SanPham $sanPham): int
    {
        $phanTram = (int) config('pos.online_giu_toi_da_phan_tram');

        // intdiv = floor(phanTram/100 * tonGoc) cho số nguyên, tránh sai số float.
        return max(1, intdiv($phanTram * $this->tonGoc($sanPham), 100));
    }

    /**
     * True nếu còn giữ được soLuongMoi; ném RuntimeException tiếng Việt nếu vượt
     * trần (kèm số lượng còn có thể giữ) hoặc số lượng không dương.
     */
    public function kiemTra(int $sanPhamId, int $soLuongMoi): bool
    {
        if ($soLuongMoi <= 0) {
            throw new RuntimeException('Số lượng đặt phải lớn hơn 0.');
        }

        $sanPham = SanPham::findOrFail($sanPhamId);
        $dangGiu = $this->dangGiuPickup($sanPhamId);
        $tran = $this->tran($sanPham);

        if ($dangGiu + $soLuongMoi > $tran) {
            $conLai = max(0, $tran - $dangGiu);
            throw new RuntimeException(
                "Sản phẩm này chỉ còn có thể giữ thêm {$conLai} sản phẩm cho đơn pickup chưa thanh toán (trần {$tran}, đang giữ {$dangGiu})."
            );
        }

        return true;
    }
}