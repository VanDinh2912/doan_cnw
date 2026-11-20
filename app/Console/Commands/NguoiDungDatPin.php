<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\NhatKyService;
use App\Services\PinService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class NguoiDungDatPin extends Command
{
    protected $signature = 'nguoidung:dat-pin {email : Email tài khoản nhân viên (admin/thungan/thukho)}';

    protected $description = 'Đặt/đặt lại PIN xác nhận thao tác nhạy cảm cho nhân viên (nhập ẩn, có xác nhận)';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->error("Không tìm thấy tài khoản {$email}.");

            return self::FAILURE;
        }
        if ($user->role === 'khachhang') {
            $this->error('Lệnh này không áp dụng cho tài khoản khách hàng.');

            return self::FAILURE;
        }

        $pin = $this->secret('Nhập PIN mới (6 chữ số): ');
        if (! PinService::dungFormat((string) $pin)) {
            $this->error('PIN phải gồm đúng 6 chữ số.');

            return self::FAILURE;
        }
        if (! hash_equals((string) $pin, (string) $this->secret('Nhập lại PIN: '))) {
            $this->error('PIN nhập lại không khớp.');

            return self::FAILURE;
        }
        try {
            PinService::datPin($user, (string) $pin);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        NhatKyService::ghi(
            Request::create('/', 'GET'),
            'pin_dat', 'user', $user->id,
            "Đặt PIN qua artisan cho user #{$user->id} ({$user->role})",
            null, null, 'console', 'pin'
        );
        $this->info("Đã đặt PIN cho {$email} (role {$user->role}).");

        return self::SUCCESS;
    }
}
