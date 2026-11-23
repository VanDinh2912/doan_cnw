<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\NhatKyService;
use App\Services\PinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PinController extends Controller
{
    /**
     * GET /xac-nhan-pin — trang nhập PIN (redirect từ middleware khi GET
     * bị chặn) hoặc đích của hộp thoại JS.
     */
    public function getNhap(Request $request): View
    {
        $return = (string) $request->query('return', '');
        // Chỉ cho quay về URL cùng host, tránh open-redirect
        $returnOk = $return !== '' && str_starts_with($return, url('/'));

        return view('pin.nhap', [
            'returnUrl' => $returnOk ? $return : null,
            'muc' => $request->query('muc') === PinService::MUC_NGHIEMTRONG
                ? PinService::MUC_NGHIEMTRONG : PinService::MUC_THUONG,
        ]);
    }

    /**
     * POST /xac-nhan-pin/kiem-tra — AJAX từ hộp thoại PIN.
     * Body: pin, muc, hanh_dong ("METHOD /path" của route sẽ gọi).
     */
    public function postKiemTra(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pin' => ['required', 'string', 'max:12'],
            'muc' => ['required', 'in:'.PinService::MUC_THUONG.','.PinService::MUC_NGHIEMTRONG],
            'hanh_dong' => ['required', 'string', 'max:255'],
        ]);
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['admin', 'thungan', 'thukho'], true), 403);

        // Throttle theo IP: 20 lần/phút — chống dò PIN qua endpoint này
        $key = 'pin-kiemtra:'.$request->ip();
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 20)) {
            return response()->json(['ma' => 'QUA_NHIEU', 'message' => 'Thử lại sau ít phút.'], 429);
        }
        \Illuminate\Support\Facades\RateLimiter::hit($key, 60);

        $kq = PinService::kiemTra(
            $user,
            $validated['pin'],
            $validated['muc'],
            $this->chuanHoaHanhDong($validated['hanh_dong']),
            $request
        );
        $status = $kq['ok'] ? 200 : (($kq['ma'] ?? '') === 'KHOA' ? 423 : 422);

        return response()->json([
            'ok' => $kq['ok'],
            'ma' => $kq['ma'],
            'token' => $kq['token'] ?? null,
            'con_lai_phut' => $kq['con_lai_phut'] ?? null,
            'message' => $this->thongBao($kq),
        ], $status);
    }

    /**
     * POST /xac-nhan-pin/xac-nhan — submit form THƯỜNG của trang nhập PIN
     * (không JS): kiểm PIN rồi redirect về URL return/intended.
     */
    public function postXacNhan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pin' => ['required', 'string', 'max:12'],
            'muc' => ['required', 'in:'.PinService::MUC_THUONG.','.PinService::MUC_NGHIEMTRONG],
        ]);
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['admin', 'thungan', 'thukho'], true), 403);

        $hanhDong = $this->hanhDongTuReturn((string) $request->input('return', ''));
        $kq = PinService::kiemTra($user, $validated['pin'], $validated['muc'], $hanhDong, $request);
        if (! $kq['ok']) {
            return redirect()->back()->withInput()->with('loi_pin', $this->thongBao($kq));
        }

        $return = (string) $request->input('return', '');
        $returnOk = $return !== '' && str_starts_with($return, url('/'));
        $dich = $returnOk ? $return : url()->previous(route('tongquan'));
        if ($validated['muc'] === PinService::MUC_NGHIEMTRONG && isset($kq['token'])) {
            $dich .= (str_contains($dich, '?') ? '&' : '?').'_pin_token='.urlencode($kq['token']);
        }

        return redirect($dich);
    }

    /** GET /pin/xin-cap — NV gửi yêu cầu cấp/đặt lại PIN kèm lý do. */
    public function getXinCap(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['admin', 'thungan', 'thukho'], true), 403);

        // M4: hien trang thai cac yeu cau xin cap PIN gan day cua chinh minh.
        $yeuCauPin = collect();
        if (\App\Support\KiemTraSchema::coBang('yeu_cau_pin')) {
            \App\Models\YeuCauPin::donHetHan();
            $yeuCauPin = \App\Models\YeuCauPin::where('user_id', $user->id)
                ->orderByDesc('id')->limit(10)->get();
        }

        return view('pin.xincap', ['yeuCauPin' => $yeuCauPin]);
    }

    /**
     * POST /pin/xin-cap — ghi yêu cầu + lý do vào nhật ký, báo Telegram cho admin.
     * Admin duyệt bằng luồng cũ /taikhoan/dat-lai-pin (PIN admin + lý do).
     */
    public function postXinCap(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['admin', 'thungan', 'thukho'], true), 403);

        $validated = $request->validate([
            'ly_do' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'ly_do.required' => 'Vui lòng nhập lý do xin cấp PIN.',
            'ly_do.min' => 'Lý do phải có ít nhất 5 ký tự.',
        ]);

        // M4: khi bang yeu_cau_pin da ton tai -> tao ban ghi co trang thai;
        // thieu bang -> luong cu (chi log + Telegram), khong loi.
        if (\App\Support\KiemTraSchema::coBang('yeu_cau_pin')) {
            \App\Models\YeuCauPin::donHetHan();
            $dangCho = \App\Models\YeuCauPin::where('user_id', $user->id)
                ->where('trang_thai', \App\Models\YeuCauPin::TT_CHO_XU_LY)->exists();
            if ($dangCho) {
                return redirect()->route('pin.xincap')
                    ->with('loi_pin', 'Bạn đã gửi yêu cầu và đang chờ quản trị viên xử lý — vui lòng chờ, không gửi trùng.');
            }
            try {
                \App\Models\YeuCauPin::create([
                    'user_id' => $user->id,
                    'ly_do' => trim($validated['ly_do']),
                    'trang_thai' => \App\Models\YeuCauPin::TT_CHO_XU_LY,
                    'khoa_cho' => $user->id,
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // khoa_cho unique: request dong thoi -> coi nhu da co yeu cau cho
                if (str_contains($e->getMessage(), 'Duplicate')) {
                    return redirect()->route('pin.xincap')
                        ->with('loi_pin', 'Bạn đã gửi yêu cầu và đang chờ quản trị viên xử lý — vui lòng chờ.');
                }
                throw $e;
            }
        }

        $lyDo = trim($validated['ly_do']);
        NhatKyService::ghi($request, 'pin_xin_cap', 'user', $user->id,
            "NV #{$user->id} ({$user->role}) xin cấp/đặt lại PIN — {$lyDo}", null, null, null, 'pin');

        try {
            app(\App\Services\ThongBaoService::class)->guiTelegram(
                "NV #{$user->id} ({$user->role}) xin cấp/đặt lại PIN. Lý do: {$lyDo}. Vào /taikhoan để duyệt."
            );
        } catch (\Throwable) {
            // Telegram lỗi không chặn yêu cầu
        }

        return redirect()->route('pin.xincap')->with('thanh_cong', 'Đã gửi yêu cầu tới quản trị viên. Sau khi được cấp, PIN tạm sẽ hiện tại mục "PIN tạm của tôi" — trang này, chỉ xem được một lần.');
    }

    /**
     * GET /pin/tam-cua-toi — NV xem PIN tạm admin vừa cấp cho CHÍNH MÌNH.
     * Đọc cache mã hóa (TTL 30'), hiện 1 lần rồi xóa ngay.
     */
    public function getTamCuaToi(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['admin', 'thungan', 'thukho'], true), 403);

        $key = self::KHOA_PIN_TAM().$user->id;
        $maHoa = \Illuminate\Support\Facades\Cache::get($key);
        if (! is_string($maHoa) || $maHoa === '') {
            return redirect()->route('pin.xincap')->with('loi_pin', 'Không có PIN tạm nào đang chờ — có thể đã xem hoặc chưa được cấp.');
        }

        try {
            $pinTam = decrypt($maHoa);
        } catch (\Throwable) {
            \Illuminate\Support\Facades\Cache::forget($key);

            return redirect()->route('pin.xincap')->with('loi_pin', 'PIN tạm không còn hiệu lực — liên hệ quản trị viên cấp lại.');
        }
        \Illuminate\Support\Facades\Cache::forget($key); // hiện đúng 1 lần

        return view('pin.tam', [
            'user' => $user,
            'pinTam' => $pinTam,
            'urlLai' => route('pin.xincap'),
            'nhanLai' => 'Về trang xin cấp PIN',
        ]);
    }

    /** Khóa cache giữ PIN tạm cho từng user — ủy quyền PinTamService (giữ tên cũ cho BC). */
    public static function KHOA_PIN_TAM(): string
    {
        return 'pin_tam_user_';
    }

    /**
     * POST /taikhoan/dat-lai-pin/{user} — admin cấp PIN tạm cho NV
     * (route gắn xac.nhan.pin:nghiemtrong nên admin đã nhập PIN của mình).
     */
    public function postDatLaiPin(Request $request, User $user): Response|RedirectResponse
    {
        $admin = $request->user();
        abort_unless($admin && $admin->role === 'admin', 403);
        abort_if($user->role === 'khachhang', 422, 'Không áp dụng cho tài khoản khách hàng.');
        $validated = $request->validate(['ly_do' => ['required', 'string', 'max:255']]);

        // Dung chung PinTamService voi duyet yeu cau (M4) — nguon 'web' = chu dong.
        \App\Services\PinTamService::capTamCho($user, $admin, $validated['ly_do'], $request, 'web');

        // KHONG hien PIN cho admin — chi NV tu xem 1 lan tai /pin/tam-cua-toi.
        // Admin biet PIN cua NV se danh bai muc dich "chong nguoi la o quay".
        return response()->view('pin.tam', [
            'user' => $user, 'pinTam' => null, 'chePin' => true,
            'urlLai' => route('taikhoan.danhsach'), 'nhanLai' => 'Quay lại danh sách tài khoản',
        ])
            ->header('Cache-Control', 'no-store, no-cache')
            ->header('Pragma', 'no-cache');
    }

    private function chuanHoaHanhDong(string $hd): string
    {
        // Chấp nhận "POST /path" hoặc chỉ "/path" (mặc định POST cho AJAX)
        if (preg_match('/^(GET|POST|PUT|PATCH|DELETE)\s+(\/\S*)$/i', trim($hd), $m)) {
            return strtoupper($m[1]).' '.rtrim($m[2], '/');
        }

        return 'POST '.rtrim($hd, '/');
    }

    private function hanhDongTuReturn(string $return): string
    {
        $path = parse_url($return, PHP_URL_PATH) ?: '/';

        return 'GET '.rtrim($path, '/');
    }

    private function thongBao(array $kq): string
    {
        return match ($kq['ma'] ?? '') {
            'SAI' => 'PIN không đúng.',
            'KHOA' => 'PIN đã bị khóa do nhập sai nhiều lần. Vui lòng thử lại sau '.($kq['con_lai_phut'] ?? 15).' phút.',
            'CHUA_CO_PIN' => 'Tài khoản chưa có PIN — liên hệ quản trị viên.',
            'PIN_YEU' => 'PIN quá yếu hoặc trùng mật khẩu.',
            default => 'Xác nhận PIN thất bại.',
        };
    }
}
