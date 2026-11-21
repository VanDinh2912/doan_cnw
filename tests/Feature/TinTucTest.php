<?php

namespace Tests\Feature;

use App\Models\BaiViet;
use App\Models\BinhLuanBaiViet;
use App\Models\ChuDe;
use App\Models\User;
use Database\Seeders\TinTucSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TinTucTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::firstOrCreate(
            ['email' => 'admin.tintuc@test.local'],
            ['name' => 'Admin Tin Tuc', 'password' => bcrypt('12345678'), 'role' => 'admin']
        );
    }

    private function khach(): User
    {
        return User::firstOrCreate(
            ['email' => 'khach.tintuc@test.local'],
            ['name' => 'Khach Hang', 'password' => bcrypt('12345678'), 'role' => 'khachhang']
        );
    }

    private function chuDe(string $ten = 'Khuyến mãi', string $slug = 'khuyen-mai'): ChuDe
    {
        return ChuDe::create(['ten_chude' => $ten, 'slug' => $slug]);
    }

    private function baiViet(ChuDe $chuDe, array $ghiDe = []): BaiViet
    {
        return BaiViet::create(array_merge([
            'topic_id' => $chuDe->id,
            'user_id' => $this->admin()->id,
            'tieu_de' => 'Bài viết thử nghiệm',
            'slug' => 'bai-viet-thu-nghiem',
            'tom_tat' => 'Tóm tắt thử nghiệm',
            'noi_dung' => '<p>Nội dung thử nghiệm</p>',
            'kiem_duyet' => true,
            'kich_hoat' => true,
        ], $ghiDe));
    }

    // ==================== Phía khách (guest) ====================

    public function test_guest_xem_danh_sach_chi_thay_bai_da_dang(): void
    {
        $chuDe = $this->chuDe();
        $baiHien = $this->baiViet($chuDe);
        $this->baiViet($chuDe, ['tieu_de' => 'Bài chưa duyệt', 'slug' => 'bai-chua-duyet', 'kiem_duyet' => false]);
        $this->baiViet($chuDe, ['tieu_de' => 'Bài đang ẩn', 'slug' => 'bai-dang-an', 'kich_hoat' => false]);

        $this->get('/bai-viet')
            ->assertOk()
            ->assertSee($baiHien->tieu_de)
            ->assertDontSee('Bài chưa duyệt')
            ->assertDontSee('Bài đang ẩn');
    }

    public function test_loc_bai_theo_chu_de(): void
    {
        $chuDe1 = $this->chuDe();
        $chuDe2 = $this->chuDe('Thủ thuật', 'thu-thuat');
        $bai1 = $this->baiViet($chuDe1);
        $bai2 = $this->baiViet($chuDe2, ['tieu_de' => 'Bài thủ thuật', 'slug' => 'bai-thu-thuat']);

        $this->get('/bai-viet/'.$chuDe2->slug)
            ->assertOk()
            ->assertSee($bai2->tieu_de)
            ->assertDontSee($bai1->tieu_de);
    }

    public function test_chi_tiet_tang_luot_xem(): void
    {
        $chuDe = $this->chuDe();
        $baiViet = $this->baiViet($chuDe);

        $this->get("/bai-viet/{$chuDe->slug}/{$baiViet->slug}")->assertOk();
        $this->get("/bai-viet/{$chuDe->slug}/{$baiViet->slug}")->assertOk();

        $this->assertDatabaseHas('news_posts', ['id' => $baiViet->id, 'luot_xem' => 2]);
    }

    public function test_bai_chua_duyet_hoac_an_khong_xem_duoc_chi_tiet(): void
    {
        $chuDe = $this->chuDe();
        $chuaDuyet = $this->baiViet($chuDe, ['slug' => 'chua-duyet', 'kiem_duyet' => false]);
        $dangAn = $this->baiViet($chuDe, ['slug' => 'dang-an', 'kich_hoat' => false]);

        $this->get("/bai-viet/{$chuDe->slug}/{$chuaDuyet->slug}")->assertNotFound();
        $this->get("/bai-viet/{$chuDe->slug}/{$dangAn->slug}")->assertNotFound();
    }

    public function test_chi_tiet_chi_hien_binh_luan_da_duyet_va_dang_hien(): void
    {
        $chuDe = $this->chuDe();
        $baiViet = $this->baiViet($chuDe);
        $khach = $this->khach();

        BinhLuanBaiViet::create(['post_id' => $baiViet->id, 'user_id' => $khach->id, 'noi_dung' => 'Bình luận được hiện', 'kiem_duyet' => true]);
        BinhLuanBaiViet::create(['post_id' => $baiViet->id, 'user_id' => $khach->id, 'noi_dung' => 'Bình luận chờ duyệt', 'kiem_duyet' => false]);
        BinhLuanBaiViet::create(['post_id' => $baiViet->id, 'user_id' => $khach->id, 'noi_dung' => 'Bình luận bị ẩn', 'kiem_duyet' => true, 'kich_hoat' => false]);

        $this->get("/bai-viet/{$chuDe->slug}/{$baiViet->slug}")
            ->assertOk()
            ->assertSee('Bình luận được hiện')
            ->assertDontSee('Bình luận chờ duyệt')
            ->assertDontSee('Bình luận bị ẩn');
    }

    public function test_guest_gui_binh_luan_bi_chuyen_ve_dang_nhap(): void
    {
        $chuDe = $this->chuDe();
        $baiViet = $this->baiViet($chuDe);

        $this->post("/bai-viet/{$chuDe->slug}/{$baiViet->slug}/binh-luan", ['noi_dung' => 'Xin chào'])
            ->assertRedirect(route('auth.dangnhap'));

        $this->assertDatabaseCount('news_comments', 0);
    }

    public function test_khach_gui_binh_luan_hien_ngay(): void
    {
        $chuDe = $this->chuDe();
        $baiViet = $this->baiViet($chuDe);
        $khach = $this->khach();

        $this->actingAs($khach)
            ->post("/bai-viet/{$chuDe->slug}/{$baiViet->slug}/binh-luan", ['noi_dung' => 'Bài viết hay quá'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Bình luận của bạn đã được đăng.');

        $this->assertDatabaseHas('news_comments', [
            'post_id' => $baiViet->id,
            'user_id' => $khach->id,
            'noi_dung' => 'Bài viết hay quá',
            'kiem_duyet' => true,
            'kich_hoat' => true,
        ]);

        // Gui trung noi dung lien tiep bi chan
        $this->actingAs($khach)
            ->post("/bai-viet/{$chuDe->slug}/{$baiViet->slug}/binh-luan", ['noi_dung' => 'Bài viết hay quá'])
            ->assertSessionHas('error');
        $this->assertDatabaseCount('news_comments', 1);
    }

    public function test_binh_luan_vuot_1000_ky_tu_bi_tu_choi(): void
    {
        $chuDe = $this->chuDe();
        $baiViet = $this->baiViet($chuDe);

        $this->actingAs($this->khach())
            ->post("/bai-viet/{$chuDe->slug}/{$baiViet->slug}/binh-luan", ['noi_dung' => str_repeat('a', 1001)])
            ->assertSessionHasErrors('noi_dung');

        $this->assertDatabaseCount('news_comments', 0);
    }

    // ==================== Admin: chủ đề ====================

    public function test_admin_them_chu_de_tu_sinh_slug_khong_dau(): void
    {
        $this->actingAs($this->admin())
            ->post('/tin-tuc/chu-de/them', ['ten_chude' => 'Tin công nghệ mới', 'mo_ta' => 'Mô tả'])
            ->assertRedirect(route('chude.danhsach'));

        $this->assertDatabaseHas('news_topics', ['ten_chude' => 'Tin công nghệ mới', 'slug' => 'tin-cong-nghe-moi']);
    }

    public function test_admin_them_chu_de_trung_ten_bi_tu_choi(): void
    {
        $this->chuDe();

        $this->actingAs($this->admin())
            ->post('/tin-tuc/chu-de/them', ['ten_chude' => 'Khuyến mãi'])
            ->assertSessionHasErrors('ten_chude');
    }

    public function test_admin_sua_chu_de(): void
    {
        $chuDe = $this->chuDe();

        $this->actingAs($this->admin())
            ->put("/tin-tuc/chu-de/sua/{$chuDe->id}", ['ten_chude' => 'Ưu đãi hot', 'mo_ta' => null])
            ->assertRedirect(route('chude.danhsach'));

        $this->assertDatabaseHas('news_topics', ['id' => $chuDe->id, 'ten_chude' => 'Ưu đãi hot', 'slug' => 'uu-dai-hot']);
    }

    public function test_admin_xoa_chu_de_dang_co_bai_bi_chan(): void
    {
        $chuDe = $this->chuDe();
        $this->baiViet($chuDe);

        $this->actingAs($this->admin())
            ->delete("/tin-tuc/chu-de/xoa/{$chuDe->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('news_topics', ['id' => $chuDe->id]);
    }

    public function test_admin_xoa_chu_de_trong(): void
    {
        $chuDe = $this->chuDe();

        $this->actingAs($this->admin())
            ->delete("/tin-tuc/chu-de/xoa/{$chuDe->id}")
            ->assertRedirect(route('chude.danhsach'));

        $this->assertDatabaseMissing('news_topics', ['id' => $chuDe->id]);
    }

    // ==================== Admin: bài viết ====================

    public function test_admin_them_bai_viet(): void
    {
        $chuDe = $this->chuDe();
        $admin = $this->admin();

        $this->actingAs($admin)->post('/tin-tuc/bai-viet/them', [
            'topic_id' => $chuDe->id,
            'tieu_de' => 'Bài mới từ admin',
            'tom_tat' => 'Tóm tắt',
            'noi_dung' => '<p>Nội dung <strong>đậm</strong></p>',
            'kiem_duyet' => '1',
            'kich_hoat' => '1',
        ])->assertRedirect(route('baiviet.danhsach'));

        $this->assertDatabaseHas('news_posts', [
            'tieu_de' => 'Bài mới từ admin',
            'slug' => 'bai-moi-tu-admin',
            'user_id' => $admin->id,
            'kiem_duyet' => true,
        ]);
    }

    public function test_noi_dung_bi_loc_xss_truoc_khi_luu(): void
    {
        $chuDe = $this->chuDe();

        $this->actingAs($this->admin())->post('/tin-tuc/bai-viet/them', [
            'topic_id' => $chuDe->id,
            'tieu_de' => 'Bài chứa mã độc',
            'noi_dung' => '<p>An toàn</p><script>alert(1)</script><img src=x onerror=alert(2)><iframe src="https://evil.test"></iframe><a href="javascript:alert(3)">link</a>',
            'kiem_duyet' => '1',
            'kich_hoat' => '1',
        ])->assertRedirect(route('baiviet.danhsach'));

        $baiViet = BaiViet::where('tieu_de', 'Bài chứa mã độc')->firstOrFail();
        $this->assertStringNotContainsString('<script', $baiViet->noi_dung);
        $this->assertStringNotContainsString('onerror', $baiViet->noi_dung);
        $this->assertStringNotContainsString('<iframe', $baiViet->noi_dung);
        $this->assertStringNotContainsString('javascript:', $baiViet->noi_dung);
        $this->assertStringContainsString('<p>An toàn</p>', $baiViet->noi_dung);

        $this->get("/bai-viet/{$chuDe->slug}/{$baiViet->slug}")
            ->assertOk()
            ->assertDontSee('alert(1)', false)
            ->assertDontSee('onerror', false);
    }

    public function test_admin_upload_anh_dai_dien(): void
    {
        Storage::fake('public');
        $chuDe = $this->chuDe();

        $this->actingAs($this->admin())->post('/tin-tuc/bai-viet/them', [
            'topic_id' => $chuDe->id,
            'tieu_de' => 'Bài có ảnh',
            'noi_dung' => '<p>Có ảnh</p>',
            'hinh_anh' => UploadedFile::fake()->image('anh-dai-dien.jpg', 200, 200),
            'kiem_duyet' => '1',
            'kich_hoat' => '1',
        ])->assertRedirect(route('baiviet.danhsach'));

        $baiViet = BaiViet::where('tieu_de', 'Bài có ảnh')->firstOrFail();
        $this->assertNotNull($baiViet->hinh_anh);
        Storage::disk('public')->assertExists($baiViet->hinh_anh);
    }

    public function test_anh_dai_dien_sai_dinh_dang_bi_tu_choi(): void
    {
        Storage::fake('public');
        $chuDe = $this->chuDe();

        $this->actingAs($this->admin())->post('/tin-tuc/bai-viet/them', [
            'topic_id' => $chuDe->id,
            'tieu_de' => 'Bài ảnh lỗi',
            'noi_dung' => '<p>Ảnh lỗi</p>',
            'hinh_anh' => UploadedFile::fake()->create('tai-lieu.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('hinh_anh');

        $this->assertDatabaseMissing('news_posts', ['tieu_de' => 'Bài ảnh lỗi']);
    }

    public function test_admin_sua_bai_viet_giu_slug_khi_khong_doi_tieu_de(): void
    {
        $chuDe = $this->chuDe();
        $baiViet = $this->baiViet($chuDe);

        $this->actingAs($this->admin())->put("/tin-tuc/bai-viet/sua/{$baiViet->id}", [
            'topic_id' => $chuDe->id,
            'tieu_de' => $baiViet->tieu_de,
            'noi_dung' => '<p>Nội dung mới</p>',
            'kiem_duyet' => '1',
            'kich_hoat' => '1',
        ])->assertRedirect(route('baiviet.danhsach'));

        $this->assertDatabaseHas('news_posts', ['id' => $baiViet->id, 'slug' => 'bai-viet-thu-nghiem', 'noi_dung' => '<p>Nội dung mới</p>']);
    }

    public function test_admin_duyet_va_an_bai_viet(): void
    {
        $chuDe = $this->chuDe();
        $baiViet = $this->baiViet($chuDe, ['kiem_duyet' => false]);
        $admin = $this->admin();

        $this->actingAs($admin)->post("/tin-tuc/bai-viet/duyet/{$baiViet->id}");
        $this->assertTrue($baiViet->fresh()->kiem_duyet);

        $this->actingAs($admin)->post("/tin-tuc/bai-viet/kich-hoat/{$baiViet->id}");
        $this->assertFalse($baiViet->fresh()->kich_hoat);

        // Bài bị ẩn không còn trên danh sách guest.
        $this->get('/bai-viet')->assertDontSee($baiViet->tieu_de);
    }

    public function test_admin_xoa_bai_viet_xoa_luon_binh_luan(): void
    {
        $chuDe = $this->chuDe();
        $baiViet = $this->baiViet($chuDe);
        $binhLuan = BinhLuanBaiViet::create([
            'post_id' => $baiViet->id,
            'user_id' => $this->khach()->id,
            'noi_dung' => 'Sẽ bị xóa theo',
        ]);

        $this->actingAs($this->admin())
            ->delete("/tin-tuc/bai-viet/xoa/{$baiViet->id}")
            ->assertRedirect(route('baiviet.danhsach'));

        $this->assertDatabaseMissing('news_posts', ['id' => $baiViet->id]);
        $this->assertDatabaseMissing('news_comments', ['id' => $binhLuan->id]);
    }

    // ==================== Admin: bình luận ====================

    public function test_admin_duyet_binh_luan(): void
    {
        $chuDe = $this->chuDe();
        $baiViet = $this->baiViet($chuDe);
        $binhLuan = BinhLuanBaiViet::create([
            'post_id' => $baiViet->id,
            'user_id' => $this->khach()->id,
            'noi_dung' => 'Chờ duyệt',
        ]);

        $this->actingAs($this->admin())
            ->post("/tin-tuc/binh-luan/duyet/{$binhLuan->id}")
            ->assertSessionHas('success');

        $this->assertTrue($binhLuan->fresh()->kiem_duyet);
        $this->get("/bai-viet/{$chuDe->slug}/{$baiViet->slug}")->assertSee('Chờ duyệt');
    }

    public function test_admin_an_binh_luan(): void
    {
        $chuDe = $this->chuDe();
        $baiViet = $this->baiViet($chuDe);
        $binhLuan = BinhLuanBaiViet::create([
            'post_id' => $baiViet->id,
            'user_id' => $this->khach()->id,
            'noi_dung' => 'Sẽ bị ẩn',
            'kiem_duyet' => true,
        ]);

        $this->actingAs($this->admin())->post("/tin-tuc/binh-luan/an/{$binhLuan->id}");

        $this->assertFalse($binhLuan->fresh()->kich_hoat);
        $this->get("/bai-viet/{$chuDe->slug}/{$baiViet->slug}")->assertDontSee('Sẽ bị ẩn');
    }

    public function test_admin_xoa_binh_luan(): void
    {
        $chuDe = $this->chuDe();
        $baiViet = $this->baiViet($chuDe);
        $binhLuan = BinhLuanBaiViet::create([
            'post_id' => $baiViet->id,
            'user_id' => $this->khach()->id,
            'noi_dung' => 'Xóa tôi',
        ]);

        $this->actingAs($this->admin())->delete("/tin-tuc/binh-luan/xoa/{$binhLuan->id}");

        $this->assertDatabaseMissing('news_comments', ['id' => $binhLuan->id]);
    }

    // ==================== Phân quyền ====================

    public function test_nhan_vien_thu_ngan_thu_kho_khong_quan_tri_duoc_tin_tuc(): void
    {
        foreach (['thungan', 'thukho'] as $role) {
            $nhanVien = User::create([
                'name' => 'NV '.$role,
                'email' => $role.'.tintuc@test.local',
                'password' => bcrypt('12345678'),
                'role' => $role,
            ]);

            $this->actingAs($nhanVien)->get('/tin-tuc/bai-viet')->assertForbidden();
            $this->actingAs($nhanVien)->get('/tin-tuc/chu-de')->assertForbidden();
            $this->actingAs($nhanVien)->get('/tin-tuc/binh-luan')->assertForbidden();
        }
    }

    public function test_khach_hang_bi_chuyen_khoi_trang_quan_tri_tin_tuc(): void
    {
        $this->actingAs($this->khach())
            ->get('/tin-tuc/bai-viet')
            ->assertRedirect(route('storefront.home'));
    }

    public function test_guest_khong_vao_duoc_quan_tri_tin_tuc(): void
    {
        $this->get('/tin-tuc/bai-viet')->assertRedirect(route('auth.dangnhap'));
    }

    // ==================== Seeder ====================

    public function test_seeder_idempotent_chay_hai_lan_khong_nhan_doi(): void
    {
        $this->seed(TinTucSeeder::class);
        $soChuDe = ChuDe::count();
        $soBai = BaiViet::count();
        $this->assertGreaterThan(0, $soChuDe);
        $this->assertGreaterThan(0, $soBai);

        $this->seed(TinTucSeeder::class);
        $this->assertSame($soChuDe, ChuDe::count());
        $this->assertSame($soBai, BaiViet::count());
    }
}