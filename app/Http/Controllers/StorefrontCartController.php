<?php

namespace App\Http\Controllers;

use App\Models\SanPham;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Guest + customer cart. Uses the `storefront_cart` session key — intentionally
 * separate from the POS `gio_hang` key so a staff user signed into POS and the
 * storefront on the same browser does not mix carts.
 */
class StorefrontCartController extends Controller
{
    private const KEY = 'storefront_cart';

    public function index(Request $request): View
    {
        $cart = $request->session()->get(self::KEY, []);
        $total = collect($cart)->sum(fn ($item) => $item['gia_ban'] * $item['soluong']);

        return view('storefront.cart', compact('cart', 'total'));
    }

    public function add(Request $request, SanPham $sanPham): RedirectResponse
    {
        if (! $sanPham->trang_thai || $sanPham->soluong < 1) {
            return back()->with('error', 'Sản phẩm đã hết hàng hoặc đang tạm dừng.');
        }

        $cart = $request->session()->get(self::KEY, []);
        $id = (string) $sanPham->id;
        $newQty = ($cart[$id]['soluong'] ?? 0) + 1;

        if ($newQty > $sanPham->soluong) {
            return back()->with('error', 'Số lượng trong giỏ vượt quá tồn kho.');
        }

        $cart[$id] = [
            'id' => $sanPham->id,
            'sku' => $sanPham->sku,
            'ten_sanpham' => $sanPham->ten_sanpham,
            'hinh_anh' => $sanPham->hinh_anh,
            'gia_ban' => (float) $sanPham->gia_ban,
            'soluong' => $newQty,
            'ton_kho' => $sanPham->soluong,
        ];

        $request->session()->put(self::KEY, $cart);

        return back()->with('success', 'Đã thêm "' . $sanPham->ten_sanpham . '" vào giỏ hàng.');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'soluong' => ['required', 'array'],
            'soluong.*' => ['required', 'integer', 'min:1'],
        ], [
            'soluong.*.integer' => 'Số lượng phải là số nguyên.',
            'soluong.*.min' => 'Số lượng phải lớn hơn 0.',
        ]);

        $cart = $request->session()->get(self::KEY, []);

        foreach ($validated['soluong'] as $id => $qty) {
            if (! isset($cart[$id])) {
                continue;
            }

            $sanPham = SanPham::find($id);
            if (! $sanPham || $qty > $sanPham->soluong) {
                return back()->with('error', 'Số lượng sản phẩm vượt quá tồn kho.');
            }

            $cart[$id]['soluong'] = $qty;
            $cart[$id]['ton_kho'] = $sanPham->soluong;
            $cart[$id]['gia_ban'] = (float) $sanPham->gia_ban;
        }

        $request->session()->put(self::KEY, $cart);

        return back()->with('success', 'Đã cập nhật giỏ hàng.');
    }

    public function remove(Request $request, SanPham $sanPham): RedirectResponse
    {
        $cart = $request->session()->get(self::KEY, []);
        unset($cart[(string) $sanPham->id]);
        $request->session()->put(self::KEY, $cart);

        return back()->with('success', 'Đã xóa sản phẩm khỏi giỏ.');
    }

    public function clear(Request $request): RedirectResponse
    {
        $request->session()->forget(self::KEY);

        return back()->with('success', 'Đã làm mới giỏ hàng.');
    }
}