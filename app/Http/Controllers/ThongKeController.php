<?php

namespace App\Http\Controllers;

use App\Models\DonHang;
use App\Models\SanPham;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class ThongKeController extends Controller
{
    public function getTongQuan(): View
    {
        $data = [
            'soSanPham' => SanPham::count(),
            'soHoaDon' => DonHang::count(),
            'sanPhamTonThap' => in_array(auth()->user()->role, ['admin', 'thukho'], true)
                ? SanPham::whereColumn('soluong', '<=', 'soluong_toithieu')->orderBy('soluong')->get()
                : collect(),
        ];

        return view('tongquan', $data);
    }

    public function getTrangThongKe(): View
    {
        return view('thongke.index');
    }

    public function getDoanhThu(Request $request): JsonResponse
    {
        $kieu = $request->query('kieu', 'ngay');
        abort_unless(in_array($kieu, ['ngay', 'tuan', 'thang'], true), 422, 'Kiểu thống kê không hợp lệ.');
        $hoaDons = DonHang::with('chiTietDonHangs')
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
                'doanh_thu' => $nhom->sum('tong_thanh_toan'),
                'loi_nhuan' => $nhom->sum(fn (DonHang $donHang) => $donHang->chiTietDonHangs->sum(
                    fn ($chiTiet) => ($chiTiet->don_gia - $chiTiet->gia_von) * $chiTiet->soluong
                )),
            ];
        })->values();

        return response()->json($doanhThu);
    }

    public function getTopSanPham(): JsonResponse
    {
        $topSanPhams = DB::table('invoice_details')
            ->join('products', 'products.id', '=', 'invoice_details.product_id')
            ->join('invoices', 'invoices.id', '=', 'invoice_details.invoice_id')
            ->where('invoices.trang_thai', 'da_thanh_toan')
            ->select('products.id', 'products.ten_sanpham')
            ->selectRaw('SUM(invoice_details.soluong) as tong_so_luong')
            ->groupBy('products.id', 'products.ten_sanpham')
            ->orderByDesc('tong_so_luong')
            ->limit(5)
            ->get();

        return response()->json($topSanPhams);
    }
}
