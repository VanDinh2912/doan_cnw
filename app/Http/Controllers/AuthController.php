<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function getDangNhap(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('tongquan');
        }

        return view('auth.dangnhap');
    }

    public function postDangNhap(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'Email hoặc mật khẩu không đúng.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('tongquan'));
    }

    public function getDangXuat(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('auth.dangnhap')->with('success', 'Bạn đã đăng xuất.');
    }
}
