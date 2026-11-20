<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\NhatKyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class DangNhapGoogleController extends Controller
{
    private const STAFF_ROLES = ['admin', 'thungan', 'thukho'];

    public static function daCauHinh(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    public function getRedirect(): RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('storefront.home');
        }

        if (! self::daCauHinh()) {
            return redirect()->route('auth.dangnhap')
                ->with('error', 'Đăng nhập Google chưa được cấu hình.');
        }

        return Socialite::driver('google')->redirect();
    }

    public function getCallback(Request $request): RedirectResponse
    {
        if (! self::daCauHinh()) {
            return redirect()->route('auth.dangnhap')
                ->with('error', 'Đăng nhập Google chưa được cấu hình.');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('auth.dangnhap')
                ->with('error', 'Không thể đăng nhập bằng Google lúc này. Vui lòng thử lại hoặc dùng email/mật khẩu.');
        }

        $email = trim((string) $googleUser->getEmail());
        if ($email === '') {
            return redirect()->route('auth.dangnhap')
                ->with('error', 'Tài khoản Google không cung cấp email nên không thể đăng nhập.');
        }

        $user = User::where('email', $email)->first();

        // Email nhân viên phải đăng nhập bằng mật khẩu — không nhận qua Google
        // để tránh chiếm quyền nhân viên khi email Google bị lộ.
        if ($user !== null && in_array($user->role, self::STAFF_ROLES, true)) {
            return redirect()->route('auth.dangnhap')
                ->with('error', 'Email này thuộc tài khoản nhân viên. Vui lòng đăng nhập bằng email và mật khẩu.');
        }

        $hoSoKhoa = $user !== null
            && \App\Models\KhachHang::where('user_id', $user->id)->whereNotNull('khoa_den')->exists();
        if ($user !== null && ($user->khoa_luc !== null || $hoSoKhoa)) {
            return redirect()->route('auth.dangnhap')
                ->with('error', 'Tài khoản đã bị khóa, vui lòng liên hệ cửa hàng.');
        }

        $laTaiKhoanMoi = $user === null;
        if ($laTaiKhoanMoi) {
            // Chỉ tạo tài khoản KHÁCH HÀNG, không bao giờ cấp quyền nhân viên.
            // Không tạo hồ sơ KhachHang ở đây vì thiếu SĐT (cột bắt buộc, unique);
            // khách bổ sung sau ở màn hình Thông tin cá nhân.
            $user = User::create([
                'name' => $googleUser->getName() ?: Str::before($email, '@'),
                'email' => $email,
                'password' => Str::random(32),
                'role' => 'khachhang',
            ]);
            $user->email_verified_at = now();
            $user->save();
        }

        Auth::login($user);
        $request->session()->regenerate();
        \App\Services\PinService::xoaSessionPin();
        // last_login_at qua query builder — không đụng updated_at.
        DB::table('users')->where('id', $user->id)->update(['last_login_at' => now()]);
        NhatKyService::ghi(
            $request,
            $laTaiKhoanMoi ? 'dang_ky_google' : 'dang_nhap_google',
            'user',
            $user->id,
            $laTaiKhoanMoi ? 'Đăng ký tài khoản khách hàng qua Google' : 'Đăng nhập bằng Google'
        );

        return redirect()->route('storefront.home')
            ->with('success', 'Đăng nhập Google thành công. Chào mừng bạn!');
    }
}