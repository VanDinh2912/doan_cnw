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
     *
     * GUARD: Không đọc khoa_luc nếu cột chưa tồn tại (DB thật chưa áp migration)
     * - Dùng array_key_exists trên user đã nạp thay vì Schema::hasColumn
     * - Đúng cả khi bật preventAccessingMissingAttributes
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Guard: Chỉ kiểm tra khóa nếu cột khoa_luc đã tồn tại trong attributes
        if ($user && array_key_exists('khoa_luc', $user->getAttributes())) {
            // Nếu user đã đăng nhập và bị khóa
            if ($user->khoa_luc !== null) {
                // Đăng xuất
                Auth::logout();

                // Hủy session nếu driver cho phép (file, database)
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                // Chuyển hướng về trang đăng nhập với thông báo
                return redirect()->route('auth.dangnhap')
                    ->with('error', 'Tài khoản đã bị khóa, liên hệ quản trị viên.');
            }
        }

        return $next($request);
    }
}
