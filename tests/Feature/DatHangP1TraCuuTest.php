<?php

namespace Tests\Feature;

use App\Models\BienDongKho;
use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\SanPham;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P1 — Tra cứu / trạng thái / tự hủy (T-DH08, T-DH12) + T5 (không SĐT trên URL,
 * polling chỉ trả trạng thái). Bằng chứng đã xác thực lưu trong session
 * `don_hang_xac_thuc[ma_tra_cuu]`; SĐT chỉ đi trong thân POST, không lên URL/query.
 */
class DatHangP1TraCuuTest extends TestCase
{
    use RefreshDatabase;

    private function sp(int $ton = 10): SanPham
    {
        $this->seed();
        $sp = SanPham::orderBy('id')->firstOrFail();
        $sp->update(['soluong' => $ton, 'trang_thai' => true]);

        return $sp->fresh();
    }

    private function donPickup(string $ma, string $sdt, SanPham $sp, int $qty, $hanGiu = null): DonHang
    {
        $don = DonHang::create([
            'ma_hoadon' => 'HD-'.uniqid(),
            'user_id' => null,
            'customer_id' => null,
            'kenh' => 'online',
            'phuong_thuc' => 'tienmat',
            'trang_thai' => 'cho_thanh_toan',
            'ma_tra_cuu' => $ma,
            'ten_khach_dat' => 'Khách Test',
            'so_dienthoai_dat' => $sdt,
            'tong_tien' => $sp->gia_ban * $qty,
            'tong_thanh_toan' => $sp->gia_ban * $qty,
            'giam_gia_phantram' => 0,
            'tien_giam' => 0,
            'diem_su_dung' => 0,
            'tien_giam_diem' => 0,
            'diem_tichluy_nhan' => 0,
            'tien_khach_dua' => 0,
            'tien_thua' => 0,
            'han_giu' => $hanGiu ?? now()->addHours(4),
        ]);
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

    public function test_tra_cuu_sai_ma_va_sai_sdt_cung_thong_bao_chung(): void
    {
        $sp = $this->sp();
        $this->donPickup('MA123456789', '0901234567', $sp, 1);

        $saiMa = $this->post(route('don-hang.posttracuu'), [
            'ma_tra_cuu' => 'KHONGTONTAI', 'so_dienthoai' => '0901234567',
        ]);
        $saiSdt = $this->post(route('don-hang.posttracuu'), [
            'ma_tra_cuu' => 'MA123456789', 'so_dienthoai' => '0999999999',
        ]);

        $saiMa->assertSessionHas('error', 'Không tìm thấy đơn hàng');
        $saiSdt->assertSessionHas('error', 'Không tìm thấy đơn hàng');
        // Không thiết lập bằng chứng khi tra cứu sai
        $this->assertEmpty(session('don_hang_xac_thuc'));
    }

    public function test_tra_cuu_dung_redirect_khong_lo_sdt_tren_url(): void
    {
        $sp = $this->sp();
        $this->donPickup('MA123456789', '0901234567', $sp, 1);

        $res = $this->post(route('don-hang.posttracuu'), [
            'ma_tra_cuu' => 'MA123456789', 'so_dienthoai' => '0901234567',
        ]);

        $res->assertRedirect(route('don-hang.trangthai', 'MA123456789'));
        $target = $res->headers->get('Location');
        $this->assertStringNotContainsString('0901234567', $target);
        $this->assertStringNotContainsString('sdt=', $target);
        $this->assertTrue(session('don_hang_xac_thuc.MA123456789'));
    }

    public function test_polling_chi_tra_trang_thai_khong_pii(): void
    {
        $sp = $this->sp();
        $this->donPickup('MA123456789', '0901234567', $sp, 1);

        $res = $this->withSession(['don_hang_xac_thuc' => ['MA123456789' => true]])
            ->post(route('don-hang.poll', 'MA123456789'));

        $res->assertOk();
        $res->assertExactJson(['trang_thai' => 'cho_thanh_toan', 'trang_thai_giao' => null, 'da_nhan' => false]);
    }

    public function test_polling_va_trang_thai_yeu_cau_bang_chung_session(): void
    {
        $sp = $this->sp();
        $this->donPickup('MA123456789', '0901234567', $sp, 1);

        // Chưa xác thực → polling 403, trang trạng thái redirect về tra cứu
        $this->post(route('don-hang.poll', 'MA123456789'))->assertForbidden();
        $this->get(route('don-hang.trangthai', 'MA123456789'))
            ->assertRedirect(route('don-hang.tracuu'));
    }

    public function test_khach_tu_huy_don_cho_hoan_kho(): void
    {
        $sp = $this->sp(10);
        $don = $this->donPickup('MA123456789', '0901234567', $sp, 3);
        $this->assertDatabaseHas('products', ['id' => $sp->id, 'soluong' => 7]);

        $res = $this->withSession(['don_hang_xac_thuc' => ['MA123456789' => true]])
            ->post(route('don-hang.huy', 'MA123456789'));

        $res->assertRedirect();
        $this->assertSame('da_huy', $don->fresh()->trang_thai);
        $this->assertDatabaseHas('products', ['id' => $sp->id, 'soluong' => 10]);
        $this->assertDatabaseHas('stock_movements', [
            'invoice_id' => $don->id, 'loai' => 'dieuchinh', 'soluong' => 3,
        ]);
    }

    public function test_khach_huy_don_da_thanh_toan_bi_tu_choi(): void
    {
        $sp = $this->sp(10);
        $don = $this->donPickup('MA123456789', '0901234567', $sp, 2);
        $don->update(['trang_thai' => 'da_thanh_toan']);

        $res = $this->withSession(['don_hang_xac_thuc' => ['MA123456789' => true]])
            ->post(route('don-hang.huy', 'MA123456789'));

        $res->assertRedirect();
        $res->assertSessionHas('error');
        $this->assertSame('da_thanh_toan', $don->fresh()->trang_thai);
        $this->assertDatabaseHas('products', ['id' => $sp->id, 'soluong' => 8]);
    }

    public function test_polling_huy_luoi_khi_qua_han_giu(): void
    {
        $sp = $this->sp(10);
        $don = $this->donPickup('MA123456789', '0901234567', $sp, 2, now()->subHour());

        $res = $this->withSession(['don_hang_xac_thuc' => ['MA123456789' => true]])
            ->post(route('don-hang.poll', 'MA123456789'));

        $res->assertExactJson(['trang_thai' => 'da_huy', 'trang_thai_giao' => null, 'da_nhan' => false]);
        $this->assertDatabaseHas('products', ['id' => $sp->id, 'soluong' => 10]);
    }
}