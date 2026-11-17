<?php

namespace Tests\Feature;

use App\Models\BienDongKho;
use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\SanPham;
use App\Models\User;
use App\Services\ThanhToanChuyenKhoanService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * BƯỚC C (P2) — giao_tan_noi trên nền P1: địa chỉ 4 phần ghép <=255, vùng giao
 * theo tỉnh, honeypot tên trung tính, phí tính từ giá DB (không tin session),
 * D12 chạy cả luồng giao, không ghi ngược hồ sơ (D8), D17 hủy theo han_giu,
 * redirect pickup giữ thanhcong / QR sang qr.
 */
class DatHangP2GiaoTanNoiMoRongTest extends TestCase
{
    use RefreshDatabase;

    private const LOI_CHUNG = 'Không thể tạo đơn hàng. Vui lòng thử lại.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        Config::set('pos.online_max_don_mo', 3);
        Config::set('pos.online_max_soluong', 10);
        Config::set('pos.online_max_sanpham', 10);
        Config::set('pos.online_throttle_dat', '5,1');
        Config::set('pos.phi_giao_hang', 30000);
        Config::set('pos.mien_phi_giao_tu', 0);
        Config::set('pos.giao_hang_tinh', '');
        Config::set('pos.telegram_bot_token', '');
        Config::set('pos.telegram_chat_id', '');
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
    }

    private function sp(int $ton, int $chiSo = 0): SanPham
    {
        $sp = SanPham::orderBy('id')->skip($chiSo)->firstOrFail();
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

    private function diaChi(array $overrides = []): array
    {
        return array_merge([
            'dia_chi_so_nha' => '12 Nguyễn Trãi',
            'dia_chi_phuong_xa' => 'Phường 1',
            'dia_chi_quan_huyen' => 'Quận 5',
            'dia_chi_tinh_thanh' => 'Hồ Chí Minh',
        ], $overrides);
    }

    private function datGiao(array $cart, array $payload = [], string $token = 'TOKEN'): TestResponse
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
            ], $this->diaChi(), $payload));
    }

    private function taoDonMoGiao(SanPham $sp, string $sdt): DonHang
    {
        $don = DonHang::create([
            'ma_hoadon' => 'HD-'.uniqid(), 'user_id' => null, 'customer_id' => null,
            'kenh' => 'online', 'phuong_thuc' => 'chuyenkhoan', 'trang_thai' => 'cho_thanh_toan',
            'hinh_thuc_nhan' => 'giao_tan_noi',
            'ma_tra_cuu' => 'MO'.random_int(100000, 999999),
            'ten_khach_dat' => 'Khách Cũ', 'so_dienthoai_dat' => $sdt,
            'tong_tien' => $sp->gia_ban, 'tong_thanh_toan' => $sp->gia_ban,
            'giam_gia_phantram' => 0, 'tien_giam' => 0, 'diem_su_dung' => 0,
            'tien_giam_diem' => 0, 'diem_tichluy_nhan' => 0, 'tien_khach_dua' => 0, 'tien_thua' => 0,
            'han_giu' => now()->addMinutes(30),
        ]);
        ChiTietDonHang::create([
            'invoice_id' => $don->id, 'product_id' => $sp->id, 'soluong' => 1,
            'don_gia' => $sp->gia_ban, 'gia_von' => $sp->gia_nhap, 'thanh_tien' => $sp->gia_ban,
        ]);
        $sp->decrement('soluong', 1);

        return $don;
    }

    // (a) Form + honeypot trung tính --------------------------------------
    public function test_form_hien_honeypot_trung_tinh_va_khong_con_trang_web(): void
    {
        $sp = $this->sp(10);
        $res = $this->withSession(['storefront_cart' => $this->cart([[$sp, 1]])])->get(route('dat-hang.form'));

        $res->assertOk();
        $res->assertSee('name="truong_phu"', false);
        $res->assertDontSee('name="trang_web"', false);
        // Ô email tùy chọn đã được thêm có chủ đích (Bước B) — không còn là trường honeypot.
        $res->assertSee('name="email"', false);
    }

    public function test_honeypot_trung_tinh_bi_dien_bi_tu_choi_khong_tao_don(): void
    {
        $sp = $this->sp(10);

        $res = $this->datGiao($this->cart([[$sp, 1]]), ['truong_phu' => 'spam']);

        $res->assertSessionHas('error', self::LOI_CHUNG);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertSame(10, (int) $sp->fresh()->soluong);
    }

    public function test_form_giao_tan_noi_hien_bon_o_dia_chi(): void
    {
        $sp = $this->sp(10);
        $res = $this->withSession(['storefront_cart' => $this->cart([[$sp, 1]])])->get(route('dat-hang.form'));

        foreach (['dia_chi_so_nha', 'dia_chi_phuong_xa', 'dia_chi_quan_huyen', 'dia_chi_tinh_thanh'] as $name) {
            $res->assertSee('name="'.$name.'"', false);
        }
    }

    public function test_form_dien_san_ho_so_khi_dang_nhap(): void
    {
        $sp = $this->sp(10);
        $user = User::create(['name' => 'Khách', 'email' => 'khach'.uniqid().'@test.local', 'password' => bcrypt('x'), 'role' => 'khachhang']);
        KhachHang::create(['user_id' => $user->id, 'ho_ten' => 'Nguyễn Văn A', 'so_dienthoai' => '0909999999', 'dia_chi' => null, 'diem_tichluy' => 0]);

        $res = $this->actingAs($user)->withSession(['storefront_cart' => $this->cart([[$sp, 1]])])->get(route('dat-hang.form'));

        $res->assertSee('Nguyễn Văn A', false);
        $res->assertSee('0909999999', false);
    }

    // (b) Địa chỉ 4 phần + vùng giao ---------------------------------------
    public function test_giao_tan_noi_ghep_bon_phan_dia_chi_luu_mot_cot(): void
    {
        $sp = $this->sp(10);

        $this->datGiao($this->cart([[$sp, 1]]))->assertRedirect();

        $don = DonHang::where('kenh', 'online')->firstOrFail();
        $this->assertSame('giao_tan_noi', $don->hinh_thuc_nhan);
        $this->assertSame('12 Nguyễn Trãi, Phường 1, Quận 5, Hồ Chí Minh', $don->dia_chi_giao);
        $this->assertLessThanOrEqual(255, mb_strlen($don->dia_chi_giao));
    }

    public function test_giao_tan_noi_thieu_mot_phan_dia_chi_bi_tu_choi(): void
    {
        $sp = $this->sp(10);

        $res = $this->datGiao($this->cart([[$sp, 1]]), ['dia_chi_quan_huyen' => '']);

        $res->assertSessionHasErrors();
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_dia_chi_ghep_qua_255_bi_tu_choi(): void
    {
        $sp = $this->sp(10);

        $res = $this->datGiao($this->cart([[$sp, 1]]), [
            'dia_chi_so_nha' => str_repeat('a', 100),
            'dia_chi_phuong_xa' => str_repeat('b', 50),
            'dia_chi_quan_huyen' => str_repeat('c', 50),
            'dia_chi_tinh_thanh' => str_repeat('d', 50),
        ]);

        $res->assertSessionHasErrors();
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_dia_chi_html_duoc_escape_khi_render_lai_form(): void
    {
        $sp = $this->sp(10);

        $res = $this->datGiao($this->cart([[$sp, 1]]), ['dia_chi_so_nha' => '<script>alert(1)</script>']);

        // Quay lại form với old(): dữ liệu khách phải escape, không render thô.
        $res = $this->withSession(['storefront_cart' => $this->cart([[$sp, 1]])])->get(route('dat-hang.form'));
        $res->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_ngoai_vung_giao_theo_tinh_bi_tu_choi(): void
    {
        Config::set('pos.giao_hang_tinh', 'Hà Nội, Hồ Chí Minh');
        $sp = $this->sp(10);

        $res = $this->datGiao($this->cart([[$sp, 1]]), ['dia_chi_tinh_thanh' => 'Đà Nẵng']);

        $res->assertSessionHasErrors('dia_chi_tinh_thanh');
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_trong_vung_giao_thi_dat_duoc(): void
    {
        Config::set('pos.giao_hang_tinh', 'Hà Nội, Hồ Chí Minh');
        $sp = $this->sp(10);

        $this->datGiao($this->cart([[$sp, 1]]))->assertRedirect();

        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_vung_giao_rong_khong_gioi_han(): void
    {
        Config::set('pos.giao_hang_tinh', '');
        $sp = $this->sp(10);

        $this->datGiao($this->cart([[$sp, 1]]), ['dia_chi_tinh_thanh' => 'Cà Mau'])->assertRedirect();

        $this->assertDatabaseCount('invoices', 1);
    }

    // (c) Phí / tổng: server tính từ giá DB ---------------------------------
    public function test_phi_mien_phi_theo_tong_gia_db_khong_theo_session(): void
    {
        Config::set('pos.mien_phi_giao_tu', 150000);
        $sp = $this->sp(10);
        $sp->update(['gia_ban' => 200000]);
        $cart = $this->cart([[$sp, 1]]);
        $cart[(string) $sp->id]['gia_ban'] = 100000.0; // session cũ/lệch, server phải tính lại

        $this->datGiao($cart)->assertRedirect();

        $don = DonHang::where('kenh', 'online')->firstOrFail();
        $this->assertSame(0, (int) $don->phi_giao_hang, 'Tiền hàng DB 200000 >= ngưỡng 150000 phải miễn phí.');
        $this->assertSame(200000, (int) $don->tong_thanh_toan);
    }

    public function test_tong_thanh_toan_bang_tien_hang_db_cong_phi(): void
    {
        $sp = $this->sp(10);

        $this->datGiao($this->cart([[$sp, 2]]))->assertRedirect();

        $don = DonHang::where('kenh', 'online')->firstOrFail();
        $this->assertSame(30000, (int) $don->phi_giao_hang);
        $this->assertSame((int) $sp->gia_ban * 2 + 30000, (int) $don->tong_thanh_toan);
    }

    public function test_request_sua_phi_tong_trang_thai_bi_bo_qua(): void
    {
        $sp = $this->sp(10);

        $this->datGiao($this->cart([[$sp, 1]]), [
            'phi_giao_hang' => 1, 'tong_thanh_toan' => 1, 'trang_thai' => 'da_thanh_toan',
        ])->assertRedirect();

        $don = DonHang::where('kenh', 'online')->firstOrFail();
        $this->assertSame(30000, (int) $don->phi_giao_hang);
        $this->assertSame('cho_thanh_toan', $don->trang_thai);
        $this->assertGreaterThan(1, (int) $don->tong_thanh_toan);
    }

    // (d) D12 trên luồng giao_tan_noi ---------------------------------------
    public function test_vuot_3_don_mo_giao_cung_sdt_bi_tu_choi(): void
    {
        $sp = $this->sp(100);
        foreach (range(1, 3) as $i) {
            $this->taoDonMoGiao($sp, '0901234567');
        }

        $res = $this->datGiao($this->cart([[$sp, 1]]));

        $res->assertSessionHas('error', self::LOI_CHUNG);
        $this->assertSame(3, DonHang::where('kenh', 'online')->where('trang_thai', 'cho_thanh_toan')->count());
    }

    public function test_sdt_chuan_hoa_khop_giao_chan_don_thu_tu(): void
    {
        $sp = $this->sp(100);
        foreach (['+84901234567', '090.123 4567', '0901234567'] as $sdt) {
            $this->taoDonMoGiao($sp, $sdt);
        }

        $res = $this->datGiao($this->cart([[$sp, 1]]), ['so_dienthoai' => '0901234567']);

        $res->assertSessionHas('error', self::LOI_CHUNG);
    }

    public function test_vuot_10_san_pham_giao_bi_tu_choi(): void
    {
        $items = [];
        foreach (range(0, 10) as $i) {
            $items[] = [$this->sp(100, $i), 1];
        }

        $res = $this->datGiao($this->cart($items));

        $res->assertSessionHas('error', self::LOI_CHUNG);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_throttle_5_phut_giao_429(): void
    {
        $sp = $this->sp(1000);
        for ($i = 1; $i <= 5; $i++) {
            $this->datGiao($this->cart([[$sp, 1]]), [], 'T'.$i)->assertRedirect();
        }

        $this->datGiao($this->cart([[$sp, 1]]), [], 'T6')->assertStatus(429);
    }

    // (e) Hồ sơ khách đăng nhập: không ghi ngược (D8) ------------------------
    public function test_khach_dang_nhap_khong_ghi_nguoc_ho_so_theo_sdt(): void
    {
        $sp = $this->sp(10);
        $user = User::create(['name' => 'Khách', 'email' => 'khach'.uniqid().'@test.local', 'password' => bcrypt('x'), 'role' => 'khachhang']);
        $hoSoNguoiKhac = KhachHang::create(['user_id' => null, 'ho_ten' => 'Người Khác', 'so_dienthoai' => '0901234567', 'dia_chi' => null, 'diem_tichluy' => 0]);
        $soHoSoTruoc = KhachHang::count();

        $this->actingAs($user)->datGiao($this->cart([[$sp, 1]]))->assertRedirect();

        $this->assertNull($hoSoNguoiKhac->fresh()->user_id, 'Không được gán hồ sơ người khác cho user qua SĐT (D8/L1).');
        $this->assertSame($soHoSoTruoc, KhachHang::count(), 'Không được tự tạo hồ sơ mới khi checkout.');
        $don = DonHang::where('kenh', 'online')->firstOrFail();
        $this->assertNull($don->customer_id);
    }

    public function test_khach_dang_nhap_co_ho_so_thi_gan_customer_id(): void
    {
        $sp = $this->sp(10);
        $user = User::create(['name' => 'Khách', 'email' => 'khach'.uniqid().'@test.local', 'password' => bcrypt('x'), 'role' => 'khachhang']);
        $hoSo = KhachHang::create(['user_id' => $user->id, 'ho_ten' => 'Khách Thân', 'so_dienthoai' => '0908888888', 'dia_chi' => null, 'diem_tichluy' => 0]);

        $this->actingAs($user)->datGiao($this->cart([[$sp, 1]]))->assertRedirect();

        $don = DonHang::where('kenh', 'online')->firstOrFail();
        $this->assertSame($hoSo->id, $don->customer_id);
    }

    // (f) D17: QR online hủy theo han_giu, không theo mốc 15 phút ------------
    public function test_qr_online_khong_bi_huy_o_moc_16_phut(): void
    {
        Carbon::setTestNow('2026-11-15 10:00:00');
        $sp = $this->sp(10);
        $don = app(ThanhToanChuyenKhoanService::class)->taoHoaDonChoThanhToan(
            [['id' => $sp->id, 'soluong' => 1]], [], null,
            [
                'kenh' => 'online', 'phuong_thuc' => 'chuyenkhoan', 'hinh_thuc_nhan' => 'giao_tan_noi',
                'ma_tra_cuu' => 'QR'.random_int(100000, 999999),
                'ten_khach_dat' => 'Khách QR', 'so_dienthoai_dat' => '0901234567',
                'han_giu' => now()->addMinutes(30),
            ]
        );
        Carbon::setTestNow('2026-11-15 10:16:00');

        $this->artisan('donhang:huy-qua-han')->assertExitCode(0);

        $this->assertSame('cho_thanh_toan', $don->fresh()->trang_thai);
        Carbon::setTestNow();
    }

    public function test_qr_online_qua_han_giu_bi_huy_hoan_kho_movement(): void
    {
        Carbon::setTestNow('2026-11-15 10:00:00');
        $sp = $this->sp(10);
        $don = app(ThanhToanChuyenKhoanService::class)->taoHoaDonChoThanhToan(
            [['id' => $sp->id, 'soluong' => 2]], [], null,
            [
                'kenh' => 'online', 'phuong_thuc' => 'chuyenkhoan', 'hinh_thuc_nhan' => 'giao_tan_noi',
                'ma_tra_cuu' => 'QR'.random_int(100000, 999999),
                'ten_khach_dat' => 'Khách QR', 'so_dienthoai_dat' => '0901234567',
                'han_giu' => now()->addMinutes(30),
            ]
        );
        Carbon::setTestNow('2026-11-15 10:31:00');

        $this->artisan('donhang:huy-qua-han')->assertExitCode(0);

        $this->assertSame('da_huy', $don->fresh()->trang_thai);
        $this->assertSame(10, (int) $sp->fresh()->soluong);
        $this->assertDatabaseHas('stock_movements', ['invoice_id' => $don->id, 'loai' => 'dieuchinh', 'soluong' => 2]);
        Carbon::setTestNow();
    }

    // (g) Redirect: pickup giữ thanhcong (P1), QR sang qr (WIP) --------------
    public function test_pickup_redirect_thanhcong_qr_redirect_qr(): void
    {
        $sp = $this->sp(10);
        $cart = $this->cart([[$sp, 1]]);

        $res = $this->withSession(['checkout_token' => 'TP', 'storefront_cart' => $cart])
            ->post(route('dat-hang.dat'), [
                'ten' => 'Khách', 'so_dienthoai' => '0901234567', 'phuong_thuc' => 'pickup',
                'checkout_token' => 'TP',
            ]);
        $donPickup = DonHang::where('kenh', 'online')->firstOrFail();
        $res->assertRedirect(route('dat-hang.thanhcong', $donPickup->ma_tra_cuu));

        $res = $this->datGiao($cart, [], 'TQ');
        $donQr = DonHang::where('kenh', 'online')->where('phuong_thuc', 'chuyenkhoan')->firstOrFail();
        $res->assertRedirect(route('dat-hang.qr', $donQr->ma_tra_cuu));
    }
}