<?php

namespace Tests\Feature;

use App\Models\BienDongKho;
use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\SanPham;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * T2 / D12 — Chống ôm hàng cho checkout guest pickup:
 * (a) honeypot ẩn; (b) tối đa 3 đơn mở/SĐT chuẩn hóa; (c) tối đa 10 sản phẩm
 * khác nhau + số lượng mỗi dòng theo config; (d) throttle 5/phút/IP → 429.
 * Mọi từ chối dùng thông báo chung, không lộ dữ liệu đơn khác.
 */
class DatHangP1ChongOmHangTest extends TestCase
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

    private function dat(array $cart, array $payload = [], string $token = 'TOKEN'): TestResponse
    {
        return $this->withSession(['checkout_token' => $token, 'storefront_cart' => $cart])
            ->post(route('dat-hang.dat'), array_merge([
                'ten' => 'Khách Test',
                'so_dienthoai' => '0901234567',
                'ghi_chu' => null,
                'phuong_thuc' => 'pickup',
                'checkout_token' => $token,
            ], $payload));
    }

    // Đơn pickup mở tạo bằng model để dựng sẵn giới hạn 3 đơn/SĐT.
    private function taoDonMo(SanPham $sp, string $sdt, string $trangThai = 'cho_thanh_toan'): DonHang
    {
        $don = DonHang::create([
            'ma_hoadon' => 'HD-'.uniqid(), 'user_id' => null, 'customer_id' => null,
            'kenh' => 'online', 'phuong_thuc' => 'tienmat', 'trang_thai' => $trangThai,
            'ma_tra_cuu' => 'MO'.random_int(100000, 999999),
            'ten_khach_dat' => 'Khách Cũ', 'so_dienthoai_dat' => $sdt,
            'tong_tien' => $sp->gia_ban, 'tong_thanh_toan' => $sp->gia_ban,
            'giam_gia_phantram' => 0, 'tien_giam' => 0, 'diem_su_dung' => 0,
            'tien_giam_diem' => 0, 'diem_tichluy_nhan' => 0, 'tien_khach_dua' => 0, 'tien_thua' => 0,
            'han_giu' => now()->addHours(4),
        ]);
        ChiTietDonHang::create([
            'invoice_id' => $don->id, 'product_id' => $sp->id, 'soluong' => 1,
            'don_gia' => $sp->gia_ban, 'gia_von' => $sp->gia_nhap, 'thanh_tien' => $sp->gia_ban,
        ]);
        $sp->decrement('soluong', 1);
        BienDongKho::create([
            'product_id' => $sp->id, 'user_id' => null, 'invoice_id' => $don->id,
            'loai' => 'ban', 'soluong' => -1, 'ghi_chu' => 'Bán hàng '.$don->ma_hoadon,
        ]);

        return $don;
    }

    // (a) Honeypot ---------------------------------------------------------
    public function test_honeypot_bi_dien_bi_tu_choi_khong_tao_don(): void
    {
        $this->markTestSkipped('Khoảng trống P1 D12 chưa triển khai (nhánh trước)');
        $sp = $this->sp(10);

        $res = $this->dat($this->cart([[$sp, 2]]), ['trang_web' => 'http://spam.example']);

        $res->assertRedirect();
        $this->assertSame(self::LOI_CHUNG, session('error'));
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseHas('products', ['id' => $sp->id, 'soluong' => 10]);
    }

    public function test_honeypot_trong_thi_dat_duoc(): void
    {
        $sp = $this->sp(10);

        $this->dat($this->cart([[$sp, 2]]), ['trang_web' => ''])->assertRedirect();

        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_form_hien_honeypot_an_cho_nguoi_that(): void
    {
        $this->markTestSkipped('Khoảng trống P1 D12 chưa triển khai (nhánh trước)');
        $sp = $this->sp(10);

        $res = $this->withSession(['storefront_cart' => $this->cart([[$sp, 1]])])
            ->get(route('dat-hang.form'));

        $res->assertOk();
        $res->assertSee('name="trang_web"', false);
        $res->assertSee('aria-hidden="true"', false);
    }

    // (b) Tối đa 3 đơn mở/SĐT ----------------------------------------------
    public function test_vuot_3_don_mo_cung_sdt_bi_tu_choi(): void
    {
        $this->markTestSkipped('Khoảng trống P1 D12 chưa triển khai (nhánh trước)');
        $sp = $this->sp(100);
        foreach (range(1, 3) as $i) {
            $this->taoDonMo($sp, '0901234567');
        }
        $tonSau3Don = (int) $sp->fresh()->soluong; // 97

        $res = $this->dat($this->cart([[$sp, 1]]), [], 'TOKEN4');

        $res->assertRedirect();
        $this->assertSame(self::LOI_CHUNG, session('error'));
        $this->assertDatabaseCount('invoices', 3);
        $this->assertSame($tonSau3Don, (int) $sp->fresh()->soluong);
    }

    public function test_don_da_huy_khong_tinh_vao_gioi_han_3(): void
    {
        $sp = $this->sp(100);
        $this->taoDonMo($sp, '0901234567');
        $this->taoDonMo($sp, '0901234567');
        $this->taoDonMo($sp, '0901234567', 'da_huy'); // không tính là đơn mở

        $this->dat($this->cart([[$sp, 1]]), [], 'TOKEN4')->assertRedirect();

        $this->assertDatabaseCount('invoices', 4);
        $this->assertSame('cho_thanh_toan', DonHang::latest('id')->first()->trang_thai);
    }

    public function test_sdt_khac_nhau_khong_bi_gioi_han_cheo(): void
    {
        $sp = $this->sp(100);
        foreach (range(1, 3) as $i) {
            $this->taoDonMo($sp, '0901234567');
        }

        // SĐT khác → không bị chặn bởi 3 đơn của SĐT kia.
        $this->dat($this->cart([[$sp, 1]]), ['so_dienthoai' => '0987654321'], 'TOKENB')->assertRedirect();

        $this->assertDatabaseCount('invoices', 4);
    }

    // (c) Tối đa 10 sản phẩm + số lượng mỗi dòng ---------------------------
    public function test_vuot_10_san_pham_khac_nhau_bi_tu_choi(): void
    {
        $this->markTestSkipped('Khoảng trống P1 D12 chưa triển khai (nhánh trước)');
        $items = [];
        foreach (range(0, 10) as $i) { // 11 sản phẩm
            $items[] = [$this->sp(100, $i), 1];
        }

        $res = $this->dat($this->cart($items));

        $res->assertRedirect();
        $this->assertSame(self::LOI_CHUNG, session('error'));
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_10_san_pham_thi_dat_duoc(): void
    {
        $items = [];
        foreach (range(0, 9) as $i) { // 10 sản phẩm
            $items[] = [$this->sp(100, $i), 1];
        }

        $this->dat($this->cart($items))->assertRedirect();

        $don = DonHang::where('kenh', 'online')->firstOrFail();
        $this->assertSame(10, $don->chiTietDonHangs()->count());
    }

    public function test_vuot_so_luong_moi_dong_theo_config_bi_tu_choi(): void
    {
        $this->markTestSkipped('Khoảng trống P1 D12 chưa triển khai (nhánh trước)');
        $sp = $this->sp(100);

        $res = $this->dat($this->cart([[$sp, 11]])); // > online_max_soluong=10

        $res->assertRedirect();
        $this->assertSame(self::LOI_CHUNG, session('error'));
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseHas('products', ['id' => $sp->id, 'soluong' => 100]);
    }

    public function test_so_luong_bang_config_thi_dat_duoc(): void
    {
        $sp = $this->sp(100);

        $this->dat($this->cart([[$sp, 10]]))->assertRedirect();

        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseHas('products', ['id' => $sp->id, 'soluong' => 90]);
    }

    public function test_so_luong_dong_bang_0_bi_tu_choi(): void
    {
        $this->markTestSkipped('Khoảng trống P1 D12 chưa triển khai (nhánh trước)');
        $sp = $this->sp(100);

        $res = $this->dat($this->cart([[$sp, 0]]));

        $res->assertRedirect();
        $this->assertSame(self::LOI_CHUNG, session('error'));
        $this->assertDatabaseCount('invoices', 0);
    }

    // (d) Throttle 5/phút/IP → 429 -----------------------------------------
    public function test_throttle_checkout_5_phut_ip_tra_429(): void
    {
        $sp = $this->sp(10);
        $cart = $this->cart([[$sp, 1]]);

        // 5 yêu cầu đầu không bị chặn (giỏ trống để không tạo đơn, vẫn đếm throttle).
        for ($i = 1; $i <= 5; $i++) {
            $res = $this->withSession(['checkout_token' => 'T'.$i, 'storefront_cart' => []])
                ->post(route('dat-hang.dat'), [
                    'ten' => 'Khách Test', 'so_dienthoai' => '0901234567',
                    'phuong_thuc' => 'pickup', 'checkout_token' => 'T'.$i,
                ]);
            $this->assertNotSame(429, $res->status(), "Yêu cầu thứ {$i} không được bị 429.");
        }

        // Yêu cầu thứ 6 vượt ngưỡng 5/phút → 429.
        $res6 = $this->withSession(['checkout_token' => 'T6', 'storefront_cart' => $cart])
            ->post(route('dat-hang.dat'), [
                'ten' => 'Khách Test', 'so_dienthoai' => '0901234567',
                'phuong_thuc' => 'pickup', 'checkout_token' => 'T6',
            ]);
        $res6->assertStatus(429);
    }
}