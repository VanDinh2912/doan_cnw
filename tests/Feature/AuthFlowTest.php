<?php

namespace Tests\Feature;

use App\Models\KhachHang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role = 'admin'): User
    {
        return User::create([
            'name' => 'Staff ' . $role,
            'email' => $role . '@test.local',
            'password' => bcrypt('12345678'),
            'role' => $role,
        ]);
    }

    public function test_guest_can_open_homepage_without_redirect_to_login(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(config('storefront.ten_cua_hang'))
            ->assertSee('Phụ kiện nổi bật')
            ->assertSee('Đăng nhập')
            ->assertSee('Đăng ký');
    }

    public function test_registration_creates_khachhang_and_links_customer(): void
    {
        $response = $this->post('/dang-ky', [
            'name' => 'Nguyen Van A',
            'email' => 'khach.a@test.local',
            'so_dienthoai' => '0912345678',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            // Attempt privilege escalation; must be ignored.
            'role' => 'admin',
        ]);

        $response->assertRedirect(route('storefront.home'));
        $this->assertAuthenticated();

        $user = User::where('email', 'khach.a@test.local')->firstOrFail();
        $this->assertSame('khachhang', $user->role);

        $khachHang = KhachHang::where('so_dienthoai', '0912345678')->firstOrFail();
        $this->assertSame($user->id, $khachHang->user_id);
    }

    public function test_registration_links_existing_walkin_customer(): void
    {
        // Thay đổi hành vi (Stage 3-4, Bước 2): đăng ký KHÔNG còn tự động
        // liên kết hồ sơ khách trùng SĐT — trước đây ai biết SĐT đều chiếm
        // được hồ sơ (đơn hàng, điểm, địa chỉ). Giờ tài khoản tạo thành công
        // nhưng user_id vẫn NULL; nhân viên gán liên kết thủ công.
        $existing = KhachHang::create([
            'ho_ten' => 'Walk-in',
            'so_dienthoai' => '0987654321',
            'dia_chi' => null,
            'diem_tichluy' => 50,
        ]);

        $this->post('/dang-ky', [
            'name' => 'Nguyen Van B',
            'email' => 'khach.b@test.local',
            'so_dienthoai' => '0987654321',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('storefront.home'));

        $userB = User::where('email', 'khach.b@test.local')->firstOrFail();
        $this->assertAuthenticatedAs($userB);

        // Hồ sơ walk-in KHÔNG bị chiếm: user_id vẫn null, điểm giữ nguyên.
        $existing->refresh();
        $this->assertNull($existing->user_id);
        $this->assertSame(50, $existing->diem_tichluy);
        $this->assertNull($userB->khachHang);
    }

    public function test_registration_rejects_phone_already_linked(): void
    {
        $other = User::create([
            'name' => 'Other',
            'email' => 'other@test.local',
            'password' => bcrypt('12345678'),
            'role' => 'khachhang',
        ]);
        KhachHang::create([
            'user_id' => $other->id,
            'ho_ten' => 'Other',
            'so_dienthoai' => '0901112222',
            'dia_chi' => null,
            'diem_tichluy' => 0,
        ]);

        $this->from('/dang-ky')
            ->post('/dang-ky', [
                'name' => 'Attacker',
                'email' => 'attacker@test.local',
                'so_dienthoai' => '0901112222',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertRedirect('/dang-ky')
            ->assertSessionHasErrors('so_dienthoai');

        $this->assertDatabaseMissing('users', ['email' => 'attacker@test.local']);
    }

    public function test_khachhang_login_redirects_to_storefront(): void
    {
        $user = User::create([
            'name' => 'Khach',
            'email' => 'khach@test.local',
            'password' => bcrypt('password123'),
            'role' => 'khachhang',
        ]);

        $this->post('/dangnhap', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertRedirect(route('storefront.home'));
    }

    public function test_staff_login_redirects_to_dashboard(): void
    {
        $user = $this->staff('admin');

        $this->post('/dangnhap', [
            'email' => $user->email,
            'password' => '12345678',
        ])->assertRedirect(route('tongquan'));
    }

    public function test_khachhang_cannot_access_staff_dashboard(): void
    {
        $user = User::create([
            'name' => 'Khach',
            'email' => 'khach2@test.local',
            'password' => bcrypt('password123'),
            'role' => 'khachhang',
        ]);

        $this->actingAs($user)
            ->get('/tongquan')
            ->assertRedirect(route('storefront.home'))
            ->assertSessionHas('error', 'Trang này dành cho nhân viên.');
    }
}