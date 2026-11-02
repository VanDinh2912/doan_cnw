<?php

use Illuminate\Support\Facades\Route;

Route::post('/webhook/thanhtoan', [\App\Http\Controllers\Api\PaymentWebhookController::class, 'postThanhToan'])
    ->name('api.webhook.thanhtoan');
