<?php

namespace App\Console\Commands;

use App\Models\NhatKyHoatDong;
use App\Models\User;
use App\Services\CanhBaoMatKhauMacDinh;
use Illuminate\Console\Command;

class DoiMatKhauNguoiDung extends Command
{
    protected $signature = 'nguoidung:doi-matkhau {email}';

    protected $description = 'Đổi mật khẩu một tài khoản nội bộ (mật khẩu nhập ẩn)';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));

        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            $this->error("Không tìm thấy tài khoản \"{$email}\".");

            return self::FAILURE;
        }

        $matKhau = (string) $this->secret('Mật khẩu mới (tối thiểu 8 ký tự)');
        if (mb_strlen($matKhau) < 8) {
            $this->error('Mật khẩu phải có ít nhất 8 ký tự.');

            return self::FAILURE;
        }

        if ((string) $this->secret('Nhập lại mật khẩu mới') !== $matKhau) {
            $this->error('Hai lần nhập không khớp. Không có thay đổi nào.');

            return self::FAILURE;
        }

        $user->update(['password' => $matKhau]);
        CanhBaoMatKhauMacDinh::quenCache();

        NhatKyHoatDong::create([
            'user_id' => null,
            'hanh_dong' => 'doi_mat_khau',
            'doi_tuong' => 'user',
            'doi_tuong_id' => $user->id,
            'mo_ta' => 'Đổi mật khẩu qua dòng lệnh cho '.$user->email,
            'ip' => null,
        ]);

        $this->info("Đã đổi mật khẩu cho {$user->email}.");

        return self::SUCCESS;
    }
}