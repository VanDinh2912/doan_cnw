<?php

namespace App\Http\Controllers;

use App\Models\ChiTietHoaDonNhap;
use App\Models\HoaDonNhap;
use App\Models\LoaiSanPham;
use App\Models\NhaCungCap;
use App\Models\SanPham;
use App\Services\HoaDonNhapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HoaDonNhapController extends Controller
{
    public function getDanhSach(): View
    {
        return view('hoadonnhap.danhsach', ['hoaDons' => HoaDonNhap::with('nhaCungCap')->latest()->paginate(15)]);
    }

    public function getThem(): View
    {
        return view('hoadonnhap.form', [
            'hoaDon' => new HoaDonNhap(['thue_suat' => 8, 'hinh_thuc_thanh_toan' => 'chuyenkhoan']),
            'nhaCungCaps' => NhaCungCap::orderBy('ten_nhacungcap')->get(),
            'sanPhams' => SanPham::orderBy('ten_sanpham')->get(),
            'loais' => LoaiSanPham::orderBy('ten_loai')->get(),
        ]);
    }

    public function postThem(Request $request): RedirectResponse
    {
        $hoaDon = DB::transaction(fn () => $this->luu($request, new HoaDonNhap()));
        return redirect()->route('hoadonnhap.xem', $hoaDon)->with('success', 'Đã lưu hóa đơn nhập tạm.');
    }

    public function getXem(HoaDonNhap $hoaDon): View
    {
        $hoaDon->load(['nhaCungCap', 'chiTiets.sanPham', 'nguoiTao', 'nguoiNhap']);
        return view('hoadonnhap.xem', compact('hoaDon'));
    }

    public function getSua(HoaDonNhap $hoaDon): View
    {
        abort_unless($hoaDon->trang_thai === 'nhap_tam', 422, 'Chỉ được sửa hóa đơn nhập tạm.');
        return view('hoadonnhap.form', [
            'hoaDon' => $hoaDon->load('chiTiets'),
            'nhaCungCaps' => NhaCungCap::orderBy('ten_nhacungcap')->get(),
            'sanPhams' => SanPham::orderBy('ten_sanpham')->get(),
            'loais' => LoaiSanPham::orderBy('ten_loai')->get(),
        ]);
    }

    public function postSua(Request $request, HoaDonNhap $hoaDon): RedirectResponse
    {
        abort_unless($hoaDon->trang_thai === 'nhap_tam', 422, 'Chỉ được sửa hóa đơn nhập tạm.');
        DB::transaction(function () use ($request, $hoaDon): void {
            $hoaDon->chiTiets()->delete();
            $this->luu($request, $hoaDon);
        });
        return redirect()->route('hoadonnhap.xem', $hoaDon)->with('success', 'Đã cập nhật hóa đơn nhập.');
    }

    public function postXacNhan(Request $request, HoaDonNhap $hoaDon, HoaDonNhapService $service): RedirectResponse
    {
        try {
            $warnings = $service->xacNhanNhapKho($hoaDon, $request->user());
            $response = redirect()->route('hoadonnhap.xem', $hoaDon)->with('success', 'Đã xác nhận nhập kho.');
            return $warnings === [] ? $response : $response->with('warning', implode(' ', $warnings));
        } catch (\Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function postHuy(HoaDonNhap $hoaDon): RedirectResponse
    {
        abort_unless($hoaDon->trang_thai === 'nhap_tam', 422, 'Chỉ được hủy hóa đơn nhập tạm.');
        $hoaDon->update(['trang_thai' => 'da_huy']);
        return back()->with('success', 'Đã hủy hóa đơn nhập.');
    }

    private function luu(Request $request, HoaDonNhap $hoaDon): HoaDonNhap
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'so_hoadon' => ['required', 'string', 'max:30'],
            'ky_hieu' => ['required', 'string', 'max:20'],
            'mau_so' => ['nullable', 'string', 'max:10'],
            'ngay_hoadon' => ['required', 'date', 'before_or_equal:today'],
            'thue_suat' => ['required', 'numeric', 'in:0,5,8,10'],
            'hinh_thuc_thanh_toan' => ['required', 'in:tienmat,chuyenkhoan,congno'],
            'ghi_chu' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'distinct', 'exists:products,id'],
            'items.*.category_id' => ['nullable', 'exists:categories,id'],
            'items.*.ten_hang' => ['required', 'string', 'max:255'],
            'items.*.don_vi' => ['required', 'string', 'max:20'],
            'items.*.soluong' => ['required', 'integer', 'min:1'],
            'items.*.don_gia' => ['required', 'numeric', 'min:0'],
        ]);
        if (HoaDonNhap::where('supplier_id', $validated['supplier_id'])
            ->where('ky_hieu', $validated['ky_hieu'])->where('so_hoadon', $validated['so_hoadon'])
            ->when($hoaDon->exists, fn ($query) => $query->where('id', '<>', $hoaDon->id))->exists()) {
            abort(422, 'Hóa đơn nhập đã tồn tại.');
        }
        $total = 0;
        foreach ($validated['items'] as $item) {
            if (empty($item['product_id']) && empty($item['category_id'])) {
                abort(422, 'Mỗi dòng phải có sản phẩm hoặc loại sản phẩm.');
            }
            $total += $item['soluong'] * $item['don_gia'];
        }
        $tax = (int) round($total * ((float) $validated['thue_suat']) / 100);
        $hoaDon->fill([
            'ma_phieu' => $hoaDon->ma_phieu ?: 'HN'.now()->format('YmdHis').random_int(10, 99),
            'supplier_id' => $validated['supplier_id'], 'user_id' => $request->user()->id,
            'so_hoadon' => $validated['so_hoadon'], 'ky_hieu' => $validated['ky_hieu'], 'mau_so' => $validated['mau_so'] ?? null,
            'ngay_hoadon' => $validated['ngay_hoadon'], 'trang_thai' => 'nhap_tam',
            'hinh_thuc_thanh_toan' => $validated['hinh_thuc_thanh_toan'], 'tong_tien_hang' => $total,
            'thue_suat' => $validated['thue_suat'], 'tien_thue' => $tax, 'tong_thanh_toan' => $total + $tax,
            'ghi_chu' => $validated['ghi_chu'] ?? null,
        ]);
        $hoaDon->save();
        foreach ($validated['items'] as $index => $item) {
            $hoaDon->chiTiets()->create([
                'stt' => $index + 1, 'product_id' => $item['product_id'] ?? null, 'category_id' => $item['category_id'] ?? null,
                'ten_hang' => $item['ten_hang'], 'don_vi' => $item['don_vi'], 'soluong' => $item['soluong'],
                'don_gia' => $item['don_gia'], 'thanh_tien' => $item['soluong'] * $item['don_gia'],
            ]);
        }
        return $hoaDon;
    }
}
