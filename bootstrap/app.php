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

        // Chi nhom web: chan byte khong hop le UTF-8 trong input -> 422/loi
        // validate. Webhook /api/webhook/thanhtoan withoutMiddleware('web')
        // nen khong bi anh huong; api/* cung nam ngoai nhom web.
        $middleware->web(append: [
            \App\Http\Middleware\ValidateUtf8Encoding::class,
        ]);

        // Che do chi-webhook (POS_CHI_CHO_WEBHOOK): global de phu ca route
        // webhook von withoutMiddleware('web'). Mac dinh tat.
        $middleware->append(\App\Http\Middleware\ChiChoWebhook::class);

        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'staff.only' => \App\Http\Middleware\RedirectKhachhangFromStaff::class,
            'khoa.luc' => \App\Http\Middleware\CheckKhoaLuc::class,
            'bat.buoc.doi.mat.khau' => \App\Http\Middleware\CheckBatBuocDoiMatKhau::class,
            'dat.hang.online' => \App\Http\Middleware\ChanDatHangOnline::class,
            'xac.nhan.pin' => \App\Http\Middleware\XacNhanPin::class,
            'pin.muc' => \App\Http\Middleware\PinMuc::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
