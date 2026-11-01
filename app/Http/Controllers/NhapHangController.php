<?php

namespace App\Http\Controllers;

use App\Models\BienDongKho;
use App\Models\ChiTietDonNhap;
use App\Models\DonNhap;
use App\Models\NhaCungCap;
use App\Models\SanPham;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;
use App\Services\NhatKyService;

class NhapHangController extends Controller
{
    public function getNhaCungCap(Request $request): View
    {
        $nhaCungCaps = NhaCungCap::query()
            ->when($request->filled('q'), fn ($query) => $query->where('ten_nhacungcap', 'like', '%'.$request->string('q').'%'))
            ->latest()->paginate(15)->withQueryString();

        return view('nhaphang.nhacungcap', compact('nhaCungCaps'));
    }

    public function getThemNhaCungCap(): View
    {
        return view('nhaphang.nhacungcap-form', ['nhaCungCap' => new NhaCungCap()]);
    }

    public function postThemNhaCungCap(Request $request): RedirectResponse
    {
        $nhaCungCap = NhaCungCap::create($this->validateNhaCungCap($request));
        NhatKyService::ghi($request, 'them', 'nha_cung_cap', $nhaCungCap->id, 'Thêm nhà cung cấp');

        return redirect()->route('nhaphang.nhacungcap')->with('success', 'Đã thêm nhà cung cấp.');
    }

    public function getSuaNhaCungCap(NhaCungCap $nhaCungCap): View
    {
        return view('nhaphang.nhacungcap-form', compact('nhaCungCap'));
    }

    public function postSuaNhaCungCap(Request $request, NhaCungCap $nhaCungCap): RedirectResponse
    {
        $nhaCungCap->update($this->validateNhaCungCap($request));
        NhatKyService::ghi($request, 'sua', 'nha_cung_cap', $nhaCungCap->id, 'Cập nhật nhà cung cấp');

        return redirect()->route('nhaphang.nhacungcap')->with('success', 'Đã cập nhật nhà cung cấp.');
    }

    public function xoaNhaCungCap(NhaCungCap $nhaCungCap): RedirectResponse
    {
        if ($nhaCungCap->donNhaps()->exists()) {
            return back()->with('error', 'Không thể xóa nhà cung cấp đã có đơn nhập.');
        }

        $nhaCungCap->delete();
        NhatKyService::ghi($request, 'xoa', 'nha_cung_cap', $nhaCungCap->id, 'Xóa nhà cung cấp');

        return back()->with('success', 'Đã xóa nhà cung cấp.');
    }

    public function getDonNhap(): View
    {
        return view('nhaphang.donnhap', [
            'donNhaps' => DonNhap::with('nhaCungCap')->latest()->paginate(15),
        ]);
    }

    public function getThemDonNhap(): View
    {
        return view('nhaphang.donnhap-form', [
            'nhaCungCaps' => NhaCungCap::orderBy('ten_nhacungcap')->get(),
            'sanPhams' => SanPham::where('trang_thai', true)->orderBy('ten_sanpham')->get(),
        ]);
    }

    public function postThemDonNhap(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'ngay_dukien' => ['nullable', 'date'],
            'ghi_chu' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'distinct', 'exists:products,id'],
            'items.*.soluong_dat' => ['required', 'integer', 'min:1'],
            'items.*.don_gia_nhap' => ['required', 'numeric', 'min:0'],
        ]);

        $donNhap = DB::transaction(function () use ($validated, $request): DonNhap {
            $donNhap = DonNhap::create([
                'ma_don' => 'PN'.now()->format('YmdHis').random_int(10, 99),
                'supplier_id' => $validated['supplier_id'],
                'user_id' => $request->user()->id,
                'trang_thai' => 'cho_duyet',
                'ngay_dukien' => $validated['ngay_dukien'] ?? null,
                'ghi_chu' => $validated['ghi_chu'] ?? null,
                'tong_tien' => 0,
            ]);
            $tongTien = 0;
            foreach ($validated['items'] as $item) {
                $thanhTien = $item['soluong_dat'] * $item['don_gia_nhap'];
                $donNhap->chiTiets()->create([
                    'product_id' => $item['product_id'],
                    'soluong_dat' => $item['soluong_dat'],
                    'don_gia_nhap' => $item['don_gia_nhap'],
                    'thanh_tien' => $thanhTien,
                ]);
                $tongTien += $thanhTien;
            }
            $donNhap->update(['tong_tien' => $tongTien]);

            return $donNhap;
        });
        NhatKyService::ghi($request, 'tao_don_nhap', 'don_nhap', $donNhap->id, 'Tạo đơn '.$donNhap->ma_don);

        return redirect()->route('nhaphang.donnhap.chitiet', $donNhap)->with('success', 'Đã tạo đơn nhập.');
    }

    public function getChiTietDonNhap(DonNhap $donNhap): View
    {
        $donNhap->load(['nhaCungCap', 'chiTiets.sanPham', 'nguoiTao']);

        return view('nhaphang.donnhap-chitiet', compact('donNhap'));
    }

    public function capNhatTrangThai(Request $request, DonNhap $donNhap): RedirectResponse
    {
        $validated = $request->validate(['trang_thai' => ['required', 'in:cho_giao,dang_giao,da_nhan,da_huy']]);
        $allowed = [
            'cho_duyet' => ['cho_giao', 'da_huy'],
            'cho_giao' => ['dang_giao', 'da_huy'],
            'dang_giao' => ['da_nhan', 'da_huy'],
        ];
        if (!in_array($validated['trang_thai'], $allowed[$donNhap->trang_thai] ?? [], true)) {
            return back()->with('error', 'Trạng thái chuyển không hợp lệ.');
        }
        if ($validated['trang_thai'] === 'da_nhan') {
            $this->nhanHang($donNhap, $request);
        } else {
            $donNhap->update(['trang_thai' => $validated['trang_thai']]);
        }

        return back()->with('success', 'Đã cập nhật trạng thái đơn nhập.');
    }

    private function nhanHang(DonNhap $donNhap, Request $request): void
    {
        DB::transaction(function () use ($donNhap, $request): void {
            $donNhap = DonNhap::query()->lockForUpdate()->with('chiTiets')->findOrFail($donNhap->id);
            if ($donNhap->trang_thai !== 'dang_giao') {
                abort(422, 'Chỉ được nhận đơn đang giao.');
            }
            foreach ($donNhap->chiTiets as $item) {
                $sanPham = SanPham::query()->lockForUpdate()->findOrFail($item->product_id);
                $sanPham->increment('soluong', $item->soluong_dat);
                $sanPham->update(['gia_nhap' => $item->don_gia_nhap]);
                $item->update(['soluong_nhan' => $item->soluong_dat]);
                BienDongKho::create([
                    'product_id' => $sanPham->id,
                    'user_id' => $request->user()->id,
                    'purchase_order_id' => $donNhap->id,
                    'invoice_id' => null,
                    'loai' => 'nhap',
                    'soluong' => $item->soluong_dat,
                    'ghi_chu' => 'Nhập từ đơn '.$donNhap->ma_don,
                ]);
            }
            $donNhap->update([
                'trang_thai' => 'da_nhan',
                'nguoi_nhan_id' => $request->user()->id,
                'ngay_nhan' => now(),
            ]);
            NhatKyService::ghi($request, 'nhan_hang', 'don_nhap', $donNhap->id, 'Nhận hàng từ đơn '.$donNhap->ma_don);
        });
    }

    private function validateNhaCungCap(Request $request): array
    {
        return $request->validate([
            'ma_so_thue' => ['required', 'string', 'max:20'],
            'ten_nhacungcap' => ['required', 'string', 'max:150'],
            'nguoi_lienhe' => ['nullable', 'string', 'max:100'],
            'so_dienthoai' => ['nullable', 'string', 'max:15'],
            'dia_chi' => ['nullable', 'string', 'max:255'],
            'ghi_chu' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
