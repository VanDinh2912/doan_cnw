<?php

namespace App\Support;

/**
 * NGUỒN DUY NHẤT cấp tên hiển thị của khách trên storefront.
 * Luôn trả tên ĐÃ CHE — kể cả sau khi khách xác thực lại hồ sơ.
 * Dữ liệu đầy đủ chỉ được hiển thị trong cửa sổ xác thực ở trang hồ sơ.
 */
class TenHienThi
{
    public static function khachDangNhap(): ?string
    {
        $user = auth()->user();

        return $user ? ChePII::hoTen($user->name) : null;
    }
}
