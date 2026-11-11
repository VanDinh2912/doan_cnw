<?php

namespace App\Console\Commands;

use App\Models\NhatKyHoatDong;
use App\Models\User;
use Illuminate\Console\Command;

class TaoNguoiDung extends Command
{
    public const VAI_TRO_HOP_LE = ['admin', 'thungan', 'thukho'];

    protected $signature = 'nguoidung:tao
                            {email}
                            {vai_tro : admin, thungan hoặc thukho}
                            {--ten= : Tên hiển thị, mặc định lấy phần trước dấu @}';

    protected $description = 'Tạo tài khoản nội bộ (mật khẩu nhập ẩn)';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        $vaiTro = (string) $this->argument('vai_tro');

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Email không hợp lệ.');

            return self::FAILURE;
        }

        if (! in_array($vaiTro, self::VAI_TRO_HOP_LE, true)) {
            $this->error('Vai trò phải là một trong: '.implode(', ', self::VAI_TRO_HOP_LE).'.');

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error("Email \"{$email}\" đã được sử dụng.");

            return self::FAILURE;
        }

        $matKhau = (string) $this->secret('Mật khẩu (tối thiểu 8 ký tự)');
        if (mb_strlen($matKhau) < 8) {
            $this->error('Mật khẩu phải có ít nhất 8 ký tự.');

            return self::FAILURE;
        }

        if ((string) $this->secret('Nhập lại mật khẩu') !== $matKhau) {
            $this->error('Hai lần nhập không khớp. Không có thay đổi nào.');

            return self::FAILURE;
        }

        $ten = trim((string) ($this->option('ten') ?: strstr($email, '@', true)));

        $user = User::create([
            'name' => $ten === '' ? $email : $ten,
            'email' => $email,
            'password' => $matKhau,
            'role' => $vaiTro,
        ]);

        NhatKyHoatDong::create([
            'user_id' => null,
            'hanh_dong' => 'tao_nguoi_dung',
            'doi_tuong' => 'user',
            'doi_tuong_id' => $user->id,
            'mo_ta' => "Tạo tài khoản {$email} với vai trò {$vaiTro} qua dòng lệnh",
            'ip' => null,
        ]);

        $this->info("Đã tạo tài khoản {$user->email} (vai trò {$vaiTro}).");

        return self::SUCCESS;
    }
}