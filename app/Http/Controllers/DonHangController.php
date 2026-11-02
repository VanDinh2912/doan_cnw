<?php

namespace App\Http\Controllers;

use App\Models\BienDongKho;
use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\LoaiSanPham;
use App\Models\KhachHang;
use App\Models\SanPham;
use App\Services\ThanhToanChuyenKhoanService;
use App\Services\VietQrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class DonHangController extends Controller
{
    public function getPos(): View
    {
        return view('pos.index', [
            'loais' => LoaiSanPham::orderBy('ten_loai')->get(),
        ]);
    }

    /**
     * Trả về URL ảnh VietQR dựa trên giỏ hàng trong session.
     * GET /pos/vietqr?giam_gia_phantram=0
     */
    public function getVietQr(Request $request, VietQrService $vietQr): JsonResponse
    {
        $gioHang = collect($request->session()->get('gio_hang', []));
        $tongTien = $gioHang->sum(fn (array $item) => $item['gia_ban'] * $item['soluong']);

        $giamGia = min(100, max(0, (float) $request->query('giam_gia_phantram', 0)));
        $thanhToan = (int) round($tongTien * (1 - $giamGia / 100));

        $url = $vietQr->url($thanhToan, 'Thanh toan Web POS');

        return response()->json([
            'enabled' => $vietQr->enabled(),
            'url'     => $url,
            'amount'  => $thanhToan,
        ]);
    }

    /**
     * Tạo hóa đơn chờ thanh toán (cho_thanh_toan) và trả về URL ảnh VietQR kèm hạn chờ.
     * POST /donhang/taoqr
     */
    public function postTaoQr(Request $request, ThanhToanChuyenKhoanService $service, VietQrService $vietQr): JsonResponse
    {
        $validated = $request->validate([
            'giam_gia_phantram' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'customer_id'       => ['nullable', 'integer', 'exists:customers,id'],
            'diem_su_dung'      => ['nullable', 'integer', 'min:0'],
        ]);

        $gioHang = $request->session()->get('gio_hang', []);
        if (empty($gioHang)) {
            return response()->json(['error' => 'Giỏ hàng đang trống.'], 422);
        }

        try {
            $donHang = $service->taoHoaDonChoThanhToan($gioHang, $validated, $request->user());

            // Xóa giỏ trong session sau khi đã chuyển thành hóa đơn cho_thanh_toan và giữ tồn kho
            $request->session()->forget('gio_hang');

            $phut = (int) config('pos.cho_thanh_toan_phut', 15);
            $hetHanLuc = $donHang->created_at->addMinutes($phut)->toIso8601String();

            // Sinh URL VietQR với nội dung là mã hóa đơn
            $urlQr = $vietQr->url((int) $donHang->tong_thanh_toan, $donHang->ma_hoadon);

            return response()->json([
                'id'              => $donHang->id,
                'ma_hoadon'       => $donHang->ma_hoadon,
                'tong_thanh_toan' => (int) $donHang->tong_thanh_toan,
                'url_qr'          => $urlQr,
                'het_han_luc'     => $hetHanLuc,
            ]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Tra cứu trạng thái hóa đơn cho POS polling.
     * Áp dụng cơ chế hủy lười (lazy expiration) nếu hóa đơn cho_thanh_toan đã quá hạn.
     * GET /donhang/trangthai/{donHang}
     */
    public function getTrangThai(DonHang $donHang, ThanhToanChuyenKhoanService $service): JsonResponse
    {
        $phut = (int) config('pos.cho_thanh_toan_phut', 15);
        if ($donHang->trang_thai === 'cho_thanh_toan' && $donHang->created_at->addMinutes($phut)->isPast()) {
            $service->huyChoThanhToan($donHang, 'Quá hạn chờ thanh toán (hủy lười)');
            $donHang->refresh();
        }

        return response()->json([
            'trang_thai' => $donHang->trang_thai,
            'url_in'     => $donHang->trang_thai === 'da_thanh_toan' ? route('donhang.inhoadon', $donHang) : null,
        ]);
    }

    /**
     * Xác nhận thanh toán thủ công từ thu ngân (phương án dự phòng khi mất mạng / khách chuyển không quét webhook).
     * POST /donhang/xacnhan/{donHang}
     */
    public function postXacNhanThanhToan(DonHang $donHang, ThanhToanChuyenKhoanService $service): JsonResponse
    {
        $res = $service->hoanTatThanhToan($donHang, null, auth()->user(), 'Thu ngân xác nhận thủ công');

        if (! $res['success']) {
            return response()->json(['error' => $res['message']], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã xác nhận thanh toán thành công.',
            'url_in'  => route('donhang.inhoadon', $donHang),
        ]);
    }

    /**
     * Thu ngân chủ động hủy chờ thanh toán (khách đổi ý sang tiền mặt hoặc không mua nữa).
     * POST /donhang/huycho/{donHang}
     */
    public function postHuyCho(DonHang $donHang, ThanhToanChuyenKhoanService $service): JsonResponse
    {
        if ($donHang->trang_thai !== 'cho_thanh_toan') {
            return response()->json(['error' => 'Chỉ có thể hủy hóa đơn đang chờ thanh toán.'], 422);
        }

        $res = $service->huyChoThanhToan($donHang, 'Thu ngân hủy chờ thanh toán');

        if (! $res['success']) {
            return response()->json(['error' => $res['message']], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã hủy chờ thanh toán và hoàn trả tồn kho.',
        ]);
    }

    public function getDanhSach(Request $request): View
    {
        $validated = $request->validate([
            'tu_ngay' => ['nullable', 'date'],
            'den_ngay' => ['nullable', 'date', 'after_or_equal:tu_ngay'],
        ], [
            'tu_ngay.date' => 'Ngày bắt đầu không hợp lệ.',
            'den_ngay.date' => 'Ngày kết thúc không hợp lệ.',
            'den_ngay.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
        ]);

        $truyVan = DonHang::with('user')->latest();
        if (! empty($validated['tu_ngay'])) {
            $truyVan->whereDate('created_at', '>=', $validated['tu_ngay']);
        }
        if (! empty($validated['den_ngay'])) {
            $truyVan->whereDate('created_at', '<=', $validated['den_ngay']);
        }

        return view('donhang.danhsach', [
            'donHangs' => $truyVan->paginate(20)->withQueryString(),
            'tuNgay' => $validated['tu_ngay'] ?? '',
            'denNgay' => $validated['den_ngay'] ?? '',
        ]);
    }

    public function getInHoaDon(Request $request, DonHang $donHang): View
    {
        if ($donHang->trang_thai !== 'da_thanh_toan') {
            abort(403, 'Chỉ có thể in hóa đơn đã thanh toán.');
        }

        $donHang->load(['user', 'chiTietDonHangs.sanPham']);

        $kho = $request->query('kho', 'k80');
        if (! in_array($kho, ['k58', 'k80'], true)) {
            $kho = 'k80';
        }

        return view('donhang.hoadon', ['donHang' => $donHang, 'kho' => $kho]);
    }

    public function postThanhToan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'giam_gia_phantram' => ['required', 'numeric', 'min:0', 'max:100'],
            'tien_khach_dua' => ['required', 'numeric', 'min:0'],
            'phuong_thuc' => ['required', 'in:tienmat,chuyenkhoan'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'diem_su_dung' => ['nullable', 'integer', 'min:0'],
        ], [
            'giam_gia_phantram.min' => 'Giảm giá không được âm.',
            'giam_gia_phantram.max' => 'Giảm giá không được vượt quá 100%.',
            'tien_khach_dua.min' => 'Tiền khách đưa không được âm.',
            'phuong_thuc.in' => 'Phương thức thanh toán không hợp lệ.',
        ]);

        $gioHang = collect($request->session()->get('gio_hang', []))
            ->sortBy(fn (array $item, string|int $key): int => (int) ($item['id'] ?? $key))
            ->all();
        if ($gioHang === []) {
            return back()->with('error', 'Giỏ hàng đang trống.');
        }

        try {
            $donHang = DB::transaction(function () use ($validated, $gioHang, $request): DonHang {
                $tongTien = 0;
                $sanPhams = [];

                foreach ($gioHang as $item) {
                    $sanPham = SanPham::query()->lockForUpdate()->find($item['id']);
                    if (! $sanPham || ! $sanPham->trang_thai || $item['soluong'] > $sanPham->soluong) {
                        throw new \RuntimeException('Tồn kho đã thay đổi, vui lòng kiểm tra lại giỏ hàng.');
                    }

                    $soLuong = (int) $item['soluong'];
                    $thanhTien = (float) $sanPham->gia_ban * $soLuong;
                    $tongTien += $thanhTien;
                    $sanPhams[] = [$sanPham, $soLuong, $thanhTien];
                }

                $giamGiaPhanTram = (float) $validated['giam_gia_phantram'];
                $tienGiam = round($tongTien * $giamGiaPhanTram / 100);
                $khachHang = null;
                $diemSuDung = (int) ($validated['diem_su_dung'] ?? 0);
                $tienGiamDiem = 0;
                if (! empty($validated['customer_id'])) {
                    $khachHang = KhachHang::query()->lockForUpdate()->findOrFail($validated['customer_id']);
                    if ($diemSuDung > $khachHang->diem_tichluy) {
                        throw new \RuntimeException('Số điểm sử dụng vượt quá điểm hiện có.');
                    }
                    $tienGiamDiem = $diemSuDung * config('pos.gia_tri_diem');
                    if ($tienGiam + $tienGiamDiem > $tongTien) {
                        throw new \RuntimeException('Số điểm sử dụng vượt quá giá trị hóa đơn.');
                    }
                } elseif ($diemSuDung > 0) {
                    throw new \RuntimeException('Cần chọn khách hàng trước khi dùng điểm.');
                }
                $tongThanhToan = $tongTien - $tienGiam - $tienGiamDiem;
                $diemTichLuyNhan = $khachHang ? (int) floor($tongThanhToan / config('pos.diem_moi_dong')) : 0;
                $tienKhachDua = $validated['phuong_thuc'] === 'chuyenkhoan'
                    ? $tongThanhToan
                    : (float) $validated['tien_khach_dua'];

                if ($validated['phuong_thuc'] === 'tienmat' && $tienKhachDua < $tongThanhToan) {
                    throw new \RuntimeException('Tiền khách đưa chưa đủ thanh toán.');
                }

                $maHoaDon = $this->taoMaHoaDon();
                $donHang = DonHang::create([
                    'ma_hoadon' => $maHoaDon,
                    'user_id' => $request->user()->id,
                    'customer_id' => $khachHang?->id,
                    'tong_tien' => $tongTien,
                    'giam_gia_phantram' => $giamGiaPhanTram,
                    'tien_giam' => $tienGiam,
                    'diem_su_dung' => $diemSuDung,
                    'tien_giam_diem' => $tienGiamDiem,
                    'diem_tichluy_nhan' => $diemTichLuyNhan,
                    'tong_thanh_toan' => $tongThanhToan,
                    'tien_khach_dua' => $tienKhachDua,
                    'tien_thua' => $validated['phuong_thuc'] === 'tienmat' ? $tienKhachDua - $tongThanhToan : 0,
                    'phuong_thuc' => $validated['phuong_thuc'],
                    'trang_thai' => 'da_thanh_toan',
                    'thoi_gian_thanh_toan' => now(),
                ]);

                foreach ($sanPhams as [$sanPham, $soLuong, $thanhTien]) {
                    ChiTietDonHang::create([
                        'invoice_id' => $donHang->id,
                        'product_id' => $sanPham->id,
                        'soluong' => $soLuong,
                        'don_gia' => $sanPham->gia_ban,
                        'gia_von' => $sanPham->gia_nhap,
                        'thanh_tien' => $thanhTien,
                    ]);
                    $sanPham->decrement('soluong', $soLuong);
                    BienDongKho::create([
                        'product_id' => $sanPham->id,
                        'user_id' => $request->user()->id,
                        'invoice_id' => $donHang->id,
                        'loai' => 'ban',
                        'soluong' => -$soLuong,
                        'ghi_chu' => 'Bán hàng '.$donHang->ma_hoadon,
                    ]);
                }

                if ($khachHang) {
                    $khachHang->update([
                        'diem_tichluy' => $khachHang->diem_tichluy - $diemSuDung + $diemTichLuyNhan,
                    ]);
                }

                return $donHang;
            }, 2);

            $request->session()->forget('gio_hang');

            return redirect()->route('donhang.inhoadon', $donHang)->with('success', 'Thanh toán thành công.');
        } catch (Throwable $exception) {
            Log::error('Thanh toán thất bại.', ['message' => $exception->getMessage()]);

            $message = $exception instanceof \RuntimeException
                ? $exception->getMessage()
                : 'Không thể hoàn tất thanh toán. Vui lòng thử lại.';

            return back()->with('error', $message);
        }
    }

    private function taoMaHoaDon(): string
    {
        $ngay = now()->format('Ymd');
        $cuoiCung = DonHang::query()
            ->where('ma_hoadon', 'like', 'HD'.$ngay.'-%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('ma_hoadon');
        $soThuTu = $cuoiCung ? ((int) substr($cuoiCung, -4)) + 1 : 1;

        return sprintf('HD%s-%04d', $ngay, $soThuTu);
    }
}
