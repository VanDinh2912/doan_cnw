<?php

namespace App\Http\Controllers;

use App\Models\DonHang;
use App\Support\CheThongTin;
use App\Support\KiemTraSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Màn hình nhân viên xử lý đơn online (P3): danh sách lọc/phân trang,
 * chi tiết đầy đủ (SĐT/địa chỉ chỉ cho nhân viên, no-store), badge số đơn
 * cần xử lý. Guard schema: DB thiếu cột online → thông báo "Cần áp migration".
 */
class DonHangOnlineController extends Controller
{
    private const PHAN_TRANG = 20;

    public function getDanhSach(Request $request): View
    {
        if (! $this->coCotOnline()) {
            return view('donhangonline.danhsach', [
                'thieuMigration' => true,
                'donHangs' => null,
            ]);
        }

        $validated = $request->validate([
            'hinh_thuc_nhan' => ['nullable', 'in:tai_cua_hang,giao_tan_noi'],
            'trang_thai' => ['nullable', 'in:cho_thanh_toan,da_thanh_toan,da_huy'],
            'trang_thai_giao' => ['nullable', 'string', 'max:50'],
            'tu_ngay' => ['nullable', 'date'],
            'den_ngay' => ['nullable', 'date', 'after_or_equal:tu_ngay'],
            'tim' => ['nullable', 'string', 'max:50'],
        ], [
            'den_ngay.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
        ]);

        $truyVan = DonHang::where('kenh', 'online')->with('khachHang')->latest();

        if (! empty($validated['hinh_thuc_nhan']) && KiemTraSchema::coCot('invoices', 'hinh_thuc_nhan')) {
            $truyVan->where('hinh_thuc_nhan', $validated['hinh_thuc_nhan']);
        }
        if (! empty($validated['trang_thai'])) {
            $truyVan->where('trang_thai', $validated['trang_thai']);
        }
        if (! empty($validated['trang_thai_giao']) && KiemTraSchema::coCot('invoices', 'trang_thai_giao')) {
            $truyVan->where('trang_thai_giao', $validated['trang_thai_giao']);
        }
        if (! empty($validated['tu_ngay'])) {
            $truyVan->whereDate('created_at', '>=', $validated['tu_ngay']);
        }
        if (! empty($validated['den_ngay'])) {
            $truyVan->whereDate('created_at', '<=', $validated['den_ngay']);
        }
        if (! empty($validated['tim'])) {
            $kw = '%'.$validated['tim'].'%';
            $truyVan->where(function ($q) use ($kw): void {
                $q->where('ma_hoadon', 'like', $kw)
                    ->orWhere('ma_tra_cuu', 'like', $kw)
                    ->orWhere('ten_khach_dat', 'like', $kw)
                    ->orWhere('ten_nguoi_nhan', 'like', $kw)
                    ->orWhere('so_dienthoai_dat', 'like', $kw)
                    ->orWhere('sdt_nguoi_nhan', 'like', $kw);
            });
        }

        return view('donhangonline.danhsach', [
            'thieuMigration' => false,
            'donHangs' => $truyVan->paginate(self::PHAN_TRANG)->withQueryString(),
            'filters' => $validated,
            'coCotGiao' => KiemTraSchema::coCot('invoices', 'trang_thai_giao'),
        ]);
    }

    public function getChiTiet(DonHang $donHang): View|\Illuminate\Http\Response
    {
        abort_unless($donHang->kenh === 'online', 404);
        $donHang->load(['chiTietDonHangs.sanPham', 'khachHang']);

        // Địa chỉ/SĐT đầy đủ chỉ cho nhân viên → cấm cache mọi tầng.
        return response()
            ->view('donhangonline.chitiet', ['don' => $donHang])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * Badge menu: số đơn cần xử lý = giao tận nơi chờ xác nhận
     * + pickup online còn chờ thanh toán chưa quá hạn.
     */
    public function getSoLuongCanXuLy(): JsonResponse
    {
        $soLuong = 0;
        if ($this->coCotOnline()) {
            $soLuong += DonHang::where('kenh', 'online')
                ->where('trang_thai', 'da_thanh_toan')
                ->where('hinh_thuc_nhan', 'giao_tan_noi')
                ->where('trang_thai_giao', 'cho_xac_nhan')
                ->count();

            $soLuong += DonHang::where('kenh', 'online')
                ->where('phuong_thuc', 'tienmat')
                ->where('trang_thai', 'cho_thanh_toan')
                ->where(function ($q): void {
                    $q->whereNull('han_giu')->orWhere('han_giu', '>', now());
                })
                ->count();
        }

        return response()->json(['so_luong' => $soLuong])->header('Cache-Control', 'no-store');
    }

    private function coCotOnline(): bool
    {
        return KiemTraSchema::coBang('invoices')
            && KiemTraSchema::coCot('invoices', 'kenh')
            && KiemTraSchema::coCot('invoices', 'hinh_thuc_nhan');
    }

    /** Mask SĐT cho danh sách (dùng chung view). */
    public static function maskSdt(?string $sdt): string
    {
        return CheThongTin::soDienThoai($sdt);
    }
}
