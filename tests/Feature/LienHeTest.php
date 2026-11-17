<?php

namespace Tests\Feature;

use App\Models\KhachHang;
use App\Models\LienHe;
use App\Models\NhatKyHoatDong;
use App\Models\User;
use App\Services\ThongBaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LienHeTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $override = []): array
    {
        return array_merge([
            'ho_ten' => 'Nguyễn Văn Test',
            'so_dien_thoai' => '0912345678',
            'email' => '',
            'loai' => 'lien_he',
            'vi_tri' => '',
            'chu_de' => 'Hỏi về bảo hành',
            'noi_dung' => 'Sản phẩm của tôi bị lỗi, cần hỗ trợ bảo hành.',
        ], $override);
    }

    public function test_guest_gui_lien_he_thanh_cong_va_luu_db(): void
    {
        $this->post(route('lien-he.gui'), $this->payload())->assertRedirect(route('lien-he.cam-on'));

        $this->assertDatabaseHas('lien_he', [
            'ho_ten' => 'Nguyễn Văn Test',
            'so_dien_thoai' => '0912345678',
            'loai' => 'lien_he',
            'chu_de' => 'Hỏi về bảo hành',
            'trang_thai' => 'moi',
            'user_id' => null,
        ]);
        $this->assertDatabaseCount('lien_he', 1);
    }

    public function test_chi_can_mot_trong_sdt_hoac_email(): void
    {
        $this->post(route('lien-he.gui'), $this->payload(['so_dien_thoai' => '', 'email' => '']))
            ->assertSessionHasErrors();
        $this->assertDatabaseCount('lien_he', 0);

        // Chỉ email cũng được.
        $this->post(route('lien-he.gui'), $this->payload(['so_dien_thoai' => '', 'email' => 'Test@Example.com ']))
            ->assertRedirect(route('lien-he.cam-on'));
        $this->assertDatabaseHas('lien_he', ['email' => 'test@example.com', 'so_dien_thoai' => null]);
    }

    public function test_sdt_sai_dinh_dang_bi_tu_choi_va_chuan_hoa_khi_hop_le(): void
    {
        $this->post(route('lien-he.gui'), $this->payload(['so_dien_thoai' => '12345']))
            ->assertSessionHasErrors('so_dien_thoai');
        $this->assertDatabaseCount('lien_he', 0);

        // Khoảng trắng/gạch được loại bỏ trước khi validate.
        $this->post(route('lien-he.gui'), $this->payload(['so_dien_thoai' => '0912 345 678']))
            ->assertRedirect(route('lien-he.cam-on'));
        $this->assertDatabaseHas('lien_he', ['so_dien_thoai' => '0912345678']);
    }

    public function test_thieu_truong_bat_buoc_bi_tu_choi(): void
    {
        $this->post(route('lien-he.gui'), $this->payload(['ho_ten' => '', 'chu_de' => '', 'noi_dung' => '']))
            ->assertSessionHasErrors(['ho_ten', 'chu_de', 'noi_dung']);
        $this->assertDatabaseCount('lien_he', 0);
    }

    public function test_noi_dung_qua_dai_bi_tu_choi(): void
    {
        $this->post(route('lien-he.gui'), $this->payload(['noi_dung' => str_repeat('a', 2001)]))
            ->assertSessionHasErrors('noi_dung');
        $this->assertDatabaseCount('lien_he', 0);
    }

    public function test_loai_la_bi_tu_choi_nhung_vi_tri_la_bi_bo_qua(): void
    {
        $this->post(route('lien-he.gui'), $this->payload(['loai' => 'spam']))
            ->assertSessionHasErrors('loai');
        $this->assertDatabaseCount('lien_he', 0);

        // vitri lạ → bản ghi vẫn tạo, vi_tri = null.
        $this->post(route('lien-he.gui'), $this->payload(['loai' => 'ung_tuyen', 'vi_tri' => 'hackervn']))
            ->assertRedirect(route('lien-he.cam-on'));
        $this->assertDatabaseHas('lien_he', ['loai' => 'ung_tuyen', 'vi_tri' => null]);
    }

    public function test_honeypot_bi_chan_khong_tao_ban_ghi(): void
    {
        $this->post(route('lien-he.gui'), $this->payload(['truong_phu' => 'http://spam.example']))
            ->assertRedirect();
        $this->assertDatabaseCount('lien_he', 0);
    }

    public function test_gui_qua_5_lan_bi_throttle(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('lien-he.gui'), $this->payload(['chu_de' => "Tin $i"]))->assertRedirect();
        }
        $this->post(route('lien-he.gui'), $this->payload())->assertStatus(429);
        $this->assertDatabaseCount('lien_he', 5);
    }

    public function test_khach_dang_nhap_duoc_gan_user_id_va_prefill(): void
    {
        $user = User::factory()->create(['role' => 'khachhang', 'name' => 'Khách Test', 'email' => 'khach@test.vn']);
        KhachHang::create([
            'user_id' => $user->id, 'ho_ten' => 'Khách Test',
            'so_dienthoai' => '0987654321', 'diem_tichluy' => 0,
        ]);

        $this->actingAs($user)->get(route('lien-he.form'))
            ->assertOk()
            ->assertSee('value="Khách Test"', false)
            ->assertSee('value="0987654321"', false)
            ->assertSee('value="khach@test.vn"', false);

        $this->actingAs($user)->post(route('lien-he.gui'), $this->payload())
            ->assertRedirect(route('lien-he.cam-on'));
        $this->assertDatabaseHas('lien_he', ['user_id' => $user->id]);
    }

    public function test_khach_bi_khoa_van_gui_duoc_lien_he(): void
    {
        $user = User::factory()->create(['role' => 'khachhang']);
        KhachHang::create([
            'user_id' => $user->id, 'ho_ten' => 'Khách Bị Khóa',
            'so_dienthoai' => '0977000111', 'diem_tichluy' => 0,
            'khoa_den' => now(), 'ly_do_khoa' => 'Vi phạm',
        ]);

        $this->actingAs($user)->post(route('lien-he.gui'), $this->payload(['loai' => 'khieu_nai']))
            ->assertRedirect(route('lien-he.cam-on'));
        $this->assertDatabaseHas('lien_he', ['loai' => 'khieu_nai', 'user_id' => $user->id]);
    }

    public function test_telegram_loi_khong_chan_luu_lien_he(): void
    {
        $this->mock(ThongBaoService::class, function ($mock): void {
            $mock->shouldReceive('thongBaoLienHeMoi')->andThrow(new \RuntimeException('Telegram sập'));
        });

        $this->post(route('lien-he.gui'), $this->payload())->assertRedirect(route('lien-he.cam-on'));
        $this->assertDatabaseCount('lien_he', 1);
    }

    public function test_telegram_khong_chua_pii(): void
    {
        Config::set('pos.telegram_bot_token', 'test-token');
        Config::set('pos.telegram_chat_id', '123');

        $guiDi = null;
        Http::fake(function ($request) use (&$guiDi) {
            $guiDi = $request->data();

            return Http::response(['ok' => true]);
        });

        $lienHe = LienHe::create([
            'ho_ten' => 'Trần Thị Bí Mật', 'so_dien_thoai' => '0901234567',
            'email' => 'bi.mat@example.com', 'loai' => 'khieu_nai',
            'chu_de' => 'Khiếu nại <b>đơn</b>', 'noi_dung' => 'Nội dung nhạy cảm',
            'trang_thai' => 'moi',
        ]);

        app(ThongBaoService::class)->thongBaoLienHeMoi($lienHe);

        $this->assertNotNull($guiDi);
        $text = (string) ($guiDi['text'] ?? '');
        $this->assertStringContainsString('Khiếu nại', $text);
        $this->assertStringContainsString('LIÊN HỆ MỚI #'.$lienHe->id, $text);
        // Không lộ PII.
        $this->assertStringNotContainsString('Trần Thị Bí Mật', $text);
        $this->assertStringNotContainsString('0901234567', $text);
        $this->assertStringNotContainsString('bi.mat@example.com', $text);
        $this->assertStringNotContainsString('Nội dung nhạy cảm', $text);
        // Chủ đề escape HTML (parse_mode HTML).
        $this->assertStringContainsString('&lt;b&gt;', $text);
    }

    public function test_admin_xem_danh_sach_va_chitiet_thuong_khach_thi_403(): void
    {
        $lienHe = LienHe::create([
            'ho_ten' => 'A', 'so_dien_thoai' => '0911111111', 'loai' => 'gop_y',
            'chu_de' => 'Góp ý nhỏ', 'noi_dung' => 'Nội dung', 'trang_thai' => 'moi',
        ]);

        // Guest chưa đăng nhập → redirect về login.
        $this->get(route('lienhe.danhsach'))->assertRedirect();

        $admin = User::factory()->create(['role' => 'admin']);
        $thuNgan = User::factory()->create(['role' => 'thungan']);
        $thuKho = User::factory()->create(['role' => 'thukho']);

        $this->actingAs($admin)->get(route('lienhe.danhsach'))->assertOk()->assertSee('Góp ý nhỏ');
        $this->actingAs($admin)->get(route('lienhe.chitiet', $lienHe))->assertOk()->assertSee('Nội dung');
        $this->actingAs($thuNgan)->get(route('lienhe.danhsach'))->assertOk();
        $this->actingAs($thuKho)->get(route('lienhe.danhsach'))->assertForbidden();
        $this->actingAs($thuKho)->get(route('lienhe.chitiet', $lienHe))->assertForbidden();
        $this->actingAs($thuKho)->post(route('lienhe.capnhat', $lienHe), ['trang_thai' => 'da_xu_ly'])->assertForbidden();
    }

    public function test_admin_loc_theo_loai_va_trang_thai(): void
    {
        LienHe::create(['ho_ten' => 'A', 'loai' => 'gop_y', 'chu_de' => 'GY1', 'noi_dung' => 'x', 'trang_thai' => 'moi']);
        LienHe::create(['ho_ten' => 'B', 'loai' => 'khieu_nai', 'chu_de' => 'KN1', 'noi_dung' => 'x', 'trang_thai' => 'da_xu_ly']);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('lienhe.danhsach', ['loai' => 'khieu_nai']))
            ->assertOk()->assertSee('KN1')->assertDontSee('GY1');
        $this->actingAs($admin)->get(route('lienhe.danhsach', ['trang_thai' => 'moi']))
            ->assertOk()->assertSee('GY1')->assertDontSee('KN1');
    }

    public function test_admin_doi_trang_thai_va_ghi_chu_co_audit_log(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lienHe = LienHe::create([
            'ho_ten' => 'A', 'so_dien_thoai' => '0911111111', 'loai' => 'lien_he',
            'chu_de' => 'Hỏi', 'noi_dung' => 'Nội dung', 'trang_thai' => 'moi',
        ]);

        $this->actingAs($admin)->post(route('lienhe.capnhat', $lienHe), [
            'trang_thai' => 'dang_xu_ly',
            'ghi_chu_noibo' => 'Đã gọi lại khách',
        ])->assertRedirect();

        $lienHe->refresh();
        $this->assertSame('dang_xu_ly', $lienHe->trang_thai);
        $this->assertSame('Đã gọi lại khách', $lienHe->ghi_chu_noibo);

        $this->assertDatabaseHas('activity_logs', [
            'hanh_dong' => 'cap_nhat_lien_he',
            'doi_tuong' => 'lien_he',
            'doi_tuong_id' => $lienHe->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'hanh_dong' => 'ghi_chu_lien_he',
            'doi_tuong' => 'lien_he',
            'doi_tuong_id' => $lienHe->id,
        ]);

        // Audit không chứa PII (SĐT/email/nội dung khách).
        $moTa = (string) NhatKyHoatDong::where('doi_tuong', 'lien_he')->latest('id')->value('mo_ta');
        $this->assertStringNotContainsString('0911111111', $moTa);
        $this->assertStringNotContainsString('Hỏi', $moTa);
    }

    public function test_admin_doi_trang_thai_khong_hop_le_bi_tu_choi(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lienHe = LienHe::create([
            'ho_ten' => 'A', 'loai' => 'lien_he', 'chu_de' => 'X', 'noi_dung' => 'Y', 'trang_thai' => 'moi',
        ]);

        $this->actingAs($admin)->post(route('lienhe.capnhat', $lienHe), ['trang_thai' => 'xxx'])
            ->assertSessionHasErrors('trang_thai');
        $this->assertSame('moi', $lienHe->fresh()->trang_thai);
    }

    public function test_noi_dung_script_bi_escape_trong_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        LienHe::create([
            'ho_ten' => '<script>alert(1)</script>', 'loai' => 'lien_he',
            'chu_de' => 'Test XSS', 'noi_dung' => '<script>alert("xss")</script>',
            'trang_thai' => 'moi',
        ]);

        $response = $this->actingAs($admin)->get(route('lienhe.danhsach'));
        $response->assertOk();
        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee('&lt;script&gt;', false);
    }

    public function test_trang_tinh_hop_le_200_va_slug_la_404(): void
    {
        $this->get(route('trang-tinh', 'gioi-thieu'))->assertOk()->assertSee('Giới thiệu');
        $this->get(route('trang-tinh', 'chinh-sach-bao-hanh'))->assertOk()->assertSee('12 tháng');
        $this->get(route('trang-tinh', 'slug-khong-ton-tai-xyz'))->assertNotFound();
    }

    public function test_tuyen_dung_hien_vi_tri_va_link_ung_tuyen_prefill(): void
    {
        $this->get(route('tuyen-dung'))->assertOk()
            ->assertSee('Nhân viên bán hàng')
            ->assertSee('vitri=nhan-vien-ban-hang', false);

        $this->get(route('lien-he.form', ['loai' => 'ung_tuyen', 'vitri' => 'nhan-vien-ban-hang']))
            ->assertOk()
            ->assertSee('value="ung_tuyen" selected', false);

        // vitri lạ bị bỏ qua → không selected.
        $this->get(route('lien-he.form', ['loai' => 'ung_tuyen', 'vitri' => 'xxx']))
            ->assertOk();
    }

    public function test_cam_on_khong_hien_lai_du_lieu_da_gui(): void
    {
        $this->post(route('lien-he.gui'), $this->payload(['chu_de' => 'ChuDeBiMat123']))->assertRedirect();
        $this->get(route('lien-he.cam-on'))
            ->assertOk()
            ->assertSee('Đã gửi thành công')
            ->assertDontSee('ChuDeBiMat123');
    }
}
