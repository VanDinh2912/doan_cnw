<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chế độ "chỉ webhook" cho instance thử nghiệm SePay qua tunnel công khai:
 * khi POS_CHI_CHO_WEBHOOK=true, mọi request ngoài POST /api/webhook/thanhtoan
 * đều trả 404 — tránh lộ toàn bộ app ra internet trong lúc test.
 * Mặc định TẮT: không ảnh hưởng vận hành bình thường.
 */
class ChiChoWebhook
{
    private const DUONG_DAN_CHO_PHEP = 'api/webhook/thanhtoan';

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('pos.chi_cho_webhook')) {
            return $next($request);
        }

        if (! $request->is(self::DUONG_DAN_CHO_PHEP)) {
            abort(404);
        }

        return $next($request);
    }
}
