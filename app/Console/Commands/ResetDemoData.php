<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ChanLenhPhaHuy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ResetDemoData extends Command
{
    use ChanLenhPhaHuy;

    protected $signature = 'demo:reset
                            {--force : Bỏ qua xác nhận}
                            {--force-dangerous : Cho phép chạy ở production (vẫn phải nhập lại tên database)}
                            {--ten-db-xac-nhan= : Tên database xác nhận khi chạy production không tương tác}';

    protected $description = 'Đặt lại dữ liệu demo của Web POS';

    public function handle(): int
    {
        $tenDb = $this->xacNhanLenhPhaHuy();
        if ($tenDb === false) {
            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Thao tác sẽ xóa toàn bộ dữ liệu. Tiếp tục?')) {
            $this->warn('Đã hủy.');

            return self::SUCCESS;
        }

        try {
            $this->call('migrate:fresh', array_merge([
                '--seed' => true,
                '--seeder' => 'DatabaseSeeder',
                '--force' => true,
            ], $this->tuyChonXacNhanPhaHuy($tenDb)));
            $this->call('cache:clear');
            $this->call('view:clear');
            $this->xoaSession();
            Storage::disk('public')->deleteDirectory('sanpham');
            Storage::disk('public')->deleteDirectory('hoadonnhap');

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
