<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            'api/webhook/thanhtoan',
        ]);

        $middleware->redirectGuestsTo(fn () => route('auth.dangnhap'));

        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'staff.only' => \App\Http\Middleware\RedirectKhachhangFromStaff::class,
            'khoa.luc' => \App\Http\Middleware\CheckKhoaLuc::class,
            'bat.buoc.doi.mat.khau' => \App\Http\Middleware\CheckBatBuocDoiMatKhau::class,
            'dat.hang.online' => \App\Http\Middleware\ChanDatHangOnline::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
