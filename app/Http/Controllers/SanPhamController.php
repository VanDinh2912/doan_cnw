<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\LoaiSanPham;
use App\Models\SanPham;
use App\Rules\AnhSanPhamUrl;
use App\Services\TepAnhSanPham;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use Picqer\Barcode\BarcodeGeneratorSVG;
use App\Services\MaDuyetService;
use App\Services\NhatKyService;
use App\Services\PinService;

class SanPhamController extends Controller
{
    public function getDanhSach(Request $request): View
    {
        $loc = (string) $request->query('loc', '');

        return view('sanpham.danhsach', [
            'sanPhams' => SanPham::with(['loaiSanPham', 'hangSanXuat'])
                ->when($loc === 'ngung_ban', fn ($q) => $q->where('trang_thai', false))
                ->when($loc === 'gia_nhap_0', fn ($q) => $q->where('gia_nhap', 0))
                ->orderBy('ten_sanpham')->get(),
            'loc' => $loc,
            'laAdmin' => $request->user()?->role === 'admin',
        ]);
    }

    public function getThem(): View
    {
        return view('sanpham.form', [
            'sanPham' => new SanPham(['trang_thai' => true, 'soluong_toithieu' => 5]),
            'loais' => LoaiSanPham::orderBy('ten_loai')->get(),
            'hangs' => $this->hangsChoForm(null),
            'gioiHanUrlAnh' => AnhSanPhamUrl::gioiHanDoDai(),
        ]);
    }

    public function postThem(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules(null, $request->hasFile('hinh_anh')), $this->messages());
        $urlAnh = is_string($validated['hinh_anh_url'] ?? null) ? trim($validated['hinh_anh_url']) : '';
        unset($validated['hinh_anh'], $validated['hinh_anh_url'], $validated['xoa_anh']);

        $thongBao = 'Đã thêm sản phẩm.';
        if ($request->hasFile('hinh_anh')) {
            $validated['hinh_anh'] = $this->luuHinhAnh($request);
            if ($urlAnh !== '') {
                $thongBao .= ' Đã ưu tiên tệp tải lên thay vì URL.';
            }
        } elseif ($urlAnh !== '') {
            $validated['hinh_anh'] = $urlAnh;
        }
        $validated['trang_thai'] = $request->boolean('trang_thai');

        $sanPham = SanPham::create($validated);
        NhatKyService::ghi($request, 'them', 'san_pham', $sanPham->id, 'Thêm sản phẩm '.$sanPham->sku);

        return redirect()->route('sanpham.danhsach')->with('success', $thongBao);
    }

    public function getSua(SanPham $sanPham): View
    {
        return view('sanpham.form', [
            'sanPham' => $sanPham,
            'loais' => LoaiSanPham::orderBy('ten_loai')->get(),
            'hangs' => $this->hangsChoForm($sanPham),
            'gioiHanUrlAnh' => AnhSanPhamUrl::gioiHanDoDai(),
        ]);
    }

    public function postSua(Request $request, SanPham $sanPham): RedirectResponse
    {
        $validated = $request->validate($this->rules($sanPham, $request->hasFile('hinh_anh')), $this->messages());
        $urlAnh = is_string($validated['hinh_anh_url'] ?? null) ? trim($validated['hinh_anh_url']) : '';
        unset($validated['hinh_anh'], $validated['hinh_anh_url'], $validated['xoa_anh']);

        if ($request->hasFile('hinh_anh')) {
            TepAnhSanPham::xoaNeuLaTepCucBo($sanPham->hinh_anh);
            $validated['hinh_anh'] = $this->luuHinhAnh($request);
        } elseif ($request->boolean('xoa_anh')) {
            TepAnhSanPham::xoaNeuLaTepCucBo($sanPham->hinh_anh);
            $validated['hinh_anh'] = null;
        } elseif ($urlAnh !== '') {
            TepAnhSanPham::xoaNeuLaTepCucBo($sanPham->hinh_anh);
            $validated['hinh_anh'] = $urlAnh;
        }
        $validated['trang_thai'] = $request->boolean('trang_thai');

        // Đổi giá bán / trạng thái bán = thao tác M2 (mã duyệt cho NV,
        // PIN nghiêm trọng cho admin); sửa thông thường chỉ cần M1.
        $doiGia = (float) $validated['gia_ban'] !== (float) $sanPham->gia_ban;
        $doiTrangThai = (int) $validated['trang_thai'] !== (int) $sanPham->trang_thai;
        $canM2 = PinService::bat() && ($doiGia || $doiTrangThai);
        $thamSo = $canM2 ? MaDuyetService::thamSoRequest($request, 'sanpham.postsua') : [];

        $ketQua = DB::transaction(function () use ($request, $sanPham, $validated, $canM2, $thamSo) {
            $kq = ['ok' => true, 'yc' => null, 'response' => null];
            if ($canM2) {
                $kq = MaDuyetService::xacNhanM2($request, 'sanpham.postsua', 'san_pham', $sanPham->id, $thamSo);
                if (! $kq['ok']) {
                    return $kq['response'];
                }
            }
            $sanPham->update($validated);
            MaDuyetService::ketThucXacNhan($kq, $request);

            return null;
        });
        if ($ketQua !== null) {
            return $ketQua;
        }
        NhatKyService::ghi($request, 'sua', 'san_pham', $sanPham->id, 'Cập nhật sản phẩm '.$sanPham->sku);

        return redirect()->route('sanpham.danhsach')->with('success', 'Đã cập nhật sản phẩm.');
    }

    public function getXoa(Request $request, SanPham $sanPham): RedirectResponse
    {
        $daPhatSinhGiaoDich = $sanPham->chiTietDonHangs()->exists()
            || $sanPham->bienDongKhos()->exists()
            || DB::table('stocktake_items')->where('product_id', $sanPham->id)->exists()
            || DB::table('purchase_invoice_items')->where('product_id', $sanPham->id)->exists()
            || DB::table('return_items')->where('product_id', $sanPham->id)->exists();

        if ($daPhatSinhGiaoDich) {
            return back()->with('error', 'Không thể xóa sản phẩm đã phát sinh giao dịch.');
        }

        TepAnhSanPham::xoaNeuLaTepCucBo($sanPham->hinh_anh);
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

        $sanPhams->each->append('anh_url');

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

    /**
     * Dropdown hãng cho form SP: hãng đang kích hoạt + hãng hiện tại của SP
     * (kể cả đã tắt) để khi sửa không mất lựa chọn.
     */
    private function hangsChoForm(?SanPham $sanPham): \Illuminate\Support\Collection
    {
        return Brand::query()
            ->where('kich_hoat', true)
            ->when($sanPham?->brand_id, fn ($q) => $q->orWhere('id', $sanPham->brand_id))
            ->orderBy('ten')
            ->get();
    }

    private function rules(?SanPham $sanPham = null, bool $coTepTaiLen = false): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')],
            'sku' => ['required', 'string', 'max:50', Rule::unique('products', 'sku')->ignore($sanPham)],
            'barcode' => ['nullable', 'string', 'max:50', Rule::unique('products', 'barcode')->ignore($sanPham)],
            'ten_sanpham' => ['required', 'string', 'max:255'],
            'don_vi' => ['required', 'string', 'max:20'],
            'gia_nhap' => ['required', 'numeric', 'min:0'],
            'gia_ban' => ['required', 'numeric', 'min:0'],
            'soluong' => ['required', 'integer', 'min:0'],
            'soluong_toithieu' => ['required', 'integer', 'min:0'],
            'hinh_anh' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            // Có tệp tải lên thì tệp thắng, URL hỏng không được chặn việc lưu tệp.
            'hinh_anh_url' => $coTepTaiLen ? ['nullable'] : ['nullable', new AnhSanPhamUrl()],
            'xoa_anh' => ['nullable', 'boolean'],
        ];
    }

    private function messages(): array
    {
        return [
            'category_id.required' => 'Vui lòng chọn loại sản phẩm.',
            'category_id.exists' => 'Loại sản phẩm không tồn tại.',
            'brand_id.exists' => 'Hãng sản xuất không tồn tại.',
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
}
