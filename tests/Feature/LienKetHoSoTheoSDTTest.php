<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bước 2 — đánh giá rủi ro liên kết hồ sơ theo SĐT.
 *
 * KỊCH BẢN: khách A có hồ sơ (đơn POS + đơn online chờ), người lạ B đăng ký
 * tài khoản bằng ĐÚNG SĐT của A mà không xác minh gì.
 *
 * Kết luận sau fix: đăng ký KHÔNG tự liên kết → B không thấy đơn/điểm/địa
 * chỉ/SĐT của A, không poll/hủy được đơn chờ của A. Chỉ khi nhân viên gán
 * liên kết thủ công (PUT khachhang.sua?user_id=...) thì B mới thấy — và
 * đó là hành vi mong muốn vì nhân viên đã xác nhận danh tính ngoài hệ thống.
 */
class LienKetHoSoTheoSDTTest extends TestCase
{
    use RefreshDatabase;

    private KhachHang $hoSoA;
    private DonHang $donPosA;
    private DonHang $donOnlineA;
    private string $sdtA = '0911222333';

    protected function setUp(): void
    {
        parent::setUp();

        // Hồ sơ A: khách vãng lai tại quầy, CHƯA liên kết tài khoản.
        $this->hoSoA = KhachHang::create([
            'ho_ten' => 'Khach Hang A',
            'so_dienthoai' => $this->sdtA,
            'dia_chi' => '123 Duong A, Quan 1',
            'diem_tichluy' => 120,
        ]);

        $staff = User::create([
            'name' => 'Thu ngan',
            'email' => 'staff.lienket@test.local',
            'password' => bcrypt('12345678'),
            'role' => 'thungan',
        ]);

        // Đơn POS đã thanh toán của A.
        $this->donPosA = DonHang::create([
            'ma_hoadon' => 'HD-LK-001',
            'user_id' => $staff->id,
            'customer_id' => $this->hoSoA->id,
            'tong_tien' => 200000,
            'tong_thanh_toan' => 200000,
            'tien_khach_dua' => 200000,
            'trang_thai' => 'da_thanh_toan',
            'kenh' => 'pos',
        ]);

        // Đơn online đang chờ thanh toán của A.
        $this->donOnlineA = DonHang::create([
            'ma_hoadon' => 'HD-LK-ONLINE-001',
            'user_id' => $staff->id,
            'customer_id' => $this->hoSoA->id,
            'tong_tien' => 150000,
            'tong_thanh_toan' => 150000,
            'tien_khach_dua' => 0,
            'trang_thai' => 'cho_thanh_toan',
            'kenh' => 'online',
            'phuong_thuc' => 'tienmat',
            'ma_tra_cuu' => 'MATRA-CUU-LIEN-KET-01',
            'han_giu' => now()->addHours(4),
        ]);
    }

    private function dangKyBangSdT(string $email): User
    {
        $this->post('/dang-ky', [
            'name' => 'Ke Xam Nhap',
            'email' => $email,
            'so_dienthoai' => $this->sdtA,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('storefront.home'));

        return User::where('email', $email)->firstOrFail();
    }

    public function test_dang_ky_trung_sdt_khong_duoc_lien_ket_hay_xem_du_lieu_a(): void
    {
        $userB = $this->dangKyBangSdT('ke.lan@example.com');

        // B không được gán vào hồ sơ A.
        $this->assertNull($this->hoSoA->fresh()->user_id);
        $this->assertNull($userB->khachHang);

        // B không xem được lịch sử/chi tiết đơn POS của A.
        $this->actingAs($userB)
            ->get(route('profile.orders'))
            ->assertOk()
            ->assertDontSee($this->donPosA->ma_hoadon);

        $this->actingAs($userB)
            ->get(route('profile.orders.show', $this->donPosA))
            ->assertForbidden();

        // B không xem/trạng thái/hủy được đơn online chờ của A.
        $this->actingAs($userB)
            ->get(route('don-hang.trangthai', $this->donOnlineA->ma_tra_cuu))
            ->assertRedirect(route('don-hang.tracuu'));

        $this->actingAs($userB)
            ->postJson(route('don-hang.poll', $this->donOnlineA->ma_tra_cuu))
            ->assertForbidden();

        $this->actingAs($userB)
            ->post(route('don-hang.huy', $this->donOnlineA->ma_tra_cuu))
            ->assertRedirect(route('don-hang.tracuu'));

        $this->assertSame('cho_thanh_toan', $this->donOnlineA->fresh()->trang_thai);

        // Hồ sơ A giữ nguyên điểm/địa chỉ/SĐT — B không đọc/sửa được.
        $this->hoSoA->refresh();
        $this->assertSame(120, $this->hoSoA->diem_tichluy);
        $this->assertSame($this->sdtA, $this->hoSoA->so_dienthoai);
        $this->assertSame('123 Duong A, Quan 1', $this->hoSoA->dia_chi);
    }

    public function test_nhan_vien_gan_lien_ket_thi_b_moi_thay_du_lieu_a(): void
    {
        $userB = $this->dangKyBangSdT('nhan.vien.xac.nhan@example.com');
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin.lienket@example.com',
            'password' => bcrypt('12345678'),
            'role' => 'admin',
        ]);

        // Nhân viên xác nhận danh tính ngoài hệ thống → gán thủ công.
        $this->actingAs($admin)
            ->put(route('khachhang.postsua', $this->hoSoA), [
                'ho_ten' => $this->hoSoA->ho_ten,
                'so_dienthoai' => $this->sdtA,
                'dia_chi' => $this->hoSoA->dia_chi,
                'user_id' => $userB->id,
            ])
            ->assertRedirect(route('khachhang.danhsach'));

        $this->assertSame($userB->id, $this->hoSoA->fresh()->user_id);

        // Sau khi được gán, B thấy đúng đơn của A (hành vi mong muốn).
        $this->actingAs($userB)
            ->get(route('profile.orders'))
            ->assertOk()
            ->assertSee($this->donPosA->ma_hoadon)
            ->assertSee($this->donOnlineA->ma_hoadon);
    }

    public function test_khong_gan_duoc_tai_khoan_da_lien_ket_ho_so_khac(): void
    {
        $userB = $this->dangKyBangSdT('da.co.ho.so@example.com');

        // B đã có hồ sơ riêng (tạo khi đăng ký với SĐT khác trước đó mô phỏng
        // bằng tạo trực tiếp — user_id unique trên customers).
        KhachHang::create([
            'user_id' => $userB->id,
            'ho_ten' => 'Ho so rieng B',
            'so_dienthoai' => '0909999888',
            'diem_tichluy' => 0,
        ]);

        $admin = User::create([
            'name' => 'Admin 2',
            'email' => 'admin.lienket2@example.com',
            'password' => bcrypt('12345678'),
            'role' => 'admin',
        ]);

        // Cố gán B (đã có hồ sơ) vào hồ sơ A → bị chặn.
        $this->actingAs($admin)
            ->put(route('khachhang.postsua', $this->hoSoA), [
                'ho_ten' => $this->hoSoA->ho_ten,
                'so_dienthoai' => $this->sdtA,
                'user_id' => $userB->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull($this->hoSoA->fresh()->user_id);
    }

    public function test_khong_the_lien_ket_tai_khoan_nhan_vien(): void
    {
        $staff = User::where('email', 'staff.lienket@test.local')->firstOrFail();
        $admin = User::create([
            'name' => 'Admin 3',
            'email' => 'admin.lienket3@example.com',
            'password' => bcrypt('12345678'),
            'role' => 'admin',
        ]);

        // Role thungan không được gán vào hồ sơ khách.
        $this->actingAs($admin)
            ->from(route('khachhang.sua', $this->hoSoA))
            ->put(route('khachhang.postsua', $this->hoSoA), [
                'ho_ten' => $this->hoSoA->ho_ten,
                'so_dienthoai' => $this->sdtA,
                'user_id' => $staff->id,
            ])
            ->assertSessionHasErrors('user_id');

        $this->assertNull($this->hoSoA->fresh()->user_id);
    }
}
