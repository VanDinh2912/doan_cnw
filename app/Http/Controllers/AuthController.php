<?php

namespace App\Http\Controllers;

use App\Models\KhachHang;
use App\Models\User;
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

        return view('auth.dangnhap');
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
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'so_dienthoai' => ['required', 'string', 'regex:/^0\d{9,10}$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required' => 'Vui lòng nhập họ tên.',
            'name.max' => 'Họ tên tối đa 100 ký tự.',
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'email.unique' => 'Email đã được sử dụng.',
            'so_dienthoai.required' => 'Vui lòng nhập số điện thoại.',
            'so_dienthoai.regex' => 'Số điện thoại phải bắt đầu bằng 0 và gồm 10-11 chữ số.',
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

            if ($khachHang === null) {
                KhachHang::create([
                    'user_id' => $user->id,
                    'ho_ten' => $data['name'],
                    'so_dienthoai' => $data['so_dienthoai'],
                    'dia_chi' => null,
                    'diem_tichluy' => 0,
                ]);

                return $user;
            }

            if ($khachHang->user_id !== null) {
                throw ValidationException::withMessages([
                    'so_dienthoai' => 'Số điện thoại đã được liên kết với tài khoản khác.',
                ]);
            }

            $khachHang->update([
                'user_id' => $user->id,
                'ho_ten' => $data['name'],
            ]);

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