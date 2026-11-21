<?php

namespace App\Http\Controllers;

use App\Models\DiemBienDong;
use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\User;
use App\Rules\SoDienThoaiHopLe;
use App\Services\DiemService;
use App\Services\NhatKyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KhachHangController extends Controller
{
    public function getDanhSach(Request $request): View
    {
        // Lọc theo trạng thái liên kết tài khoản đăng nhập: ?tai_khoan=co|khong
        $query = KhachHang::latest()->withExists('user');
        $taiKhoan = $request->query('tai_khoan');
        if ($taiKhoan === 'co') {
            $query->whereHas('user');
        } elseif ($taiKhoan === 'khong') {
            $query->whereDoesntHave('user');
        }

        return view('khachhang.danhsach', [
            'khachHangs' => $query->get(),
            'taiKhoan' => $taiKhoan,
        ]);
    }

    /**
     * Chi tiết hồ sơ khách: thông tin, trạng thái khóa, lịch sử đơn
     * (POS + online) phân trang và tổng chi tiêu. Read-only.
     */
    public function getChiTiet(Request $request, KhachHang $khachHang): View
    {
        $donHangs = $khachHang->donHangs()
            ->with('chiTietDonHangs')
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        $tongChiTieu = (float) $khachHang->donHangs()
            ->whereNotIn('trang_thai', ['da_huy'])
            ->sum('tong_thanh_toan');

        $soGiaoDichDiem = DiemService::sanSang()
            ? DiemBienDong::where('customer_id', $khachHang->id)->latest('id')->limit(10)->get()
            : collect();

        return view('khachhang.chitiet', [
            'khachHang' => $khachHang,
            'donHangs' => $donHangs,
            'tongChiTieu' => $tongChiTieu,
            'soGiaoDichDiem' => $soGiaoDichDiem,
        ]);
    }

    /**
     * Điều chỉnh điểm tích lũy thủ công (CHỈ admin — route role:admin +
     * PIN nghiêm trọng). Lý do bắt buộc, |điểm| <= POS_DIEM_DIEU_CHINH_TOI_DA,
     * số dư sau điều chỉnh không âm. Ghi sổ diem_bien_dong loai=dieu_chinh.
     */
    public function postDieuChinhDiem(Request $request, KhachHang $khachHang): RedirectResponse
    {
        $data = $request->validate([
            'so_diem' => ['required', 'integer'],
            'ly_do' => ['required', 'string', 'min:3', 'max:255'],
        ], [
            'so_diem.required' => 'Vui lòng nhập số điểm điều chỉnh.',
            'so_diem.integer' => 'Số điểm phải là số nguyên.',
            'ly_do.required' => 'Vui lòng nhập lý do điều chỉnh.',
            'ly_do.min' => 'Lý do tối thiểu 3 ký tự.',
            'ly_do.max' => 'Lý do tối đa 255 ký tự.',
        ]);

        $soDiem = (int) $data['so_diem'];
        $toiDa = max(1, (int) config('pos.diem_dieu_chinh_toi_da', 500));
        if ($soDiem === 0) {
            return back()->withInput()->with('error', 'Số điểm điều chỉnh phải khác 0.');
        }
        if (abs($soDiem) > $toiDa) {
            return back()->withInput()->with('error', 'Mỗi lần điều chỉnh tối đa '.$toiDa.' điểm.');
        }

        $soDuMoi = null;
        DB::transaction(function () use ($khachHang, $soDiem, $data, &$soDuMoi): void {
            $kh = KhachHang::whereKey($khachHang->id)->lockForUpdate()->firstOrFail();
            $soDuMoi = (int) $kh->diem_tichluy + $soDiem;
            if ($soDuMoi < 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'so_diem' => ['Số dư điểm không đủ cho mức điều chỉnh này.'],
                ]);
            }
            $kh->diem_tichluy = $soDuMoi;
            $kh->save();

            DiemService::ghi($kh->id, DiemService::LOAI_DIEU_CHINH, $soDiem, $soDuMoi, [
                'user_id' => auth()->id(),
                'ly_do' => $data['ly_do'],
                'su_kien' => 'dieu-chinh:'.$kh->id.':'.Str::uuid(),
            ]);
        });

        NhatKyService::ghi(
            $request, 'dieu_chinh_diem', 'customers', $khachHang->id,
            'Điều chỉnh điểm khách #'.$khachHang->id.': '.($soDiem > 0 ? '+' : '').$soDiem.' điểm (số dư '.$soDuMoi.')',
            null,
            ['ly_do' => $data['ly_do']]
        );

        return redirect()->route('khachhang.chitiet', $khachHang)
            ->with('success', 'Đã điều chỉnh điểm: '.($soDiem > 0 ? '+' : '').$soDiem.' (số dư mới '.$soDuMoi.').');
    }

    /**
     * Khóa hồ sơ khách (CHỈ admin — route đã chặn role). Ghi customers.khoa_den
     * + ly_do_khoa và users.khoa_luc (nếu có tài khoản liên kết) TRONG CÙNG
     * TRANSACTION. Không xóa, không đụng đơn hàng/công nợ. Bắt buộc lý do.
     */
    public function postKhoa(Request $request, KhachHang $khachHang): RedirectResponse
    {
        $data = $request->validate([
            'ly_do_khoa' => ['required', 'string', 'max:255'],
        ], [
            'ly_do_khoa.required' => 'Vui lòng nhập lý do khóa khách hàng.',
            'ly_do_khoa.max' => 'Lý do khóa tối đa 255 ký tự.',
        ]);

        if ($khachHang->biKhoa()) {
            return back()->with('error', 'Khách hàng này đã bị khóa trước đó.');
        }

        DB::transaction(function () use ($khachHang, $data): void {
            $khachHang->update([
                'khoa_den' => now(),
                'ly_do_khoa' => $data['ly_do_khoa'],
            ]);
            if ($khachHang->user_id !== null) {
                User::whereKey($khachHang->user_id)->update(['khoa_luc' => now()]);
            }
        });

        NhatKyService::ghi(
            $request, 'khoa_khach', 'customers', $khachHang->id,
            'Khóa khách hàng #'.$khachHang->id.' ('.($khachHang->user_id ? 'có tài khoản' : 'không tài khoản').')',
            null,
            ['ly_do_khoa' => $data['ly_do_khoa']]
        );

        return redirect()->route('khachhang.chitiet', $khachHang)
            ->with('success', 'Đã khóa khách hàng.');
    }

    /**
     * Mở khóa hồ sơ khách (CHỈ admin). Gỡ customers.khoa_den/ly_do_khoa và
     * users.khoa_luc về null trong cùng transaction.
     */
    public function postMoKhoa(Request $request, KhachHang $khachHang): RedirectResponse
    {
        if (! $khachHang->biKhoa()) {
            return back()->with('error', 'Khách hàng này không ở trạng thái khóa.');
        }

        DB::transaction(function () use ($khachHang): void {
            $khachHang->update(['khoa_den' => null, 'ly_do_khoa' => null]);
            if ($khachHang->user_id !== null) {
                User::whereKey($khachHang->user_id)->update(['khoa_luc' => null]);
            }
        });

        NhatKyService::ghi(
            $request, 'mo_khoa_khach', 'customers', $khachHang->id,
            'Mở khóa khách hàng #'.$khachHang->id
        );

        return redirect()->route('khachhang.chitiet', $khachHang)
            ->with('success', 'Đã mở khóa khách hàng.');
    }

    /**
     * Đặt lại mật khẩu tài khoản khách hàng (CHỈ admin — route role:admin +
     * throttle). Sinh MK tạm ≥12 ký tự (loại ký tự dễ nhầm), hash bcrypt,
     * đặt bat_buoc_doi_mat_khau, đổi remember_token, xóa mọi phiên DB của
     * user đó. MK tạm KHÔNG lưu rõ ở đâu cả — chỉ render 1 lần trong response
     * (header no-store, không redirect/flash). Audit chỉ ghi người làm + lý do.
     */
    public function postDatLaiMatKhau(Request $request, KhachHang $khachHang): View|RedirectResponse|Response
    {
        $data = $request->validate([
            'ly_do_khoa' => ['required', 'string', 'max:255'],
        ], [
            'ly_do_khoa.required' => 'Vui lòng nhập lý do đặt lại mật khẩu.',
            'ly_do_khoa.max' => 'Lý do tối đa 255 ký tự.',
        ]);

        $user = $khachHang->user;
        if ($user === null) {
            return back()->with('error', 'Khách hàng chưa liên kết tài khoản đăng nhập.');
        }
        if ($user->role !== 'khachhang') {
            return back()->with('error', 'Chỉ hỗ trợ đặt lại mật khẩu cho tài khoản khách hàng.');
        }
        if ($user->khoa_luc !== null) {
            return back()->with('error', 'Tài khoản đăng nhập đang bị khóa — hãy mở khóa trước.');
        }
        if ($khachHang->biKhoa()) {
            return back()->with('error', 'Hồ sơ khách hàng đang bị khóa — hãy mở khóa trước.');
        }

        $matKhauTam = $this->sinhMatKhauTam();

        DB::transaction(function () use ($user, $matKhauTam): void {
            $user->forceFill([
                'password' => Hash::make($matKhauTam),
                'bat_buoc_doi_mat_khau' => true,
                // remember_token mới → cookie "ghi nhớ đăng nhập" cũ vô hiệu.
                'remember_token' => Str::random(60),
            ])->save();

            // Hủy mọi phiên DB đang đăng nhập của user này.
            DB::table('sessions')->where('user_id', $user->id)->delete();
        });

        // Audit: người làm + lý do + id tài khoản — TUYỆT ĐỐI không chứa MK.
        NhatKyService::ghi(
            $request, 'dat_lai_mat_khau', 'user', $user->id,
            'Đặt lại mật khẩu tài khoản khách #'.$khachHang->id,
            null,
            ['ly_do' => $data['ly_do_khoa'], 'bat_buoc_doi_mat_khau' => true]
        );

        // Response 200 trực tiếp (không redirect/flash) + no-store để MK
        // tạm chỉ hiện đúng một lần và không bị cache/proxy lưu lại.
        return response()
            ->view('khachhang.matkhau_tam', [
                'khachHang' => $khachHang,
                'user' => $user,
                'matKhauTam' => $matKhauTam,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache');
    }

    /** MK tạm ≥12 ký tự, loại 0 O l 1 I (dễ nhầm khi đọc/ghi tay). */
    private function sinhMatKhauTam(): string
    {
        $kyTu = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $doDai = strlen($kyTu) - 1;
        $mk = '';
        for ($i = 0; $i < 14; $i++) {
            $mk .= $kyTu[random_int(0, $doDai)];
        }

        return $mk;
    }

    public function getThem(): View
    {
        return view('khachhang.form', [
            'khachHang' => new KhachHang(),
            'taiKhoan' => $this->taiKhoanCoTheLienKet(null),
        ]);
    }

    public function postThem(Request $request): RedirectResponse
    {
        KhachHang::create($this->validated($request));

        return redirect()->route('khachhang.danhsach')->with('success', 'Đã thêm khách hàng.');
    }

    public function getSua(KhachHang $khachHang): View
    {
        return view('khachhang.form', [
            'khachHang' => $khachHang,
            'taiKhoan' => $this->taiKhoanCoTheLienKet($khachHang),
        ]);
    }

    public function postSua(Request $request, KhachHang $khachHang): RedirectResponse
    {
        $data = $this->validated($request, $khachHang);

        // Liên kết tài khoản khách hàng do NHÂN VIÊN gán thủ công (xác nhận
        // danh tính ngoài hệ thống). Không bao giờ tự động theo SĐT.
        // Chỉ đổi liên kết khi form thực sự gửi field user_id; request cũ
        // không có field này thì giữ nguyên liên kết hiện tại.
        $userId = $khachHang->user_id;
        if ($request->has('user_id')) {
            $userId = $request->validate([
                'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'khachhang')],
            ])['user_id'] ?? null;
        }

        if ($userId !== null) {
            $daCoChu = KhachHang::where('user_id', $userId)
                ->where('id', '!=', $khachHang->id)
                ->exists();
            if ($daCoChu) {
                return back()->withInput()->with('error', 'Tài khoản này đã liên kết với khách hàng khác.');
            }
        }
        $data['user_id'] = $userId;

        $khachHang->update($data);

        return redirect()->route('khachhang.danhsach')->with('success', 'Đã cập nhật khách hàng.');
    }

    /**
     * Tài khoản role khachhang chưa liên kết hồ sơ nào, hoặc đang liên kết
     * chính hồ sơ đang sửa (để select hiển thị đúng chủ hiện tại).
     */
    private function taiKhoanCoTheLienKet(?KhachHang $khachHang)
    {
        return User::where('role', 'khachhang')
            ->where(function ($q) use ($khachHang): void {
                $q->whereDoesntHave('khachHang');
                if ($khachHang?->exists) {
                    $q->orWhereHas('khachHang', fn ($q2) => $q2->where('customers.id', $khachHang->id));
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    public function getXoa(KhachHang $khachHang): RedirectResponse
    {
        if ($khachHang->donHangs()->exists()) {
            return back()->with('error', 'Không thể xóa khách hàng đã có hóa đơn.');
        }

        $khachHang->delete();

        return redirect()->route('khachhang.danhsach')->with('success', 'Đã xóa khách hàng.');
    }

    public function getTimKiem(Request $request): JsonResponse
    {
        $sdt = $request->query('sdt', '');
        $soDienThoai = is_string($sdt) ? SoDienThoaiHopLe::chuanHoa($sdt) : '';
        $tuKhoa = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $soDienThoai);

        return response()->json(
            $soDienThoai === ''
                ? []
                : KhachHang::where('so_dienthoai', 'like', $tuKhoa.'%')
                    ->limit(10)->get(['id', 'ho_ten', 'so_dienthoai', 'diem_tichluy'])
        );
    }

    public function postTaoNhanh(Request $request): JsonResponse
    {
        $request->merge([
            'so_dienthoai' => SoDienThoaiHopLe::chuanHoa($request->input('so_dienthoai')),
        ]);

        $validated = $request->validate([
            'ho_ten' => ['required', 'string', 'max:100'],
            'so_dienthoai' => ['bail', 'required', 'string', new SoDienThoaiHopLe(), 'unique:customers,so_dienthoai'],
        ], [
            'ho_ten.required' => 'Vui lòng nhập họ tên.',
            'so_dienthoai.required' => 'Vui lòng nhập số điện thoại.',
            'so_dienthoai.string' => SoDienThoaiHopLe::THONG_BAO,
            'so_dienthoai.unique' => 'Số điện thoại đã tồn tại.',
        ]);

        return response()->json(KhachHang::create($validated), 201);
    }

    private function validated(Request $request, ?KhachHang $khachHang = null): array
    {
        $request->merge([
            'so_dienthoai' => SoDienThoaiHopLe::chuanHoa($request->input('so_dienthoai')),
        ]);

        return $request->validate([
            'ho_ten' => ['required', 'string', 'max:100'],
            'so_dienthoai' => ['bail', 'required', 'string', new SoDienThoaiHopLe(), Rule::unique('customers', 'so_dienthoai')->ignore($khachHang)],
            'dia_chi' => ['nullable', 'string', 'max:255'],
        ], [
            'ho_ten.required' => 'Vui lòng nhập họ tên.',
            'so_dienthoai.required' => 'Vui lòng nhập số điện thoại.',
            'so_dienthoai.string' => SoDienThoaiHopLe::THONG_BAO,
            'so_dienthoai.unique' => 'Số điện thoại đã tồn tại.',
        ]);
    }
}
