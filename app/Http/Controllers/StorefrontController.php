<?php

namespace App\Http\Controllers;

use App\Models\LoaiSanPham;
use App\Models\SanPham;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function home(): View
    {
        $categories = LoaiSanPham::query()
            ->withCount(['sanPhams' => fn ($q) => $q->where('trang_thai', true)])
            ->orderByDesc('san_phams_count')
            ->limit(8)
            ->get();

        $featured = SanPham::query()
            ->where('trang_thai', true)
            ->where('soluong', '>', 0)
            ->with('loaiSanPham')
            ->latest('updated_at')
            ->limit(12)
            ->get();

        // "Flash sale" placeholder — cheapest in-stock products, no discount
        // column exists yet. When a `gia_khuyen_mai` column lands, order by the
        // real discount percentage instead.
        $flashSale = SanPham::query()
            ->where('trang_thai', true)
            ->where('soluong', '>', 0)
            ->orderBy('gia_ban')
            ->limit(4)
            ->get();

        return view('storefront.home', compact('categories', 'featured', 'flashSale'));
    }

    public function category(Request $request, LoaiSanPham $loaiSanPham): View
    {
        $query = SanPham::query()
            ->where('trang_thai', true)
            ->where('category_id', $loaiSanPham->id)
            ->with('loaiSanPham');

        // Filter: in-stock only
        if ($request->boolean('con_hang')) {
            $query->where('soluong', '>', 0);
        }

        // Sort
        match ($request->input('sap_xep', 'moi')) {
            'gia_tang' => $query->orderBy('gia_ban'),
            'gia_giam' => $query->orderByDesc('gia_ban'),
            default => $query->latest('updated_at'),
        };

        $products = $query->paginate(12)->withQueryString();

        return view('storefront.category', [
            'category' => $loaiSanPham,
            'products' => $products,
        ]);
    }

    public function product(SanPham $sanPham): View
    {
        abort_if(! $sanPham->trang_thai, 404);

        $sanPham->load('loaiSanPham');

        $related = SanPham::query()
            ->where('trang_thai', true)
            ->where('soluong', '>', 0)
            ->where('category_id', $sanPham->category_id)
            ->where('id', '!=', $sanPham->id)
            ->limit(4)
            ->get();

        return view('storefront.product', compact('sanPham', 'related'));
    }

    public function search(Request $request): View
    {
        $q = trim((string) $request->input('q', ''));

        $products = SanPham::query()
            ->where('trang_thai', true)
            ->when($q !== '', function ($query) use ($q): void {
                $query->where(function ($inner) use ($q): void {
                    $inner->where('ten_sanpham', 'like', "%{$q}%")
                        ->orWhere('sku', 'like', "%{$q}%")
                        ->orWhere('barcode', 'like', "%{$q}%");
                });
            })
            ->with('loaiSanPham')
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString();

        return view('storefront.search', compact('q', 'products'));
    }
}