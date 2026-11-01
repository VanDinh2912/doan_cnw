<?php

namespace App\Http\Controllers;

use App\Models\BienDongKho;
use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\SanPham;
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
        return view('pos.index');
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

    public function getInHoaDon(DonHang $donHang): View
    {
        $donHang->load(['user', 'chiTietDonHangs.sanPham']);

        return view('donhang.hoadon', ['donHang' => $donHang]);
    }

    public function postThanhToan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'giam_gia_phantram' => ['required', 'numeric', 'min:0', 'max:100'],
            'tien_khach_dua' => ['required', 'numeric', 'min:0'],
            'phuong_thuc' => ['required', 'in:tienmat,chuyenkhoan'],
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
                $tongThanhToan = $tongTien - $tienGiam;
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
                    'tong_tien' => $tongTien,
                    'giam_gia_phantram' => $giamGiaPhanTram,
                    'tien_giam' => $tienGiam,
                    'tong_thanh_toan' => $tongThanhToan,
                    'tien_khach_dua' => $tienKhachDua,
                    'tien_thua' => $validated['phuong_thuc'] === 'tienmat' ? $tienKhachDua - $tongThanhToan : 0,
                    'phuong_thuc' => $validated['phuong_thuc'],
                ]);

                foreach ($sanPhams as [$sanPham, $soLuong, $thanhTien]) {
                    ChiTietDonHang::create([
                        'invoice_id' => $donHang->id,
                        'product_id' => $sanPham->id,
                        'soluong' => $soLuong,
                        'don_gia' => $sanPham->gia_ban,
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
