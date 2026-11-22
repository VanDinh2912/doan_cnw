<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * BƯỚC B (P2) — D15: POS_ONLINE_TU_DONG_XAC_NHAN phải nhận đúng chuỗi env
 * 'true'/'false' (env() trả string, không phải bool). Giữ nguyên không dùng
 * RefreshDatabase để không cần chốt chặn.
 *
 * phpunit.xml ghim POS_ONLINE_TU_DONG_XAC_NHAN=false vào $_ENV/$_SERVER —
 * putenv() một mình KHÔNG ghi đè được $_ENV nên phải set cả ba nguồn.
 */
class ConfigP2BooleanTest extends TestCase
{
    private const KEY = 'POS_ONLINE_TU_DONG_XAC_NHAN';

    private function datEnv(?string $giaTri): void
    {
        if ($giaTri === null) {
            putenv(self::KEY);
            unset($_ENV[self::KEY], $_SERVER[self::KEY]);

            return;
        }
        putenv(self::KEY.'='.$giaTri);
        $_ENV[self::KEY] = $giaTri;
        $_SERVER[self::KEY] = $giaTri;
    }

    protected function tearDown(): void
    {
        // Trả về trạng thái ghim của phpunit.xml sau mỗi test.
        $this->datEnv('false');
        parent::tearDown();
    }

    public function test_online_tu_dong_xac_nhan_mac_dinh_false(): void
    {
        $this->datEnv(null);
        $config = require config_path('pos.php');

        $this->assertFalse($config['online_tu_dong_xac_nhan']);
    }

    public function test_online_tu_dong_xac_nhan_env_true_thanh_true(): void
    {
        $this->datEnv('true');
        $config = require config_path('pos.php');

        $this->assertTrue($config['online_tu_dong_xac_nhan'], 'env chuỗi "true" phải bật cờ tự động xác nhận (D15).');
    }

    public function test_online_tu_dong_xac_nhan_env_false_giu_false(): void
    {
        $this->datEnv('false');
        $config = require config_path('pos.php');

        $this->assertFalse($config['online_tu_dong_xac_nhan']);
    }
}
