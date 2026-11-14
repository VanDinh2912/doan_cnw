<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * BƯỚC B (P2) — D15: POS_ONLINE_TU_DONG_XAC_NHAN phải nhận đúng chuỗi env
 * 'true'/'false' (env() trả string, không phải bool). Giữ nguyên không dùng
 * RefreshDatabase để không cần chốt chặn.
 */
class ConfigP2BooleanTest extends TestCase
{
    public function test_online_tu_dong_xac_nhan_mac_dinh_false(): void
    {
        putenv('POS_ONLINE_TU_DONG_XAC_NHAN');
        $config = require config_path('pos.php');

        $this->assertFalse($config['online_tu_dong_xac_nhan']);
    }

    public function test_online_tu_dong_xac_nhan_env_true_thanh_true(): void
    {
        putenv('POS_ONLINE_TU_DONG_XAC_NHAN=true');
        $config = require config_path('pos.php');
        putenv('POS_ONLINE_TU_DONG_XAC_NHAN');

        $this->assertTrue($config['online_tu_dong_xac_nhan'], 'env chuỗi "true" phải bật cờ tự động xác nhận (D15).');
    }

    public function test_online_tu_dong_xac_nhan_env_false_giu_false(): void
    {
        putenv('POS_ONLINE_TU_DONG_XAC_NHAN=false');
        $config = require config_path('pos.php');
        putenv('POS_ONLINE_TU_DONG_XAC_NHAN');

        $this->assertFalse($config['online_tu_dong_xac_nhan']);
    }
}