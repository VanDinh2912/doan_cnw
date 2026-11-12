<?php

// Lời gọi random_int() không đủ định danh trong namespace App\Http\Controllers
// được PHP phân giải sang hàm này trước, nên test khóa được bộ sinh số ngẫu
// nhiên mà không cần sửa code production. Mặc định ủy quyền về hàm gốc.
namespace App\Http\Controllers;

use Tests\Support\KhoaBoSinhSo;

function random_int(int $min, int $max): int
{
    return KhoaBoSinhSo::giaTri() ?? \random_int($min, $max);
}