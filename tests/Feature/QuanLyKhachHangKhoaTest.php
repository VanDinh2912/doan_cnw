<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\NhatKyHoatDong;
use App\Models\SanPham;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Quản lý khách hàng — khóa/mở khóa hồ sơ (customers.khoa_den + ly_do_khoa)
 * đồng bộ users.khoa_luc trong cùng transaction. Chỉ admin được khóa/mở;
 * thu ngân chỉ xem. Khách bị khóa không đăng nhập/đặt hàng/tra cứu online
 * được. Guest trùng SĐT hồ sơ bị khóa vẫn đặt được nhưng có cảnh báo nội bộ.
 */
class QuanLyKhachHangKhoaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $thungan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('role', 'admin')->firstOrFail();
        $this->thungan = User::where('role', 'thungan')->firstOrFail();
    }

    private function taoKhachCoTaiKhoan(string $sdt = '0901111222'): array
    {
        $user = User::create([
            'name' => 'Khach Khoa Test',
            'email' => 'khach.khoa@test.vn',
            'password' => Hash::make('MatKhau123!'),
            'role' => 'khachhang',
        ]);
        $khach = KhachHang::create([
            'user_id' => $user->id,
            'ho_ten' => 'Khach Khoa Test',
            'so_dienthoai' => $sdt,
            'diem_tichluy' => 0,
        ]);

        return [$user, $khach];
    }

    private function sp(int $ton = 10): SanPham
    {
        $sp = SanPham::orderBy('id')->firstOrFail();
        $sp->update(['soluong' => $ton, 'trang_thai' => true]);

        return $sp->fresh();
    }

    private function cart(SanPham $sp, int $qty): array
    {
        return [(string) $sp->id => [
            'id' => $sp->id, 'sku' => $sp->sku, 'ten_sanpham' => $sp->ten_sanpham,
            'gia_ban' => (float) $sp->gia_ban, 'soluong' => $qty, 'ton_kho' => $sp->soluong,
        ]];
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'ten' => 'Khach Khoa Test',
            'so_dienthoai' => '0901111222',
            'phuong_thuc' => 'pickup',
            'hinh_thuc_nhan' => 'tai_cua_hang',
            'checkout_token' => 'TOKEN',
        ], $overrides);
    }

    // ---------------------------------------------------------------
    // Khóa / mở khóa — phân quyền & transaction đôi
    // ---------------------------------------------------------------

    public function test_admin_khoa_khach_co_tai_khoan_set_ca_hai_truong(): void
    {
        [$user, $khach] = $this->taoKhachCoTaiKhoan();

        $this->actingAs($this->admin)
            ->post(route('khachhang.khoa', $khach), ['ly_do_khoa' => 'Khách yêu cầu khóa'])
            ->assertRedirect(route('khachhang.chitiet', $khach));

        $khach->refresh();
        $this->assertNotNull($khach->khoa_den);
        $this->assertSame('Khách yêu cầu khóa', $khach->ly_do_khoa);
        $this->assertNotNull($user->fresh()->khoa_luc);
    }

    public function test_admin_mo_khoa_tra_ca_hai_ve_null(): void
    {
        [$user, $khach] = $this->taoKhachCoTaiKhoan();
        $khach->update(['khoa_den' => now(), 'ly_do_khoa' => 'test']);
        $user->update(['khoa_luc' => now()]);

        $this->actingAs($this->admin)
            ->post(route('khachhang.mokhoa', $khach))
            ->assertRedirect(route('khachhang.chitiet', $khach));

        $khach->refresh();
        $this->assertNull($khach->khoa_den);
        $this->assertNull($khach->ly_do_khoa);
        $this->assertNull($user->fresh()->khoa_luc);
    }

    public function test_khoa_bat_buoc_ly_do_va_ghi_audit(): void
    {
        [, $khach] = $this->taoKhachCoTaiKhoan('0903333444');

        $this->actingAs($this->admin)
            ->post(route('khachhang.khoa', $khach), [])
            ->assertSessionHasErrors('ly_do_khoa');

        $this->assertNull($khach->fresh()->khoa_den);

        $this->actingAs($this->admin)
            ->post(route('khachhang.khoa', $khach), ['ly_do_khoa' => 'Nghi ngờ gian lận'])
            ->assertRedirect();

        $this->assertNotNull($khach->fresh()->khoa_den);
        $this->assertDatabaseHas('activity_logs', [
            'hanh_dong' => 'khoa_khach',
            'doi_tuong' => 'customers',
            'doi_tuong_id' => $khach->id,
        ]);
    }

    public function test_thungan_goi_route_khoa_bi_403_nhung_xem_duoc(): void
    {
        [, $khach] = $this->taoKhachCoTaiKhoan('0905555666');

        $this->actingAs($this->thungan)
            ->post(route('khachhang.khoa', $khach), ['ly_do_khoa' => 'test'])
            ->assertForbidden();
        $this->actingAs($this->thungan)
            ->post(route('khachhang.mokhoa', $khach))
            ->assertForbidden();

        $this->assertNull($khach->fresh()->khoa_den);

        // Thu ngân vẫn xem danh sách + chi tiết
        $this->actingAs($this->thungan)->get(route('khachhang.danhsach'))->assertOk();
        $this->actingAs($this->thungan)->get(route('khachhang.chitiet', $khach))->assertOk();
        // Nhưng không thấy form khóa trong HTML chi tiết
        $this->actingAs($this->thungan)->get(route('khachhang.chitiet', $khach))
            ->assertDontSee('name="ly_do_khoa"', false);
    }

    public function test_khach_khong_tai_khoan_khoa_chi_anh_huong_hoso(): void
    {
        $khach = KhachHang::create([
            'ho_ten' => 'Khach Vo Tai Khoan', 'so_dienthoai' => '0907777888', 'diem_tichluy' => 0,
        ]);

        $this->actingAs($this->admin)
            ->post(route('khachhang.khoa', $khach), ['ly_do_khoa' => 'Lý do'])
            ->assertRedirect();

        $this->assertNotNull($khach->fresh()->khoa_den);
        // Không có user_id → không tài khoản nào bị khóa theo
        $this->assertSame(0, User::where('role', 'khachhang')->whereNotNull('khoa_luc')->count());
    }

    // ---------------------------------------------------------------
    // Chặn đăng nhập / đặt hàng online
    // ---------------------------------------------------------------

    public function test_khach_bi_khoa_khong_dang_nhap_duoc_chan_som(): void
    {
        [$user, $khach] = $this->taoKhachCoTaiKhoan('0909999000');
        $khach->update(['khoa_den' => now(), 'ly_do_khoa' => 'test']);
        $user->update(['khoa_luc' => now()]);

        $res = $this->post(route('auth.postdangnhap'), [
            'email' => 'khach.khoa@test.vn',
            'password' => 'MatKhau123!',
        ]);

        $res->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_khach_bi_khoa_dat_hang_online_bi_chan(): void
    {
        [$user, $khach] = $this->taoKhachCoTaiKhoan('0909999001');
        $khach->update(['khoa_den' => now(), 'ly_do_khoa' => 'test']);
        $user->update(['khoa_luc' => now()]);
        $sp = $this->sp(10);

        // Form bị chặn
        $this->actingAs($user)
            ->withSession(['storefront_cart' => $this->cart($sp, 1)])
            ->get(route('dat-hang.form'))
            ->assertRedirect(route('storefront.home'));

        // POST bị chặn, không tạo đơn
        $this->actingAs($user)
            ->withSession(['checkout_token' => 'TOKEN', 'storefront_cart' => $this->cart($sp, 1)])
            ->post(route('dat-hang.dat'), $this->payload())
            ->assertSessionHas('error');
        $this->assertDatabaseCount('invoices', 0);

        // Tra cứu bị chặn
        $this->actingAs($user)->post(route('don-hang.posttracuu'), [
            'ma_tra_cuu' => 'XXXX', 'so_dienthoai' => '0909999001',
        ])->assertSessionHas('error');
    }

    public function test_session_song_cua_khach_vua_bi_khoa_bi_chan_ngay(): void
    {
        [$user, $khach] = $this->taoKhachCoTaiKhoan('0909999002');
        $sp = $this->sp(10);
        $session = ['checkout_token' => 'TOKEN', 'storefront_cart' => $this->cart($sp, 1)];

        // Session đang sống, chưa khóa → form OK
        $this->actingAs($user)->withSession($session)->get(route('dat-hang.form'))->assertOk();

        // Admin khóa khách NGAY BÂY GIỜ (giữa session)
        $khach->update(['khoa_den' => now(), 'ly_do_khoa' => 'Khóa đột xuất']);

        // Request kế tiếp với cùng session → bị chặn
        $this->actingAs($user)
            ->withSession($session)
            ->post(route('dat-hang.dat'), $this->payload())
            ->assertSessionHas('error');
        $this->assertDatabaseCount('invoices', 0);
    }

    // ---------------------------------------------------------------
    // Guest trùng SĐT hồ sơ bị khóa
    // ---------------------------------------------------------------

    public function test_guest_trung_sdt_khach_khoa_van_dat_duoc_co_canh_bao(): void
    {
        [, $khach] = $this->taoKhachCoTaiKhoan('0909999003');
        $khach->update(['khoa_den' => now(), 'ly_do_khoa' => 'test']);
        $sp = $this->sp(10);

        $res = $this->withSession(['checkout_token' => 'TOKEN', 'storefront_cart' => $this->cart($sp, 1)])
            ->post(route('dat-hang.dat'), $this->payload(['so_dienthoai' => '0909999003']));

        $res->assertRedirect();
        $don = DonHang::where('kenh', 'online')->firstOrFail();
        // Đơn guest: KHÔNG gắn hồ sơ khách bị khóa
        $this->assertNull($don->customer_id);
        $this->assertSame('cho_thanh_toan', $don->trang_thai);

        // Audit log cảnh báo cho nhân viên
        $this->assertDatabaseHas('activity_logs', [
            'hanh_dong' => 'canh_bao_sdt_khach_khoa',
            'doi_tuong' => 'invoices',
            'doi_tuong_id' => $don->id,
        ]);
    }

    public function test_guest_sdt_khach_khong_khoa_khong_co_canh_bao(): void
    {
        [, $khach] = $this->taoKhachCoTaiKhoan('0909999004');
        // Không khóa
        $sp = $this->sp(10);

        $this->withSession(['checkout_token' => 'TOKEN', 'storefront_cart' => $this->cart($sp, 1)])
            ->post(route('dat-hang.dat'), $this->payload(['so_dienthoai' => '0909999004']))
            ->assertRedirect();

        $don = DonHang::where('kenh', 'online')->firstOrFail();
        $this->assertSame(0, NhatKyHoatDong::where('hanh_dong', 'canh_bao_sdt_khach_khoa')
            ->where('doi_tuong_id', $don->id)->count());
    }

    // ---------------------------------------------------------------
    // Trang chi tiết & ràng buộc cũ
    // ---------------------------------------------------------------

    public function test_chitiet_khach_hien_don_va_tong_chi_tieu(): void
    {
        [, $khach] = $this->taoKhachCoTaiKhoan('0909999005');
        $sp = $this->sp(10);
        $don = DonHang::create([
            'ma_hoadon' => 'HD-TEST-001', 'customer_id' => $khach->id,
            'tong_tien' => 50000, 'giam_gia_phantram' => 0, 'tien_giam' => 0,
            'diem_su_dung' => 0, 'tien_giam_diem' => 0, 'diem_tichluy_nhan' => 0,
            'tong_thanh_toan' => 50000, 'tien_khach_dua' => 50000, 'tien_thua' => 0,
            'phuong_thuc' => 'tienmat', 'trang_thai' => 'da_thanh_toan',
        ]);

        $this->actingAs($this->thungan)
            ->get(route('khachhang.chitiet', $khach))
            ->assertOk()
            ->assertSee('HD-TEST-001')
            ->assertSee('50.000');
    }

    public function test_xoa_khach_co_don_van_bi_chan(): void
    {
        [, $khach] = $this->taoKhachCoTaiKhoan('0909999006');
        DonHang::create([
            'ma_hoadon' => 'HD-TEST-002', 'customer_id' => $khach->id,
            'tong_tien' => 10000, 'giam_gia_phantram' => 0, 'tien_giam' => 0,
            'diem_su_dung' => 0, 'tien_giam_diem' => 0, 'diem_tichluy_nhan' => 0,
            'tong_thanh_toan' => 10000, 'tien_khach_dua' => 10000, 'tien_thua' => 0,
            'phuong_thuc' => 'tienmat', 'trang_thai' => 'da_thanh_toan',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('khachhang.xoa', $khach))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('customers', ['id' => $khach->id]);
    }
}
