<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    // Trang đăng nhập đọc bảng users để cảnh báo mật khẩu mặc định.
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_guest_can_open_the_login_page(): void
    {
        $response = $this->get('/dangnhap');

        $response->assertStatus(200);
        $response->assertSee('Đăng nhập Web POS');
    }
}
