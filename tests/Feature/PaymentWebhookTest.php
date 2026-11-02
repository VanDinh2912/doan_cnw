<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\PaymentWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Helper: tạo hóa đơn chuyển khoản mẫu.
     */
    private function taoHoaDon(int $tongThanhToan = 500000): DonHang
    {
        return DonHang::create([
            'ma_hoadon'          => 'HD' . now()->format('Ymd') . '-9999',
            'user_id'            => 1,
            'tong_tien'          => $tongThanhToan,
            'giam_gia_phantram'  => 0,
            'tien_giam'          => 0,
            'tong_thanh_toan'    => $tongThanhToan,
            'tien_khach_dua'     => $tongThanhToan,
            'tien_thua'          => 0,
            'phuong_thuc'        => 'chuyenkhoan',
            'trang_thai'         => 'da_thanh_toan',
            'thoi_gian_thanh_toan' => now(),
        ]);
    }

    /**
     * Helper: gửi webhook với chữ ký HMAC nếu có secret.
     */
    private function guiWebhook(array $payload, ?string $secret = null): \Illuminate\Testing\TestResponse
    {
        $json    = json_encode($payload, JSON_THROW_ON_ERROR);
        $headers = ['Content-Type' => 'application/json'];

        if ($secret) {
            $headers['X-Webhook-Signature'] = hash_hmac('sha256', $json, $secret);
        }

        return $this->call('POST', '/api/webhook/thanhtoan', [], [], [], $this->transformHeadersToServerVars($headers), $json);
    }

    // ----------------------------------------------------------------
    // T66: Webhook hợp lệ ➜ cập nhật ma_giao_dich vào hóa đơn
    // ----------------------------------------------------------------
    public function test_T66_webhook_hop_le_cap_nhat_ma_giao_dich(): void
    {
        $donHang = $this->taoHoaDon(500000);

        $response = $this->guiWebhook([
            'ma_giao_dich' => 'FT26110200012345',
            'so_tien'      => 500000,
            'nha_cung_cap' => 'vietqr',
            'ma_hoadon'    => $donHang->ma_hoadon,
        ]);

        $response->assertOk();
        $response->assertJson(['message' => 'Xác nhận thanh toán thành công.']);

        // Kiểm tra DB
        $donHang->refresh();
        $this->assertSame('FT26110200012345', $donHang->ma_giao_dich);

        $this->assertDatabaseHas('payment_webhooks', [
            'ma_giao_dich_ngoai' => 'FT26110200012345',
            'invoice_id'         => $donHang->id,
            'ket_qua'            => 'success',
        ]);
    }

    // ----------------------------------------------------------------
    // T67: Webhook trùng lặp (idempotent) ➜ trả 200 nhưng không ghi thêm
    // ----------------------------------------------------------------
    public function test_T67_webhook_trung_lap_idempotent(): void
    {
        $donHang = $this->taoHoaDon(300000);

        $payload = [
            'ma_giao_dich' => 'FT26110200099999',
            'so_tien'      => 300000,
            'nha_cung_cap' => 'vietqr',
            'ma_hoadon'    => $donHang->ma_hoadon,
        ];

        // Gửi lần 1
        $this->guiWebhook($payload)->assertOk();
        $this->assertSame(1, PaymentWebhook::where('ma_giao_dich_ngoai', 'FT26110200099999')->count());

        // Gửi lần 2 (trùng)
        $response = $this->guiWebhook($payload);
        $response->assertOk();
        $response->assertJson(['message' => 'Giao dịch đã được xử lý.']);

        // Vẫn chỉ 1 bản ghi
        $this->assertSame(1, PaymentWebhook::where('ma_giao_dich_ngoai', 'FT26110200099999')->count());
    }

    // ----------------------------------------------------------------
    // T68: Webhook số tiền không khớp ➜ ghi log lỗi, trả 422
    // ----------------------------------------------------------------
    public function test_T68_webhook_so_tien_khong_khop(): void
    {
        $donHang = $this->taoHoaDon(500000);

        $response = $this->guiWebhook([
            'ma_giao_dich' => 'FT26110200055555',
            'so_tien'      => 499999,            // sai 1 đồng
            'nha_cung_cap' => 'vietqr',
            'ma_hoadon'    => $donHang->ma_hoadon,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Số tiền không khớp.']);

        // ma_giao_dich trên hóa đơn vẫn null
        $donHang->refresh();
        $this->assertNull($donHang->ma_giao_dich);

        // Webhook log ghi nhận ket_qua = failed
        $this->assertDatabaseHas('payment_webhooks', [
            'ma_giao_dich_ngoai' => 'FT26110200055555',
            'ket_qua'            => 'failed',
        ]);
    }
}
