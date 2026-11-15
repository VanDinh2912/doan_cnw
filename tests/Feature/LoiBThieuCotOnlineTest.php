<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\SanPham;
use App\Models\User;
use App\Services\ThanhToanChuyenKhoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Lỗi B (code đi trước schema) — DB thật doan_cnw chưa áp M1/M3.
 * Mô phỏng SQLite thiếu cột online (M1: kenh, ma_tra_cuu, ... han_giu) và cột
 * giao hàng (M3: hinh_thuc_nhan, ... trang_thai_giao) bằng cách bỏ cột, rồi
 * chứng minh scheduler (scopeQuaHanCho fallback 15 phút) và POS QR/tiền mặt
 * vẫn chạy, không lỗi SQL. Guard Schema::hasColumn trong DonHang::scopeQuaHanCho
 * và ThanhToanChuyenKhoanService::taoHoaDonChoThanhToan bảo vệ các đường này.
 * TODO: gỡ guard + test này sau khi M1-M3 đã áp lên DB thật doan_cnw.
 */
class LoiBThieuCotOnlineTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $mocGoc;
    private User $cashier;
    private SanPham $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->mocGoc = Carbon::parse('2026-11-13 10:00:00');
        Carbon::setTestNow($this->mocGoc);

        Config::set('pos.bank_code', 'MB');
        Config::set('pos.bank_account', '0987654321');
        Config::set('pos.bank_name', 'CUA HANG TEST');
        Config::set('pos.cho_thanh_toan_phut', 15);
        Config::set('pos.telegram_bot_token', '');
        Config::set('pos.telegram_chat_id', '');
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $this->cashier = User::where('role', 'thungan')->firstOrFail();
        $this->product = SanPham::where('sku', 'CS-0001')->firstOrFail();
        $this->product->update(['soluong' => 10, 'trang_thai' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Bỏ cột M1 (online) + M3 (giao hàng) khỏi invoices trên SQLite.
     * Gỡ mọi index người dùng trước (SQLite không cho DROP COLUMN cột có index),
     * rồi ALTER TABLE DROP COLUMN native (SQLite 3.35+, không rebuild bảng).
     * Giữ lại cột cũ (ghi_chu, ma_giao_dich, diem_*) để scheduler/POS chạy đúng.
     */
    private function goBoCotM1M3(): void
    {
        $indexes = DB::select(
            "SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='invoices' AND name NOT LIKE 'sqlite_%'"
        );
        foreach ($indexes as $idx) {
            DB::statement('DROP INDEX IF EXISTS "'.$idx->name.'"');
        }

        $cotM1M3 = [
            'kenh', 'ma_tra_cuu', 'ten_khach_dat', 'so_dienthoai_dat', 'ghi_chu_khach', 'han_giu',
            'hinh_thuc_nhan', 'ten_nguoi_nhan', 'sdt_nguoi_nhan', 'dia_chi_giao', 'phi_giao_hang', 'trang_thai_giao',
        ];
        foreach ($cotM1M3 as $cot) {
            DB::statement('ALTER TABLE invoices DROP COLUMN '.$cot);
        }

        // Sanity: guard phải thấy cột đã biến mất.
        $this->assertFalse(Schema::hasColumn('invoices', 'kenh'));
        $this->assertFalse(Schema::hasColumn('invoices', 'hinh_thuc_nhan'));
    }

    private function sessionGioHang(int $soLuong = 1): array
    {
        return [
            'gio_hang' => [
                (string) $this->product->id => [
                    'id' => $this->product->id,
                    'sku' => $this->product->sku,
                    'ten_sanpham' => $this->product->ten_sanpham,
                    'gia_ban' => (float) $this->product->gia_ban,
                    'soluong' => $soLuong,
                    'ton_kho' => $this->product->soluong,
                ],
            ],
        ];
    }

    public function test_scheduler_huy_don_pos_qua_15_phut_khi_thieu_cot_kenh(): void
    {
        $this->goBoCotM1M3();

        $don = app(ThanhToanChuyenKhoanService::class)->taoHoaDonChoThanhToan(
            [['id' => $this->product->id, 'soluong' => 2]],
            [],
            $this->cashier
        );
        $this->assertSame(8, (int) $this->product->fresh()->soluong);

        Carbon::setTestNow($this->mocGoc->copy()->addMinutes(16));
        $this->artisan('donhang:huy-qua-han')->assertExitCode(0);

        $this->assertSame('da_huy', $don->fresh()->trang_thai);
        $this->assertSame(10, (int) $this->product->fresh()->soluong);
        $this->assertDatabaseHas('stock_movements', [
            'invoice_id' => $don->id, 'loai' => 'dieuchinh', 'soluong' => 2,
        ]);
    }

    public function test_scheduler_khong_huy_don_pos_chua_qua_15_phut_khi_thieu_cot_kenh(): void
    {
        $this->goBoCotM1M3();

        $don = app(ThanhToanChuyenKhoanService::class)->taoHoaDonChoThanhToan(
            [['id' => $this->product->id, 'soluong' => 1]],
            [],
            $this->cashier
        );

        Carbon::setTestNow($this->mocGoc->copy()->addMinutes(14));
        $this->artisan('donhang:huy-qua-han')->assertExitCode(0);

        $this->assertSame('cho_thanh_toan', $don->fresh()->trang_thai);
        $this->assertSame(9, (int) $this->product->fresh()->soluong);
    }

    public function test_pos_tao_qr_van_chay_khi_thieu_cot_online_va_giao_hang(): void
    {
        $this->goBoCotM1M3();

        $res = $this->actingAs($this->cashier)
            ->withSession($this->sessionGioHang(2))
            ->postJson(route('donhang.taoqr'), ['giam_gia_phantram' => 0]);

        $res->assertOk();
        $this->assertStringContainsString('img.vietqr.io', (string) $res->json('url_qr'));
        $this->assertDatabaseCount('invoices', 1);
        $this->assertSame(8, (int) $this->product->fresh()->soluong);

        $don = DonHang::firstOrFail();
        $this->assertSame('chuyenkhoan', $don->phuong_thuc);
        $this->assertSame('cho_thanh_toan', $don->trang_thai);
    }

    public function test_pos_tien_mat_van_chay_khi_thieu_cot_online_va_giao_hang(): void
    {
        $this->goBoCotM1M3();

        $res = $this->actingAs($this->cashier)
            ->withSession($this->sessionGioHang(1))
            ->post(route('donhang.thanhtoan'), [
                'giam_gia_phantram' => 0,
                'tien_khach_dua' => 10000000,
                'phuong_thuc' => 'tienmat',
            ]);

        $res->assertRedirect();
        $this->assertDatabaseCount('invoices', 1);
        $this->assertSame(9, (int) $this->product->fresh()->soluong);

        $don = DonHang::firstOrFail();
        $this->assertSame('tienmat', $don->phuong_thuc);
        $this->assertSame('da_thanh_toan', $don->trang_thai);
    }
}