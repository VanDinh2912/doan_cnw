<?php

namespace App\Http\Controllers;

use App\Models\KhachHang;
use App\Rules\SoDienThoaiHopLe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ThongTinCaNhanController extends Controller
{
    public function getProfile(): View
    {
        $khachHang = Auth::user()->khachHang ?? null;

        return view('thongtincanhan.profile', [
            'khachHang' => $khachHang,
        ]);
    }

    public function postProfile(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $khachHang = $user->khachHang;

        $request->merge([
            'so_dienthoai' => SoDienThoaiHopLe::chuanHoa($request->input('so_dienthoai')),
        ]);

        $validated = $request->validate([
            'ho_ten' => ['required', 'string', 'max:100'],
            'so_dienthoai' => ['bail', 'required', 'string', new SoDienThoaiHopLe(), Rule::unique('customers', 'so_dienthoai')->ignore($khachHang)],
            'dia_chi' => ['nullable', 'string', 'max:255'],
        ], [
            'ho_ten.required' => 'Vui lòng nhập họ tên.',
            'so_dienthoai.required' => 'Vui lòng nhập số điện thoại.',
            'so_dienthoai.string' => SoDienThoaiHopLe::THONG_BAO,
            'so_dienthoai.unique' => 'Số điện thoại này không thể sử dụng.',
            'dia_chi.max' => 'Địa chỉ tối đa 255 ký tự.',
        ]);

        if (! $khachHang) {
            $khachHang = KhachHang::create([
                'user_id' => $user->id,
                'ho_ten' => $validated['ho_ten'],
                'so_dienthoai' => $validated['so_dienthoai'],
                'dia_chi' => $validated['dia_chi'] ?? null,
                'diem_tichluy' => 0,
            ]);
        } else {
            $khachHang->update([
                'ho_ten' => $validated['ho_ten'],
                'so_dienthoai' => $validated['so_dienthoai'],
                'dia_chi' => $validated['dia_chi'] ?? null,
            ]);
        }

        return back()->with('success', 'Cập nhật thông tin thành công.');
    }

    public function getOrders(): View
    {
        $khachHang = Auth::user()->khachHang ?? null;
        $donHangs = $khachHang ? $khachHang->donHangs()->latest()->paginate(10) : collect();

        return view('thongtincanhan.orders', [
            'khachHang' => $khachHang,
            'donHangs' => $donHangs,
        ]);
    }
}
