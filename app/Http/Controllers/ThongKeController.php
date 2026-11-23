<?php

namespace App\Http\Controllers;

use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\SanPham;
use App\Services\TongQuanKhoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class ThongKeController extends Controller
{
    public function getTongQuan(TongQuanKhoService $tongQuan): View
    {
        $data = [
            'chiSo' => $tongQuan->chiSo(),
            'sanPhamTonThap' => in_array(auth()->user()->role, ['admin', 'thukho'], true)
                ? SanPham::where('trang_thai', true)
                    ->whereColumn('soluong', '<=', 'soluong_toithieu')
                    ->orderBy('soluong')
                    ->get()
                : collect(),
            'canhBaoMatKhauMacDinh' => auth()->user()->role === 'admin'
                ? app(\App\Services\CanhBaoMatKhauMacDinh::class)->danhSachEmail()
                : [],
            // Công nợ điểm: chỉ đọc, không đổi công thức doanh thu/lợi nhuận.
            'congNoDiem' => $this->congNoDiem(),
            // M5: thẻ "Xác nhận thủ công chưa đối chiếu" — chỉ admin, chỉ khi bảng đã migrate.
            'xacNhanThuCongChuaSoat' => $this->thongKeXacNhanThuCongChuaSoat(),
        ];

        return view('tongquan', $data);
    }

    public function getSoLieuTongQuan(TongQuanKhoService $tongQuan): JsonResponse
    {
        return response()->json($tongQuan->chiSo());
    }

    public function getTrangThongKe(): View
    {
        return view('thongke.index', [
            'soDongChuaCoGiaVon' => $this->demDongChuaCoGiaVon(),
        ]);
    }

    /**
     * Đếm dòng bán (hóa đơn đã thanh toán) chưa có giá vốn — gia_von = 0/NULL.
     * Chỉ dùng để CẢNH BÁO, không đổi cách tính lợi nhuận.
     */
    /**
     * Công nợ điểm: tổng diem_tichluy quy đổi + số khách đang có điểm.
     * Chỉ đọc — không liên quan doanh thu/lợi nhuận.
     */
    private function congNoDiem(): array
    {
        $row = KhachHang::query()
            ->where('diem_tichluy', '>', 0)
            ->selectRaw('COUNT(*) AS so_khach, COALESCE(SUM(diem_tichluy), 0) AS tong_diem')
            ->first();

        $tongDiem = (int) ($row->tong_diem ?? 0);

        return [
            'tong_diem' => $tongDiem,
            'so_khach' => (int) ($row->so_khach ?? 0),
            'quy_doi' => $tongDiem * (int) config('pos.gia_tri_diem', 1000),
        ];
    }

    /** M5 — đếm + tổng tiền xác nhận thủ công chưa đối chiếu (admin only, null nếu bảng chưa có). */
    private function thongKeXacNhanThuCongChuaSoat(): ?array
    {
        if (auth()->user()->role !== 'admin'
            || ! \App\Support\KiemTraSchema::coBang('xac_nhan_thu_cong')) {
            return null;
        }

        $row = \App\Models\XacNhanThuCong::query()
            ->whereNull('soat_luc')
            ->selectRaw('COUNT(*) AS so_luong, COALESCE(SUM(so_tien), 0) AS tong_tien')
            ->first();

        return [
            'so_luong' => (int) ($row->so_luong ?? 0),
            'tong_tien' => (int) ($row->tong_tien ?? 0),
        ];
    }

    private function demDongChuaCoGiaVon()
    {
        return ChiTietDonHang::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_details.invoice_id')
            ->where('invoices.trang_thai', 'da_thanh_toan')
            ->where(function ($q) {
                $q->whereNull('invoice_details.gia_von')
                    ->orWhere('invoice_details.gia_von', 0);
            })
            ->count();
    }

    /** Danh sách các dòng bán chưa có giá vốn (chỉ admin, qua nhóm role:admin). */
    public function getDongChuaCoGiaVon(): View
    {
        $cacDong = ChiTietDonHang::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_details.invoice_id')
            ->join('products', 'products.id', '=', 'invoice_details.product_id')
            ->where('invoices.trang_thai', 'da_thanh_toan')
            ->where(function ($q) {
                $q->whereNull('invoice_details.gia_von')
                    ->orWhere('invoice_details.gia_von', 0);
            })
            ->orderByDesc('invoices.created_at')
            ->orderByDesc('invoice_details.id')
            ->select([
                'invoice_details.id',
                'invoices.ma_hoadon',
                'invoices.created_at as ngay_ban',
                'products.ten_sanpham',
                'products.sku',
                'invoice_details.soluong',
                'invoice_details.don_gia',
                'invoice_details.thanh_tien',
            ])
            ->paginate(50)
            ->withQueryString();

        return view('thongke.chua-co-gia-von', [
            'cacDong' => $cacDong,
            'tongDong' => $this->demDongChuaCoGiaVon(),
        ]);
    }

    public function getDoanhThu(Request $request): JsonResponse
    {
        $kieu = $request->query('kieu', 'ngay');
        abort_unless(in_array($kieu, ['ngay', 'tuan', 'thang'], true), 422, 'Kiểu thống kê không hợp lệ.');
        $hoaDons = DonHang::with(['chiTietDonHangs', 'phieuTras.chiTiets'])
            ->where('trang_thai', 'da_thanh_toan')
            ->get();
        $doanhThu = $hoaDons->groupBy(function (DonHang $donHang) use ($kieu): string {
            $ngay = $donHang->created_at;
            return match ($kieu) {
                'tuan' => $ngay->format('o-\WW'),
                'thang' => $ngay->format('Y-m'),
                default => $ngay->format('Y-m-d'),
            };
        })->map(function ($nhom, $ky): array {
            return [
                'ngay' => $ky,
                // P2 bước 4 + quy tắc hoàn ship: doanh thu hàng hóa KHÔNG gồm phí giao.
                // tong_hoan = tiền hàng hoàn + tiền hoàn phí giao (tien_hoan_phi_giao);
                // chỉ phần tiền hàng trừ vào doanh thu hàng, phí ship hoàn tách riêng.
                'doanh_thu' => $nhom->sum(function (DonHang $donHang): float {
                    $phieuXong = $donHang->phieuTras->where('trang_thai', 'hoan_thanh');
                    $hoanHang = max(0.0, (float) $phieuXong->sum('tong_hoan')
                        - (float) $phieuXong->sum('tien_hoan_phi_giao'));
                    $ship = (float) ($donHang->phi_giao_hang ?? 0);
                    $hangThuan = max(0.0, (float) $donHang->tong_thanh_toan - $ship);

                    return max(0.0, $hangThuan - $hoanHang);
                }),
                'phi_giao_hang' => $nhom->sum(function (DonHang $donHang): float {
                    $phieuXong = $donHang->phieuTras->where('trang_thai', 'hoan_thanh');
                    $hoanShip = (float) $phieuXong->sum('tien_hoan_phi_giao');
                    $ship = (float) ($donHang->phi_giao_hang ?? 0);

                    return max(0.0, $ship - $hoanShip);
                }),
                'hoan_phi_giao_hang' => $nhom->sum(function (DonHang $donHang): float {
                    return (float) $donHang->phieuTras->where('trang_thai', 'hoan_thanh')
                        ->sum('tien_hoan_phi_giao');
                }),
                'loi_nhuan' => $nhom->sum(function (DonHang $donHang): float {
                    $loiNhuanBan = $donHang->chiTietDonHangs->sum(
                        fn ($chiTiet) => ($chiTiet->don_gia - $chiTiet->gia_von) * $chiTiet->soluong
                    );
                    $loiNhuanTra = $donHang->phieuTras->where('trang_thai', 'hoan_thanh')
                        ->flatMap->chiTiets
                        ->sum(fn ($chiTiet) => ($chiTiet->don_gia - $chiTiet->gia_von) * $chiTiet->soluong);

                    return $loiNhuanBan - $loiNhuanTra;
                }),
            ];
        })->values();

        return response()->json($doanhThu);
    }

    public function getTopSanPham(): JsonResponse
    {
        $returned = DB::table('return_items')
            ->join('returns', 'returns.id', '=', 'return_items.return_id')
            ->where('returns.trang_thai', 'hoan_thanh')
            ->groupBy('return_items.product_id')
            ->select('return_items.product_id')
            ->selectRaw('SUM(return_items.soluong) as so_luong_tra');

        $topSanPhams = DB::table('invoice_details')
            ->join('products', 'products.id', '=', 'invoice_details.product_id')
            ->join('invoices', 'invoices.id', '=', 'invoice_details.invoice_id')
            ->where('invoices.trang_thai', 'da_thanh_toan')
            ->select('products.id', 'products.ten_sanpham')
            ->leftJoinSub($returned, 'returned', 'returned.product_id', '=', 'invoice_details.product_id')
            ->selectRaw('SUM(invoice_details.soluong) - COALESCE(MAX(returned.so_luong_tra), 0) as tong_so_luong')
            ->groupBy('products.id', 'products.ten_sanpham')
            ->orderByDesc('tong_so_luong')
            ->limit(5)
            ->get();

        return response()->json($topSanPhams);
    }
}
