<?php

namespace App\Http\Controllers;

use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\User;
use App\Rules\SoDienThoaiHopLe;
use App\Services\NhatKyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KhachHangController extends Controller
{
    public function getDanhSach(): View
    {
        return view('khachhang.danhsach', ['khachHangs' => KhachHang::latest()->get()]);
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

        return view('khachhang.chitiet', [
            'khachHang' => $khachHang,
            'donHangs' => $donHangs,
            'tongChiTieu' => $tongChiTieu,
        ]);
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
