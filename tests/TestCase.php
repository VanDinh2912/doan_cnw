<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * T3 (P1) + BƯỚC B (P2) — chốt chặn mọi trait tự migrate/truncate:
     * RefreshDatabase, DatabaseMigrations, DatabaseTruncation chỉ được chạy
     * trên sqlite :memory: như phpunit.xml; kết nối khác (MySQL thật/bản sao,
     * sqlite file) bị ném ngoại lệ tiếng Việt TRƯỚC khi migrate/truncate.
     * Các test MySQL-copy không dùng ba trait này nên không bị ảnh hưởng.
     *
     * Không đặt guard vào chính hook beforeRefreshingDatabase() của trait:
     * trait use ở lớp con ghi đè method cùng tên của lớp cha (PHP precedence)
     * nên guard ở lớp cha sẽ bị vô hiệu. Chặn ở setUpTraits — chạy ngay trước
     * khi các trait database được khởi động.
     */
    protected function setUpTraits()
    {
        $uses = array_flip(class_uses_recursive(static::class));

        if (isset($uses[RefreshDatabase::class])
            || isset($uses[DatabaseMigrations::class])
            || isset($uses[DatabaseTruncation::class])) {
            $this->chanRefreshDatabase();
        }

        return parent::setUpTraits();
    }

    protected function chanRefreshDatabase(): void
    {
        $tenKetNoi = (string) config('database.default');
        $driver = (string) config("database.connections.{$tenKetNoi}.driver");
        $database = (string) config("database.connections.{$tenKetNoi}.database");

        if ($driver !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                'RefreshDatabase bị chặn: chỉ được chạy trên sqlite :memory: (phpunit.xml) để bảo vệ CSDL thật. '
                ."Kết nối hiện tại: {$driver}/{$database}. "
                ."Test MySQL-copy không dùng RefreshDatabase — chạy bằng filter chính xác --filter='MysqlCopyTest|KhoaDongMysqlTest' "
                .'trên bản sao *_copy (xem README mục 9/17).'
            );
        }
    }
}