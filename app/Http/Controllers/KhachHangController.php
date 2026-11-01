<?php

namespace App\Http\Controllers;

use App\Models\KhachHang;
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
        return view('khachhang.form', ['khachHang' => new KhachHang()]);
    }

    public function postThem(Request $request): RedirectResponse
    {
        KhachHang::create($this->validated($request));

        return redirect()->route('khachhang.danhsach')->with('success', 'Đã thêm khách hàng.');
    }

    public function getSua(KhachHang $khachHang): View
    {
        return view('khachhang.form', compact('khachHang'));
    }

    public function postSua(Request $request, KhachHang $khachHang): RedirectResponse
    {
        $khachHang->update($this->validated($request, $khachHang));

        return redirect()->route('khachhang.danhsach')->with('success', 'Đã cập nhật khách hàng.');
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
        $soDienThoai = trim((string) $request->query('sdt', ''));

        return response()->json(
            $soDienThoai === ''
                ? []
                : KhachHang::where('so_dienthoai', 'like', $soDienThoai.'%')
                    ->limit(10)->get(['id', 'ho_ten', 'so_dienthoai', 'diem_tichluy'])
        );
    }

    public function postTaoNhanh(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ho_ten' => ['required', 'string', 'max:100'],
            'so_dienthoai' => ['required', 'string', 'max:15', 'unique:customers,so_dienthoai'],
        ], [
            'ho_ten.required' => 'Vui lòng nhập họ tên.',
            'so_dienthoai.required' => 'Vui lòng nhập số điện thoại.',
            'so_dienthoai.unique' => 'Số điện thoại đã tồn tại.',
        ]);

        return response()->json(KhachHang::create($validated), 201);
    }

    private function validated(Request $request, ?KhachHang $khachHang = null): array
    {
        return $request->validate([
            'ho_ten' => ['required', 'string', 'max:100'],
            'so_dienthoai' => ['required', 'string', 'max:15', Rule::unique('customers', 'so_dienthoai')->ignore($khachHang)],
            'dia_chi' => ['nullable', 'string', 'max:255'],
        ], [
            'ho_ten.required' => 'Vui lòng nhập họ tên.',
            'so_dienthoai.required' => 'Vui lòng nhập số điện thoại.',
            'so_dienthoai.unique' => 'Số điện thoại đã tồn tại.',
        ]);
    }
}
