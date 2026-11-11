<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CanhBaoMatKhauMacDinh;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BaoMatDangNhapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_dang_nhap_sai_qua_nguong_bi_chan_429(): void
    {
        for ($lan = 1; $lan <= 10; $lan++) {
            $this->post(route('auth.postdangnhap'), [
                'email' => 'admin@example.com',
                'password' => 'sai-mat-khau',
            ])->assertRedirect();
        }

        $this->post(route('auth.postdangnhap'), [
            'email' => 'admin@example.com',
            'password' => 'sai-mat-khau',
        ])->assertTooManyRequests();

        $this->assertGuest();
    }

    public function test_dang_nhap_dung_van_vao_duoc_khi_chua_het_han_muc(): void
    {
        $this->post(route('auth.postdangnhap'), [
            'email' => 'admin@example.com',
            'password' => 'sai-mot-lan',
        ])->assertRedirect();

        $this->post(route('auth.postdangnhap'), [
            'email' => 'admin@example.com',
            'password' => '123456',
        ])->assertRedirect(route('tongquan'));

        $this->assertAuthenticatedAs(User::where('email', 'admin@example.com')->firstOrFail());
    }

    public function test_trang_dang_nhap_canh_bao_mat_khau_mac_dinh(): void
    {
        $this->get(route('auth.dangnhap'))
            ->assertOk()
            ->assertSee('mật khẩu mặc định của dữ liệu mẫu', false);
    }

    public function test_dashboard_admin_liet_ke_tai_khoan_con_mat_khau_mac_dinh(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($admin)->get(route('tongquan'))
            ->assertOk()
            ->assertSee('tài khoản còn dùng mật khẩu mặc định', false)
            ->assertSee('admin@example.com')
            ->assertSee('thungan@example.com')
            ->assertSee('thukho@example.com');
    }

    public function test_thu_ngan_khong_thay_danh_sach_tai_khoan_mat_khau_mac_dinh(): void
    {
        $thuNgan = User::where('email', 'thungan@example.com')->firstOrFail();

        $this->actingAs($thuNgan)->get(route('tongquan'))
            ->assertOk()
            ->assertDontSee('tài khoản còn dùng mật khẩu mặc định', false);
    }

    public function test_canh_bao_tat_khi_moi_tai_khoan_da_doi_mat_khau(): void
    {
        $this->assertCount(3, app(CanhBaoMatKhauMacDinh::class)->danhSachEmail());

        User::query()->whereIn('role', ['admin', 'thungan', 'thukho'])->get()
            ->each(fn (User $user) => $user->update(['password' => 'mat-khau-moi-123']));
        CanhBaoMatKhauMacDinh::quenCache();

        $this->assertSame([], app(CanhBaoMatKhauMacDinh::class)->danhSachEmail());
        $this->assertFalse(app(CanhBaoMatKhauMacDinh::class)->conHieuLuc());
        $this->assertTrue(Hash::check('mat-khau-moi-123', User::where('email', 'admin@example.com')->firstOrFail()->password));
    }
}