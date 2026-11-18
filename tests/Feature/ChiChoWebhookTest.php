<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ChiChoWebhookTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_mac_dinh_tat_khong_anh_huong_route(): void
    {
        Config::set('pos.chi_cho_webhook', false);

        $this->get('/')->assertOk();
        $this->get('/bai-viet')->assertOk();
    }

    public function test_bat_len_moi_route_tra_404_ngoai_webhook(): void
    {
        Config::set('pos.chi_cho_webhook', true);

        $this->get('/')->assertNotFound();
        $this->get('/bai-viet')->assertNotFound();
        $this->get('/sanpham')->assertNotFound();
        $this->get('/up')->assertNotFound();
        $this->postJson('/api/webhook/thanhtoan', [])->assertStatus(401);
    }

    public function test_bat_len_webhook_sai_key_van_den_duoc_controller(): void
    {
        Config::set('pos.chi_cho_webhook', true);
        Config::set('pos.sepay_webhook_api_key', 'khoa-that');

        // Tới được controller → 401 do sai key (không phải 404 do middleware)
        $this->postJson('/api/webhook/thanhtoan', [], [
            'Authorization' => 'Apikey sai-key',
        ])->assertUnauthorized();
    }
}
