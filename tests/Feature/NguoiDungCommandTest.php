<?php

namespace Tests\Feature;

use App\Models\NhatKyHoatDong;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NguoiDungCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_tao_tai_khoan_voi_mat_khau_nhap_an(): void
    {
        $this->artisan('nguoidung:tao', ['email' => 'moi@example.com', 'vai_tro' => 'thungan'])
            ->expectsQuestion('Mật khẩu (tối thiểu 8 ký tự)', 'mat-khau-123')
            ->expectsQuestion('Nhập lại mật khẩu', 'mat-khau-123')
            ->assertExitCode(0);

        $user = User::where('email', 'moi@example.com')->firstOrFail();
        $this->assertSame('thungan', $user->role);
        $this->assertSame('moi', $user->name);
        $this->assertTrue(Hash::check('mat-khau-123', $user->password));
        $this->assertDatabaseHas('activity_logs', [
            'hanh_dong' => 'tao_nguoi_dung',
            'doi_tuong_id' => $user->id,
        ]);
    }

    public function test_tao_tai_khoan_tu_choi_vai_tro_khong_hop_le(): void
    {
        $this->artisan('nguoidung:tao', ['email' => 'moi@example.com', 'vai_tro' => 'khachhang'])
            ->expectsOutputToContain('Vai trò phải là một trong: admin, thungan, thukho')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'moi@example.com']);
    }

    public function test_tao_tai_khoan_tu_choi_email_trung(): void
    {
        $this->artisan('nguoidung:tao', ['email' => 'admin@example.com', 'vai_tro' => 'admin'])
            ->expectsOutputToContain('đã được sử dụng')
            ->assertExitCode(1);
    }

    public function test_tao_tai_khoan_tu_choi_mat_khau_qua_ngan(): void
    {
        $this->artisan('nguoidung:tao', ['email' => 'moi@example.com', 'vai_tro' => 'thukho'])
            ->expectsQuestion('Mật khẩu (tối thiểu 8 ký tự)', 'ngan')
            ->expectsOutputToContain('ít nhất 8 ký tự')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'moi@example.com']);
    }

    public function test_tao_tai_khoan_tu_choi_hai_lan_nhap_khong_khop(): void
    {
        $this->artisan('nguoidung:tao', ['email' => 'moi@example.com', 'vai_tro' => 'admin'])
            ->expectsQuestion('Mật khẩu (tối thiểu 8 ký tự)', 'mat-khau-123')
            ->expectsQuestion('Nhập lại mật khẩu', 'mat-khau-456')
            ->expectsOutputToContain('không khớp')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'moi@example.com']);
    }

    public function test_mat_khau_khong_the_truyen_qua_tham_so_dong_lenh(): void
    {
        $lenh = $this->app[Kernel::class]->all()['nguoidung:tao'];
        $dinhNghia = $lenh->getDefinition();

        $this->assertFalse($dinhNghia->hasOption('password'));
        $this->assertFalse($dinhNghia->hasOption('mat-khau'));
        $this->assertFalse($dinhNghia->hasArgument('password'));
    }

    public function test_doi_mat_khau_cap_nhat_hash_nhat_ky_va_xoa_canh_bao(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $hashCu = $admin->password;

        $this->artisan('nguoidung:doi-matkhau', ['email' => 'admin@example.com'])
            ->expectsQuestion('Mật khẩu mới (tối thiểu 8 ký tự)', 'mat-khau-moi-123')
            ->expectsQuestion('Nhập lại mật khẩu mới', 'mat-khau-moi-123')
            ->assertExitCode(0);

        $admin->refresh();
        $this->assertNotSame($hashCu, $admin->password);
        $this->assertTrue(Hash::check('mat-khau-moi-123', $admin->password));
        $this->assertFalse(Hash::check('123456', $admin->password));
        $this->assertDatabaseHas('activity_logs', [
            'hanh_dong' => 'doi_mat_khau',
            'doi_tuong_id' => $admin->id,
        ]);
        $this->assertSame(1, NhatKyHoatDong::where('hanh_dong', 'doi_mat_khau')->count());
    }

    public function test_doi_mat_khau_tu_choi_email_khong_ton_tai(): void
    {
        $this->artisan('nguoidung:doi-matkhau', ['email' => 'khong-co@example.com'])
            ->expectsOutputToContain('Không tìm thấy tài khoản')
            ->assertExitCode(1);
    }

    public function test_doi_mat_khau_tu_choi_hai_lan_nhap_khong_khop(): void
    {
        $this->artisan('nguoidung:doi-matkhau', ['email' => 'admin@example.com'])
            ->expectsQuestion('Mật khẩu mới (tối thiểu 8 ký tự)', 'mat-khau-moi-123')
            ->expectsQuestion('Nhập lại mật khẩu mới', 'khac-hoan-toan')
            ->expectsOutputToContain('không khớp')
            ->assertExitCode(1);

        $this->assertTrue(Hash::check('123456', User::where('email', 'admin@example.com')->firstOrFail()->password));
    }
}