<?php

namespace App\Http\Controllers;

use App\Services\NhatKyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class DoiMatKhauController extends Controller
{
    /**
     * Form đổi mật khẩu (cho mọi vai trò đã đăng nhập)
     */
    public function getForm()
    {
        if (!Auth::check()) {
            return redirect()->route('auth.dangnhap');
        }

        $user = Auth::user();
        $isStaff = in_array($user->role, ['admin', 'thungan', 'thukho'], true);

        return view($isStaff ? 'taikhoan.doimatkhau' : 'taikhoan.doimatkhau_khach', compact('user'));
    }

    /**
     * Xử lý đổi mật khẩu
     */
    public function postDoi(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('auth.dangnhap');
        }

        $user = Auth::user();

        $validated = $request->validate([
            'mat_khau_hien_tai' => 'required|string',
            'mat_khau_moi' => ['required', 'string', Password::min(8)->letters()->numbers()->symbols()],
            'mat_khau_xac_nhan' => 'required|string|same:mat_khau_moi',
        ], [
            'mat_khau_hien_tai.required' => 'Vui lòng nhập mật khẩu hiện tại.',
            'mat_khau_moi.required' => 'Vui lòng nhập mật khẩu mới.',
            'mat_khau_moi.min' => 'Mật khẩu mới phải có ít nhất 8 ký tự.',
            'mat_khau_xac_nhan.required' => 'Vui lòng xác nhận mật khẩu mới.',
            'mat_khau_xac_nhan.same' => 'Mật khẩu xác nhận không khớp.',
        ]);

        // Kiểm tra mật khẩu hiện tại
        if (!Hash::check($validated['mat_khau_hien_tai'], $user->password)) {
            return back()->with('error', 'Mật khẩu hiện tại không đúng.');
        }

        // Đổi mật khẩu
        $user->update([
            'password' => Hash::make($validated['mat_khau_moi']),
            'bat_buoc_doi_mat_khau' => false, // Bỏ cờ bắt buộc đổi mật khẩu
        ]);
        \App\Services\PinService::xoaSessionPin();
        \App\Support\HoSoXacThuc::xoa(request());

        // Ghi nhật ký (không ghi mật khẩu)
        NhatKyService::ghi(
            request(),
            'doi_mat_khau',
            'users',
            $user->id,
            "Đổi mật khẩu: {$user->name} ({$user->role})",
            ['bat_buoc_doi_mat_khau' => true],
            ['bat_buoc_doi_mat_khau' => false],
            'web'
        );

        $isStaff = in_array($user->role, ['admin', 'thungan', 'thukho'], true);
        $redirectRoute = $isStaff ? 'tongquan' : 'storefront.home';

        return redirect()->route($redirectRoute)
            ->with('success', 'Đã đổi mật khẩu thành công.');
    }
}
