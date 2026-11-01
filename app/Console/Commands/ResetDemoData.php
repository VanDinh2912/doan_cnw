<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ResetDemoData extends Command
{
    protected $signature = 'demo:reset {--force : Bỏ qua xác nhận}';

    protected $description = 'Đặt lại dữ liệu demo của Web POS';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Không được chạy lệnh reset demo ở môi trường production.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Thao tác sẽ xóa toàn bộ dữ liệu. Tiếp tục?')) {
            $this->warn('Đã hủy.');

            return self::SUCCESS;
        }

        try {
            $this->call('migrate:fresh', [
                '--seed' => true,
                '--seeder' => 'DatabaseSeeder',
                '--force' => true,
            ]);
            $this->call('cache:clear');
            $this->call('view:clear');
            $this->xoaSession();

            $this->info('Đã reset demo.');
            $this->table(
                ['Vai trò', 'Email', 'Mật khẩu'],
                DB::table('users')->orderBy('id')->get(['role', 'email'])
                    ->map(fn ($user) => [$user->role, $user->email, '123456'])
                    ->all()
            );
        } catch (\Throwable $exception) {
            $this->error('Reset thất bại: '.$exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function xoaSession(): void
    {
        if (config('session.driver') !== 'file') {
            return;
        }

        foreach (File::glob(storage_path('framework/sessions/*')) as $file) {
            if (basename($file) !== '.gitignore') {
                File::delete($file);
            }
        }
    }
}
