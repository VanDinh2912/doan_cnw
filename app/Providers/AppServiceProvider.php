<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(150);

        // Ngoài production, ném lỗi khi quan hệ bị lazy-load để phát hiện N+1
        // ngay trong lúc phát triển/test thay vì âm thầm bắn truy vấn.
        if (! $this->app->isProduction()) {
            Model::preventLazyLoading();
        }
    }
}
