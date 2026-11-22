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

        // Mot view phan trang duy nhat cho toan he thong (gon, mot bo nut).
        \Illuminate\Pagination\Paginator::defaultView('vendor.pagination.app');

        // Ngoài production, ném lỗi khi quan hệ bị lazy-load để phát hiện N+1
        // ngay trong lúc phát triển/test thay vì âm thầm bắn truy vấn.
        if (! $this->app->isProduction()) {
            Model::preventLazyLoading();
        }

        // Chặn migrate/db:wipe/db:seed khi database đích là prod (doan_cnw)
        // trừ khi có cờ CHO_PHEP_MIGRATE_PROD=1 truyền tường minh.
        $this->app['events']->listen(
            \Illuminate\Console\Events\CommandStarting::class,
            \App\Listeners\ChanThaoTacCsdlProdListener::class,
        );
    }
}
