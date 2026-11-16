<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\SanPham;
use App\Models\User;
use App\Services\ThanhToanChuyenKhoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** HM3 — đơn pickup/QR chờ trên màn hình đơn online: nút hiển thị đúng trạng thái,
 *  xác nhận 1 lần, hủy hoàn kho, gia hạn có trần, đơn quá hạn không xác nhận được. */
class DonHangPickupXuLyTest extends TestCase
{
    use RefreshDatabase;

    private function donPickup(array $overrides = []): DonHang
    {
        $sp = SanPham::orderBy('id')->firstOrFail();
        $sp->update(['soluong' => 10, 'trang_thai' => true]);
        $don = app(ThanhToanChuyenKhoanService::class)->taoHoaDonChoThanhToan(
            [['id' => $sp->id, 'soluong' => 2]], [], null,
            array_merge([
                'kenh' => 'online', 'phuong_thuc' => 'tienmat',
                'hinh_thuc_nhan' => 'tai_cua_hang',
                'ma_tra_cuu' => 'PK'.random_int(100000, 999999),
                'ten_khach_dat' => 'Khách Pickup', 'so_dienthoai_dat' => '0903333444',
                'han_giu' => now()->addHours(4),
            ], $overrides)
        );
        DonHang::whereKey($don->id)->update(array_intersect_key($overrides, array_flip(['han_giu'])));

        return $don->fresh();
    }

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    public function test_nut_hien_dung_trang_thai_va_vai_tro(): void
    {
        $this->seed();
        $don = $this->donPickup();
        $thuNgan = User::where('role', 'thungan')->firstOrFail();
        $thuKho = User::where('role', 'thukho')->firstOrFail();

        // thu ngân thấy 3 nút khi còn chờ thanh toán
        $this->actingAs($thuNgan)->get(route('donhangonline.chitiet', $don))
            ->assertOk()->assertSee('btn-xacnhan')->assertSee('btn-giahan')->assertSee('btn-huycho');
        // thủ kho KHÔNG thấy nút thao tác thu ngân
        $this->actingAs($thuKho)->get(route('donhangonline.chitiet', $don))
            ->assertOk()->assertDontSee('btn-xacnhan');

        // đã thanh toán → không còn nút
        $don->update(['trang_thai' => 'da_thanh_toan']);
        $this->actingAs($thuNgan)->get(route('donhangonline.chitiet', $don))
            ->assertOk()->assertDontSee('btn-xacnhan');
    }

    public function test_xac_nhan_mot_lan_khong_tru_kho_lai(): void
    {
        $this->seed();
        $don = $this->donPickup();
        $sp = SanPham::orderBy('id')->firstOrFail();
        $this->assertSame(8, (int) $sp->fresh()->soluong); // 10 - 2 đang giữ

        $thuNgan = User::where('role', 'thungan')->firstOrFail();
        $this->actingAs($thuNgan)->postJson(route('donhang.xacnhan', $don))
            ->assertOk()->assertJson(['success' => true]);
        $this->assertSame('da_thanh_toan', $don->fresh()->trang_thai);
        $this->assertSame(8, (int) $sp->fresh()->soluong); // không trừ lại

        // Bấm đúp → đã xử lý (422 already_processed), không trừ kho/ghi movement lần hai
        $this->actingAs($thuNgan)->postJson(route('donhang.xacnhan', $don))
            ->assertStatus(422);
        $this->assertSame(8, (int) $sp->fresh()->soluong);
        $this->assertSame(1, \App\Models\BienDongKho::where('invoice_id', $don->id)->where('loai', 'ban')->count());
    }

    public function test_huy_don_cho_hoan_kho_va_khong_xac_nhan_duoc(): void
    {
        $this->seed();
        $don = $this->donPickup();
        $sp = SanPham::orderBy('id')->firstOrFail();
        $thuNgan = User::where('role', 'thungan')->firstOrFail();

        $this->actingAs($thuNgan)->postJson(route('donhang.huycho', $don))
            ->assertOk()->assertJson(['success' => true]);
        $this->assertSame('da_huy', $don->fresh()->trang_thai);
        $this->assertSame(10, (int) $sp->fresh()->soluong); // hoàn kho

        // Đơn đã hủy không xác nhận được nữa
        $this->actingAs($thuNgan)->postJson(route('donhang.xacnhan', $don))
            ->assertStatus(422);
        $this->assertSame('da_huy', $don->fresh()->trang_thai);
        $this->assertSame(10, (int) $sp->fresh()->soluong);
    }

    public function test_gia_han_co_tran_va_qua_han_khong_xac_nhan(): void
    {
        $this->seed();
        config(['pos.online_pickup_gio' => 4, 'pos.online_gia_han_toi_da_gio' => 8]);
        // han_giu sát trần → gia hạn bị chặn
        $don = $this->donPickup(['han_giu' => now()->addHours(8)]);
        DonHang::whereKey($don->id)->update(['created_at' => now()->subHours(2)]);
        $don = $don->fresh();

        $thuNgan = User::where('role', 'thungan')->firstOrFail();
        $this->actingAs($thuNgan)->postJson(route('donhang.giahan', $don))
            ->assertStatus(422);

        // Đơn quá hạn nhưng scheduler chưa hủy: nhân viên vẫn xác nhận được
        // (tiền đã về) — hành vi hiện có của hoanTatThanhToan, giữ nguyên.
        $don2 = $this->donPickup(['han_giu' => now()->subMinute()]);
        $this->actingAs($thuNgan)->postJson(route('donhang.xacnhan', $don2))
            ->assertOk()->assertJson(['success' => true]);

        // Đơn quá hạn ĐÃ bị hủy → không xác nhận được nữa
        $don3 = $this->donPickup(['han_giu' => now()->subMinute()]);
        app(ThanhToanChuyenKhoanService::class)->huyChoThanhToan($don3, 'Quá hạn giữ hàng');
        $this->actingAs($thuNgan)->postJson(route('donhang.xacnhan', $don3))
            ->assertStatus(422);
        $this->assertSame('da_huy', $don3->fresh()->trang_thai);
    }

    public function test_don_qr_giao_tan_noi_cung_dung_duoc_xac_nhan_huy(): void
    {
        $this->seed();
        $sp = SanPham::orderBy('id')->firstOrFail();
        $sp->update(['soluong' => 10, 'trang_thai' => true]);
        $don = app(ThanhToanChuyenKhoanService::class)->taoHoaDonChoThanhToan(
            [['id' => $sp->id, 'soluong' => 1]], [], null,
            [
                'kenh' => 'online', 'phuong_thuc' => 'chuyenkhoan',
                'hinh_thuc_nhan' => 'giao_tan_noi',
                'ma_tra_cuu' => 'DL'.random_int(100000, 999999),
                'ten_khach_dat' => 'Khách Giao', 'so_dienthoai_dat' => '0905555666',
                'ten_nguoi_nhan' => 'Người Nhận', 'sdt_nguoi_nhan' => '0905555666',
                'dia_chi_giao' => '1 Lê Lợi, Q1',
                'han_giu' => now()->addMinutes(30),
            ]
        );
        $thuNgan = User::where('role', 'thungan')->firstOrFail();
        $this->actingAs($thuNgan)->postJson(route('donhang.xacnhan', $don))
            ->assertOk()->assertJson(['success' => true]);
        $this->assertSame('da_thanh_toan', $don->fresh()->trang_thai);
        // Webhook lặp sau xác nhận thủ công không tác dụng lần hai
        $res = app(ThanhToanChuyenKhoanService::class)->hoanTatThanhToan($don->fresh(), 'FT999', null, 'webhook');
        $this->assertFalse($res['success']);
        $this->assertTrue($res['already_processed']);
        $this->assertSame('da_thanh_toan', $don->fresh()->trang_thai);
    }
}
