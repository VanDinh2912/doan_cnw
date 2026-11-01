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
            'ma_loai' => ['required', 'regex:/^[A-Z0-9]{2,10}$/', 'unique:categories,ma_loai'],
            'ten_loai' => ['required', 'string', 'max:100', 'unique:categories,ten_loai'],
            'mo_ta' => ['nullable', 'string'],
            'tu_khoa' => ['nullable', 'string'],
            'he_so_lai' => ['required', 'numeric', 'between:1,5'],
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
            'ma_loai' => ['required', 'regex:/^[A-Z0-9]{2,10}$/', 'unique:categories,ma_loai,'.$loai->id],
            'ten_loai' => ['required', 'string', 'max:100', 'unique:categories,ten_loai,'.$loai->id],
            'mo_ta' => ['nullable', 'string'],
            'tu_khoa' => ['nullable', 'string'],
            'he_so_lai' => ['required', 'numeric', 'between:1,5'],
        ], $this->messages());

        $loai->update($validated);

        return redirect()->route('loaisanpham.danhsach')->with('success', 'Đã cập nhật loại sản phẩm.');
    }

    public function getGoiY(Request $request)
    {
        $ten = mb_strtolower($request->string('ten')->toString());
        return response()->json(LoaiSanPham::all()->map(function (LoaiSanPham $loai) use ($ten): array {
            $matches = collect(explode(',', (string) $loai->tu_khoa))->map(fn (string $word) => trim($word))
                ->filter(fn (string $word) => $word !== '' && mb_strpos($ten, mb_strtolower($word)) !== false);
            return ['id' => $loai->id, 'ten_loai' => $loai->ten_loai, 'khop' => $matches->sortByDesc(fn (string $word) => mb_strlen($word))->first()];
        })->filter(fn (array $item) => $item['khop'] !== null)->values());
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
