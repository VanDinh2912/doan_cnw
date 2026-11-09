<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectKhachhangFromStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->role === 'khachhang') {
            return redirect()
                ->route('storefront.home')
                ->with('error', 'Trang này dành cho nhân viên.');
        }

        return $next($request);
    }
}