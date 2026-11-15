<?php

namespace App\Http\Controllers;

use App\Models\KhachHang;
use App\Models\User;
use App\Rules\SoDienThoaiHopLe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KhachHangController extends Controller
{
    public function getDanhSach(): View
    {
        return view('khachhang.danhsach', ['khachHangs' => KhachHang::latest()->get()]);
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
