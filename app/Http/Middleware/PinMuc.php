<?php

namespace App\Http\Middleware;

use App\Services\MaDuyetService;
use App\Services\PinService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware mức bảo vệ theo config/pin_muc.php (tên route -> K/M1/M2/A).
 *
 * - K  : đi qua.
 * - M1 : PIN riêng mức thường (cửa sổ) — admin cũng dùng mức thường.
 * - M2 : nhân viên cần mã duyệt admin cấp (một lần, ràng buộc thao tác);
 *        admin dùng PIN riêng mức nghiêm trọng mỗi lần.
 *        POS_MA_DUYET_BAT=false hoặc thiếu bảng -> tạm hạ M1 (PIN thường).
 * - A  : chỉ admin — PIN riêng mức nghiêm trọng mỗi lần.
 *
 * Cờ POS_PIN_BAT=false -> bỏ qua hoàn toàn, không đọc bảng yeu_cau_duyet.
 */
class PinMuc
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! PinService::bat()) {
            return $next($request);
        }
        $route = $request->route();
        $tenRoute = $route ? (string) $route->getName() : '';
        // Route name chứa dấu chấm -> không dùng config('pin_muc.ten.route').
        $muc = strtoupper((string) (config('pin_muc')[$tenRoute] ?? 'K'));
        if ($muc === 'K' || $muc === '') {
            return $next($request);
        }
        $user = $request->user();
        if (! $user || ! in_array($user->role, ['admin', 'thungan', 'thukho'], true)) {
            return $next($request);
        }

        if ($user->role === 'admin') {
            // Admin: M1 = PIN thường (cửa sổ); M2/A = PIN nghiêm trọng mỗi lần.
            $mucPin = $muc === 'M1' ? PinService::MUC_THUONG : PinService::MUC_NGHIEMTRONG;

            return app(XacNhanPin::class)->handle($request, $next, $mucPin);
        }

        return match ($muc) {
            'M1' => app(XacNhanPin::class)->handle($request, $next, PinService::MUC_THUONG),
            'M2' => $this->kiemM2($request, $next, $tenRoute),
            'A' => $this->chanNhanVien($request),
            default => $next($request),
        };
    }

    /**
     * M2 cho nhân viên: cần _ma_duyet hợp lệ. Verify + đánh dấu dùng
     * trong MỘT transaction — hai request cùng mã chỉ một thành công.
     */
    private function kiemM2(Request $request, Closure $next, string $tenRoute): Response
    {
        // Hạ mức tạm thời khi tắt mã duyệt / thiếu bảng — vẫn giữ PIN thường.
        if (! MaDuyetService::bat()) {
            return app(XacNhanPin::class)->handle($request, $next, PinService::MUC_THUONG);
        }
        if (MaDuyetService::conKhoa($request->user())) {
            return $this->canMaDuyet($request, 'Mã duyệt tạm khóa do nhập sai nhiều lần. Vui lòng thử lại sau.');
        }
        $ma = trim((string) $request->input('_ma_duyet', ''));
        if ($ma === '') {
            return $this->canMaDuyet($request);
        }

        return DB::transaction(function () use ($request, $next, $tenRoute, $ma) {
            [$loai, $id, $thamSo] = MaDuyetService::dinhDanh($request, $tenRoute);
            $yc = MaDuyetService::xacNhanMa(
                $request->user(), $ma, $tenRoute, $loai, $id, $thamSo, $request
            );
            if (! $yc) {
                return $this->canMaDuyet($request, 'Mã duyệt không đúng hoặc đã hết hạn.');
            }
            // M5: controller doc de lien ket xac_nhan_thu_cong.yeu_cau_duyet_id/admin_duyet_id.
            $request->attributes->set('ma_duyet_yc_id', $yc->id);
            $request->attributes->set('ma_duyet_admin_id', $yc->admin_cap_id);
            $response = $next($request);
            // Chỉ đốt mã khi thao tác thành công (không phải redirect lỗi validate).
            $loiValidate = $response->isRedirect()
                && $request->hasSession() && $request->session()->has('errors');
            if ($response->getStatusCode() < 400 && ! $loiValidate) {
                MaDuyetService::danhDauDaDung($yc, $request);
            }

            return $response;
        });
    }

    /** Mã 423 CAN_MA_DUYET — JSON cho fetch, redirect-back cho form. */
    private function canMaDuyet(Request $request, string $msg = 'Thao tác này cần quản lý duyệt.'): Response
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ma' => 'CAN_MA_DUYET', 'message' => $msg], 423);
        }
        $referer = $request->headers->get('referer');

        // Fallback về khu quản trị — staff không bị văng ra storefront '/'.
        return redirect($referer && ! str_contains($referer, $request->path())
            ? $referer : route('tongquan'))->withInput()->with('can_ma_duyet', $msg);
    }

    private function chanNhanVien(Request $request): Response
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ma' => 'KHONG_CO_QUYEN', 'message' => 'Thao tác này chỉ dành cho quản trị viên.'], 403);
        }
        abort(403);
    }
}
