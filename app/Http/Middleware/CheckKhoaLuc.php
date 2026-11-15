<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class CheckKhoaLuc
{
    /**
     * Cache kết quả Schema::hasColumn trong request để tránh truy vấn DB nhiều lần
     */
    protected static array $columnCache = [];

    /**
     * Kiểm tra cột có tồn tại trong bảng users (có cache trong request)
     */
    protected function columnExists(string $column): bool
    {
        $key = 'users.' . $column;

        if (!isset(self::$columnCache[$key])) {
            self::$columnCache[$key] = Schema::hasColumn('users', $column);
        }

        return self::$columnCache[$key];
    }

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
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Guard: Chỉ kiểm tra khóa nếu cột khoa_luc đã tồn tại
        if ($user && $this->columnExists('khoa_luc')) {
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
