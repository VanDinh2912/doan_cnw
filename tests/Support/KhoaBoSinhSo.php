<?php

namespace Tests\Support;

class KhoaBoSinhSo
{
    private static ?int $giaTri = null;

    public static function khoa(?int $giaTri): void
    {
        self::$giaTri = $giaTri;
    }

    public static function giaTri(): ?int
    {
        return self::$giaTri;
    }
}