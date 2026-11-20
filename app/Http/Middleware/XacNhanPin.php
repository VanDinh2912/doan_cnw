<?php

namespace App\Http\Middleware;

use App\Services\PinService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Yêu cầu xác nhận PIN cho thao tác nhạy cảm.
 * Tham số: thuong | nghiemtrong. Cờ POS_PIN_BAT=false -> bỏ qua ngay,
 * KHÔNG đọc cột pin_* của users.
 */
class XacNhanPin
{
    public function handle(Request $request, Closure $next, string $muc = PinService::MUC_THUONG): Response
    {
        if (! PinService::bat()) {
            return $next($request);
        }
        $user = $request->user();
        // staff.only đã chặn khách/guest; phòng hờ: role khác admin/thungan/thukho bỏ qua
        if (! $user || ! in_array($user->role, ['admin', 'thungan', 'thukho'], true)) {
            return $next($request);
        }

        $choPhep = false;
        if ($muc === PinService::MUC_NGHIEMTRONG) {
            $choPhep = PinService::kiemTraTokenNt($user, $request);
        } else {
            $choPhep = PinService::daXacNhanThuong($user);
        }
        if ($choPhep) {
            return $next($request);
        }

        // Chưa có PIN / đang khóa -> thông báo riêng
        $ma = 'CAN_PIN';
        $msg = 'Cần xác nhận PIN để tiếp tục.';
        if (! $user->pin_hash) {
            $ma = 'CAN_THIET_LAP_PIN';
            $msg = 'Tài khoản chưa có PIN. Vui lòng liên hệ quản trị viên để được cấp PIN.';
        } elseif ($khoaDen = PinService::dangKhoa($user)) {
            $phut = max(1, (int) ceil(($khoaDen - time()) / 60));
            $ma = 'PIN_KHOA';
            $msg = "PIN đang bị khóa. Vui lòng thử lại sau {$phut} phút.";
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ma' => $ma, 'muc' => $muc, 'message' => $msg], 423);
        }
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return redirect()->guest(route('pin.nhap', ['return' => $request->fullUrl()]));
        }
        // POST/PUT/DELETE không JS: quay về trang trước (referer), không về URL route (tránh 405)
        $referer = $request->headers->get('referer');
        return redirect($referer && ! str_contains($referer, $request->path())
            ? $referer
            : url('/'))->with('loi_pin', $msg);
    }
}
