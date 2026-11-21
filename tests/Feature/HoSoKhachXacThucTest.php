<?php

namespace Tests\Feature;

use App\Models\KhachHang;
use App\Models\NhatKyHoatDong;
use App\Models\User;
use App\Support\ChePII;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HoSoKhachXacThucTest extends TestCase
{
    use RefreshDatabase;

    private function khachHangUser(array $userAttrs = [], $khachAttrs = 'default'): User
    {
        $user = User::create(array_merge([
            'name' => 'Nguyen Van An',
            'email' => 'nguyenvana@test.local',
            'password' => bcrypt('matkhau123'),
            'role' => 'khachhang',
        ], $userAttrs));

        $khach = null;
        if ($khachAttrs === 'default') {
            $khach = KhachHang::create([
                'user_id' => $user->id,
                'ho_ten' => 'Nguyen Van An',
                'so_dienthoai' => '0901234567',
                'dia_chi' => '123 Le Loi, Phuong Ben Nghe, Quan 1, Tinh Nghe An',
            ]);
            $khach->diem_tichluy = 1500;
        } elseif (is_array($khachAttrs)) {
            $diem = $khachAttrs['diem_tichluy'] ?? 0;
            unset($khachAttrs['diem_tichluy']);
            $khach = KhachHang::create(array_merge(['user_id' => $user->id], $khachAttrs));
            $khach->diem_tichluy = (int) $diem;
        }
        $khach?->save();

        return $user->fresh();
    }

    private function xacThuc(User $user, string $matKhau = 'matkhau123', ?string $sdt = '0901234567')
    {
        return $this->actingAs($user)->post(route('profile.xacthuc'), [
            'mat_khau' => $matKhau,
            'so_dien_thoai' => $sdt,
        ]);
    }

    // ---------- Che PII ----------

    public function test_che_pii_cac_truong_hop(): void
    {
        $this->assertSame('N*** V*** A***', ChePII::hoTen('Nguyen Van An'));
        $this->assertSame('Đ*** V*** Ắ***', ChePII::hoTen('Đỗ Văn Ắng'));
        $this->assertSame('Chưa cập nhật', ChePII::hoTen(''));
        $this->assertSame('Chưa cập nhật', ChePII::hoTen(null));

        $this->assertSame('090****567', ChePII::soDienThoai('0901234567'));
        $this->assertSame('Chưa cập nhật', ChePII::soDienThoai(''));

        $this->assertSame('ng***@gmail.com', ChePII::email('nguyenvana@gmail.com'));
        $this->assertSame('ab***@test.local', ChePII::email('abc@test.local'));
        $this->assertSame('Chưa cập nhật', ChePII::email('khong-co-at'));

        $this->assertSame('***, Tinh Nghe An', ChePII::diaChi('123 Le Loi, P.1, Q.1, Tinh Nghe An'));
        $this->assertSame('Chưa cập nhật', ChePII::diaChi(''));
    }

    // ---------- Chế độ che ----------

    public function test_trang_ho_so_mac_dinh_hien_du_lieu_da_che(): void
    {
        $user = $this->khachHangUser();

        $response = $this->actingAs($user)->get(route('profile.show'));

        $response->assertOk();
        // Không chứa dữ liệu thật trong HTML.
        $response->assertDontSee('Nguyen Van An', false);
        $response->assertDontSee('0901234567', false);
        $response->assertDontSee('nguyenvana@test.local', false);
        $response->assertDontSee('Le Loi', false);
        $response->assertDontSee('Phuong Ben Nghe', false);
        // Có dữ liệu đã che (phần cuối địa chỉ vẫn hiện theo quy tắc mask).
        $response->assertSee('N*** V*** A***', false);
        $response->assertSee('090****567', false);
        $response->assertSee('ng***@test.local', false);
        $response->assertSee('***, Tinh Nghe An', false);
        // Không hiện điểm tích lũy ở chế độ che.
        $response->assertDontSee('Điểm tích lũy', false);
        // Có nút xác thực.
        $response->assertSee('Xác thực', false);
    }

    public function test_trang_lich_su_don_hang_che_pii_khi_chua_xac_thuc(): void
    {
        $user = $this->khachHangUser();

        $response = $this->actingAs($user)->get(route('profile.orders'));

        $response->assertOk();
        $response->assertDontSee('Nguyen Van An', false);
        $response->assertDontSee('0901234567', false);
        $response->assertSee('N*** V*** A***', false);
        $response->assertSee('090****567', false);
    }

    public function test_sau_xac_thuc_hien_du_lieu_day_du_va_form(): void
    {
        $user = $this->khachHangUser();

        $this->xacThuc($user)->assertRedirect(route('profile.show'));

        $response = $this->actingAs($user)->get(route('profile.show'));
        $response->assertOk();
        $response->assertSee('Nguyen Van An', false);
        $response->assertSee('0901234567', false);
        $response->assertSee('nguyenvana@test.local', false);
        $response->assertSee('Điểm tích lũy', false);
        $response->assertSee('Lưu thông tin', false);
    }

    // ---------- Xác thực ----------

    public function test_xac_thuc_sai_mat_khau(): void
    {
        $user = $this->khachHangUser();

        $this->xacThuc($user, 'sai-mat-khau')
            ->assertSessionHas('error', 'Thông tin xác thực không đúng');

        $this->actingAs($user)->get(route('profile.show'))
            ->assertDontSee('Lưu thông tin', false);
    }

    public function test_xac_thuc_sai_sdt(): void
    {
        $user = $this->khachHangUser();

        $this->xacThuc($user, 'matkhau123', '0999999999')
            ->assertSessionHas('error', 'Thông tin xác thực không đúng');
    }

    public function test_xac_thuc_thieu_mot_trong_hai(): void
    {
        $user = $this->khachHangUser();

        // Thiếu SĐT -> rơi vào lỗi chung (không lộ thiếu trường nào).
        $this->actingAs($user)->post(route('profile.xacthuc'), [
            'mat_khau' => 'matkhau123',
        ])->assertSessionHas('error', 'Thông tin xác thực không đúng');

        // Thiếu mật khẩu -> validation báo thiếu.
        $this->actingAs($user)->post(route('profile.xacthuc'), [
            'so_dien_thoai' => '0901234567',
        ])->assertSessionHasErrors(['mat_khau']);
    }

    public function test_xac_thuc_chuan_hoa_sdt_plus84_va_khoang_trang(): void
    {
        $user = $this->khachHangUser();

        // +84 901 234 567 -> 0901234567
        $this->xacThuc($user, 'matkhau123', '+84 901.234.567')
            ->assertRedirect(route('profile.show'));

        $this->actingAs($user)->get(route('profile.show'))
            ->assertSee('Lưu thông tin', false);
    }

    public function test_khoa_sau_5_lan_sai_va_dung_luc_khoa_van_bi_chan(): void
    {
        $user = $this->khachHangUser();

        for ($i = 0; $i < 5; $i++) {
            $this->xacThuc($user, 'sai')->assertSessionHas('error');
        }

        // Lần thứ 6 — kể cả đúng mật khẩu — vẫn bị chặn.
        $this->xacThuc($user, 'matkhau123', '0901234567')
            ->assertSessionHas('error');
        $response = $this->actingAs($user)->get(route('profile.show'));
        $response->assertDontSee('Lưu thông tin', false);
    }

    public function test_audit_ghi_nhan_xac_thuc_va_khong_lo_gia_tri(): void
    {
        $user = $this->khachHangUser();

        $this->xacThuc($user, 'sai')->assertSessionHas('error');
        $this->xacThuc($user)->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'hanh_dong' => 'ho_so_xac_thuc_that_bai',
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'hanh_dong' => 'ho_so_xac_thuc_thanh_cong',
            'user_id' => $user->id,
        ]);

        // Audit không chứa mật khẩu/SĐT thật.
        $logs = NhatKyHoatDong::where('user_id', $user->id)->get();
        foreach ($logs as $log) {
            $this->assertStringNotContainsString('matkhau123', json_encode($log->toArray()));
            $this->assertStringNotContainsString('0901234567', json_encode($log->toArray()));
        }
    }

    public function test_session_khong_luu_mat_khau_sdt(): void
    {
        $user = $this->khachHangUser();

        $this->xacThuc($user)->assertRedirect();

        $session = session()->all();
        $this->assertArrayNotHasKey('mat_khau', $session);
        $this->assertArrayNotHasKey('so_dien_thoai', $session);
        $this->assertArrayNotHasKey('password', $session);
    }

    // ---------- Cửa sổ xác thực & lưu ----------

    public function test_post_update_khi_chua_xac_thuc_bi_tu_choi(): void
    {
        $user = $this->khachHangUser();

        $this->actingAs($user)
            ->post(route('profile.update'), [
                'ho_ten' => 'Hack Attempt',
                'so_dienthoai' => '0901234567',
            ])
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('customers', [
            'user_id' => $user->id,
            'ho_ten' => 'Nguyen Van An',
        ]);
    }

    public function test_moi_xac_thuc_chi_cho_mot_lan_luu(): void
    {
        $user = $this->khachHangUser();

        // Lần 1: xác thực -> lưu OK -> cửa sổ bị xóa.
        $this->xacThuc($user)->assertRedirect();
        $this->actingAs($user)->post(route('profile.update'), [
            'ho_ten' => 'Nguyen Van An Moi',
            'email' => 'nguyenvana@test.local',
            'so_dienthoai' => '0901234567',
            'dia_chi' => '456 An Duong, Quan 5, Tinh Nghe An',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('customers', ['user_id' => $user->id, 'ho_ten' => 'Nguyen Van An Moi']);

        // Lần 2: POST tiếp mà không xác thực lại -> bị từ chối.
        $this->actingAs($user)->post(route('profile.update'), [
            'ho_ten' => 'Lan Hai',
            'so_dienthoai' => '0901234567',
        ])->assertSessionHas('error');

        $this->assertDatabaseHas('customers', ['user_id' => $user->id, 'ho_ten' => 'Nguyen Van An Moi']);
    }

    public function test_user_a_khong_dung_duoc_cua_so_user_b(): void
    {
        $userA = $this->khachHangUser();
        $userB = $this->khachHangUser([
            'name' => 'Tran Thi B',
            'email' => 'tranthib@test.local',
            'password' => bcrypt('matkhau456'),
        ], [
            'ho_ten' => 'Tran Thi B',
            'so_dienthoai' => '0912345678',
            'dia_chi' => null,
            'diem_tichluy' => 0,
        ]);

        // A xác thực.
        $this->xacThuc($userA)->assertRedirect();

        // B (chưa xác thực) POST update -> bị từ chối.
        $this->actingAs($userB)->post(route('profile.update'), [
            'ho_ten' => 'Boi Chiem',
            'so_dienthoai' => '0912345678',
        ])->assertSessionHas('error');

        $this->assertDatabaseHas('customers', ['user_id' => $userB->id, 'ho_ten' => 'Tran Thi B']);
    }

    public function test_cua_so_het_han_thi_post_update_bi_tu_choi(): void
    {
        $user = $this->khachHangUser();

        $this->xacThuc($user)->assertRedirect();

        // Giả lập hết hạn: đặt timestamp xa quá khứ trong session.
        session()->put('ho_so_khach_xac_thuc', [
            'user_id' => $user->id,
            'luc' => time() - 3600,
        ]);

        $this->actingAs($user)->post(route('profile.update'), [
            'ho_ten' => 'Qua Han',
            'so_dienthoai' => '0901234567',
        ])->assertSessionHas('error');
    }

    public function test_dang_xuat_xoa_cua_so(): void
    {
        $user = $this->khachHangUser();

        $this->xacThuc($user)->assertRedirect();
        $this->assertNotNull(session('ho_so_khach_xac_thuc'));

        $this->actingAs($user)->get(route('auth.dangxuat'));

        $this->assertNull(session('ho_so_khach_xac_thuc'));
    }

    public function test_doi_mat_khau_xoa_cua_so(): void
    {
        $user = $this->khachHangUser();

        $this->xacThuc($user)->assertRedirect();
        $this->assertNotNull(session('ho_so_khach_xac_thuc'));

        $this->actingAs($user)->post(route('doi-mat-khau.post'), [
            'mat_khau_hien_tai' => 'matkhau123',
            'mat_khau_moi' => 'MatKhau@456',
            'mat_khau_xac_nhan' => 'MatKhau@456',
        ]);

        $this->assertNull(session('ho_so_khach_xac_thuc'));
    }

    // ---------- Trường hợp đặc biệt ----------

    public function test_user_chua_co_ho_so_khach_chi_can_mat_khau(): void
    {
        $user = User::create([
            'name' => 'Khach Moi',
            'email' => 'khachmoi@test.local',
            'password' => bcrypt('matkhau123'),
            'role' => 'khachhang',
        ]);

        // Không có SĐT trong hồ sơ -> chỉ cần mật khẩu.
        $this->actingAs($user)->post(route('profile.xacthuc'), [
            'mat_khau' => 'matkhau123',
        ])->assertRedirect(route('profile.show'));

        $this->actingAs($user)->get(route('profile.show'))
            ->assertSee('Lưu thông tin', false);
    }

    public function test_tai_khoan_google_khong_co_mat_khau_bi_chan_ro_rang(): void
    {
        // Google-only: email_verified_at được đặt, mật khẩu là chuỗi ngẫu nhiên.
        $user = User::create([
            'name' => 'Google User',
            'email' => 'googleuser@gmail.com',
            'password' => bcrypt('random-string-khong-ai-biet-32ky-tu'),
            'role' => 'khachhang',
        ]);
        // email_verified_at không fillable — gán sau (giống flow Google thật).
        $user->email_verified_at = now();
        $user->save();
        KhachHang::create([
            'user_id' => $user->id,
            'ho_ten' => 'Google User',
            'so_dienthoai' => '0923456789',
            'dia_chi' => null,
            'diem_tichluy' => 0,
        ]);

        $this->xacThuc($user, 'bat-ky-mat-khau-nao')
            ->assertSessionHas('error',
                'Tài khoản chưa có mật khẩu, vui lòng liên hệ cửa hàng để đặt mật khẩu.');
    }

    public function test_trung_sdt_voi_ho_so_khac_bi_tu_choi_khong_lo(): void
    {
        $userA = $this->khachHangUser();
        $userB = $this->khachHangUser([
            'name' => 'User B',
            'email' => 'userb@test.local',
            'password' => bcrypt('matkhau123'),
        ], [
            'ho_ten' => 'User B',
            'so_dienthoai' => '0912345678',
            'dia_chi' => null,
            'diem_tichluy' => 0,
        ]);

        $this->xacThuc($userA)->assertRedirect();

        // A cố đổi SĐT trùng hồ sơ của B -> thông báo chung, không lộ hồ sơ B.
        $this->actingAs($userA)->post(route('profile.update'), [
            'ho_ten' => 'Nguyen Van An',
            'so_dienthoai' => '0912345678',
        ])->assertSessionHasErrors(['so_dienthoai']);

        // Thông báo không tiết lộ thông tin hồ sơ trùng.
        $errors = session('errors')?->get('so_dienthoai');
        $this->assertNotNull($errors);
        $this->assertStringNotContainsString('User B', implode(' ', $errors));
        $this->assertStringNotContainsString('userb@test.local', implode(' ', $errors));
    }

    public function test_cap_nhat_ho_so_ghi_audit_chi_ten_truong(): void
    {
        $user = $this->khachHangUser();

        $this->xacThuc($user)->assertRedirect();
        $this->actingAs($user)->post(route('profile.update'), [
            'ho_ten' => 'Ten Moi',
            'email' => 'emailmoi@test.local',
            'so_dienthoai' => '0901234567',
            'dia_chi' => '789 Duong Moi, Quan 10, Tinh Nghe An',
        ])->assertSessionHas('success');

        $log = NhatKyHoatDong::where('hanh_dong', 'cap_nhat_ho_so')
            ->where('user_id', $user->id)->latest()->first();

        $this->assertNotNull($log);
        // Không chứa giá trị cũ/mới thô trong mô tả.
        $this->assertStringNotContainsString('Ten Moi', $log->mo_ta);
        $this->assertStringNotContainsString('789 Duong Moi', $log->mo_ta);
        $this->assertStringNotContainsString('emailmoi@test.local', $log->mo_ta);
        $this->assertStringNotContainsString('0901234567', $log->mo_ta);
    }
}
