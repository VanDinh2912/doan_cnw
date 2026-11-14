<?php

namespace Tests\Feature;

use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\SanPham;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * D12 bổ sung — giới hạn 3 đơn mở tính trên SĐT CHUẨN HÓA: đơn cũ lưu dạng
 * +84 / khoảng trắng / dấu chấm vẫn tính cùng SĐT 0xxxxxxxxx khi đặt đơn mới.
 */
class DatHangP1ChuanHoaSdtTest extends TestCase
{
    use RefreshDatabase;

    private const LOI_CHUNG = 'Không thể tạo đơn hàng. Vui lòng thử lại.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        Config::set('pos.online_max_don_mo', 3);
        Config::set('pos.telegram_bot_token', '');
        Config::set('pos.telegram_chat_id', '');
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
    }

    private function taoDonMo(SanPham $sp, string $sdtLuu): void
    {
        $don = DonHang::create([
            'ma_hoadon' => 'HD-'.uniqid(), 'user_id' => null, 'customer_id' => null,
            'kenh' => 'online', 'phuong_thuc' => 'tienmat', 'trang_thai' => 'cho_thanh_toan',
            'ma_tra_cuu' => 'MO'.random_int(100000, 999999),
            'ten_khach_dat' => 'Khách Cũ', 'so_dienthoai_dat' => $sdtLuu,
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
    }

    private function dat(SanPham $sp, string $sdt): \Illuminate\Testing\TestResponse
    {
        $cart = [(string) $sp->id => [
            'id' => $sp->id, 'sku' => $sp->sku, 'ten_sanpham' => $sp->ten_sanpham,
            'gia_ban' => (float) $sp->gia_ban, 'soluong' => 1, 'ton_kho' => $sp->soluong,
        ]];

        return $this->withSession(['checkout_token' => 'TOKEN', 'storefront_cart' => $cart])
            ->post(route('dat-hang.dat'), [
                'ten' => 'Khách Test', 'so_dienthoai' => $sdt, 'ghi_chu' => null,
                'phuong_thuc' => 'pickup', 'checkout_token' => 'TOKEN',
            ]);
    }

    public function test_don_cu_luu_dang_84_van_tinh_vao_gioi_han_3(): void
    {
        $sp = SanPham::orderBy('id')->firstOrFail();
        $sp->update(['soluong' => 100, 'trang_thai' => true]);
        foreach (range(1, 3) as $i) {
            $this->taoDonMo($sp->fresh(), '+84901234567');
        }

        $res = $this->dat($sp->fresh(), '0901234567');

        $res->assertRedirect();
        $this->assertSame(self::LOI_CHUNG, session('error'));
        $this->assertDatabaseCount('invoices', 3);
    }

    public function test_don_cu_luu_khoang_trang_dau_cham_van_tinh_vao_gioi_han_3(): void
    {
        $sp = SanPham::orderBy('id')->firstOrFail();
        $sp->update(['soluong' => 100, 'trang_thai' => true]);
        foreach (range(1, 3) as $i) {
            $this->taoDonMo($sp->fresh(), '090.123 4567');
        }

        $res = $this->dat($sp->fresh(), '0901234567');

        $res->assertRedirect();
        $this->assertSame(self::LOI_CHUNG, session('error'));
        $this->assertDatabaseCount('invoices', 3);
    }

    public function test_don_84_cua_sdt_khac_khong_tinh_cheo(): void
    {
        $sp = SanPham::orderBy('id')->firstOrFail();
        $sp->update(['soluong' => 100, 'trang_thai' => true]);
        foreach (range(1, 3) as $i) {
            $this->taoDonMo($sp->fresh(), '+84987654321');
        }

        $this->dat($sp->fresh(), '0901234567')->assertRedirect();

        $this->assertDatabaseCount('invoices', 4);
    }
}