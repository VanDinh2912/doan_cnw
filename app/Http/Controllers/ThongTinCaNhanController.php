<?php

namespace App\Http\Controllers;

use App\Models\DonHang;
use App\Models\KhachHang;
use App\Rules\SoDienThoaiHopLe;
use App\Services\NhatKyService;
use App\Services\ThongBaoService;
use App\Support\ChePII;
use App\Support\HoSoXacThuc;
use App\Support\SoDienThoai;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ThongTinCaNhanController extends Controller
{
    private const MAX_SAI = 5;

    private const GIAY_KHOA = 15 * 60;

    public function getProfile(Request $request): View
    {
        $user = $request->user();
        $khachHang = $user->khachHang ?? null;
        $daXacThuc = HoSoXacThuc::conHieuLuc($request);

        if (! $daXacThuc) {
            // Chế độ che: KHÔNG truyền dữ liệu thật xuống view.
            // Chỉ truyền chuỗi đã che + cờ, server-side.
            return view('thongtincanhan.profile', [
                'daXacThuc' => false,
                'user' => $user,
                'khachHang' => null,
                'che' => [
                    'ho_ten' => ChePII::hoTen($khachHang?->ho_ten),
                    'so_dienthoai' => ChePII::soDienThoai($khachHang?->so_dienthoai),
                    'email' => ChePII::email($user->email),
                    'dia_chi' => ChePII::diaChi($khachHang?->dia_chi),
                ],
                'coSoDienThoai' => filled($khachHang?->so_dienthoai),
                'khoaDangNhap' => $khachHang?->khoa_den !== null,
            ]);
        }

        return view('thongtincanhan.profile', [
            'daXacThuc' => true,
            'user' => $user,
            'khachHang' => $khachHang,
            'che' => null,
            'coSoDienThoai' => filled($khachHang?->so_dienthoai),
            'khoaDangNhap' => $khachHang?->khoa_den !== null,
        ]);
    }

    /**
     * Xác thực lại: mật khẩu hiện tại + SĐT đang lưu trong hồ sơ.
     * Lỗi luôn trả thông báo CHUNG, không lộ sai ở đâu.
     */
    public function postXacThuc(Request $request): RedirectResponse
    {
        $user = $request->user();
        $key = 'ho_so_xac_thuc:'.$user->id.':'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_SAI)) {
            $choPhut = (int) ceil(RateLimiter::availableIn($key) / 60);
            NhatKyService::ghi($request, 'ho_so_xac_thuc_bi_khoa', 'user', $user->id,
                'Xác thực lại hồ sơ bị chặn do quá số lần sai');

            return back()->with('error',
                "Quá nhiều lần thử sai. Vui lòng thử lại sau {$choPhut} phút.");
        }

        $data = $request->validate([
            'mat_khau' => ['required', 'string'],
            'so_dien_thoai' => ['nullable', 'string', 'max:20'],
        ], [
            'mat_khau.required' => 'Vui lòng nhập mật khẩu hiện tại.',
        ]);

        $khachHang = $user->khachHang;
        $sdtHoSo = $khachHang?->so_dienthoai;

        // Tài khoản Google-only: mật khẩu là chuỗi ngẫu nhiên, không ai biết.
        // users không có cột provider; email_verified_at được đặt khi tạo qua
        // Google (đăng ký thường không đặt vì chưa có SMTP) — dùng làm dấu hiệu.
        $laGoogleOnly = $user->email_verified_at !== null
            && ! Hash::check($data['mat_khau'], (string) $user->password);

        $matKhauDung = Hash::check($data['mat_khau'], (string) $user->password);

        // Chỉ yêu cầu SĐT khi hồ sơ đã có SĐT; chưa có thì chỉ cần mật khẩu
        // và nhắc bổ sung SĐT sau khi vào.
        $sdtDung = true;
        if (filled($sdtHoSo)) {
            $sdtNhap = SoDienThoai::chuanHoa($data['so_dien_thoai'] ?? null);
            $sdtDung = is_string($sdtNhap) && $sdtNhap !== ''
                && hash_equals((string) $sdtHoSo, $sdtNhap);
        }

        if (! $matKhauDung || ! $sdtDung) {
            RateLimiter::hit($key, self::GIAY_KHOA);
            NhatKyService::ghi($request, 'ho_so_xac_thuc_that_bai', 'user', $user->id,
                'Xác thực lại hồ sơ thất bại');

            // Cảnh báo Telegram khi vừa chạm ngưỡng khóa — tái dùng guiTelegram,
            // không PII (chỉ ID user).
            if (RateLimiter::attempts($key) >= self::MAX_SAI) {
                app(ThongBaoService::class)->guiTelegram(
                    'Cảnh báo: bước xác thực lại hồ sơ khách bị khóa tạm thời (user #'.$user->id.').'
                );
            }

            if ($laGoogleOnly) {
                return back()->with('error',
                    'Tài khoản chưa có mật khẩu, vui lòng liên hệ cửa hàng để đặt mật khẩu.');
            }

            return back()->with('error', 'Thông tin xác thực không đúng');
        }

        RateLimiter::clear($key);
        HoSoXacThuc::bat($request);
        NhatKyService::ghi($request, 'ho_so_xac_thuc_thanh_cong', 'user', $user->id,
            'Xác thực lại hồ sơ thành công');

        return redirect()->route('profile.show');
    }

    public function postProfile(Request $request): RedirectResponse
    {
        // Bắt buộc cửa sổ xác thực ở SERVER — ẩn form không đủ.
        if (! HoSoXacThuc::conHieuLuc($request)) {
            return redirect()->route('profile.show')
                ->with('error', 'Phiên xác thực đã hết hạn. Vui lòng xác thực lại để cập nhật.');
        }

        $user = Auth::user();
        $khachHang = $user->khachHang;

        $request->merge([
            'so_dienthoai' => SoDienThoaiHopLe::chuanHoa($request->input('so_dienthoai')),
        ]);

        $validated = $request->validate([
            'ho_ten' => ['required', 'string', 'max:100'],
            'email' => ['sometimes', 'required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'so_dienthoai' => ['bail', 'required', 'string', new SoDienThoaiHopLe(), Rule::unique('customers', 'so_dienthoai')->ignore($khachHang)],
            'dia_chi' => ['nullable', 'string', 'max:255'],
        ], [
            'ho_ten.required' => 'Vui lòng nhập họ tên.',
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'email.unique' => 'Email này đã được sử dụng.',
            'so_dienthoai.required' => 'Vui lòng nhập số điện thoại.',
            'so_dienthoai.string' => SoDienThoaiHopLe::THONG_BAO,
            'so_dienthoai.unique' => 'Số điện thoại này không thể sử dụng.',
            'dia_chi.max' => 'Địa chỉ tối đa 255 ký tự.',
        ]);

        // Ghi tên trường thay đổi, KHÔNG ghi giá trị cũ/mới.
        $truongThayDoi = [];
        $soSanh = [
            'ho_ten' => $khachHang?->ho_ten,
            'email' => $user->email,
            'so_dienthoai' => $khachHang?->so_dienthoai,
            'dia_chi' => $khachHang?->dia_chi,
        ];
        foreach (['ho_ten', 'email', 'so_dienthoai', 'dia_chi'] as $truong) {
            $moi = $validated[$truong] ?? null;
            if (($soSanh[$truong] ?? null) != $moi) {
                $truongThayDoi[] = $truong;
            }
        }

        if ($khachHang === null) {
            $khachHang = KhachHang::create([
                'user_id' => $user->id,
                'ho_ten' => $validated['ho_ten'],
                'so_dienthoai' => $validated['so_dienthoai'],
                'dia_chi' => $validated['dia_chi'] ?? null,
                'diem_tichluy' => 0,
            ]);
        } else {
            $khachHang->update([
                'ho_ten' => $validated['ho_ten'],
                'so_dienthoai' => $validated['so_dienthoai'],
                'dia_chi' => $validated['dia_chi'] ?? null,
            ]);
        }

        if (array_key_exists('email', $validated) && $user->email !== $validated['email']) {
            $user->update(['email' => $validated['email']]);
        }

        // Mỗi lần xác thực chỉ cho MỘT lần cập nhật — xóa cửa sổ ngay sau lưu.
        HoSoXacThuc::xoa($request);
        NhatKyService::ghi($request, 'cap_nhat_ho_so', 'customer', $khachHang->id,
            'Cập nhật hồ sơ khách: '.implode(', ', $truongThayDoi),
            null,
            ['truong' => $truongThayDoi]);

        return back()->with('success', 'Cập nhật thông tin thành công.');
    }

    public function getOrders(): View
    {
        $khachHang = Auth::user()->khachHang ?? null;
        $donHangs = $khachHang
            ? $khachHang->donHangs()->with('chiTietDonHangs.sanPham')->latest()->paginate(10)
            : collect();

        return view('thongtincanhan.orders', [
            'khachHang' => $khachHang,
            'donHangs' => $donHangs,
        ]);
    }

    public function getOrderDetail(DonHang $donHang): View
    {
        $khachHang = Auth::user()->khachHang;

        abort_if(
            $khachHang === null || $donHang->customer_id !== $khachHang->id,
            403,
            'Bạn không có quyền xem đơn hàng này.'
        );

        $donHang->load('chiTietDonHangs.sanPham');

        return view('thongtincanhan.order_detail', [
            'khachHang' => $khachHang,
            'don' => $donHang,
        ]);
    }
}
