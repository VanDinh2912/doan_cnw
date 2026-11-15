<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
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

    /**
     * Độ dài khai báo của cột VARCHAR (số ký tự), null nếu không đọc được
     * (SQLite không ghi độ dài, driver lạ, hoặc lỗi truy vấn).
     */
    public static function doDaiCotVarchar(string $bang, string $cot): ?int
    {
        $key = 'dai:'.$bang.'.'.$cot;
        if (! array_key_exists($key, self::$cache)) {
            self::$cache[$key] = self::doDaiCotVarcharTrucTiep($bang, $cot);
        }

        return self::$cache[$key];
    }

    private static function doDaiCotVarcharTrucTiep(string $bang, string $cot): ?int
    {
        try {
            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'mysql') {
                $row = DB::selectOne(
                    'SELECT CHARACTER_MAXIMUM_LENGTH AS n FROM information_schema.COLUMNS'
                    .' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                    [$bang, $cot]
                );

                return $row && $row->n !== null ? (int) $row->n : null;
            }

            if ($driver === 'sqlite') {
                foreach (DB::select('PRAGMA table_info('.$bang.')') as $cotInfo) {
                    if (($cotInfo->name ?? null) === $cot
                        && preg_match('/varchar\((\d+)/i', (string) ($cotInfo->type ?? ''), $m) === 1) {
                        return (int) $m[1];
                    }
                }
            }
        } catch (\Throwable) {
            // Không đọc được schema — caller tự chọn giá trị mặc định an toàn.
        }

        return null;
    }

    public static function datLai(): void
    {
        self::$cache = [];
    }
}
