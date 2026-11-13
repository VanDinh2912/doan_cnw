<?php

namespace App\Http\Controllers;

use App\Models\DonHang;
use App\Services\NhatKyService;
use App\Services\ThanhToanChuyenKhoanService;
use App\Services\ThongBaoService;
use App\Support\CheThongTin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

/**
 * Đặt hàng online guest PICKUP (P1): checkout giữ hàng qua R1, tra cứu bằng
 * mã + SĐT (SĐT chỉ đi trong thân POST, không lên URL), xem trạng thái / polling
 * và tự hủy. Bằng chứng đã xác thực lưu trong session don_hang_xac_thuc[ma_tra_cuu].
 * Giá / tồn / trạng thái / user_id / customer_id đều tính lại từ DB trong R1.
 */
class DatHangOnlineController extends Controller
{
    private const CART_KEY = 'storefront_cart';
    private const EVIDENCE_KEY = 'don_hang_xac_thuc';

    public function getForm(Request $request): View|RedirectResponse
    {
        $cart = $request->session()->get(self::CART_KEY, []);
        if (empty($cart)) {
            return redirect()->route('storefront.cart.index')->with('error', 'Giỏ hàng đang trống.');
        }

        $token = $request->session()->get('checkout_token');
        if (! $token) {
            $token = Str::random(40);
            $request->session()->put('checkout_token', $token);
        }

        return view('dathang.form', [
            'cart' => $cart,
            'token' => $token,
            'tong' => collect($cart)->sum(fn (array $i): float => (float) $i['gia_ban'] * (int) $i['soluong']),
            'pickupGio' => (int) config('pos.online_pickup_gio', 4),
            'phiGiaoHang' => (int) config('pos.phi_giao_hang', 30000),
            'mienPhiGiaoTu' => (int) config('pos.mien_phi_giao_tu', 0),
        ]);
    }

    public function postDat(Request $request, ThanhToanChuyenKhoanService $service): RedirectResponse
    {
        $validated = $request->validate([
            'ten' => ['required', 'string', 'max:100'],
            'so_dienthoai' => ['required', 'string', 'regex:/^0\d{9,10}$/'],
            'ghi_chu' => ['nullable', 'string', 'max:500'],
            'hinh_thuc_nhan' => ['nullable', 'in:tai_cua_hang,giao_tan_noi'],
            'phuong_thuc' => ['required', 'in:pickup,qr'],
            'checkout_token' => ['required', 'string'],
            // Chỉ validate khi giao_tan_noi
            'ten_nguoi_nhan' => ['nullable', 'required_if:hinh_thuc_nhan,giao_tan_noi', 'string', 'max:100'],
            'sdt_nguoi_nhan' => ['nullable', 'required_if:hinh_thuc_nhan,giao_tan_noi', 'string', 'regex:/^0\d{9,10}$/'],
            'dia_chi_giao' => ['nullable', 'required_if:hinh_thuc_nhan,giao_tan_noi', 'string', 'max:255'],
        ], [
            'ten.required' => 'Vui lòng nhập họ tên.',
            'so_dienthoai.regex' => 'Số điện thoại phải bắt đầu bằng 0 và gồm 10-11 chữ số.',
            'ghi_chu.max' => 'Ghi chú tối đa 500 ký tự.',
            'hinh_thuc_nhan.in' => 'Hình thức nhận không hợp lệ.',
            'phuong_thuc.in' => 'Phương thức đặt hàng không hợp lệ.',
            'ten_nguoi_nhan.required_if' => 'Vui lòng nhập tên người nhận.',
            'sdt_nguoi_nhan.required_if' => 'Vui lòng nhập SĐT người nhận.',
            'sdt_nguoi_nhan.regex' => 'SĐT người nhận phải bắt đầu bằng 0 và gồm 10-11 chữ số.',
            'dia_chi_giao.required_if' => 'Vui lòng nhập địa chỉ giao.',
        ]);

        // Mặc định tai_cua_hang nếu không gửi hinh_thuc_nhan (tương thích P1)
        $validated['hinh_thuc_nhan'] = $validated['hinh_thuc_nhan'] ?? 'tai_cua_hang';

        // P2: tai_cua_hang cho cả QR và trả tại quầy (không còn chặn như P1)

        // Kiểm tra giao_tan_noi phải dùng QR
        if ($validated['hinh_thuc_nhan'] === 'giao_tan_noi' && $validated['phuong_thuc'] !== 'qr') {
            return back()->withErrors(['phuong_thuc' => 'Giao tận nơi bắt buộc thanh toán QR trả trước.']);
        }

        // Kiểm tra vùng giao (nếu có cấu hình)
        if ($validated['hinh_thuc_nhan'] === 'giao_tan_noi' && !empty($validated['dia_chi_giao'])) {
            $tinhGiao = config('pos.giao_hang_tinh', '');
            if (!empty($tinhGiao)) {
                $danhSachTinh = array_map('trim', explode(',', $tinhGiao));
                $diaChi = mb_strtolower($validated['dia_chi_giao']);
                $trongVung = false;
                foreach ($danhSachTinh as $tinh) {
                    if (!empty($tinh) && mb_strpos($diaChi, mb_strtolower($tinh)) !== false) {
                        $trongVung = true;
                        break;
                    }
                }
                if (!$trongVung) {
                    return back()->withErrors(['dia_chi_giao' => 'Địa chỉ giao nằm ngoài vùng phục vụ. Vui lòng chọn tỉnh/thành trong danh sách được giao.']);
                }
            }
        }

        // Token chỉ dùng một lần: pull rồi so khớp; lệch thì cấp lại để form thử lại.
        $token = $request->session()->pull('checkout_token');
        if (! $token || ! hash_equals((string) $token, $validated['checkout_token'])) {
            $this->capLaiToken($request);

            return back()->with('error', 'Phiên đặt hàng đã hết hạn hoặc không hợp lệ, vui lòng thử lại.');
        }

        $cart = $request->session()->get(self::CART_KEY, []);
        if (empty($cart)) {
            $this->capLaiToken($request);

            return back()->with('error', 'Giỏ hàng đang trống.');
        }

        // Chỉ gửi id + số lượng; R1 tính lại giá / tồn / trạng thái từ DB trong transaction.
        $gioHang = collect($cart)->map(fn (array $item): array => [
            'id' => (int) $item['id'],
            'soluong' => (int) $item['soluong'],
        ])->values()->all();

        // Tính phi giao hang theo config
        $tongTien = collect($cart)->sum(fn (array $i): float => (float) $i['gia_ban'] * (int) $i['soluong']);
        $phiGiaoHang = 0;
        if ($validated['hinh_thuc_nhan'] === 'giao_tan_noi') {
            $phiGiaoHang = (int) config('pos.phi_giao_hang', 30000);
            $mienPhiGiaoTu = (int) config('pos.mien_phi_giao_tu', 0);
            if ($mienPhiGiaoTu > 0 && $tongTien >= $mienPhiGiaoTu) {
                $phiGiaoHang = 0;
            }
        }

        $online = [
            'kenh' => 'online',
            'phuong_thuc' => $validated['phuong_thuc'] === 'qr' ? 'chuyenkhoan' : 'tienmat',
            'ma_tra_cuu' => $this->taoMaTraCuu(),
            'ten_khach_dat' => $validated['ten'],
            'so_dienthoai_dat' => $validated['so_dienthoai'],
            'ghi_chu_khach' => $validated['ghi_chu'] ?? null,
            'han_giu' => $validated['phuong_thuc'] === 'qr' ? now()->addMinutes((int) config('pos.online_qr_phut', 30)) : now()->addHours((int) config('pos.online_pickup_gio', 4)),
            'hinh_thuc_nhan' => $validated['hinh_thuc_nhan'],
            'ten_nguoi_nhan' => $validated['ten_nguoi_nhan'] ?? null,
            'sdt_nguoi_nhan' => $validated['sdt_nguoi_nhan'] ?? null,
            'dia_chi_giao' => $validated['dia_chi_giao'] ?? null,
            'phi_giao_hang' => $phiGiaoHang,
        ];

        try {
            $don = $service->taoHoaDonChoThanhToan($gioHang, [], null, $online);
        } catch (Throwable $e) {
            $this->capLaiToken($request);
            $message = $e instanceof RuntimeException ? $e->getMessage() : 'Không thể tạo đơn hàng. Vui lòng thử lại.';

            return back()->with('error', $message);
        }

        $request->session()->forget(self::CART_KEY);
        $this->ghiBangChung($request, $don->ma_tra_cuu);

        NhatKyService::ghi(
            $request,
            'dat_hang_online',
            'invoices',
            $don->id,
            'Đơn pickup online '.$don->ma_hoadon.', khách '.$validated['ten']
                .', SĐT '.CheThongTin::soDienThoai($validated['so_dienthoai'])
                .', tổng '.number_format((float) $don->tong_thanh_toan, 0, ',', '.').'đ'
        );

        app(ThongBaoService::class)->thongBaoDonOnlineMoi($don);

        return redirect()->route('dat-hang.thanhcong', $don->ma_tra_cuu);
    }

    public function getThanhCong(string $ma_tra_cuu, ThanhToanChuyenKhoanService $service): View
    {
        $don = DonHang::where('ma_tra_cuu', $ma_tra_cuu)->firstOrFail();
        $don = $this->huyNeuQuaHan($don, $service);

        return view('dathang.thanhcong', ['don' => $don]);
    }

    public function getTraCuu(): View
    {
        return view('dathang.tracuu');
    }

    public function postTraCuu(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ma_tra_cuu' => ['required', 'string', 'max:20'],
            'so_dienthoai' => ['required', 'string', 'regex:/^0\d{9,10}$/'],
        ], [
            'so_dienthoai.regex' => 'Số điện thoại phải bắt đầu bằng 0 và gồm 10-11 chữ số.',
        ]);

        $don = DonHang::where('ma_tra_cuu', $validated['ma_tra_cuu'])
            ->where('so_dienthoai_dat', $validated['so_dienthoai'])
            ->where('kenh', 'online')
            ->first();

        // Thông báo chung cho cả sai mã lẫn sai SĐT để không dò được đơn tồn tại.
        if (! $don) {
            return back()->with('error', 'Không tìm thấy đơn hàng');
        }

        $this->ghiBangChung($request, $don->ma_tra_cuu);

        return redirect()->route('don-hang.trangthai', $don->ma_tra_cuu);
    }

    public function getTrangThai(Request $request, string $ma_tra_cuu, ThanhToanChuyenKhoanService $service): View|RedirectResponse
    {
        if (! $this->coBangChung($request, $ma_tra_cuu)) {
            return redirect()->route('don-hang.tracuu');
        }

        $don = DonHang::where('ma_tra_cuu', $ma_tra_cuu)->first();
        if (! $don) {
            return redirect()->route('don-hang.tracuu')->with('error', 'Không tìm thấy đơn hàng');
        }

        $don = $this->huyNeuQuaHan($don, $service);

        return view('dathang.trangthai', ['don' => $don]);
    }

    public function postPoll(Request $request, string $ma_tra_cuu, ThanhToanChuyenKhoanService $service): JsonResponse
    {
        if (! $this->coBangChung($request, $ma_tra_cuu)) {
            abort(403);
        }

        $don = DonHang::where('ma_tra_cuu', $ma_tra_cuu)->firstOrFail();
        $don = $this->huyNeuQuaHan($don, $service);

        // Polling chỉ trả trạng thái, không kèm SĐT / thông tin khách (T5).
        return response()->json(['trang_thai' => $don->trang_thai]);
    }

    public function postHuy(Request $request, string $ma_tra_cuu, ThanhToanChuyenKhoanService $service): RedirectResponse
    {
        if (! $this->coBangChung($request, $ma_tra_cuu)) {
            return redirect()->route('don-hang.tracuu');
        }

        $don = DonHang::where('ma_tra_cuu', $ma_tra_cuu)->first();
        if (! $don) {
            return redirect()->route('don-hang.tracuu')->with('error', 'Không tìm thấy đơn hàng');
        }

        if ($don->trang_thai !== 'cho_thanh_toan') {
            return back()->with('error', 'Chỉ có thể hủy đơn đang chờ thanh toán.');
        }

        $res = $service->huyChoThanhToan($don, 'Khách tự hủy đơn pickup online');
        if (! $res['success']) {
            return back()->with('error', $res['message']);
        }

        return redirect()->route('don-hang.trangthai', $don->ma_tra_cuu)->with('success', 'Đã hủy đơn và hoàn trả tồn kho.');
    }

    private function huyNeuQuaHan(DonHang $don, ThanhToanChuyenKhoanService $service): DonHang
    {
        if ($don->trang_thai === 'cho_thanh_toan' && $don->han_giu !== null && $don->han_giu->isPast()) {
            $service->huyChoThanhToan($don, 'Quá hạn giữ hàng pickup (hủy lười)');
            $don->refresh();
        }

        return $don;
    }

    private function capLaiToken(Request $request): void
    {
        $request->session()->put('checkout_token', Str::random(40));
    }

    private function ghiBangChung(Request $request, string $ma): void
    {
        $evidence = $request->session()->get(self::EVIDENCE_KEY, []);
        $evidence[$ma] = true;
        $request->session()->put(self::EVIDENCE_KEY, $evidence);
    }

    private function coBangChung(Request $request, string $ma): bool
    {
        $evidence = $request->session()->get(self::EVIDENCE_KEY, []);

        return ! empty($evidence[$ma]);
    }

    private function taoMaTraCuu(): string
    {
        // Bỏ ký tự dễ nhầm (I, O, 0, 1); 12 ký tự ≈ 32^12, không đoán được.
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $doDai = strlen($alphabet);

        do {
            $ma = '';
            for ($i = 0; $i < 12; $i++) {
                $ma .= $alphabet[random_int(0, $doDai - 1)];
            }
        } while (DonHang::where('ma_tra_cuu', $ma)->exists());

        return $ma;
    }
}