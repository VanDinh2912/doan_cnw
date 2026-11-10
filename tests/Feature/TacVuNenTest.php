<?php

namespace Tests\Feature;

use App\Models\GioTam;
use App\Models\User;
use App\Services\ThongBaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Bước 5B mục 5 và 8: command hold:clean và nhánh Telegram gửi thành công.
 */
class TacVuNenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_hold_clean_chi_xoa_gio_tam_qua_24_gio(): void
    {
        $user = User::where('role', 'thungan')->firstOrFail();

        $cu = GioTam::create([
            'user_id' => $user->id,
            'ten_goi' => 'Giỏ cũ',
            'du_lieu' => [['id' => 1, 'soluong' => 1]],
        ]);
        $cu->created_at = now()->subHours(25);
        $cu->save();

        $moi = GioTam::create([
            'user_id' => $user->id,
            'ten_goi' => 'Giỏ mới',
            'du_lieu' => [['id' => 2, 'soluong' => 1]],
        ]);

        Artisan::call('hold:clean');
        $output = Artisan::output();

        $this->assertStringContainsString('Đã xóa 1 giỏ tạm hết hạn.', $output);
        $this->assertDatabaseMissing('held_carts', ['id' => $cu->id]);
        $this->assertDatabaseHas('held_carts', ['id' => $moi->id]);
    }

    public function test_gui_telegram_thanh_cong_tra_ve_true_va_khong_ghi_canh_bao(): void
    {
        config([
            'pos.telegram_bot_token' => '123456:token-gia-lap',
            'pos.telegram_chat_id' => '-100123456',
        ]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200)]);
        Log::spy();

        $ketQua = app(ThongBaoService::class)->guiTelegram('Đơn hàng đã thanh toán');

        $this->assertTrue($ketQua);
        Log::shouldNotHaveReceived('warning');
        Http::assertSent(fn ($request) => $request['chat_id'] === '-100123456'
            && $request['text'] === 'Đơn hàng đã thanh toán');
    }
}
