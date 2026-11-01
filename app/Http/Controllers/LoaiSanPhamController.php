<?php

namespace App\Http\Controllers;

use App\Models\LoaiSanPham;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoaiSanPhamController extends Controller
{
    public function getDanhSach(): View
    {
        return view('loaisanpham.danhsach', [
            'loais' => LoaiSanPham::withCount('sanPhams')->orderBy('ten_loai')->get(),
        ]);
    }

    public function getThem(): View
    {
        return view('loaisanpham.form', ['loai' => new LoaiSanPham()]);
    }

    public function postThem(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ten_loai' => ['required', 'string', 'max:100', 'unique:categories,ten_loai'],
            'mo_ta' => ['nullable', 'string'],
        ], $this->messages());

        LoaiSanPham::create($validated);

        return redirect()->route('loaisanpham.danhsach')->with('success', 'Đã thêm loại sản phẩm.');
    }

    public function getSua(LoaiSanPham $loai): View
    {
        return view('loaisanpham.form', ['loai' => $loai]);
    }

    public function postSua(Request $request, LoaiSanPham $loai): RedirectResponse
    {
        $validated = $request->validate([
            'ten_loai' => ['required', 'string', 'max:100', 'unique:categories,ten_loai,'.$loai->id],
            'mo_ta' => ['nullable', 'string'],
        ], $this->messages());

        $loai->update($validated);

        return redirect()->route('loaisanpham.danhsach')->with('success', 'Đã cập nhật loại sản phẩm.');
    }

    public function getXoa(LoaiSanPham $loai): RedirectResponse
    {
        if ($loai->sanPhams()->exists()) {
            return back()->with('error', 'Không thể xóa loại đang có sản phẩm.');
        }

        $loai->delete();

        return redirect()->route('loaisanpham.danhsach')->with('success', 'Đã xóa loại sản phẩm.');
    }

    private function messages(): array
    {
        return [
            'ten_loai.required' => 'Vui lòng nhập tên loại sản phẩm.',
            'ten_loai.max' => 'Tên loại sản phẩm không được vượt quá 100 ký tự.',
            'ten_loai.unique' => 'Tên loại sản phẩm đã tồn tại.',
        ];
    }
}
