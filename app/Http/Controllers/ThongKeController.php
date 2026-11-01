<?php

namespace App\Http\Controllers;

use App\Models\DonHang;
use App\Models\SanPham;
use Illuminate\Http\JsonResponse;
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

    public function getDoanhThu(): JsonResponse
    {
        $doanhThu = DonHang::query()
            ->selectRaw('DATE(created_at) as ngay, SUM(tong_thanh_toan) as doanh_thu')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('ngay')
            ->get();

        return response()->json($doanhThu);
    }

    public function getTopSanPham(): JsonResponse
    {
        $topSanPhams = DB::table('invoice_details')
            ->join('products', 'products.id', '=', 'invoice_details.product_id')
            ->select('products.id', 'products.ten_sanpham')
            ->selectRaw('SUM(invoice_details.soluong) as tong_so_luong')
            ->groupBy('products.id', 'products.ten_sanpham')
            ->orderByDesc('tong_so_luong')
            ->limit(5)
            ->get();

        return response()->json($topSanPhams);
    }
}
