<?php

namespace App\Http\Controllers;

use App\Models\GioTam;
use App\Models\SanPham;
use App\Services\NhatKyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HoldController extends Controller
{
    public function giu(Request $request): RedirectResponse
    {
        $validated = $request->validate(['ten_goi' => ['nullable', 'string', 'max:120']]);
        $cart = $request->session()->get('gio_hang', []);
        if ($cart === []) {
            return back()->with('error', 'Không thể giữ giỏ hàng trống.');
        }

        $gioTam = GioTam::create([
            'user_id' => $request->user()->id,
            'ten_goi' => $validated['ten_goi'] ?: 'Giỏ lúc '.now()->format('H:i'),
            'du_lieu' => $cart,
        ]);
        $request->session()->forget('gio_hang');
        NhatKyService::ghi($request, 'giu_gio', 'gio_tam', $gioTam->id, 'Giữ giỏ '.$gioTam->ten_goi);

        return back()->with('success', 'Đã giữ giỏ hàng.');
    }

    public function danhSach(Request $request): View
    {
        return view('hold.danhsach', [
            'gioTams' => GioTam::with('user')
                ->when($request->user()->role !== 'admin', fn ($query) => $query->where('user_id', $request->user()->id))
                ->where('created_at', '>=', now()->subDay())
                ->latest()->paginate(20),
        ]);
    }

    public function khoiPhuc(Request $request, GioTam $gioTam): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin' || $gioTam->user_id === $request->user()->id, 403);
        $cart = [];
        $invalid = [];
        foreach ($gioTam->du_lieu as $id => $item) {
            $product = SanPham::find($id);
            if (! $product || ! $product->trang_thai || $product->soluong < (int) $item['soluong']) {
                $invalid[] = $item['ten_sanpham'] ?? ('#'.$id);
                continue;
            }
            $item['ton_kho'] = $product->soluong;
            $item['gia_ban'] = (float) $product->gia_ban;
            $cart[(string) $product->id] = $item;
        }
        $request->session()->put('gio_hang', $cart);
        $gioTam->delete();
        $message = $invalid === [] ? 'Đã khôi phục giỏ hàng.' : 'Đã khôi phục giỏ, bỏ qua: '.implode(', ', $invalid);

        return redirect()->route('pos.index')->with($invalid === [] ? 'success' : 'error', $message);
    }

    public function xoa(Request $request, GioTam $gioTam): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin' || $gioTam->user_id === $request->user()->id, 403);
        $gioTam->delete();
        return back()->with('success', 'Đã xóa giỏ tạm.');
    }
}
