<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\YeuCauDuyet;
use App\Models\YeuCauPin;
use App\Services\MaDuyetService;
use App\Services\PinTamService;
use App\Services\ThongBaoService;
use App\Support\KiemTraSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Luồng mã duyệt hai-người:
 * - NV: POST /yeu-cau-duyet/gui tạo yêu cầu chờ (không cần PIN).
 * - Admin: GET /yeu-cau-duyet xem danh sách; POST cap-ma/tu-choi (mức A,
 *   pin.muc bắt PIN nghiêm trọng). Mã chỉ trả về JSON một lần, no-store.
 */
class YeuCauDuyetController extends Controller
{
    /** Admin xem danh sách yêu cầu — 2 tab: thao tác (mac dinh) / xin cap PIN (M4). */
    public function index(Request $request)
    {
        abort_unless($request->user()?->role === 'admin', 403);
        MaDuyetService::donHetHan();

        $tab = $request->query('tab') === 'pin' ? 'pin' : 'thao_tac';
        $data = ['tab' => $tab, 'ds' => null, 'dsPin' => null, 'soChoPin' => 0];

        if (KiemTraSchema::coBang('yeu_cau_pin')) {
            YeuCauPin::donHetHan();
            $data['soChoPin'] = YeuCauPin::where('trang_thai', YeuCauPin::TT_CHO_XU_LY)->count();
        }

        if ($tab === 'pin') {
            // Thieu bang -> tab trong, khong loi (code chay truoc migration).
            if (KiemTraSchema::coBang('yeu_cau_pin')) {
                YeuCauPin::donHetHan();
                $data['dsPin'] = YeuCauPin::with(['nhanVien:id,name,email', 'adminXuLy:id,name'])
                    ->orderByRaw("CASE WHEN trang_thai = 'cho_xu_ly' THEN 0 ELSE 1 END")
                    ->orderByDesc('id')
                    ->paginate(30)
                    ->withQueryString();
            }
        } else {
            $data['ds'] = YeuCauDuyet::with(['nhanVien:id,name,email', 'adminCap:id,name'])
                ->orderByRaw("CASE WHEN trang_thai = 'cho_cap' THEN 0 ELSE 1 END")
                ->orderByDesc('id')
                ->paginate(30)
                ->withQueryString();
        }

        return view('duyet.index', $data);
    }

    /** Badge số yêu cầu đang chờ (admin, JSON nhẹ cho polling) — gộp 2 loại. */
    public function soLuong(Request $request): \Illuminate\Http\JsonResponse
    {
        abort_unless($request->user()?->role === 'admin', 403);
        MaDuyetService::donHetHan();
        $so = MaDuyetService::coBang()
            ? YeuCauDuyet::where('trang_thai', MaDuyetService::TT_CHO_CAP)->count()
            : 0;
        if (KiemTraSchema::coBang('yeu_cau_pin')) {
            YeuCauPin::donHetHan();
            $so += YeuCauPin::where('trang_thai', YeuCauPin::TT_CHO_XU_LY)->count();
        }

        return response()->json(['so_luong' => $so]);
    }

    /**
     * NV gửi yêu cầu duyệt. Client gửi method+uri của thao tác gốc kèm
     * các field form; server TỰ match route, kiểm whitelist/quyền/đối tượng,
     * sinh mô tả — không nhận mo_ta từ client.
     */
    public function gui(Request $request)
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['thungan', 'thukho'], true), 403);
        abort_unless(MaDuyetService::bat(), 423, 'Tính năng mã duyệt chưa bật.');

        $lyDo = trim((string) $request->input('ly_do', ''));
        if ($lyDo === '' || mb_strlen($lyDo) > 255) {
            return response()->json(['ma' => 'THIEU_LY_DO', 'message' => 'Phải nhập lý do yêu cầu (tối đa 255 ký tự).'], 422);
        }

        $method = strtoupper((string) $request->input('_route_method', 'POST'));
        $uri = '/' . ltrim((string) $request->input('_route_uri', ''), '/');
        if (! in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) || $uri === '/') {
            return response()->json(['ma' => 'ROUTE_KHONG_HOP_LE', 'message' => 'Thao tác không hợp lệ.'], 422);
        }
        // Field form gốc (trừ field nội bộ) — dùng để tính tham số ràng buộc.
        $fields = $request->except(['_token', '_route_method', '_route_uri', '_ma_duyet', '_pin_token', 'ly_do']);
        $reqAo = Request::create($uri, $method, $fields);
        $reqAo->setLaravelSession($request->session());

        try {
            $route = Route::getRoutes()->match($reqAo);
        } catch (\Throwable) {
            return response()->json(['ma' => 'ROUTE_KHONG_HOP_LE', 'message' => 'Không xác định được thao tác.'], 422);
        }
        $tenRoute = (string) $route->getName();
        [$loai, $id] = MaDuyetService::dinhDanhRoute($route);
        $request->merge($fields); // thamSoRequest đọc input của request hiện tại
        $thamSo = MaDuyetService::thamSoRequest($request, $tenRoute);

        $kq = MaDuyetService::taoYeuCau($user, $tenRoute, $method, $loai, $id, $thamSo, $lyDo, $request);
        if ($kq['yc'] === null) {
            $status = $kq['ma'] === 'QUA_NHIEU_YEU_CAU' ? 429 : 422;

            return response()->json(['ma' => $kq['ma'], 'message' => $kq['loi']], $status);
        }
        if ($kq['ma'] === 'OK') {
            try {
                app(ThongBaoService::class)->guiTelegram(
                    "Co yeu cau duyet moi #{$kq['yc']->id} - {$kq['yc']->mo_ta_thao_tac}"
                );
            } catch (\Throwable) {
                // Telegram lỗi không chặn tạo yêu cầu
            }
        }

        return response()->json([
            'ma' => $kq['ma'],
            'yeu_cau_id' => $kq['yc']->id,
            'trang_thai' => $kq['yc']->trang_thai,
            'message' => $kq['ma'] === 'DA_CO_YEU_CAU'
                ? 'Đã có yêu cầu chờ cho thao tác này.'
                : "Đã gửi yêu cầu #{$kq['yc']->id}. Nhờ quản lý cấp mã.",
        ]);
    }

    /** Admin cấp mã — pin.muc mức A đã bắt PIN nghiêm trọng. Trả mã 1 lần. */
    public function capMa(Request $request, YeuCauDuyet $yeuCau)
    {
        $kq = MaDuyetService::capMa($request->user(), $yeuCau->id, $request);
        if (! $kq['ok']) {
            return response()->json(['ma' => $kq['ma'], 'message' => $kq['message'] ?? 'Không cấp được mã.'], 422);
        }

        // Mã hiện đúng MỘT LẦN: no-store, không flash/session/log.
        return response()->json([
            'ma' => 'OK',
            'ma_hien' => $kq['ma_hien'],
            'het_han_luc' => $kq['het_han_luc']?->toDateTimeString(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /** Admin từ chối kèm lý do. */
    public function tuChoi(Request $request, YeuCauDuyet $yeuCau)
    {
        $kq = MaDuyetService::tuChoi(
            $request->user(), $yeuCau->id,
            (string) $request->input('ly_do', ''), $request
        );
        if (! $kq['ok']) {
            return back()->with('error', 'Không từ chối được yêu cầu.');
        }

        return back()->with('success', "Đã từ chối yêu cầu #{$yeuCau->id}.");
    }

    /**
     * M4 — Admin CAP PIN TAM cho yeu cau xin cap PIN.
     * Route gan pin.muc muc A -> admin da nhap PIN nghiem trong moi lan.
     * PIN tam ma hoa cache 30', CHI NV do xem 1 lan — admin khong thay.
     */
    public function capPin(Request $request, int $yeuCauPin)
    {
        abort_unless(KiemTraSchema::coBang('yeu_cau_pin'), 404);
        $yc = YeuCauPin::findOrFail($yeuCauPin);
        abort_unless($request->user()?->role === 'admin', 403);
        abort_unless($yc->trang_thai === YeuCauPin::TT_CHO_XU_LY, 422, 'Yêu cầu không còn chờ xử lý.');
        abort_if($yc->nhanVien?->role === 'khachhang' || $yc->nhanVien === null, 422, 'Tài khoản không hợp lệ.');

        $admin = $request->user();
        PinTamService::capTamCho($yc->nhanVien, $admin, $yc->ly_do, $request, 'duyet');

        $yc->update([
            'trang_thai' => YeuCauPin::TT_DA_CAP,
            'admin_xu_ly_id' => $admin->id,
            'xu_ly_luc' => now(),
            'khoa_cho' => null,
        ]);

        return back()->with('success', "Đã cấp PIN tạm cho NV #{$yc->user_id}. Nhân viên tự xem 1 lần tại /pin/tam-cua-toi.");
    }

    /** M4 — Admin TU CHOI yeu cau xin cap PIN kem ly do (>=5 ky tu). */
    public function tuChoiPin(Request $request, int $yeuCauPin)
    {
        abort_unless(KiemTraSchema::coBang('yeu_cau_pin'), 404);
        $yc = YeuCauPin::findOrFail($yeuCauPin);
        abort_unless($request->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'ly_do' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'ly_do.required' => 'Vui lòng nhập lý do từ chối.',
            'ly_do.min' => 'Lý do từ chối phải có ít nhất 5 ký tự.',
        ]);
        abort_unless($yc->trang_thai === YeuCauPin::TT_CHO_XU_LY, 422, 'Yêu cầu không còn chờ xử lý.');

        $yc->update([
            'trang_thai' => YeuCauPin::TT_TU_CHOI,
            'admin_xu_ly_id' => $request->user()->id,
            'xu_ly_luc' => now(),
            'ly_do_tu_choi' => trim($validated['ly_do']),
            'khoa_cho' => null,
        ]);

        return back()->with('success', "Đã từ chối yêu cầu xin cấp PIN #{$yc->id}.");
    }
}
