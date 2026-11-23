<?php

namespace Tests\Feature;

use App\Models\BienDongKho;
use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\SanPham;
use App\Models\User;
use App\Services\ThongBaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * P1 — Các điểm bàn giao: view movement null-safe (điểm 2), che SĐT nhật ký (điểm 3),
 * escape ghi_chu render + Telegram (điểm 5), thu ngân xác nhận/hủy (tái dùng R2/R3)
 * + gia hạn có trần config (điểm 4).
 */
class DatHangP1BaoVeTest extends TestCase
{
    use RefreshDatabase;

    private function sp(int $ton = 10): SanPham
    {
        $this->seed();
        $sp = SanPham::orderBy('id')->firstOrFail();
        $sp->update(['soluong' => $ton, 'trang_thai' => true]);

        return $sp->fresh();
    }

    private function donPickup(SanPham $sp, int $qty, array $overrides = []): DonHang
    {
        $don = DonHang::create(array_merge([
            'ma_hoadon' => 'HD-'.uniqid(),
            'user_id' => null,
            'customer_id' => null,
            'kenh' => 'online',
            'phuong_thuc' => 'tienmat',
            'trang_thai' => 'cho_thanh_toan',
            'ma_tra_cuu' => 'MA'.random_int(100000, 999999),
            'ten_khach_dat' => 'Khách Test',
            'so_dienthoai_dat' => '0901234567',
            'ghi_chu_khach' => null,
            'tong_tien' => $sp->gia_ban * $qty,
            'tong_thanh_toan' => $sp->gia_ban * $qty,
            'giam_gia_phantram' => 0,
            'tien_giam' => 0,
            'diem_su_dung' => 0,
            'tien_giam_diem' => 0,
            'diem_tichluy_nhan' => 0,
            'tien_khach_dua' => 0,
            'tien_thua' => 0,
            'han_giu' => now()->addHours(4),
        ], $overrides));
        ChiTietDonHang::create([
            'invoice_id' => $don->id, 'product_id' => $sp->id, 'soluong' => $qty,
            'don_gia' => $sp->gia_ban, 'gia_von' => $sp->gia_nhap, 'thanh_tien' => $sp->gia_ban * $qty,
        ]);
        $sp->decrement('soluong', $qty);
        BienDongKho::create([
            'product_id' => $sp->id, 'user_id' => null, 'invoice_id' => $don->id,
            'loai' => 'ban', 'soluong' => -$qty, 'ghi_chu' => 'Bán hàng '.$don->ma_hoadon,
        ]);

        return $don;
    }

    public function test_view_lich_su_kho_null_safe_voi_movement_user_null(): void
    {
        $sp = $this->sp();
        $this->donPickup($sp, 1); // tạo movement user_id null

        $admin = User::where('role', 'admin')->firstOrFail();
        $res = $this->actingAs($admin)->get(route('kho.lichsu'));

        $res->assertOk();
        $res->assertSee('Đơn online');
    }

    public function test_nhat_ky_dat_hang_online_che_sdt(): void
    {
        $sp = $this->sp(10);
        $cart = [(string) $sp->id => ['id' => $sp->id, 'soluong' => 1, 'gia_ban' => (float) $sp->gia_ban]];

        $this->withSession(['checkout_token' => 'TOKEN', 'storefront_cart' => $cart])
            ->post(route('dat-hang.dat'), [
                'ten' => 'Khách Test', 'so_dienthoai' => '0901234567',
                'phuong_thuc' => 'pickup', 'checkout_token' => 'TOKEN',
            ]);

        $this->assertDatabaseHas('activity_logs', ['hanh_dong' => 'dat_hang_online']);
        $moTa = \App\Models\NhatKyHoatDong::where('hanh_dong', 'dat_hang_online')->value('mo_ta');
        $this->assertStringContainsString('090****567', $moTa);
        $this->assertStringNotContainsString('0901234567', $moTa);
    }

    public function test_ghi_chu_khach_escape_khi_render(): void
    {
        $sp = $this->sp();
        $don = $this->donPickup($sp, 1, ['ghi_chu_khach' => '<script>alert(1)</script>']);

        $res = $this->get(route('dat-hang.thanhcong', $don->ma_tra_cuu));

        $res->assertOk();
        $res->assertDontSee('<script>alert(1)</script>', false);
        $res->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_telegram_don_online_escape_ghi_chu_va_che_sdt(): void
    {
        Http::fake();
        config(['pos.telegram_bot_token' => 'TESTTOKEN', 'pos.telegram_chat_id' => '123']);

        $sp = $this->sp();
        $don = $this->donPickup($sp, 1, [
            'ghi_chu_khach' => '<script>alert(1)</script>',
            'so_dienthoai_dat' => '0901234567',
        ]);

        app(ThongBaoService::class)->thongBaoDonOnlineMoi($don);

        Http::assertSent(function ($request) {
            $text = $request['text'];

            return str_contains($text, '&lt;script&gt;')
                && ! str_contains($text, '<script>alert(1)</script>')
                && str_contains($text, '090****567')
                && ! str_contains($text, '0901234567');
        });
    }

    public function test_thu_ngan_xac_nhan_pickup_online_tai_dung_r2(): void
    {
        $sp = $this->sp(10);
        $don = $this->donPickup($sp, 2);
        $thungan = User::where('role', 'thungan')->firstOrFail();

        $res = $this->actingAs($thungan)->postJson(route('donhang.xacnhan', $don), ['bang_chung' => 'GD-test-1234']);

        $res->assertOk();
        $this->assertSame('da_thanh_toan', $don->fresh()->trang_thai);
    }

    public function test_thu_ngan_huy_pickup_online_tai_dung_r3_hoan_kho(): void
    {
        $sp = $this->sp(10);
        $don = $this->donPickup($sp, 3);
        $thungan = User::where('role', 'thungan')->firstOrFail();

        $res = $this->actingAs($thungan)->postJson(route('donhang.huycho', $don));

        $res->assertOk();
        $this->assertSame('da_huy', $don->fresh()->trang_thai);
        $this->assertDatabaseHas('products', ['id' => $sp->id, 'soluong' => 10]);
    }

    public function test_gia_han_cong_gio_trong_tran_config_ghi_nhat_ky(): void
    {
        $sp = $this->sp();
        $don = $this->donPickup($sp, 1);
        $hanCu = $don->han_giu;
        $thungan = User::where('role', 'thungan')->firstOrFail();

        $res = $this->actingAs($thungan)->postJson(route('donhang.giahan', $don));

        $res->assertOk();
        $don->refresh();
        $this->assertTrue($don->han_giu->greaterThan($hanCu));
        $tran = $don->created_at->addHours((int) config('pos.online_gia_han_toi_da_gio'));
        $this->assertTrue($don->han_giu->lessThanOrEqualTo($tran));
        $this->assertDatabaseHas('activity_logs', ['hanh_dong' => 'gia_han_don_online', 'doi_tuong_id' => $don->id]);
    }

    public function test_gia_han_vuot_tran_toi_da_bi_tu_choi(): void
    {
        $sp = $this->sp();
        $don = $this->donPickup($sp, 1);
        // Đẩy hạn giữ lên đúng trần tối đa
        $don->update(['han_giu' => $don->created_at->addHours((int) config('pos.online_gia_han_toi_da_gio'))]);
        $thungan = User::where('role', 'thungan')->firstOrFail();

        $res = $this->actingAs($thungan)->postJson(route('donhang.giahan', $don));

        $res->assertStatus(422);
        $this->assertDatabaseMissing('activity_logs', ['hanh_dong' => 'gia_han_don_online', 'doi_tuong_id' => $don->id]);
    }

    public function test_gia_han_chi_cho_don_pickup_online_dang_cho(): void
    {
        $sp = $this->sp();
        $don = $this->donPickup($sp, 1, ['phuong_thuc' => 'chuyenkhoan']); // QR không gia hạn kiểu pickup
        $thungan = User::where('role', 'thungan')->firstOrFail();

        $this->actingAs($thungan)->postJson(route('donhang.giahan', $don))->assertStatus(422);
    }

    public function test_config_online_co_gia_han_toi_da(): void
    {
        $this->assertSame(24, (int) config('pos.online_gia_han_toi_da_gio'));
        $this->assertSame(4, (int) config('pos.online_pickup_gio'));
        $this->assertSame(50, (int) config('pos.online_giu_toi_da_phan_tram'));
        $this->assertSame(30, (int) config('pos.online_qr_phut'));
    }
}