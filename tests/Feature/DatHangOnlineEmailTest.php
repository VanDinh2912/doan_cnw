<?php

namespace Tests\Feature;

use App\Mail\DatHangOnlineDaThanhToanEmail;
use App\Mail\DatHangOnlineXacNhanEmail;
use App\Models\DonHang;
use App\Models\SanPham;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

/**
 * Email xác nhận đơn online + mail "đã nhận thanh toán" qua webhook SePay.
 * Guest có email nhận mail; không email/không nhập thì đơn vẫn tạo bình thường;
 * lỗi mail không làm hỏng đơn; webhook lặp không gửi mail trùng.
 */
class DatHangOnlineEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Config::set('pos.sepay_webhook_api_key', 'test_sepay_key_123');
    }

    private function sp(int $ton = 10, int $chiSo = 0): SanPham
    {
        if (SanPham::query()->count() === 0) {
            $this->seed();
        }
        $sp = SanPham::orderBy('id')->skip($chiSo)->firstOrFail();
        $sp->update(['soluong' => $ton, 'trang_thai' => true]);

        return $sp->fresh();
    }

    private function cart(SanPham $sp, int $qty): array
    {
        return [(string) $sp->id => [
            'id' => $sp->id,
            'sku' => $sp->sku,
            'ten_sanpham' => $sp->ten_sanpham,
            'gia_ban' => (float) $sp->gia_ban,
            'soluong' => $qty,
            'ton_kho' => $sp->soluong,
        ]];
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'ten' => 'Khách Email Test',
            'so_dienthoai' => '0901234567',
            'ghi_chu' => null,
            'phuong_thuc' => 'pickup',
            'checkout_token' => 'TOKEN',
        ], $overrides);
    }

    private function dat(array $cart, array $payload = [], string $token = 'TOKEN'): \Illuminate\Testing\TestResponse
    {
        return $this->withSession([
            'checkout_token' => $token,
            'storefront_cart' => $cart,
        ])->post(route('dat-hang.dat'), $this->payload(array_merge(['checkout_token' => $token], $payload)));
    }

    public function test_guest_co_email_nhan_mail_xac_nhan_va_luu_chuan_hoa(): void
    {
        Mail::fake();
        $sp = $this->sp(10);

        $this->dat($this->cart($sp, 1), ['email' => '  Guest@Test.COM '])
            ->assertRedirect();

        $don = DonHang::where('kenh', 'online')->firstOrFail();
        $this->assertSame('guest@test.com', $don->email_dat);

        Mail::assertSent(DatHangOnlineXacNhanEmail::class, function ($mail) use ($don) {
            return $mail->hasTo('guest@test.com')
                && $mail->donHang->id === $don->id;
        });
    }

    public function test_guest_khong_email_van_tao_don_khong_gui_mail(): void
    {
        Mail::fake();
        $sp = $this->sp(10);

        $this->dat($this->cart($sp, 1))->assertRedirect();

        $don = DonHang::where('kenh', 'online')->firstOrFail();
        $this->assertNull($don->email_dat);
        Mail::assertNothingSent();
    }

    public function test_email_sai_dinh_dang_bi_tu_choi_khong_tao_don(): void
    {
        Mail::fake();
        $sp = $this->sp(10);

        $res = $this->dat($this->cart($sp, 1), ['email' => 'khong-phai-email']);
        $res->assertSessionHasErrors('email');

        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseHas('products', ['id' => $sp->id, 'soluong' => 10]);
        Mail::assertNothingSent();
    }

    public function test_gui_mail_loi_van_tao_don_thanh_cong(): void
    {
        // Mail::to() trả PendingMail; giả lập send() ném lỗi (SMTP sập).
        $pending = Mockery::mock(PendingMail::class);
        $pending->shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP down'));
        Mail::shouldReceive('to')->once()->with('guest@test.com')->andReturn($pending);

        $sp = $this->sp(10);
        $res = $this->dat($this->cart($sp, 1), ['email' => 'guest@test.com']);

        $res->assertRedirect();
        $don = DonHang::where('kenh', 'online')->firstOrFail();
        $this->assertSame('cho_thanh_toan', $don->trang_thai);
        $this->assertDatabaseHas('products', ['id' => $sp->id, 'soluong' => 9]);
    }

    public function test_webhook_sepay_gui_mail_da_thanh_toan_dung_mot_lan(): void
    {
        Mail::fake();
        $sp = $this->sp(10);

        // Đơn online VietQR có email
        $this->dat($this->cart($sp, 1), [
            'phuong_thuc' => 'qr',
            'email' => 'khach@test.com',
        ], 'TOKEN_QR')->assertRedirect();

        $don = DonHang::where('kenh', 'online')->firstOrFail();
        $this->assertSame('cho_thanh_toan', $don->trang_thai);
        $this->assertSame('khach@test.com', $don->email_dat);

        $cleanMa = str_replace('-', '', $don->ma_hoadon);
        $payload = [
            'id' => 555000111,
            'transferType' => 'in',
            'transferAmount' => (int) $don->tong_thanh_toan,
            'content' => 'THANH TOAN DON ' . $cleanMa,
            'referenceCode' => 'REF-EMAIL-001',
        ];
        $headers = ['Authorization' => 'Apikey test_sepay_key_123'];

        // Webhook lần 1 → đơn da_thanh_toan + mail xác nhận thanh toán
        $this->postJson(route('webhook.thanhtoan'), $payload, $headers)->assertOk();
        $this->assertSame('da_thanh_toan', $don->fresh()->trang_thai);

        Mail::assertSent(DatHangOnlineDaThanhToanEmail::class, 1);
        Mail::assertSent(DatHangOnlineDaThanhToanEmail::class, function ($mail) use ($don) {
            return $mail->hasTo('khach@test.com') && $mail->donHang->id === $don->id;
        });

        // Webhook lặp cùng id → idempotent, không gửi mail trùng
        $this->postJson(route('webhook.thanhtoan'), $payload, $headers)->assertOk();
        Mail::assertSent(DatHangOnlineDaThanhToanEmail::class, 1);
    }

    public function test_webhook_don_khong_email_khong_gui_mail(): void
    {
        Mail::fake();
        $sp = $this->sp(10);

        $this->dat($this->cart($sp, 1), [
            'phuong_thuc' => 'qr',
        ], 'TOKEN_QR2')->assertRedirect();

        $don = DonHang::where('kenh', 'online')->firstOrFail();
        $this->assertNull($don->email_dat);

        $cleanMa = str_replace('-', '', $don->ma_hoadon);
        $this->postJson(route('webhook.thanhtoan'), [
            'id' => 555000222,
            'transferType' => 'in',
            'transferAmount' => (int) $don->tong_thanh_toan,
            'content' => 'THANH TOAN ' . $cleanMa,
        ], ['Authorization' => 'Apikey test_sepay_key_123'])->assertOk();

        $this->assertSame('da_thanh_toan', $don->fresh()->trang_thai);
        Mail::assertNotSent(DatHangOnlineDaThanhToanEmail::class);
    }
}
