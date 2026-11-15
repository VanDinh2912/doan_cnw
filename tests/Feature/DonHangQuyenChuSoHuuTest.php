<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\SanPham;
use App\Models\User;
use App\Services\ThanhToanChuyenKhoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Bước 1B-c — điều kiện quyền xem/trạng thái/hủy đơn online trong
 * DatHangOnlineController::coQuyenXem: cho phép khi (a) có bằng chứng tra
 * cứu trong session, HOẶC (b) user đang đăng nhập có hồ sơ khách
 * (customers.user_id) trùng customer_id của đơn. SĐT KHÔNG được dùng để
 * phân quyền — đơn ghi so_dienthoai_dat trùng SĐT khách B vẫn không cho
 * B quyền nếu customer_id khác.
 */
class DonHangQuyenChuSoHuuTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;
    private SanPham $sanPham;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        Config::set('pos.bank_code', 'MB');
        Config::set('pos.bank_account', '0987654321');
        Config::set('pos.bank_name', 'CUA HANG TEST');
        Config::set('pos.online_pickup_gio', 4);
        Config::set('pos.telegram_bot_token', '');
        Config::set('pos.telegram_chat_id', '');
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $this->cashier = User::where('email', 'thungan@example.com')->firstOrFail();
        $this->sanPham = SanPham::where('sku', 'CS-0001')->firstOrFail();
        $this->sanPham->update(['soluong' => 10, 'trang_thai' => true]);
    }

    private function userCoHoSo(string $email, string $soDienThoai): User
    {
        $user = User::create([
            'name' => 'Khach '.uniqid(),
            'email' => $email,
            'password' => bcrypt('12345678'),
            'role' => 'khachhang',
        ]);
        KhachHang::create([
            'user_id' => $user->id,
            'ho_ten' => 'Khach Hang',
            'so_dienthoai' => $soDienThoai,
            'diem_tichluy' => 0,
        ]);

        return $user;
    }

    /**
     * Tạo đơn online pickup qua service thật: trừ kho, gắn customer_id,
     * sinh ma_tra_cuu — mô phỏng đúng đơn do khách A đặt.
     */
    private function taoDonOnlineCho(User $chuDon, ?string $soDienThoaiDat = null): DonHang
    {
        $ma = 'MATEST-'.strtoupper(uniqid());

        return app(ThanhToanChuyenKhoanService::class)->taoHoaDonChoThanhToan(
            [['id' => $this->sanPham->id, 'soluong' => 1]],
            [],
            null,
            [
                'kenh' => 'online',
                'phuong_thuc' => 'tienmat',
                'ma_tra_cuu' => $ma,
                'ten_khach_dat' => 'Khach A',
                'so_dienthoai_dat' => $soDienThoaiDat ?? $chuDon->khachHang->so_dienthoai,
                'han_giu' => now()->addHours(4),
                'hinh_thuc_nhan' => 'tai_cua_hang',
            ],
            $chuDon->khachHang->id
        );
    }

    public function test_chu_don_da_dang_nhap_xem_trang_thai_poll_va_huy(): void
    {
        $chuA = $this->userCoHoSo('chu.a@example.com', '0911000001');
        $don = $this->taoDonOnlineCho($chuA);
        $this->assertSame(9, (int) $this->sanPham->fresh()->soluong);

        $this->actingAs($chuA)
            ->get(route('don-hang.trangthai', $don->ma_tra_cuu))
            ->assertOk();

        $this->actingAs($chuA)
            ->postJson(route('don-hang.poll', $don->ma_tra_cuu))
            ->assertOk();

        $this->actingAs($chuA)
            ->post(route('don-hang.huy', $don->ma_tra_cuu))
            ->assertRedirect(route('don-hang.trangthai', $don->ma_tra_cuu));

        $this->assertSame('da_huy', $don->fresh()->trang_thai);
        $this->assertSame(10, (int) $this->sanPham->fresh()->soluong);
    }

    public function test_khach_b_khong_quyen_du_don_ghi_dung_sdt_cua_b(): void
    {
        $chuA = $this->userCoHoSo('chu.a2@example.com', '0911000002');
        $khachB = $this->userCoHoSo('chu.b@example.com', '0911000003');

        // Đơn của A nhưng so_dienthoai_dat trùng SĐT hồ sơ của B —
        // quyền chỉ so customer_id, SĐT không phải chìa khóa.
        $don = $this->taoDonOnlineCho($chuA, $khachB->khachHang->so_dienthoai);

        $this->actingAs($khachB)
            ->get(route('don-hang.trangthai', $don->ma_tra_cuu))
            ->assertRedirect(route('don-hang.tracuu'));

        $this->actingAs($khachB)
            ->postJson(route('don-hang.poll', $don->ma_tra_cuu))
            ->assertForbidden();

        $this->actingAs($khachB)
            ->post(route('don-hang.huy', $don->ma_tra_cuu))
            ->assertRedirect(route('don-hang.tracuu'));

        $this->assertSame('cho_thanh_toan', $don->fresh()->trang_thai);
        $this->assertSame(9, (int) $this->sanPham->fresh()->soluong);
    }

    public function test_guest_khong_bang_chung_khong_quyen(): void
    {
        $chuA = $this->userCoHoSo('chu.a3@example.com', '0911000004');
        $don = $this->taoDonOnlineCho($chuA);

        $this->get(route('don-hang.trangthai', $don->ma_tra_cuu))
            ->assertRedirect(route('don-hang.tracuu'));

        $this->postJson(route('don-hang.poll', $don->ma_tra_cuu))
            ->assertForbidden();

        $this->post(route('don-hang.huy', $don->ma_tra_cuu))
            ->assertRedirect(route('don-hang.tracuu'));

        $this->assertSame('cho_thanh_toan', $don->fresh()->trang_thai);
    }

    public function test_guest_co_bang_chung_session_van_xem_duoc(): void
    {
        $chuA = $this->userCoHoSo('chu.a4@example.com', '0911000005');
        $don = $this->taoDonOnlineCho($chuA);

        $this->withSession(['don_hang_xac_thuc' => [$don->ma_tra_cuu => true]])
            ->get(route('don-hang.trangthai', $don->ma_tra_cuu))
            ->assertOk();
    }

    public function test_khong_huy_duoc_don_da_thanh_toan_va_da_huy(): void
    {
        $chuA = $this->userCoHoSo('chu.a5@example.com', '0911000006');

        $donDaThanhToan = $this->taoDonOnlineCho($chuA);
        $donDaThanhToan->update(['trang_thai' => 'da_thanh_toan']);

        $this->actingAs($chuA)
            ->post(route('don-hang.huy', $donDaThanhToan->ma_tra_cuu))
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertSame('da_thanh_toan', $donDaThanhToan->fresh()->trang_thai);

        $donDaHuy = $this->taoDonOnlineCho($chuA);
        $donDaHuy->update(['trang_thai' => 'da_huy']);

        $this->actingAs($chuA)
            ->post(route('don-hang.huy', $donDaHuy->ma_tra_cuu))
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertSame('da_huy', $donDaHuy->fresh()->trang_thai);
    }

    public function test_huy_don_hoan_kho_dung_mot_lan(): void
    {
        $chuA = $this->userCoHoSo('chu.a6@example.com', '0911000007');
        $don = $this->taoDonOnlineCho($chuA);
        $this->assertSame(9, (int) $this->sanPham->fresh()->soluong);

        $this->actingAs($chuA)
            ->post(route('don-hang.huy', $don->ma_tra_cuu))
            ->assertRedirect();
        $this->assertSame('da_huy', $don->fresh()->trang_thai);
        $this->assertSame(10, (int) $this->sanPham->fresh()->soluong);

        // Hủy lần hai: bị chặn, kho không hoàn thêm.
        $this->actingAs($chuA)
            ->post(route('don-hang.huy', $don->ma_tra_cuu))
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertSame(10, (int) $this->sanPham->fresh()->soluong);
    }
}
