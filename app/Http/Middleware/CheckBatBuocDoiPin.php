<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckBatBuocDoiPin
{
    /**
     * Nếu cờ bat_buoc_doi_pin = true (vừa được cấp PIN tạm), chuyển về
     * /doi-pin cho tới khi nhân viên tự đặt PIN mới — PIN tạm chỉ dùng
     * đúng một lần ở form đổi PIN rồi vô hiệu vĩnh viễn.
     *
     * GUARD: Không đọc cột nếu chưa tồn tại (schema cũ chưa migrate PIN).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && in_array($user->role, ['admin', 'thungan', 'thukho'], true)
            && array_key_exists('bat_buoc_doi_pin', $user->getAttributes())) {
            $currentRoute = $request->route()?->getName() ?? '';
            $isPinRoute = $currentRoute === 'auth.dangxuat' || str_starts_with($currentRoute, 'pin.');
            $isAsset = $request->is('api/*') || $request->is('storage/*')
                || str_starts_with($request->path(), 'assets/')
                || $request->is('js/*') || $request->is('css/*') || $request->is('build/*') || $request->is('vendor/*');

            if ($user->bat_buoc_doi_pin === true && ! $isPinRoute && ! $isAsset) {
                return redirect()->route('pin.doi')
                    ->with('warning', 'Bạn đang dùng PIN tạm — hãy đặt PIN mới trước khi tiếp tục.');
            }
        }

        return $next($request);
    }
}
