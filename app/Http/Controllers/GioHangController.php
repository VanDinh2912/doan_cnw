<?php

namespace App\Http\Controllers;

use App\Models\SanPham;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GioHangController extends Controller
{
    public function getThem(Request $request, SanPham $sanPham): RedirectResponse
    {
        if (! $sanPham->trang_thai || $sanPham->soluong < 1) {
            return back()->with('error', 'Sản phẩm đã hết hàng hoặc đang tạm dừng.');
        }

        $gioHang = $request->session()->get('gio_hang', []);
        $id = (string) $sanPham->id;
        $soLuongMoi = ($gioHang[$id]['soluong'] ?? 0) + 1;

        if ($soLuongMoi > $sanPham->soluong) {
            return back()->with('error', 'Số lượng trong giỏ vượt quá tồn kho.');
        }

        $gioHang[$id] = [
            'id' => $sanPham->id,
            'sku' => $sanPham->sku,
            'ten_sanpham' => $sanPham->ten_sanpham,
            'gia_ban' => (float) $sanPham->gia_ban,
            'soluong' => $soLuongMoi,
            'ton_kho' => $sanPham->soluong,
        ];
        $request->session()->put('gio_hang', $gioHang);

        return back()->with('success', 'Đã thêm sản phẩm vào giỏ.');
    }

    public function getCapNhat(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'soluong' => ['required', 'array'],
            'soluong.*' => ['required', 'integer', 'min:1'],
        ], [
            'soluong.*.integer' => 'Số lượng phải là số nguyên.',
            'soluong.*.min' => 'Số lượng phải lớn hơn 0.',
        ]);

        $gioHang = $request->session()->get('gio_hang', []);
        foreach ($validated['soluong'] as $id => $soLuong) {
            if (! isset($gioHang[$id])) {
                continue;
            }

            $sanPham = SanPham::find($id);
            if (! $sanPham || $soLuong > $sanPham->soluong) {
                return back()->with('error', 'Số lượng sản phẩm vượt quá tồn kho.');
            }

            $gioHang[$id]['soluong'] = $soLuong;
            $gioHang[$id]['ton_kho'] = $sanPham->soluong;
            $gioHang[$id]['gia_ban'] = (float) $sanPham->gia_ban;
        }
        $request->session()->put('gio_hang', $gioHang);

        return back()->with('success', 'Đã cập nhật giỏ hàng.');
    }

    public function getXoa(Request $request, SanPham $sanPham): RedirectResponse
    {
        $gioHang = $request->session()->get('gio_hang', []);
        unset($gioHang[(string) $sanPham->id]);
        $request->session()->put('gio_hang', $gioHang);

        return back()->with('success', 'Đã xóa sản phẩm khỏi giỏ.');
    }

    public function getLamMoi(Request $request): RedirectResponse
    {
        $request->session()->forget('gio_hang');

        return back()->with('success', 'Đã làm mới giỏ hàng.');
    }
}
