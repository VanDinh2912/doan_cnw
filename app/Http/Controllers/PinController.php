<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\NhatKyService;
use App\Services\PinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
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
        $dich = $returnOk ? $return : url()->previous(route('home'));
        if ($validated['muc'] === PinService::MUC_NGHIEMTRONG && isset($kq['token'])) {
            $dich .= (str_contains($dich, '?') ? '&' : '?').'_pin_token='.urlencode($kq['token']);
        }

        return redirect($dich);
    }

    /** GET /doi-pin — form đổi PIN (PIN cũ + PIN mới + xác nhận). */
    public function getDoiPin(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user->pin_hash) {
            return redirect()->route('pin.xincap')->with('loi_pin', 'Tài khoản chưa có PIN — gửi yêu cầu để quản trị viên cấp.');
        }

        return view('pin.doi');
    }

    /** GET /pin/xin-cap — NV gửi yêu cầu cấp/đặt lại PIN kèm lý do. */
    public function getXinCap(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['admin', 'thungan', 'thukho'], true), 403);

        return view('pin.xincap');
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

    /** Khóa cache giữ PIN tạm cho từng user (mã hóa bằng APP_KEY, TTL 30'). */
    public static function KHOA_PIN_TAM(): string
    {
        return 'pin_tam_user_';
    }

    /** POST /doi-pin — đổi PIN; chia sẻ bộ đếm sai/khóa với mọi điểm kiểm PIN. */
    public function postDoiPin(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pin_cu' => ['required', 'string', 'max:12'],
            'pin_moi' => ['required', 'string', 'max:12', 'confirmed'],
        ]);
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['admin', 'thungan', 'thukho'], true), 403);

        // Tái dùng bộ đếm chung: sai PIN cũ tính vào pin_sai_lien_tiep
        $kq = PinService::kiemTra($user, $validated['pin_cu'], PinService::MUC_THUONG, 'DOI /doi-pin', $request);
        if (! $kq['ok']) {
            return redirect()->back()->withInput()->with('loi_pin', $this->thongBao($kq));
        }
        try {
            PinService::datPin($user, $validated['pin_moi']);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('loi_pin', $e->getMessage());
        }
        NhatKyService::ghi($request, 'pin_doi', 'user', $user->id, 'Đổi PIN', null, null, null, 'pin');

        return redirect()->route('pin.doi')->with('thanh_cong', 'Đã đổi PIN thành công.');
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

        // PIN tạm 6 số ngẫu nhiên, tránh dãy yếu hiển nhiên
        do {
            $pinTam = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (PinService::pinYeu($pinTam, $user));

        $user->forceFill([
            'pin_hash' => Hash::make($pinTam),
            'pin_sai_lien_tiep' => 0,
            'pin_khoa_den' => null,
            'pin_doi_luc' => now(),
            'bat_buoc_doi_pin' => false, // PIN tạm dùng luon lam PIN thuong, khong ep doi
        ])->save();
        PinService::xoaSessionPin();
        // Lưu PIN tạm mã hóa trong cache 30' để CHÍNH NV đó tự xem 1 lần
        // tại /pin/tam-cua-toi (không cần admin đọc cho NV).
        \Illuminate\Support\Facades\Cache::put(
            self::KHOA_PIN_TAM().$user->id, encrypt($pinTam), now()->addMinutes(30)
        );
        NhatKyService::ghi($request, 'pin_dat_lai', 'user', $user->id,
            "Admin #{$admin->id} đặt lại PIN cho user #{$user->id} — {$validated['ly_do']}", null, null, null, 'pin');

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
