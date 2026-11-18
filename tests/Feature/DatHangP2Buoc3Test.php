<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\PaymentWebhook;
use App\Models\SanPham;
use App\Models\User;
use App\Services\ThanhToanChuyenKhoanService;
use App\Support\CheThongTin;
use App\Support\KiemTraSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * P2 BƯỚC 3 — Webhook hoàn tất đơn giao tận nơi: trạng thái giao theo
 * POS_ONLINE_TU_DONG_XAC_NHAN (D15), idempotent, thiếu/thừa tiền giữ nguyên
 * luồng đối soát hiện có, guard DB thiếu cột, Telegram che PII + escape,
 * trang trạng thái chỉ lộ địa chỉ cho người đã xác thực.
 */
class DatHangP2Buoc3Test extends TestCase
{
    use RefreshDatabase;

    private string $apiKey = 'test_sepay_key_p2b3';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        Config::set('pos.sepay_webhook_api_key', $this->apiKey);
        Config::set('pos.bank_account', '0388888888');
        Config::set('pos.telegram_bot_token', '');
        Config::set('pos.telegram_chat_id', '');
        Config::set('pos.online_tu_dong_xac_nhan', false);
        Config::set('pos.phi_giao_hang', 30000);
        Config::set('pos.mien_phi_giao_tu', 0);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
    }

    private function sp(int $ton = 10): SanPham
    {
        $sp = SanPham::orderBy('id')->firstOrFail();
        $sp->update(['soluong' => $ton, 'trang_thai' => true]);

        return $sp->fresh();
    }

    /**
     * Tạo đơn online giao tận nơi đang chờ thanh toán qua R1 (server tính phí ship).
     */
    private function donGiaoChoThanhToan(SanPham $sp, array $overrides = []): DonHang
    {
        return app(ThanhToanChuyenKhoanService::class)->taoHoaDonChoThanhToan(
            [['id' => $sp->id, 'soluong' => 1]],
            [],
            null,
            array_merge([
                'kenh' => 'online',
                'phuong_thuc' => 'chuyenkhoan',
                'hinh_thuc_nhan' => 'giao_tan_noi',
                'ma_tra_cuu' => 'GT'.random_int(100000, 999999),
                'ten_khach_dat' => 'Khách Giao',
                'so_dienthoai_dat' => '0901234567',
                'ten_nguoi_nhan' => 'Người <b>Nhận</b>',
                'sdt_nguoi_nhan' => '0907654321',
                'dia_chi_giao' => '12 Nguyễn Trãi, Phường 1, Quận 5, Hồ Chí Minh',
                'han_giu' => now()->addMinutes(30),
            ], $overrides)
        );
    }

    private function payload(DonHang $don, int $soTien, string $maGiaoDich = 'FT777001'): array
    {
        return [
            'id' => $maGiaoDich,
            'gateway' => 'MBBank',
            'transactionDate' => now()->toDateTimeString(),
            'accountNumber' => '0388888888',
            'transferType' => 'in',
            'transferAmount' => $soTien,
            'content' => 'THANH TOAN DON '.str_replace('-', '', $don->ma_hoadon),
            'referenceCode' => $maGiaoDich,
        ];
    }

    private function guiWebhook(array $payload, ?string $key = null): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('webhook.thanhtoan'), $payload, [
            'Content-Type' => 'application/json',
            'Authorization' => 'Apikey '.($key ?? $this->apiKey),
        ]);
    }

    // ---------------------------------------------------------------
    // D15: trạng thái giao theo cờ POS_ONLINE_TU_DONG_XAC_NHAN
    // ---------------------------------------------------------------

    public function test_webhook_du_tien_co_tu_dong_xac_nhan_tat_dat_cho_xac_nhan(): void
    {
        Config::set('pos.online_tu_dong_xac_nhan', false);
        $don = $this->donGiaoChoThanhToan($this->sp());
        $this->assertNull($don->trang_thai_giao);

        $res = $this->guiWebhook($this->payload($don, (int) $don->tong_thanh_toan));
        $res->assertOk()->assertJson(['success' => true]);

        $don->refresh();
        $this->assertSame('da_thanh_toan', $don->trang_thai);
        $this->assertSame('cho_xac_nhan', $don->trang_thai_giao);
        $this->assertSame('FT777001', $don->ma_giaodich);
    }

    public function test_webhook_du_tien_co_tu_dong_xac_nhan_bat_dat_da_xac_nhan(): void
    {
        Config::set('pos.online_tu_dong_xac_nhan', true);
        $don = $this->donGiaoChoThanhToan($this->sp());

        $this->guiWebhook($this->payload($don, (int) $don->tong_thanh_toan))->assertOk();

        $this->assertSame('da_xac_nhan', $don->fresh()->trang_thai_giao);
    }

    public function test_webhook_lap_khong_doi_trang_thai_giao_da_co(): void
    {
        $don = $this->donGiaoChoThanhToan($this->sp());
        $payload = $this->payload($don, (int) $don->tong_thanh_toan);

        $this->guiWebhook($payload)->assertOk();
        $this->assertSame('cho_xac_nhan', $don->fresh()->trang_thai_giao);

        // Giả lập nhân viên (P3) đã chuyển sang đang giao → webhook lặp không ghi đè.
        $don->update(['trang_thai_giao' => 'dang_giao']);

        // Cùng mã giao dịch → early return idempotent.
        $this->guiWebhook($payload)->assertOk();
        // Giao dịch khác cùng hóa đơn → "đã thanh toán", không đổi trạng thái.
        $this->guiWebhook($this->payload($don, (int) $don->tong_thanh_toan, 'FT777002'))->assertOk();

        $this->assertSame('dang_giao', $don->fresh()->trang_thai_giao);
        $this->assertSame('da_thanh_toan', $don->fresh()->trang_thai);
    }

    public function test_thieu_tien_vao_doi_soat_khong_hoan_tat_khong_dat_trang_thai_giao(): void
    {
        $don = $this->donGiaoChoThanhToan($this->sp());
        $tong = (int) $don->tong_thanh_toan;

        $this->guiWebhook($this->payload($don, $tong - 1000, 'FT777003'))->assertOk();

        $don->refresh();
        $this->assertSame('cho_thanh_toan', $don->trang_thai);
        $this->assertNull($don->trang_thai_giao);
        $this->assertDatabaseHas('payment_webhooks', [
            'ma_giao_dich_ngoai' => 'FT777003',
            'ket_qua' => 'can_doi_soat',
            'ly_do' => 'thieu_tien',
        ]);
    }

    public function test_thua_tien_van_hoan_tat_nhu_hien_co_va_dat_trang_thai_giao(): void
    {
        // Hành vi hiện có: chuyển dư vẫn hoàn tất + ghi chú "chuyển dư" (không đổi T-CK18).
        $don = $this->donGiaoChoThanhToan($this->sp());
        $tong = (int) $don->tong_thanh_toan;

        $this->guiWebhook($this->payload($don, $tong + 5000, 'FT777004'))->assertOk();

        $don->refresh();
        $this->assertSame('da_thanh_toan', $don->trang_thai);
        $this->assertSame('cho_xac_nhan', $don->trang_thai_giao);
        $this->assertStringContainsString('chuyển dư', (string) $don->ghi_chu);
    }

    public function test_pickup_online_va_pos_khong_bi_dat_trang_thai_giao(): void
    {
        // Pickup online: hinh_thuc_nhan = tai_cua_hang.
        $donPickup = $this->donGiaoChoThanhToan($this->sp(), [
            'hinh_thuc_nhan' => 'tai_cua_hang',
            'ten_nguoi_nhan' => null,
            'sdt_nguoi_nhan' => null,
            'dia_chi_giao' => null,
        ]);
        $this->guiWebhook($this->payload($donPickup, (int) $donPickup->tong_thanh_toan, 'FT777005'))->assertOk();
        $this->assertSame('da_thanh_toan', $donPickup->fresh()->trang_thai);
        $this->assertNull($donPickup->fresh()->trang_thai_giao);

        // Đơn POS (không kenh online): tạo tay tối thiểu.
        $sp = $this->sp();
        $donPos = DonHang::create([
            'ma_hoadon' => 'HD20261115-7777',
            'tong_tien' => 50000,
            'giam_gia_phantram' => 0,
            'tien_giam' => 0,
            'diem_su_dung' => 0,
            'tien_giam_diem' => 0,
            'diem_tichluy_nhan' => 0,
            'tong_thanh_toan' => 50000,
            'tien_khach_dua' => 50000,
            'tien_thua' => 0,
            'phuong_thuc' => 'chuyenkhoan',
            'trang_thai' => 'cho_thanh_toan',
        ]);
        $this->guiWebhook($this->payload($donPos, 50000, 'FT777006'))->assertOk();
        $this->assertSame('da_thanh_toan', $donPos->fresh()->trang_thai);
        $this->assertNull($donPos->fresh()->trang_thai_giao);
    }

    public function test_db_thieu_cot_trang_thai_giao_van_hoan_tat_don(): void
    {
        $don = $this->donGiaoChoThanhToan($this->sp());

        Schema::table('invoices', function ($table): void {
            $table->dropIndex(['trang_thai_giao']);
        });
        Schema::table('invoices', function ($table): void {
            $table->dropColumn('trang_thai_giao');
        });
        KiemTraSchema::datLai();

        $res = $this->guiWebhook($this->payload($don, (int) $don->tong_thanh_toan, 'FT777007'));
        $res->assertOk()->assertJson(['success' => true]);
        $this->assertSame('da_thanh_toan', $don->fresh()->trang_thai);
    }

    public function test_don_da_huy_webhook_khong_doi_trang_thai_giao(): void
    {
        $don = $this->donGiaoChoThanhToan($this->sp());
        $don->update(['trang_thai' => 'da_huy', 'trang_thai_giao' => 'da_xac_nhan']);

        $this->guiWebhook($this->payload($don, (int) $don->tong_thanh_toan, 'FT777008'))->assertOk();

        $don->refresh();
        $this->assertSame('da_huy', $don->trang_thai);
        $this->assertSame('da_xac_nhan', $don->trang_thai_giao);
    }

    public function test_thu_ngan_xac_nhan_thu_cong_cung_dat_trang_thai_giao(): void
    {
        Config::set('pos.online_tu_dong_xac_nhan', true);
        $don = $this->donGiaoChoThanhToan($this->sp());
        $thuNgan = User::where('role', 'thungan')->firstOrFail();

        $res = $this->actingAs($thuNgan)->postJson(route('donhang.xacnhan', $don));
        $res->assertOk();

        $this->assertSame('da_thanh_toan', $don->fresh()->trang_thai);
        $this->assertSame('da_xac_nhan', $don->fresh()->trang_thai_giao);
    }

    // ---------------------------------------------------------------
    // Telegram: mã đơn + tổng + hình thức, SĐT che, escape, KHÔNG địa chỉ
    // ---------------------------------------------------------------

    public function test_telegram_don_giao_da_thanh_toan_escape_va_che_pii(): void
    {
        Config::set('pos.telegram_bot_token', 'bot-token-test');
        Config::set('pos.telegram_chat_id', 'chat-123');
        Config::set('pos.online_tu_dong_xac_nhan', false);

        $don = $this->donGiaoChoThanhToan($this->sp());
        $tong = (int) $don->tong_thanh_toan;

        $this->guiWebhook($this->payload($don, $tong, 'FT777009'))->assertOk();

        Http::assertSent(function ($request) use ($don, $tong): bool {
            if (! str_contains($request->url(), 'api.telegram.org')) {
                return false;
            }
            $text = (string) ($request['text'] ?? '');
            // Có thông báo đơn giao đã thanh toán với mã đơn + tổng + hình thức.
            if (! str_contains($text, 'ĐƠN GIAO TẬN NƠI') || ! str_contains($text, $don->ma_hoadon)) {
                return false;
            }
            // SĐT người nhận phải bị che, không lộ đầy đủ.
            if (str_contains($text, '0907654321')) {
                return false;
            }
            // Không địa chỉ đầy đủ.
            if (str_contains($text, 'Nguyễn Trãi')) {
                return false;
            }
            // Tên khách có HTML phải được escape.
            if (str_contains($text, '<b>Nhận</b>') && ! str_contains($text, '&lt;b&gt;')) {
                return false;
            }

            return true;
        });
    }

    // ---------------------------------------------------------------
    // Tra cứu / trạng thái: địa chỉ chỉ cho người đã xác thực
    // ---------------------------------------------------------------

    public function test_trang_thai_don_khong_lo_dia_chi_cho_nguoi_chua_xac_thuc(): void
    {
        $don = $this->donGiaoChoThanhToan($this->sp());
        $this->guiWebhook($this->payload($don, (int) $don->tong_thanh_toan, 'FT777010'))->assertOk();

        // Chưa có bằng chứng session → redirect về tra cứu, không thấy địa chỉ.
        $res = $this->get(route('don-hang.trangthai', $don->ma_tra_cuu));
        $res->assertRedirect(route('don-hang.tracuu'));
        $res->assertDontSee('Nguyễn Trãi');

        // Tra cứu sai SĐT → thông báo chung, không lộ đơn tồn tại.
        $this->post(route('don-hang.posttracuu'), [
            'ma_tra_cuu' => $don->ma_tra_cuu,
            'so_dienthoai' => '0999999999',
        ])->assertSessionHas('error', 'Không tìm thấy đơn hàng');

        // Tra cứu đúng → có bằng chứng → xem được trạng thái + địa chỉ (đã xác thực).
        $this->post(route('don-hang.posttracuu'), [
            'ma_tra_cuu' => $don->ma_tra_cuu,
            'so_dienthoai' => '0901234567',
        ])->assertRedirect(route('don-hang.trangthai', $don->ma_tra_cuu));

        $this->get(route('don-hang.trangthai', $don->ma_tra_cuu))
            ->assertOk()
            ->assertSee('Nguyễn Trãi')
            ->assertSee('Chờ xác nhận');
    }

    public function test_poll_tra_ve_trang_thai_giao_khong_pii(): void
    {
        $don = $this->donGiaoChoThanhToan($this->sp());

        $res = $this->withSession(['don_hang_xac_thuc' => [$don->ma_tra_cuu => true]])
            ->postJson(route('don-hang.poll', $don->ma_tra_cuu));

        $res->assertOk()->assertJson([
            'trang_thai' => 'cho_thanh_toan',
            'trang_thai_giao' => null,
        ]);
        $this->assertStringNotContainsString('0907654321', $res->getContent());
        $this->assertStringNotContainsString('Nguyễn Trãi', $res->getContent());
    }
}
