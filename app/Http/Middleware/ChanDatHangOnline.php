<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cờ POS_DAT_HANG_ONLINE_BAT: khi tắt, mọi route đặt hàng online công khai
 * (form, tạo đơn, QR online, tra cứu, polling, hủy) trả 404.
 * Đơn online ĐÃ tồn tại vẫn được webhook/scheduler xử lý bình thường.
 */
class ChanDatHangOnline
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('pos.dat_hang_online_bat')) {
            abort(404);
        }

        return $next($request);
    }
}
