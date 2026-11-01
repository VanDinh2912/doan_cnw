<?php

namespace App\Http\Controllers;

use App\Models\LoaiSanPham;
use App\Models\SanPham;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use Picqer\Barcode\BarcodeGeneratorSVG;
use App\Services\NhatKyService;

class SanPhamController extends Controller
{
    public function getDanhSach(): View
    {
        return view('sanpham.danhsach', [
            'sanPhams' => SanPham::with('loaiSanPham')->orderBy('ten_sanpham')->get(),
        ]);
    }

    public function getThem(): View
    {
        return view('sanpham.form', [
            'sanPham' => new SanPham(['trang_thai' => true, 'soluong_toithieu' => 5]),
            'loais' => LoaiSanPham::orderBy('ten_loai')->get(),
        ]);
    }

    public function postThem(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules(), $this->messages());
        $validated['hinh_anh'] = $this->luuHinhAnh($request);
        $validated['trang_thai'] = $request->boolean('trang_thai');

        $sanPham = SanPham::create($validated);
        NhatKyService::ghi($request, 'them', 'san_pham', $sanPham->id, 'Thêm sản phẩm '.$sanPham->sku);

        return redirect()->route('sanpham.danhsach')->with('success', 'Đã thêm sản phẩm.');
    }

    public function getSua(SanPham $sanPham): View
    {
        return view('sanpham.form', [
            'sanPham' => $sanPham,
            'loais' => LoaiSanPham::orderBy('ten_loai')->get(),
        ]);
    }

    public function postSua(Request $request, SanPham $sanPham): RedirectResponse
    {
        $validated = $request->validate($this->rules($sanPham), $this->messages());
        $hinhAnh = $this->luuHinhAnh($request);
        if ($hinhAnh !== null) {
            $this->xoaHinhAnh($sanPham->hinh_anh);
            $validated['hinh_anh'] = $hinhAnh;
        }
        $validated['trang_thai'] = $request->boolean('trang_thai');

        $sanPham->update($validated);
        NhatKyService::ghi($request, 'sua', 'san_pham', $sanPham->id, 'Cập nhật sản phẩm '.$sanPham->sku);

        return redirect()->route('sanpham.danhsach')->with('success', 'Đã cập nhật sản phẩm.');
    }

    public function getXoa(SanPham $sanPham): RedirectResponse
    {
        if ($sanPham->chiTietDonHangs()->exists() || $sanPham->bienDongKhos()->exists()) {
            return back()->with('error', 'Không thể xóa sản phẩm đã phát sinh giao dịch.');
        }

        $this->xoaHinhAnh($sanPham->hinh_anh);
        $sanPham->delete();
        NhatKyService::ghi($request, 'xoa', 'san_pham', $sanPham->id, 'Xóa sản phẩm '.$sanPham->sku);

        return redirect()->route('sanpham.danhsach')->with('success', 'Đã xóa sản phẩm.');
    }

    public function getTimKiem(Request $request)
    {
        $tuKhoa = trim((string) $request->query('q', ''));

        if ($tuKhoa === '') {
            return response()->json([]);
        }

        $sanPhams = SanPham::query()
            ->where('trang_thai', true)
            ->where('soluong', '>', 0)
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->where(function ($query) use ($tuKhoa, $request): void {
                if ($request->boolean('chinhxac')) {
                    $query->where('barcode', $tuKhoa);
                    return;
                }
                $query->where('ten_sanpham', 'like', '%'.$tuKhoa.'%')
                    ->orWhere('sku', 'like', '%'.$tuKhoa.'%')
                    ->orWhere('barcode', 'like', '%'.$tuKhoa.'%');
            })
            ->limit(10)
            ->get(['id', 'sku', 'barcode', 'ten_sanpham', 'gia_ban', 'soluong', 'hinh_anh']);

        return response()->json($sanPhams);
    }

    public function getTem(Request $request, SanPham $sanPham): View
    {
        $validated = $request->validate([
            'soluong_tem' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [
            'soluong_tem.integer' => 'Số lượng tem phải là số nguyên.',
            'soluong_tem.min' => 'Số lượng tem phải lớn hơn 0.',
            'soluong_tem.max' => 'Số lượng tem không được vượt quá 100.',
        ]);

        $ma = $sanPham->barcode ?: $sanPham->sku;
        $generator = new BarcodeGeneratorSVG();

        return view('sanpham.tem', [
            'sanPham' => $sanPham,
            'soLuongTem' => $validated['soluong_tem'] ?? 1,
            'maVach' => $ma,
            'barcodeSvg' => $generator->getBarcode($ma, $generator::TYPE_CODE_128),
        ]);
    }

    private function rules(?SanPham $sanPham = null): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'sku' => ['required', 'string', 'max:50', Rule::unique('products', 'sku')->ignore($sanPham)],
            'barcode' => ['nullable', 'string', 'max:50', Rule::unique('products', 'barcode')->ignore($sanPham)],
            'ten_sanpham' => ['required', 'string', 'max:255'],
            'don_vi' => ['required', 'string', 'max:20'],
            'gia_nhap' => ['required', 'numeric', 'min:0'],
            'gia_ban' => ['required', 'numeric', 'min:0'],
            'soluong' => ['required', 'integer', 'min:0'],
            'soluong_toithieu' => ['required', 'integer', 'min:0'],
            'hinh_anh' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    private function messages(): array
    {
        return [
            'category_id.required' => 'Vui lòng chọn loại sản phẩm.',
            'category_id.exists' => 'Loại sản phẩm không tồn tại.',
            'sku.required' => 'Vui lòng nhập SKU.',
            'sku.unique' => 'SKU đã tồn tại.',
            'barcode.unique' => 'Barcode đã tồn tại.',
            'ten_sanpham.required' => 'Vui lòng nhập tên sản phẩm.',
            'gia_nhap.min' => 'Giá nhập không được âm.',
            'gia_ban.min' => 'Giá bán không được âm.',
            'soluong.min' => 'Số lượng không được âm.',
            'soluong_toithieu.min' => 'Ngưỡng tồn tối thiểu không được âm.',
            'hinh_anh.image' => 'Tệp tải lên phải là hình ảnh.',
            'hinh_anh.mimes' => 'Hình ảnh phải có định dạng JPG, JPEG, PNG hoặc WEBP.',
            'hinh_anh.max' => 'Hình ảnh không được vượt quá 2MB.',
        ];
    }

    private function luuHinhAnh(Request $request): ?string
    {
        if (! $request->hasFile('hinh_anh')) {
            return null;
        }

        return $request->file('hinh_anh')->store('sanpham', 'public');
    }

    private function xoaHinhAnh(?string $duongDan): void
    {
        if ($duongDan !== null && ! str_starts_with($duongDan, 'upload/')) {
            Storage::disk('public')->delete($duongDan);
        } elseif ($duongDan !== null) {
            @unlink(public_path($duongDan));
        }
    }
}
