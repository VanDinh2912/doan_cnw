<?php

namespace App\Http\Controllers;

use App\Models\BienDongKho;
use App\Models\SanPham;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class KhoController extends Controller
{
    public function getDanhSach(): View
    {
        return view('kho.danhsach', [
            'sanPhams' => SanPham::with('loaiSanPham')->orderBy('soluong')->get(),
        ]);
    }

    public function getNhapKho(): View
    {
        return view('kho.nhap', ['sanPhams' => SanPham::orderBy('ten_sanpham')->get()]);
    }

    public function postNhapKho(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'soluong' => ['required', 'integer', 'min:1'],
            'gia_nhap' => ['required', 'numeric', 'min:0'],
            'ghi_chu' => ['nullable', 'string', 'max:255'],
        ], [
            'product_id.required' => 'Vui lòng chọn sản phẩm.',
            'product_id.exists' => 'Sản phẩm không tồn tại.',
            'soluong.min' => 'Số lượng nhập phải lớn hơn 0.',
            'gia_nhap.min' => 'Giá nhập không được âm.',
        ]);

        try {
            DB::transaction(function () use ($validated, $request): void {
                $sanPham = SanPham::query()->lockForUpdate()->findOrFail($validated['product_id']);
                $sanPham->increment('soluong', $validated['soluong']);
                $sanPham->update(['gia_nhap' => $validated['gia_nhap']]);

                BienDongKho::create([
                    'product_id' => $sanPham->id,
                    'user_id' => $request->user()->id,
                    'invoice_id' => null,
                    'loai' => 'nhap',
                    'soluong' => $validated['soluong'],
                    'ghi_chu' => $validated['ghi_chu'] ?? 'Nhập kho',
                ]);
            });
        } catch (Throwable $exception) {
            Log::error('Nhập kho thất bại.', ['message' => $exception->getMessage()]);

            return back()->with('error', 'Không thể nhập kho. Vui lòng thử lại.');
        }

        return redirect()->route('kho.danhsach')->with('success', 'Đã nhập kho thành công.');
    }

    public function getLichSu(): View
    {
        return view('kho.lichsu', [
            'bienDongs' => BienDongKho::with(['sanPham', 'user', 'donHang'])->latest()->paginate(30),
        ]);
    }
}
