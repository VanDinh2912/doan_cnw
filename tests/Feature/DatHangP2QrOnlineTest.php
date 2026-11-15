<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\SanPham;
use App\Services\ThanhToanChuyenKhoanService;
use App\Services\VietQrService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * BƯỚC 2 (P2) — Trang QR online + polling (đường QR giao_tan_noi, không đổi POS/pickup):
 * số tiền QR = tong_thanh_toan đã gồm ship, nội dung = mã hóa đơn; đếm ngược theo han_giu;
 * hết hạn → hủy + hoàn kho + thông báo rõ ràng; thiếu cấu hình ngân hàng → thân thiện,
 * không 500, không lộ số tài khoản; polling POST + bằng chứng session, không PII.
 */
class DatHangP2QrOnlineTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $mocGoc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->mocGoc = Carbon::parse('2026-11-15 10:00:00');
        Carbon::setTestNow($this->mocGoc);

        Config::set('pos.bank_code', 'MB');
        Config::set('pos.bank_account', '0987654321');
        Config::set('pos.bank_name', 'CUA HANG TEST');
        Config::set('pos.phi_giao_hang', 30000);
        Config::set('pos.mien_phi_giao_tu', 0);
        Config::set('pos.giao_hang_tinh', '');
        Config::set('pos.online_qr_phut', 30);
        Config::set('pos.online_throttle_dat', '5,1');
        Config::set('pos.telegram_bot_token', '');
        Config::set('pos.telegram_chat_id', '');
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function sp(int $ton): SanPham
    {
        $sp = SanPham::orderBy('id')->firstOrFail();
        $sp->update(['soluong' => $ton, 'trang_thai' => true]);

        return $sp->fresh();
    }

    private function cart(array $items): array
    {
        $cart = [];
        foreach ($items as [$sp, $qty]) {
            $cart[(string) $sp->id] = [
                'id' => $sp->id, 'sku' => $sp->sku, 'ten_sanpham' => $sp->ten_sanpham,
                'gia_ban' => (float) $sp->gia_ban, 'soluong' => $qty, 'ton_kho' => $sp->soluong,
            ];
        }

        return $cart;
    }

    private function diaChi(): array
    {
        return [
            'dia_chi_so_nha' => '12 Nguyễn Trãi',
            'dia_chi_phuong_xa' => 'Phường 1',
            'dia_chi_quan_huyen' => 'Quận 5',
            'dia_chi_tinh_thanh' => 'Hồ Chí Minh',
        ];
    }

    private function datGiao(array $cart, string $token = 'TQ'): TestResponse
    {
        return $this->withSession(['checkout_token' => $token, 'storefront_cart' => $cart])
            ->post(route('dat-hang.dat'), array_merge([
                'ten' => 'Khách Giao',
                'so_dienthoai' => '0901234567',
                'phuong_thuc' => 'qr',
                'hinh_thuc_nhan' => 'giao_tan_noi',
                'ten_nguoi_nhan' => 'Người Nhận',
                'sdt_nguoi_nhan' => '0901234567',
                'checkout_token' => $token,
            ], $this->diaChi()));
    }

    private function donQr(): DonHang
    {
        return DonHang::where('kenh', 'online')->where('phuong_thuc', 'chuyenkhoan')->firstOrFail();
    }

    private function bangChung(DonHang $don): array
    {
        return ['don_hang_xac_thuc' => [$don->ma_tra_cuu => true]];
    }

    // Tạo đơn QR giao_tan_noi thẳng qua R1 (không qua postDat) để phiên KHÔNG có
    // bằng chứng xác thực — dùng cho test hết hạn và test polling cần bằng chứng.
    private function donQrQuaR1(SanPham $sp, int $qty): DonHang
    {
        return app(ThanhToanChuyenKhoanService::class)->taoHoaDonChoThanhToan(
            [['id' => $sp->id, 'soluong' => $qty]], [], null,
            [
                'kenh' => 'online', 'phuong_thuc' => 'chuyenkhoan', 'hinh_thuc_nhan' => 'giao_tan_noi',
                'ma_tra_cuu' => 'QR'.random_int(100000, 999999),
                'ten_khach_dat' => 'Khách QR', 'so_dienthoai_dat' => '0901234567',
                'ten_nguoi_nhan' => 'Người Nhận', 'sdt_nguoi_nhan' => '0901234567',
                'dia_chi_giao' => '12 Nguyễn Trãi, Phường 1, Quận 5, Hồ Chí Minh',
                'han_giu' => now()->addMinutes(30),
            ]
        );
    }

    public function test_qr_hien_dung_tong_gom_ship_va_noi_dung_la_ma_hoadon(): void
    {
        $sp = $this->sp(10);
        $res = $this->datGiao($this->cart([[$sp, 2]]));
        $don = $this->donQr();
        $res->assertRedirect(route('dat-hang.qr', $don->ma_tra_cuu));

        // Tổng = tiền hàng DB + phí ship (không tin session).
        $expected = (int) $sp->gia_ban * 2 + 30000;
        $this->assertSame($expected, (int) $don->tong_thanh_toan);

        $page = $this->get(route('dat-hang.qr', $don->ma_tra_cuu));
        $page->assertOk();
        $page->assertSee('img.vietqr.io', false);
        $page->assertSee('amount='.$expected, false);
        $page->assertSee(VietQrService::cleanNoiDung($don->ma_hoadon), false);
        $page->assertSee('30.000', false); // phí giao hàng hiển thị
    }

    public function test_qr_hien_dem_nguoc_theo_han_giu(): void
    {
        $sp = $this->sp(10);
        $this->datGiao($this->cart([[$sp, 1]]));
        $don = $this->donQr();

        $page = $this->get(route('dat-hang.qr', $don->ma_tra_cuu));
        $page->assertOk();
        $page->assertSee('id="qr-countdown"', false);
        $page->assertSee('data-han-giu="'.$don->han_giu->timestamp.'"', false);
    }

    public function test_qr_thieu_cau_hinh_ngan_hang_than_thien_khong_500_khong_lo_so_tai_khoan(): void
    {
        Config::set('pos.bank_code', '');
        Config::set('pos.bank_account', '0987654321');

        $sp = $this->sp(10);
        $this->datGiao($this->cart([[$sp, 1]]));
        $don = $this->donQr();

        $page = $this->get(route('dat-hang.qr', $don->ma_tra_cuu));
        $page->assertOk();
        $page->assertSee('Chưa cấu hình thông tin ngân hàng', false);
        $page->assertDontSee('img.vietqr.io', false);
        $page->assertDontSee('0987654321', false);
    }

    public function test_qr_qua_han_giu_bi_huy_hoan_kho_va_thong_bao_ro_rang(): void
    {
        $sp = $this->sp(10);
        $don = $this->donQrQuaR1($sp, 2);
        $this->assertSame(8, (int) $sp->fresh()->soluong);

        Carbon::setTestNow($this->mocGoc->copy()->addMinutes(31));

        $page = $this->withSession($this->bangChung($don))
            ->get(route('dat-hang.qr', $don->ma_tra_cuu));
        $page->assertRedirect(route('dat-hang.thanhcong', $don->ma_tra_cuu));

        $this->assertSame('da_huy', $don->fresh()->trang_thai);
        $this->assertSame(10, (int) $sp->fresh()->soluong);
        $this->assertDatabaseHas('stock_movements', [
            'invoice_id' => $don->id, 'loai' => 'dieuchinh', 'soluong' => 2,
        ]);

        $tc = $this->withSession($this->bangChung($don))
            ->get(route('dat-hang.thanhcong', $don->ma_tra_cuu));
        $tc->assertOk();
        $tc->assertSee('hết hạn thanh toán', false);
        $tc->assertSee('đã bị hủy', false);
        $tc->assertDontSee('Đặt hàng thành công', false);
    }

    public function test_qr_polling_khong_pii_va_can_bang_chung_session(): void
    {
        $sp = $this->sp(10);
        $don = $this->donQrQuaR1($sp, 1);

        // Phiên mới chưa xác thực → không có bằng chứng → 403.
        $this->post(route('don-hang.poll', $don->ma_tra_cuu))->assertForbidden();

        // Có bằng chứng → chỉ trả trạng thái, không PII.
        $res = $this->withSession($this->bangChung($don))
            ->post(route('don-hang.poll', $don->ma_tra_cuu));
        $res->assertOk()->assertExactJson(['trang_thai' => 'cho_thanh_toan']);
        $res->assertDontSee('0901234567', false);
        $res->assertDontSee('Nguyễn Trãi', false);
        $res->assertDontSee('Người Nhận', false);
    }

    public function test_pickup_thanhcong_giu_nguyen_hien_thi_thanh_cong(): void
    {
        $sp = $this->sp(10);
        $res = $this->withSession(['checkout_token' => 'TP', 'storefront_cart' => $this->cart([[$sp, 1]])])
            ->post(route('dat-hang.dat'), [
                'ten' => 'Khách Pickup', 'so_dienthoai' => '0901234567',
                'phuong_thuc' => 'pickup', 'checkout_token' => 'TP',
            ]);
        $don = DonHang::where('kenh', 'online')->firstOrFail();
        $res->assertRedirect(route('dat-hang.thanhcong', $don->ma_tra_cuu));

        $tc = $this->get(route('dat-hang.thanhcong', $don->ma_tra_cuu));
        $tc->assertOk();
        $tc->assertSee('Đặt hàng thành công', false);
        $this->assertSame('cho_thanh_toan', $don->fresh()->trang_thai);
    }
}