<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckBatBuocDoiMatKhau
{
    /**
     * Handle an incoming request.
     *
     * Nếu cờ bat_buoc_doi_mat_khau = true, chuyển về trang đổi mật khẩu
     * Loại trừ: chính trang đổi mật khẩu, đăng xuất, tài nguyên tĩnh để tránh vòng lặp
     *
     * GUARD: Không đọc bat_buoc_doi_mat_khau nếu cột chưa tồn tại
     * - Dùng array_key_exists trên user đã nạp thay vì Schema::hasColumn
     * - Đúng cả khi bật preventAccessingMissingAttributes
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Guard: Chỉ kiểm tra nếu cột bat_buoc_doi_mat_khau đã tồn tại trong attributes
        if ($user && array_key_exists('bat_buoc_doi_mat_khau', $user->getAttributes())) {
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
