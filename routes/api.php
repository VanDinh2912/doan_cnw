<?php

use Illuminate\Support\Facades\Route;

Route::post('/webhook/thanhtoan', [\App\Http\Controllers\WebhookController::class, 'postThanhToan'])
    ->name('api.webhook.thanhtoan');
