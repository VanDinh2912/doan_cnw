<?php

namespace Tests\Feature;

use App\Models\SanPham;
use App\Models\User;
use App\Support\KiemTraSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Bước 1 — sửa guard /taikhoan: trước đây controller kiểm tra
 * array_key_exists('khoa_luc', (new User())->getAttributes()) — model rỗng
 * luôn trả mảng rỗng nên /taikhoan redirect 302 vô hạn kể cả khi DB đã
 * migrate. Giờ dùng KiemTraSchema (Schema::hasColumn cache tĩnh).
 *
 * File này cũng phủ: admin factory không refresh, tài khoản khóa bị đăng
 * xuất, cờ đổi mật khẩu chuyển hướng đúng một lần, thứ tự middleware
 * (khóa trước, cờ đổi MK sau), và kịch bản DB thiếu cột Stage 3.
 */
class TaiKhoanGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $thungan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->thungan = User::factory()->create(['role' => 'thungan']);
    }

    public function test_admin_factory_khong_refresh_vao_duoc_taikhoan(): void
    {
        // User do factory tạo, KHÔNG refresh: attributes có thể thiếu cột
        // mặc định DB — guard phải kiểm tra schema thật, không đoán qua model.
        $this->actingAs($this->admin)
            ->get('/taikhoan')
            ->assertOk();
    }

    public function test_admin_bi_khoa_bi_dang_xuat(): void
    {
        $admin2 = User::factory()->create(['role' => 'admin']);
        $admin2->update(['khoa_luc' => now()]);

        $response = $this->actingAs($admin2)->get('/taikhoan');

        $response->assertRedirect(route('auth.dangnhap'));
        $response->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_co_doi_mat_khau_chuyen_huong_dung_mot_lan_khong_vong_lap(): void
    {
        $this->thungan->update(['bat_buoc_doi_mat_khau' => true]);

        // Trang thường → bị đá về đổi mật khẩu.
        $this->actingAs($this->thungan)
            ->get('/tongquan')
            ->assertRedirect(route('doi-mat-khau'));

        // Chính trang đổi mật khẩu vẫn vào được (không vòng lặp).
        $this->actingAs($this->thungan)
            ->get('/doi-mat-khau')
            ->assertOk();

        // Đổi xong cờ tắt → vào trang thường bình thường.
        $this->actingAs($this->thungan)
            ->post('/doi-mat-khau', [
                'mat_khau_hien_tai' => 'password',
                'mat_khau_moi' => 'MatKhauMoi123!',
                'mat_khau_xac_nhan' => 'MatKhauMoi123!',
            ])
            ->assertRedirect();

        $this->actingAs($this->thungan->fresh())
            ->get('/tongquan')
            ->assertOk();
    }

    public function test_thu_tu_middleware_khoa_truoc_co_doi_mat_khau(): void
    {
        // User vừa bị khóa vừa có cờ đổi MK: khoa.luc phải chạy trước và
        // đăng xuất, KHÔNG để cờ đổi MK "cứu" phiên bị khóa.
        $user = User::factory()->create(['role' => 'thungan']);
        $user->update([
            'khoa_luc' => now(),
            'bat_buoc_doi_mat_khau' => true,
        ]);

        $response = $this->actingAs($user)->get('/tongquan');

        $response->assertRedirect(route('auth.dangnhap'));
        $this->assertGuest();
    }

    // ============ DB THIẾU CỘT STAGE 3 ============

    private function goBoCotStage3(): void
    {
        foreach (['khoa_luc', 'bat_buoc_doi_mat_khau'] as $cot) {
            DB::statement('ALTER TABLE users DROP COLUMN '.$cot);
        }
        KiemTraSchema::datLai();

        $this->assertFalse(Schema::hasColumn('users', 'khoa_luc'));
        $this->assertFalse(Schema::hasColumn('users', 'bat_buoc_doi_mat_khau'));
    }

    public function test_thieu_cot_stage3_dang_nhap_pos_scheduler_van_chay(): void
    {
        $this->seed();
        $this->goBoCotStage3();

        // Đăng nhập vẫn hoạt động (middleware coi thuộc tính vắng = không cờ).
        $this->post('/dangnhap', [
            'email' => 'thungan@example.com',
            'password' => '123456',
        ])->assertRedirect();
        $this->assertAuthenticated();

        // POS tiền mặt vẫn bán được.
        $cashier = User::where('email', 'thungan@example.com')->firstOrFail();
        $sanPham = SanPham::where('sku', 'CS-0001')->firstOrFail();
        $sanPham->update(['soluong' => 10, 'trang_thai' => true]);

        $res = $this->actingAs($cashier)
            ->withSession([
                'gio_hang' => [
                    (string) $sanPham->id => [
                        'id' => $sanPham->id,
                        'sku' => $sanPham->sku,
                        'ten_sanpham' => $sanPham->ten_sanpham,
                        'gia_ban' => (float) $sanPham->gia_ban,
                        'soluong' => 1,
                        'ton_kho' => $sanPham->soluong,
                    ],
                ],
            ])
            ->post(route('donhang.thanhtoan'), [
                'giam_gia_phantram' => 0,
                'tien_khach_dua' => 10000000,
                'phuong_thuc' => 'tienmat',
            ]);

        $res->assertRedirect();
        $this->assertSame(9, (int) $sanPham->fresh()->soluong);

        // Scheduler hủy đơn quá hạn vẫn chạy không lỗi.
        $this->artisan('donhang:huy-qua-han')->assertExitCode(0);
    }

    public function test_thieu_cot_stage3_taikhoan_bao_can_migration_khong_loi_500(): void
    {
        $this->seed();
        $this->goBoCotStage3();

        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $response = $this->actingAs($admin)->get('/taikhoan');

        // Redirect một lần về trang trước kèm thông báo migration —
        // KHÔNG 500, KHÔNG redirect vòng về chính /taikhoan.
        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('migration', (string) session('error'));
        $this->assertNotSame(route('taikhoan.danhsach'), $response->headers->get('Location'));
    }
}
