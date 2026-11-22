<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chẩn đoán A2: ghi log WARNING mỗi khi một request trong khu quản trị
 * (nhóm staff.only) nhận redirect về trang chủ storefront '/'.
 * Mục đích: lần sau tái hiện được "admin bị văng ra trang khách" thì
 * truy ngược route/method/referer/user nào gây ra — KHÔNG ghi PII.
 *
 * Chỉ gắn trong nhóm route staff (auth + staff.only + khoa.luc +
 * bat.buoc.doi.mat.khau); redirect chủ đích của staff.only cho
 * khachhang không đi qua middleware này.
 */
class CanhBaoVeStorefront
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->isRedirect()) {
            $dich = (string) $response->headers->get('Location', '');
            $path = (string) (parse_url($dich, PHP_URL_PATH) ?: '/');

            // Trang chủ storefront có URI '/' — so path, bỏ query/host.
            if ($path === '/') {
                $user = $request->user();
                Log::warning('redirect_quan_tri_ve_storefront', [
                    'route' => $request->route()?->getName(),
                    'method' => $request->method(),
                    'path' => '/'.$request->path(),
                    'co_referer' => $request->headers->has('referer'),
                    'user_id' => $user?->id,
                    'role' => $user?->role,
                    'status' => $response->getStatusCode(),
                ]);
            }
        }

        return $response;
    }
}
