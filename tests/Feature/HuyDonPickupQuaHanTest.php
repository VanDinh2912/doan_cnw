<?php

namespace Tests\Feature;

use App\Console\Commands\KiemTraHeThong;
use App\Models\BienDongKho;
use App\Models\DonHang;
use App\Models\SanPham;
use App\Models\User;
use App\Services\ThanhToanChuyenKhoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * T1 — Scheduler/hủy lười không được hủy nhầm đơn pickup online còn hạn giữ.
 * Quy tắc: đơn kenh=online + phuong_thuc=tienmat + han_giu có giá trị → hủy khi
 * han_giu < now (không áp hạn 15 phút); mọi đơn còn lại (POS, QR, han_giu null)
 * giữ nguyên created_at + cho_thanh_toan_phut. Dùng Carbon::setTestNow để đặt mốc.
 */
class HuyDonPickupQuaHanTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $mocGoc;
    private SanPham $sp;
    private User $cashier;
    private static int $soMa = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->mocGoc = Carbon::parse('2026-11-13 10:00:00');
        Carbon::setTestNow($this->mocGoc);

        Config::set('pos.cho_thanh_toan_phut', 15);
        Config::set('pos.online_pickup_gio', 4);
        Config::set('pos.sepay_webhook_api_key', 'test_sepay_key_123');
        Config::set('pos.telegram_bot_token', '');
        Config::set('pos.telegram_chat_id', '');
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $this->cashier = User::where('role', 'thungan')->firstOrFail();
        $this->sp = SanPham::orderBy('id')->firstOrFail();
        $this->sp->update(['soluong' => 10, 'trang_thai' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function maTraCuu(): string
    {
        return 'PK'.str_pad((string) (++self::$soMa), 10, '0', STR_PAD_LEFT);
    }

    // Đơn pickup online thật qua R1: giữ hàng, han_giu = now + online_pickup_gio.
    private function taoPickup(int $qty = 2): DonHang
    {
        return app(ThanhToanChuyenKhoanService::class)->taoHoaDonChoThanhToan(
            [['id' => $this->sp->id, 'soluong' => $qty]],
            [],
            null,
            [
                'kenh' => 'online',
                'phuong_thuc' => 'tienmat',
                'ma_tra_cuu' => $this->maTraCuu(),
                'ten_khach_dat' => 'Khách Pickup',
                'so_dienthoai_dat' => '0901234567',
                'han_giu' => now()->addHours((int) config('pos.online_pickup_gio', 4)),
            ]
        );
    }

    // Đơn QR POS cũ: kenh=pos, chuyenkhoan, han_giu null → quy tắc created_at + 15 phút.
    private function taoQrPos(int $qty = 1): DonHang
    {
        return app(ThanhToanChuyenKhoanService::class)->taoHoaDonChoThanhToan(
            [['id' => $this->sp->id, 'soluong' => $qty]],
            [],
            $this->cashier
        );
    }

    public function test_pickup_con_han_o_moc_16_phut_khong_bi_huy(): void
    {
        $don = $this->taoPickup();
        Carbon::setTestNow($this->mocGoc->copy()->addMinutes(16));

        $this->artisan('donhang:huy-qua-han')->assertExitCode(0);

        $this->assertSame('cho_thanh_toan', $don->fresh()->trang_thai);
        $this->assertSame(8, (int) $this->sp->fresh()->soluong);
        $this->assertDatabaseMissing('stock_movements', ['invoice_id' => $don->id, 'loai' => 'dieuchinh']);
    }

    public function test_pickup_con_han_o_moc_3h59_khong_bi_huy(): void
    {
        $don = $this->taoPickup();
        Carbon::setTestNow($this->mocGoc->copy()->addHours(3)->addMinutes(59));

        $this->artisan('donhang:huy-qua-han')->assertExitCode(0);

        $this->assertSame('cho_thanh_toan', $don->fresh()->trang_thai);
        $this->assertSame(8, (int) $this->sp->fresh()->soluong);
    }

    public function test_pickup_qua_han_o_moc_4h01_bi_huy_hoan_kho_movement_nhat_ky(): void
    {
        $don = $this->taoPickup(2);
        Carbon::setTestNow($this->mocGoc->copy()->addHours(4)->addMinutes(1));

        $this->artisan('donhang:huy-qua-han')->assertExitCode(0);

        $don->refresh();
        $this->assertSame('da_huy', $don->trang_thai);
        // Hoàn kho về đúng tồn gốc.
        $this->assertSame(10, (int) $this->sp->fresh()->soluong);
        // Movement dieuchinh số lượng dương.
        $this->assertDatabaseHas('stock_movements', [
            'invoice_id' => $don->id, 'loai' => 'dieuchinh', 'soluong' => 2,
        ]);
        // Nhật ký hủy ghi trên đơn (lý do scheduler).
        $this->assertStringContainsString('Quá hạn chờ thanh toán', (string) $don->ghi_chu);
    }

    public function test_don_qr_pos_cu_van_huy_o_moc_16_phut(): void
    {
        $don = $this->taoQrPos();
        Carbon::setTestNow($this->mocGoc->copy()->addMinutes(16));

        $this->artisan('donhang:huy-qua-han')->assertExitCode(0);

        $this->assertSame('da_huy', $don->fresh()->trang_thai);
        $this->assertSame(10, (int) $this->sp->fresh()->soluong);
    }

    public function test_don_pickup_han_giu_null_van_huy_theo_created_at(): void
    {
        // Đơn online pickup cũ chưa có han_giu → fallback created_at + 15 phút.
        $don = $this->taoPickup();
        $don->update(['han_giu' => null]);
        Carbon::setTestNow($this->mocGoc->copy()->addMinutes(16));

        $this->artisan('donhang:huy-qua-han')->assertExitCode(0);

        $this->assertSame('da_huy', $don->fresh()->trang_thai);
    }

    public function test_don_gia_han_khong_bi_huy_o_han_cu(): void
    {
        $don = $this->taoPickup();
        // Gia hạn: han_giu mới = gốc + 8h (trần gia hạn cho phép).
        $don->update(['han_giu' => $this->mocGoc->copy()->addHours(8)]);
        // Mốc 4h01: quá hạn giữ cũ (4h) nhưng còn hạn mới (8h).
        Carbon::setTestNow($this->mocGoc->copy()->addHours(4)->addMinutes(1));

        $this->artisan('donhang:huy-qua-han')->assertExitCode(0);

        $this->assertSame('cho_thanh_toan', $don->fresh()->trang_thai);
        $this->assertSame(8, (int) $this->sp->fresh()->soluong);
    }

    public function test_huy_thu_cong_huycho_van_hoat_dong_khi_con_han(): void
    {
        $don = $this->taoPickup();
        Carbon::setTestNow($this->mocGoc->copy()->addMinutes(16));

        $this->actingAs($this->cashier)
            ->postJson(route('donhang.huycho', $don))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame('da_huy', $don->fresh()->trang_thai);
        $this->assertSame(10, (int) $this->sp->fresh()->soluong);
    }

    public function test_khach_tu_huy_van_hoat_dong_khi_con_han(): void
    {
        $don = $this->taoPickup();
        Carbon::setTestNow($this->mocGoc->copy()->addMinutes(16));

        $this->withSession(['don_hang_xac_thuc' => [$don->ma_tra_cuu => true]])
            ->post(route('don-hang.huy', $don->ma_tra_cuu))
            ->assertRedirect();

        $this->assertSame('da_huy', $don->fresh()->trang_thai);
        $this->assertSame(10, (int) $this->sp->fresh()->soluong);
    }

    public function test_chay_lenh_hai_lan_lien_tiep_idempotent(): void
    {
        $don = $this->taoPickup(2);
        Carbon::setTestNow($this->mocGoc->copy()->addHours(4)->addMinutes(1));

        $this->artisan('donhang:huy-qua-han')->assertExitCode(0);
        $this->assertSame('da_huy', $don->fresh()->trang_thai);
        $this->assertSame(10, (int) $this->sp->fresh()->soluong);
        $soMovement = BienDongKho::where('invoice_id', $don->id)->where('loai', 'dieuchinh')->count();
        $this->assertSame(1, $soMovement);

        $this->artisan('donhang:huy-qua-han')
            ->expectsOutput('Không có hóa đơn nào quá hạn chờ thanh toán.')
            ->assertExitCode(0);

        $this->assertSame(10, (int) $this->sp->fresh()->soluong);
        $this->assertSame(1, BienDongKho::where('invoice_id', $don->id)->where('loai', 'dieuchinh')->count());
    }

    public function test_webhook_hoan_tat_don_qr_qua_han_nhu_cu(): void
    {
        // T-CK18 không đổi: đơn QR POS quá hạn 15 phút nhưng chưa bị hủy,
        // webhook vẫn hoàn tất (không đụng quy tắc hủy pickup).
        $don = $this->taoQrPos();
        Carbon::setTestNow($this->mocGoc->copy()->addMinutes(30));

        $cleanMa = str_replace('-', '', $don->ma_hoadon);
        $this->postJson(route('webhook.thanhtoan'), [
            'id' => 990011,
            'transferType' => 'in',
            'transferAmount' => (int) $don->tong_thanh_toan,
            'content' => 'THANH TOAN DON '.$cleanMa,
        ], ['Authorization' => 'Apikey test_sepay_key_123'])->assertOk();

        $don->refresh();
        $this->assertSame('da_thanh_toan', $don->trang_thai);
        $this->assertSame('990011', $don->ma_giaodich);
    }

    public function test_pos_poll_khong_huy_nham_pickup_con_han(): void
    {
        $don = $this->taoPickup();
        Carbon::setTestNow($this->mocGoc->copy()->addMinutes(16));

        $this->actingAs($this->cashier)
            ->getJson(route('donhang.trangthai', $don))
            ->assertOk()
            ->assertJson(['trang_thai' => 'cho_thanh_toan']);

        $this->assertSame('cho_thanh_toan', $don->fresh()->trang_thai);
        $this->assertSame(8, (int) $this->sp->fresh()->soluong);
    }

    public function test_pos_poll_huy_pickup_khi_qua_han_giu(): void
    {
        $don = $this->taoPickup();
        Carbon::setTestNow($this->mocGoc->copy()->addHours(4)->addMinutes(1));

        $this->actingAs($this->cashier)
            ->getJson(route('donhang.trangthai', $don))
            ->assertOk()
            ->assertJson(['trang_thai' => 'da_huy']);

        $this->assertSame(10, (int) $this->sp->fresh()->soluong);
    }

    public function test_kiem_tra_he_thong_khong_bao_qua_han_pickup_con_han(): void
    {
        $this->taoPickup();
        Carbon::setTestNow($this->mocGoc->copy()->addMinutes(16));
        Cache::put(KiemTraHeThong::KHOA_SCHEDULER, now()->getTimestamp(), now()->addDay());

        Artisan::call('he-thong:kiem-tra');
        $output = Artisan::output();

        $this->assertStringContainsString('[OK] Đơn chờ quá hạn', $output);
        $this->assertStringNotContainsString('[WARN] Đơn chờ quá hạn', $output);
    }
}