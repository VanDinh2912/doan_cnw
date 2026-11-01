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
                'doanh_thu' => $nhom->sum(fn (DonHang $donHang) => $donHang->tong_thanh_toan
                    - $donHang->phieuTras->where('trang_thai', 'hoan_thanh')->sum('tong_hoan')),
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
