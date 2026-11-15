<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Cache kết quả Schema::hasTable/hasColumn trong biến tĩnh: tối đa một truy vấn
 * mỗi (bảng, cột) trong mỗi tiến trình. Dùng cho các guard "code đi trước
 * migration" (DB thật chưa áp cột mới) thay cho array_key_exists trên model
 * rỗng — cách cũ luôn sai vì (new Model)->getAttributes() trả mảng rỗng.
 *
 * Trong test có bỏ/thêm cột giữa chừng phải gọi datLai() sau khi đổi schema.
 *
 * TODO(gỡ): sau khi các migration bổ sung đã áp lên DB thật doan_cnw, xóa các
 * guard dùng class này và xóa class — xem docs báo cáo Stage 3-4.
 */
class KiemTraSchema
{
    /** @var array<string, bool> */
    private static array $cache = [];

    public static function coBang(string $bang): bool
    {
        return self::$cache['bang:'.$bang] ??= Schema::hasTable($bang);
    }

    public static function coCot(string $bang, string $cot): bool
    {
        return self::$cache['cot:'.$bang.'.'.$cot] ??= Schema::hasColumn($bang, $cot);
    }

    public static function datLai(): void
    {
        self::$cache = [];
    }
}
