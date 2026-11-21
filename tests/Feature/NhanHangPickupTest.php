<?php

namespace Tests\Feature;

use App\Models\BienDongKho;
use App\Models\DonHang;
use App\Models\SanPham;
use App\Models\User;
use App\Services\PinService;
use App\Services\ThanhToanChuyenKhoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use App\Support\KiemTraSchema;
use Tests\TestCase;

/** Nhận hàng pickup: xác nhận 1 lần, chặn sai trạng thái/vai trò, không trừ kho,
 *  chặn hủy/hoàn tiền sau khi đã nhận, an toàn khi DB chưa có cột. */
class NhanHangPickupTest extends TestCase
{
    use RefreshDatabase;

    private function donPickup(array $overrides = [], bool $daThanhToan = true): DonHang
    {
        $sp = SanPham::orderBy('id')->firstOrFail();
        $sp->update(['soluong' => 10, 'trang_thai' => true]);
        $don = app(ThanhToanChuyenKhoanService::class)->taoHoaDonChoThanhToan(
            [['id' => $sp->id, 'soluong' => 2]], [], null,
            array_merge([
                'kenh' => 'online', 'phuong_thuc' => 'tienmat',
                'hinh_thuc_nhan' => 'tai_cua_hang',
                'ma_tra_cuu' => 'NH'.random_int(100000, 999999),
                'ten_khach_dat' => 'Khách Nhận', 'so_dienthoai_dat' => '0905555666',
                'han_giu' => now()->addHours(4),
            ], $overrides)
        );
        if ($daThanhToan) {
            $don->update(['trang_thai' => 'da_thanh_toan', 'thoi_gian_thanh_toan' => now()]);
        }

        return $don->fresh();
    }

    private function theoVaiTro(string $role): User
    {
        return User::where('role', $role)->firstOrFail();
    }

    public function test_nhan_hang_thanh_cong_khong_tru_kho_lan_hai(): void
    {
        $this->seed();
        $don = $this->donPickup();
        $sp = SanPham::orderBy('id')->firstOrFail();
        $soluongTruoc = (int) $sp->fresh()->soluong;
        $movementsTruoc = BienDongKho::count();
        $thuNgan = $this->theoVaiTro('thungan');

        $this->actingAs($thuNgan)
            ->post(route('donhangonline.nhanhang', $don), ['ghi_chu_nhan' => 'Người nhà nhận hộ'])
            ->assertRedirect();

        $don->refresh();
        $this->assertNotNull($don->nhan_hang_luc);
        $this->assertSame($thuNgan->id, (int) $don->nhan_hang_boi);
        $this->assertSame('Người nhà nhận hộ', $don->ghi_chu_nhan);
        $this->assertSame('da_nhan', $don->trang_thai_giao);
        $this->assertSame($soluongTruoc, (int) $sp->fresh()->soluong);
        $this->assertSame($movementsTruoc, BienDongKho::count());
        $this->assertSame('da_thanh_toan', $don->trang_thai); // doanh thu/thanh toán không đổi
    }

    public function test_tu_choi_sai_trang_thai_va_khong_phai_pickup(): void
    {
        $this->seed();
        $admin = $this->theoVaiTro('admin');

        // Chưa thanh toán
        $cho = $this->donPickup([], false);
        $this->actingAs($admin)->post(route('donhangonline.nhanhang', $cho))
            ->assertSessionHas('error');
        $this->assertNull($cho->fresh()->nhan_hang_luc);

        // Đã hủy
        $daHuy = $this->donPickup();
        $daHuy->update(['trang_thai' => 'da_huy']);
        $this->actingAs($admin)->post(route('donhangonline.nhanhang', $daHuy))
            ->assertSessionHas('error');
        $this->assertNull($daHuy->fresh()->nhan_hang_luc);

        // Giao tận nơi đã thanh toán
        $giao = $this->donPickup(['hinh_thuc_nhan' => 'giao_tan_noi',
            'ten_nguoi_nhan' => 'A', 'sdt_nguoi_nhan' => '0901234567',
            'dia_chi_giao' => '123 Test']);
        $this->actingAs($admin)->post(route('donhangonline.nhanhang', $giao))
            ->assertSessionHas('error');
        $this->assertNull($giao->fresh()->nhan_hang_luc);
    }

    public function test_chong_nhan_hai_lan(): void
    {
        $this->seed();
        $admin = $this->theoVaiTro('admin');
        $don = $this->donPickup();

        $this->actingAs($admin)->post(route('donhangonline.nhanhang', $don))->assertRedirect();
        $luc1 = $don->fresh()->nhan_hang_luc;

        $this->actingAs($admin)->post(route('donhangonline.nhanhang', $don))
            ->assertSessionHas('error');
        $this->assertEquals($luc1, $don->fresh()->nhan_hang_luc);
    }

    public function test_phan_quyen_thu_kho_va_khach_bi_chan(): void
    {
        $this->seed();
        $don = $this->donPickup();
        $thuKho = $this->theoVaiTro('thukho');
        $khach = User::create([
            'name' => 'KH', 'email' => 'kh'.uniqid().'@test.local',
            'password' => bcrypt('x'), 'role' => 'khachhang',
        ]);

        $this->actingAs($thuKho)->post(route('donhangonline.nhanhang', $don))->assertForbidden();
        // Khách (role khachhang) bị role middleware chặn — redirect, không xử lý
        $this->actingAs($khach)->post(route('donhangonline.nhanhang', $don))->assertRedirect();
        $this->assertNull($don->fresh()->nhan_hang_luc);
    }

    public function test_chan_huy_va_hoan_tien_sau_khi_da_nhan(): void
    {
        $this->seed();
        $admin = $this->theoVaiTro('admin');
        $don = $this->donPickup();

        $this->actingAs($admin)->post(route('donhangonline.nhanhang', $don))->assertRedirect();
        $don->update(['can_hoan_tien' => true]);

        $this->actingAs($admin)->post(route('donhangonline.huydathanhtoan', $don), ['ly_do' => 'Khách đổi ý'])
            ->assertSessionHas('error');
        $this->assertSame('da_thanh_toan', $don->fresh()->trang_thai);

        $this->actingAs($admin)->post(route('donhangonline.dahoantien', $don), ['ghi_chu' => 'Đã chuyển khoản lại'])
            ->assertSessionHas('error');
        $this->assertTrue((bool) $don->fresh()->can_hoan_tien);
    }

    public function test_co_pin_bat_yeu_cau_xac_nhan(): void
    {
        $this->seed();
        config(['pos.pin_bat' => true]);
        $admin = $this->theoVaiTro('admin');
        PinService::datPin($admin, '483920');
        $don = $this->donPickup();

        $this->actingAs($admin)->postJson(route('donhangonline.nhanhang', $don))
            ->assertStatus(423)->assertJson(['ma' => 'CAN_PIN', 'muc' => 'thuong']);
        $this->assertNull($don->fresh()->nhan_hang_luc);
    }

    public function test_db_chua_co_cot_van_chay(): void
    {
        $this->seed();
        $admin = $this->theoVaiTro('admin');
        $don = $this->donPickup();

        Schema::table('invoices', function ($t) {
            $t->dropIndex(['nhan_hang_boi']);
            $t->dropColumn(['nhan_hang_boi', 'nhan_hang_luc', 'ghi_chu_nhan']);
        });
        KiemTraSchema::datLai();

        try {
            // Màn chi tiết vẫn 200, hiện ghi chú chưa migrate, không crash
            $this->actingAs($admin)->get(route('donhangonline.chitiet', $don))
                ->assertOk()->assertSee('chưa sẵn sàng');
            // POST bị từ chối an toàn
            $this->actingAs($admin)->post(route('donhangonline.nhanhang', $don))
                ->assertSessionHas('error');
            // Trang tra cứu khách vẫn chạy
            $this->post(route('don-hang.posttracuu'), [
                'ma_tra_cuu' => $don->ma_tra_cuu,
                'so_dienthoai' => $don->so_dienthoai_dat,
            ]);
        } finally {
            // Khôi phục cột cho các test sau trong cùng process không cần thiết
            // (RefreshDatabase mỗi test), nhưng trả lại cho chắc.
        }
    }

    public function test_trang_khach_hien_nhan_da_nhan_va_cho_den_nhan(): void
    {
        $this->seed();
        $don = $this->donPickup();

        // Tra cứu đúng -> trang trạng thái hiện "Chờ bạn đến nhận"
        $this->post(route('don-hang.posttracuu'), [
            'ma_tra_cuu' => $don->ma_tra_cuu,
            'so_dienthoai' => $don->so_dienthoai_dat,
        ])->assertRedirect(route('don-hang.trangthai', $don->ma_tra_cuu));
        $this->get(route('don-hang.trangthai', $don->ma_tra_cuu))
            ->assertOk()->assertSee('Chờ bạn đến nhận hàng');

        // Nhân viên xác nhận -> khách thấy "Đã nhận hàng"
        $admin = $this->theoVaiTro('admin');
        $this->actingAs($admin)->post(route('donhangonline.nhanhang', $don))->assertRedirect();
        $this->get(route('don-hang.trangthai', $don->ma_tra_cuu))
            ->assertOk()->assertSee('Đã nhận hàng');
    }
}
