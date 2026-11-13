<?php

namespace App\Services;

use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\SanPham;
use Illuminate\Support\Facades\DB;

/**
 * Nguồn số liệu duy nhất cho dashboard tổng quan — đọc thẳng COUNT/SUM,
 * không cache, để số luôn khớp nghiệp vụ kho ngay sau mỗi thao tác.
 *
 * Quy ước: các chỉ số ĐẾM MẶT HÀNG chỉ tính sản phẩm đang kinh doanh
 * (trang_thai = true); các chỉ số TỒN KHO tính mọi sản phẩm kể cả tạm dừng
 * vì hàng vẫn nằm trong kho. Giá trị tồn = Σ(soluong × gia_nhap), không gồm hàng hỏng.
 */
class TongQuanKhoService
{
    public function chiSo(): array
    {
        $row = SanPham::query()->selectRaw("
            SUM(CASE WHEN trang_thai = 1 THEN 1 ELSE 0 END) AS so_mat_hang_dang_kinh_doanh,
            SUM(CASE WHEN trang_thai = 1 AND soluong > 0 THEN 1 ELSE 0 END) AS so_con_hang,
            SUM(CASE WHEN trang_thai = 1 AND soluong = 0 THEN 1 ELSE 0 END) AS so_het_hang,
            COALESCE(SUM(soluong), 0) AS tong_ton_tot,
            COALESCE(SUM(soluong_hong), 0) AS tong_ton_hong,
            COALESCE(SUM(soluong * gia_nhap), 0) AS gia_tri_ton
        ")->first();

        $tonKhaDung = (int) $row->tong_ton_tot;

        $dangGiu = (int) DB::table('invoice_details')
            ->join('invoices', 'invoices.id', '=', 'invoice_details.invoice_id')
            ->where('invoices.trang_thai', 'cho_thanh_toan')
            ->sum('invoice_details.soluong');

        $tongThucTe = $tonKhaDung + $dangGiu;

        return [
            'so_mat_hang_dang_kinh_doanh' => (int) $row->so_mat_hang_dang_kinh_doanh,
            'so_con_hang' => (int) $row->so_con_hang,
            'so_het_hang' => (int) $row->so_het_hang,
            'tong_ton_tot' => $tonKhaDung,
            'ton_kha_dung' => $tonKhaDung,
            'dang_giu' => $dangGiu,
            'tong_thuc_te' => $tongThucTe,
            'tong_ton_hong' => (int) $row->tong_ton_hong,
            'gia_tri_ton' => (int) $row->gia_tri_ton,
            'so_hoa_don' => DonHang::count(),
            'cap_nhat_luc' => now()->timezone('Asia/Ho_Chi_Minh')->format('H:i:s'),
        ];
    }
}