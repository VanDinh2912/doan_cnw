<?php

namespace App\Http\Controllers;

use App\Models\KhachHang;
use App\Models\User;
use App\Rules\SoDienThoaiHopLe;
use App\Services\CanhBaoMatKhauMacDinh;
use App\Services\NhatKyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    /** Roles that belong to the internal staff dashboard. */
    private const STAFF_ROLES = ['admin', 'thungan', 'thukho'];

    private function isStaff(?User $user): bool
    {
        return $user !== null && in_array($user->role, self::STAFF_ROLES, true);
    }

    private function homeFor(?User $user): string
    {
        return $this->isStaff($user)
            ? route('tongquan')
            : route('storefront.home');
    }

    public function getDangNhap(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->to($this->homeFor(Auth::user()));
        }

        return view('auth.dangnhap', [
            'canhBaoMatKhauMacDinh' => app(CanhBaoMatKhauMacDinh::class)->conHieuLuc(),
        ]);
    }

    public function postDangNhap(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'Email hoặc mật khẩu không đúng.']);
        }

        // Chặn sớm tài khoản/hồ sơ bị khóa — không để session được thiết lập.
        // CheckCả users.khoa_luc lẫn customers.khoa_den (khóa hồ sơ khách).
        $user = $request->user();
        $hoSoKhoa = KhachHang::where('user_id', $user->id)->whereNotNull('khoa_den')->exists();
        if (($user->khoa_luc ?? null) !== null || $hoSoKhoa) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withInput($request->only('email', 'remember'))
                ->with('error', 'Tài khoản đã bị khóa, vui lòng liên hệ cửa hàng.');
        }

        $request->session()->regenerate();
        NhatKyService::ghi($request, 'dang_nhap', 'user', $request->user()?->id, 'Đăng nhập thành công');

        return redirect()->intended($this->homeFor($request->user()));
    }

    public function getDangKy(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->to($this->homeFor(Auth::user()));
        }

        return view('auth.dangky');
    }

    public function postDangKy(Request $request): RedirectResponse
    {
        $request->merge([
            'so_dienthoai' => SoDienThoaiHopLe::chuanHoa($request->input('so_dienthoai')),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'so_dienthoai' => ['bail', 'required', 'string', new SoDienThoaiHopLe()],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required' => 'Vui lòng nhập họ tên.',
            'name.max' => 'Họ tên tối đa 100 ký tự.',
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'email.unique' => 'Email đã được sử dụng.',
            'so_dienthoai.required' => 'Vui lòng nhập số điện thoại.',
            'so_dienthoai.string' => SoDienThoaiHopLe::THONG_BAO,
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
        ]);

        // Privilege-escalation guard: never trust a client-supplied role.
        unset($data['role']);

        $user = DB::transaction(function () use ($data, $request): User {
            /** @var User $user */
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => 'khachhang',
            ]);

            $khachHang = KhachHang::where('so_dienthoai', $data['so_dienthoai'])->first();

            // Chống chiếm hồ sơ: SĐT trùng KHÔNG tự động liên kết tài khoản.
            // Ai biết SĐT khách cũng đăng ký được — nếu tự gán user_id thì kẻ
            // đó xem được toàn bộ đơn hàng/điểm/địa chỉ của khách thật.
            // Hồ sơ đã có chủ (user_id != null) → từ chối đăng ký.
            // Hồ sơ chưa liên kết → tài khoản tạo thành công nhưng KHÔNG gán;
            // nhân viên xác nhận liên kết thủ công ở màn hình khách hàng.
            if ($khachHang !== null && $khachHang->user_id !== null) {
                throw ValidationException::withMessages([
                    'so_dienthoai' => 'Số điện thoại đã được đăng ký.',
                ]);
            }

            if ($khachHang === null) {
                KhachHang::create([
                    'user_id' => $user->id,
                    'ho_ten' => $data['name'],
                    'so_dienthoai' => $data['so_dienthoai'],
                    'dia_chi' => null,
                    'diem_tichluy' => 0,
                ]);
            }

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        NhatKyService::ghi($request, 'dang_ky', 'user', $user->id, 'Đăng ký tài khoản khách hàng');

        return redirect()->route('storefront.home')
            ->with('success', 'Đăng ký thành công. Chào mừng bạn!');
    }

    public function getDangXuat(Request $request): RedirectResponse
    {
        NhatKyService::ghi($request, 'dang_xuat', 'user', $request->user()?->id, 'Đăng xuất');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('auth.dangnhap')->with('success', 'Bạn đã đăng xuất.');
    }
}