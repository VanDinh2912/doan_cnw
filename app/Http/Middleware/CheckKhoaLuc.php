<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckKhoaLuc
{
    /**
     * Handle an incoming request.
     *
     * Kiểm tra nếu tài khoản đã đăng nhập bị khóa (khoa_luc != null):
     * - Đăng xuất người dùng
     * - Chuyển hướng về trang đăng nhập với thông báo
     *
     * Middleware này áp dụng cho mọi vai trò (admin, thungan, thukho, khachhang)
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Nếu user đã đăng nhập và bị khóa
        if ($user && $user->khoa_luc !== null) {
            // Đăng xuất
            Auth::logout();

            // Hủy session nếu driver cho phép (file, database)
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Chuyển hướng về trang đăng nhập với thông báo
            return redirect()->route('auth.dangnhap')
                ->with('error', 'Tài khoản đã bị khóa, liên hệ quản trị viên.');
        }

        return $next($request);
    }
}
