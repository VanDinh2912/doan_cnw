<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class CheckBatBuocDoiMatKhau
{
    /**
     * Cache kết quả Schema::hasColumn trong request
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
     * Nếu cờ bat_buoc_doi_mat_khau = true, chuyển về trang đổi mật khẩu
     * Loại trừ: chính trang đổi mật khẩu, đăng xuất, tài nguyên tĩnh để tránh vòng lặp
     *
     * GUARD: Không đọc bat_buoc_doi_mat_khau nếu cột chưa tồn tại
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Guard: Chỉ kiểm tra nếu cột bat_buoc_doi_mat_khau đã tồn tại
        if ($user && $this->columnExists('bat_buoc_doi_mat_khau')) {
            // Loại trừ các route không cần chuyển hướng
            $excludedRoutes = ['doi-mat-khau', 'doi-mat-khau.post', 'auth.dangxuat'];
            $currentRoute = $request->route()?->getName();

            if ($user->bat_buoc_doi_mat_khau === true && !in_array($currentRoute, $excludedRoutes, true)) {
                return redirect()->route('doi-mat-khau')
                    ->with('warning', 'Bạn cần đổi mật khẩu trước khi tiếp tục.');
            }
        }

        return $next($request);
    }
}
