<?php

namespace Tests\Feature;

use App\Models\KhachHang;
use App\Models\NhatKyHoatDong;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 1 quản lý tài khoản khách hàng:
 *  - Admin đặt lại MK (MK tạm 1 lần, remember_token mới, hủy sessions,
 *    bat_buoc_doi_mat_khau, audit không chứa MK).
 *  - Card tài khoản + cột/lọc "Có tài khoản" ở danh sách.
 *  - users.last_login_at (không đụng updated_at).
 *  - Throttle đăng nhập 5 lần sai / 15 phút theo email+IP.
 */
class QuanLyTaiKhoanKhachHangTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $thungan;
    private User $thukho;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('role', 'admin')->firstOrFail();
        $this->thungan = User::where('role', 'thungan')->firstOrFail();
        $this->thukho = User::where('role', 'thukho')->firstOrFail();
    }

    private function taoKhachCoTaiKhoan(string $email = 'khach.tk@test.vn', string $mkCu = 'MatKhauCu@123'): array
    {
        $user = User::create([
            'name' => 'Khach Tai Khoan',
            'email' => $email,
            'password' => Hash::make($mkCu),
            'role' => 'khachhang',
        ]);
        $khach = KhachHang::create([
            'user_id' => $user->id,
            'ho_ten' => 'Khach Tai Khoan',
            'so_dienthoai' => '0909'.random_int(100000, 999999),
            'diem_tichluy' => 0,
        ]);

        return [$user->fresh(), $khach->fresh()];
    }

    private function trichMatKhauTam($response): string
    {
        $this->assertMatchesRegularExpression(
            '/<code[^>]*id="mk-tam"[^>]*>([^<]+)<\/code>/',
            $response->getContent(),
            'Response phải chứa MK tạm trong thẻ code#mk-tam'
        );
        preg_match('/<code[^>]*id="mk-tam"[^>]*>([^<]+)<\/code>/', $response->getContent(), $m);

        return html_entity_decode($m[1]);
    }

    // ---------- Đặt lại mật khẩu ----------

    public function test_admin_dat_lai_mat_khau_thanh_cong(): void
    {
        [$user, $khach] = $this->taoKhachCoTaiKhoan();
        $user->forceFill(['remember_token' => 'token-cu-123'])->save();
        DB::table('sessions')->insert([
            'id' => Str::random(40), 'user_id' => $user->id,
            'ip_address' => '127.0.0.1', 'user_agent' => 'test',
            'payload' => 'x', 'last_activity' => time(),
        ]);

        $res = $this->actingAs($this->admin)
            ->post(route('khachhang.datlaimatkhau', $khach), ['ly_do_khoa' => 'Khách quên mật khẩu']);

        $res->assertOk();
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', $res->headers->get('Pragma'));

        $mkTam = $this->trichMatKhauTam($res);
        $this->assertGreaterThanOrEqual(12, strlen($mkTam));
        $this->assertDoesNotMatchRegularExpression('/[0Ol1I]/', $mkTam);

        $userMoi = $user->fresh();
        $this->assertTrue(Hash::check($mkTam, $userMoi->password));
        $this->assertFalse(Hash::check('MatKhauCu@123', $userMoi->password));
        $this->assertTrue((bool) $userMoi->bat_buoc_doi_mat_khau);
        $this->assertNotSame('token-cu-123', $userMoi->remember_token);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
    }

    public function test_mat_khau_tam_dang_nhap_duoc_va_bi_ep_doi(): void
    {
        [, $khach] = $this->taoKhachCoTaiKhoan();
        $res = $this->actingAs($this->admin)
            ->post(route('khachhang.datlaimatkhau', $khach), ['ly_do_khoa' => 'test']);
        $mkTam = $this->trichMatKhauTam($res);

        // Đăng xuất admin, đăng nhập bằng MK tạm
        auth()->logout();
        $this->post(route('auth.postdangnhap'), [
            'email' => 'khach.tk@test.vn',
            'password' => $mkTam,
        ])->assertRedirect();
        $this->assertAuthenticated();

        // Middleware ép đổi MK trên route auth: trang cá nhân redirect về doi-mat-khau
        // (storefront.home là public nên không đi qua middleware này)
        $this->get(route('profile.show'))->assertRedirect(route('doi-mat-khau'));

        // Đổi MK thành công → hết bị ép
        $this->post(route('doi-mat-khau.post'), [
            'mat_khau_hien_tai' => $mkTam,
            'mat_khau_moi' => 'MatKhauMoi@2026',
            'mat_khau_xac_nhan' => 'MatKhauMoi@2026',
        ])->assertRedirect();

        // Hết bị ép: trang cá nhân vào được
        $this->get(route('profile.show'))->assertOk();
        $this->assertFalse((bool) User::where('email', 'khach.tk@test.vn')->first()->bat_buoc_doi_mat_khau);
    }

    public function test_remember_cookie_cu_khong_con_hieu_luc(): void
    {
        [$user, $khach] = $this->taoKhachCoTaiKhoan();
        // Giả lập cookie remember cũ: recaller = id|token|hash
        // Sau reset remember_token đổi → recaller không còn khớp.
        $tokenCu = $user->remember_token ?? 'khong-co';

        $this->actingAs($this->admin)
            ->post(route('khachhang.datlaimatkhau', $khach), ['ly_do_khoa' => 'test']);

        $this->assertNotSame($tokenCu, $user->fresh()->remember_token);
    }

    public function test_mat_khau_tam_khong_lo_trong_audit_va_session(): void
    {
        [, $khach] = $this->taoKhachCoTaiKhoan();
        $res = $this->actingAs($this->admin)
            ->post(route('khachhang.datlaimatkhau', $khach), ['ly_do_khoa' => 'test']);
        $mkTam = $this->trichMatKhauTam($res);

        $log = NhatKyHoatDong::where('hanh_dong', 'dat_lai_mat_khau')->latest('id')->firstOrFail();
        $this->assertStringNotContainsString($mkTam, (string) json_encode($log->toArray()));
        // Response không phải redirect/flash → session không chứa MK
        $this->assertNull(session('mat_khau_tam'));
        $this->assertNull(session('password'));
    }

    public function test_thu_ngan_va_thu_kho_khong_duoc_reset(): void
    {
        [, $khach] = $this->taoKhachCoTaiKhoan();
        $this->actingAs($this->thungan)
            ->post(route('khachhang.datlaimatkhau', $khach), ['ly_do_khoa' => 'test'])
            ->assertForbidden();
        $this->actingAs($this->thukho)
            ->post(route('khachhang.datlaimatkhau', $khach), ['ly_do_khoa' => 'test'])
            ->assertForbidden();
        auth()->logout();
        $this->post(route('khachhang.datlaimatkhau', $khach), ['ly_do_khoa' => 'test'])
            ->assertRedirect(route('auth.dangnhap'));
    }

    public function test_tu_choi_khi_chua_co_tai_khoan(): void
    {
        $khach = KhachHang::create([
            'user_id' => null, 'ho_ten' => 'Khong TK', 'so_dienthoai' => '0900000001', 'diem_tichluy' => 0,
        ]);
        $this->actingAs($this->admin)
            ->post(route('khachhang.datlaimatkhau', $khach), ['ly_do_khoa' => 'test'])
            ->assertSessionHas('error');
    }

    public function test_tu_choi_khi_tai_khoan_bi_khoa(): void
    {
        [$user, $khach] = $this->taoKhachCoTaiKhoan();
        $user->forceFill(['khoa_luc' => now()])->save();
        $this->actingAs($this->admin)
            ->post(route('khachhang.datlaimatkhau', $khach), ['ly_do_khoa' => 'test'])
            ->assertSessionHas('error');
    }

    public function test_tu_choi_khi_ho_so_bi_khoa(): void
    {
        [, $khach] = $this->taoKhachCoTaiKhoan();
        $khach->update(['khoa_den' => now(), 'ly_do_khoa' => 'test']);
        $this->actingAs($this->admin)
            ->post(route('khachhang.datlaimatkhau', $khach), ['ly_do_khoa' => 'test'])
            ->assertSessionHas('error');
    }

    public function test_tu_choi_tai_khoan_nhan_vien(): void
    {
        // Ép liên kết hồ sơ khách tới user role thungan (bất thường nhưng
        // server phải tự chặn, không tin dữ liệu).
        $khach = KhachHang::create([
            'user_id' => $this->thungan->id, 'ho_ten' => 'Lien Ket La',
            'so_dienthoai' => '0900000002', 'diem_tichluy' => 0,
        ]);
        $this->actingAs($this->admin)
            ->post(route('khachhang.datlaimatkhau', $khach), ['ly_do_khoa' => 'test'])
            ->assertSessionHas('error');
    }

    public function test_thieu_ly_do_bi_chan(): void
    {
        [, $khach] = $this->taoKhachCoTaiKhoan();
        $this->actingAs($this->admin)
            ->post(route('khachhang.datlaimatkhau', $khach), [])
            ->assertSessionHasErrors('ly_do_khoa');
        $this->actingAs($this->admin)
            ->post(route('khachhang.datlaimatkhau', $khach), ['ly_do_khoa' => ''])
            ->assertSessionHasErrors('ly_do_khoa');
    }

    // ---------- Danh sách & chi tiết ----------

    public function test_danh_sach_loc_theo_tai_khoan_va_hien_cot(): void
    {
        $this->taoKhachCoTaiKhoan('khach.co.tk@test.vn');
        KhachHang::create([
            'user_id' => null, 'ho_ten' => 'Khach Khong TK',
            'so_dienthoai' => '0900000003', 'diem_tichluy' => 0,
        ]);

        $resCo = $this->actingAs($this->admin)->get(route('khachhang.danhsach', ['tai_khoan' => 'co']));
        $resCo->assertOk()->assertSee('Khach Tai Khoan')->assertDontSee('Khach Khong TK');

        $resKhong = $this->actingAs($this->admin)->get(route('khachhang.danhsach', ['tai_khoan' => 'khong']));
        $resKhong->assertOk()->assertSee('Khach Khong TK')->assertDontSee('Khach Tai Khoan');
    }

    public function test_chi_tiet_hien_thong_tin_tai_khoan(): void
    {
        [$user, $khach] = $this->taoKhachCoTaiKhoan('khach.chitiet@test.vn');
        $res = $this->actingAs($this->admin)->get(route('khachhang.chitiet', $khach));
        $res->assertOk()
            ->assertSee('khach.chitiet@test.vn')
            ->assertSee('Đăng nhập gần nhất')
            ->assertSee('Tạo MK tạm');
    }

    // ---------- last_login_at ----------

    public function test_dang_nhap_cap_nhat_last_login_at_khong_doi_updated_at(): void
    {
        $user = User::create([
            'name' => 'Khach LL', 'email' => 'khach.ll@test.vn',
            'password' => Hash::make('MatKhau@123'), 'role' => 'khachhang',
        ]);
        DB::table('users')->where('id', $user->id)->update(['updated_at' => '2020-01-01 00:00:00']);

        $this->assertNull($user->fresh()->last_login_at);

        $this->post(route('auth.postdangnhap'), [
            'email' => 'khach.ll@test.vn',
            'password' => 'MatKhau@123',
        ])->assertRedirect();

        $userMoi = $user->fresh();
        $this->assertNotNull($userMoi->last_login_at);
        $this->assertSame('2020-01-01 00:00:00', $userMoi->updated_at->format('Y-m-d H:i:s'));
    }

    // ---------- Throttle đăng nhập ----------

    public function test_sai_5_lan_bi_khoa_15_phut_ke_ca_dung_mat_khau(): void
    {
        $this->taoKhachCoTaiKhoan('khach.throttle@test.vn', 'MatKhauDung@123');

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('auth.postdangnhap'), [
                'email' => 'khach.throttle@test.vn',
                'password' => 'sai-mat-khau',
            ])->assertRedirect()->assertSessionHasErrors('email');
        }

        // Lần 6: đúng mật khẩu vẫn bị chặn, thông báo chung có thời gian chờ
        $res = $this->post(route('auth.postdangnhap'), [
            'email' => 'khach.throttle@test.vn',
            'password' => 'MatKhauDung@123',
        ]);
        $res->assertRedirect()->assertSessionHasErrors('email');
        $this->assertGuest();
        $loi = session('errors')->get('email')[0] ?? '';
        $this->assertStringContainsString('thử lại sau', $loi);
    }

    public function test_throttle_ap_cho_email_khong_ton_tai(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('auth.postdangnhap'), [
                'email' => 'khong.ton.tai@test.vn',
                'password' => 'sai',
            ]);
        }
        $this->post(route('auth.postdangnhap'), [
            'email' => 'khong.ton.tai@test.vn',
            'password' => 'sai',
        ])->assertSessionHasErrors('email');
    }

    public function test_dang_nhap_dung_xoa_bo_dem(): void
    {
        $this->taoKhachCoTaiKhoan('khach.clear@test.vn', 'MatKhauDung@123');

        // 1 lần sai (bộ đếm = 1) rồi đăng nhập đúng → phải xóa bộ đếm.
        // Nếu không xóa, bộ đếm = 1 + 5 lần sai sau = đã chạm ngưỡng sớm hơn.
        $this->post(route('auth.postdangnhap'), [
            'email' => 'khach.clear@test.vn',
            'password' => 'sai',
        ]);
        $this->post(route('auth.postdangnhap'), [
            'email' => 'khach.clear@test.vn',
            'password' => 'MatKhauDung@123',
        ])->assertRedirect();
        $this->assertAuthenticated();
        auth()->logout();

        // 5 lần sai liên tiếp: lần thứ 5 vẫn phải báo "không đúng" (chưa chạm
        // ngưỡng), chứng tỏ bộ đếm đã được xóa sau login thành công.
        for ($i = 0; $i < 4; $i++) {
            $this->post(route('auth.postdangnhap'), [
                'email' => 'khach.clear@test.vn',
                'password' => 'sai',
            ])->assertSessionHasErrors('email');
        }
        $res5 = $this->post(route('auth.postdangnhap'), [
            'email' => 'khach.clear@test.vn',
            'password' => 'sai',
        ]);
        $this->assertStringContainsString(
            'không đúng',
            session('errors')->get('email')[0] ?? '',
            'Lần sai thứ 5 sau khi clear phải báo sai MK, không phải throttle'
        );

        // Lần thứ 6 mới bị chặn bởi throttle
        $this->post(route('auth.postdangnhap'), [
            'email' => 'khach.clear@test.vn',
            'password' => 'sai',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
