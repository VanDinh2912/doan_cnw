<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Schedule::command('hold:clean')->daily();
Schedule::command('donhang:huy-qua-han')->everyMinute();
Schedule::command('sao-luu:csdl')->dailyAt('02:00');

// Chỉ báo cáo, không tự hoàn tất hóa đơn; tự bỏ qua khi chưa có SEPAY_API_TOKEN.
Schedule::command('sepay:doi-soat --gio=6')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->when(fn (): bool => trim((string) config('pos.sepay_api_token')) !== '');

// Dấu vết để he-thong:kiem-tra biết Task Scheduler còn sống hay đã ngừng.
Schedule::call(function (): void {
    Cache::put('he_thong:scheduler_last_run', now()->getTimestamp(), now()->addDay());
})->everyMinute()->name('he-thong:scheduler-heartbeat');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
