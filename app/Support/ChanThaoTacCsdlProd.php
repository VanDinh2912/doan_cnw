<?php

namespace App\Support;

/**
 * Guard chặn thao tác CSDL nguy hiểm khi database đích là prod (doan_cnw).
 *
 * Cờ mở phải truyền TƯỜNG MINH qua biến môi trường của tiến trình
 * (CHO_PHEP_MIGRATE_PROD=1) — cố ý KHÔNG đọc từ .env/config để không
 * bị bật nhầm lâu dài. CSDL dev (doan_cnw_dev, doan_cnw_dev_cu) và
 * sqlite test không bị chặn.
 */
final class ChanThaoTacCsdlProd
{
    public const DB_PROD = 'doan_cnw';

    public const CO_PHEP = 'CHO_PHEP_MIGRATE_PROD';

    /**
     * Tên database sẽ được dùng: ưu tiên --database của lệnh, không thì
     * connection mặc định.
     */
    public static function tenDatabase(?string $databaseOption = null): ?string
    {
        $connection = ($databaseOption !== null && $databaseOption !== '')
            ? $databaseOption
            : (string) config('database.default');

        $ten = config("database.connections.{$connection}.database");

        return is_string($ten) ? $ten : null;
    }

    public static function laProd(?string $databaseOption = null): bool
    {
        $ten = self::tenDatabase($databaseOption);

        return $ten !== null && strcasecmp($ten, self::DB_PROD) === 0;
    }

    /** Cờ cho phép phải đến từ môi trường tiến trình (=1), không đọc .env. */
    public static function duocPhepRoRang(): bool
    {
        return getenv(self::CO_PHEP) === '1';
    }

    /** true = lệnh phải bị chặn. */
    public static function nenChan(?string $databaseOption = null): bool
    {
        return self::laProd($databaseOption) && ! self::duocPhepRoRang();
    }

    public static function thongBao(): string
    {
        return 'Thao tác CSDL nguy hiểm bị chặn: database đích là prod (doan_cnw). '
            .'Nếu thực sự cần chạy, đặt biến môi trường '.self::CO_PHEP.'=1 '
            .'cho đúng một lệnh (xem docs/SO_TAY_TRIEN_KHAI.md).';
    }
}
