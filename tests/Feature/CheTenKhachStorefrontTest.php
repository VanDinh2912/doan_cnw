<?php

namespace Tests\Feature;

use App\Models\BaiViet;
use App\Models\BinhLuanBaiViet;
use App\Models\ChuDe;
use App\Models\KhachHang;
use App\Models\User;
use App\Support\ChePII;
use App\Support\HoSoXacThuc;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A1: tên/SĐT/email khách đăng nhập phải luôn ở dạng che trên storefront,
 * kể cả sau khi xác thực lại hồ sơ. Nguồn duy nhất: TenHienThi -> ChePII.
 */
class CheTenKhachStorefrontTest extends TestCase
{
    use RefreshDatabase;

    private const TEN_THAT = 'Nguyễn Văn Hiển Thị';

    private const SDT_THAT = '0911222333';

    private const EMAIL_THAT = 'hien.thi@example.com';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'khachhang',
            'name' => self::TEN_THAT,
            'email' => self::EMAIL_THAT,
        ]);
        KhachHang::create([
            'user_id' => $this->user->id,
            'ho_ten' => self::TEN_THAT,
            'so_dienthoai' => self::SDT_THAT,
            'dia_chi' => '123 Đường Riêng Tư, Quận 1, TP.HCM',
            'diem_tichluy' => 0,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function cacTrangStorefront(): array
    {
        return [
            route('storefront.home'),
            route('lien-he.form'),
            route('tuyen-dung'),
            route('trang-tinh', 'gioi-thieu'),
            route('tintuc.danhsach'),
            route('storefront.cart.index'),
            route('don-hang.tracuu'),
            route('profile.show'),
        ];
    }

    private function assertKhongLoPII(\Illuminate\Testing\TestResponse $response): void
    {
        $response->assertDontSee(self::TEN_THAT)
            ->assertDontSee(self::SDT_THAT)
            ->assertDontSee(self::EMAIL_THAT)
            ->assertDontSee('Đường Riêng Tư');
    }

    public function test_moi_trang_storefront_khong_lo_ten_that_va_hien_ten_da_che(): void
    {
        $tenChe = ChePII::hoTen(self::TEN_THAT);

        foreach ($this->cacTrangStorefront() as $url) {
            $response = $this->actingAs($this->user)->get($url);
            $response->assertOk();
            $this->assertKhongLoPII($response);
            // Tên đã che phải xuất hiện ở header (icon-btn mang tên).
            $response->assertSee($tenChe, false);
        }
    }

    public function test_header_van_che_sau_khi_xac_thuc_ho_so(): void
    {
        $tenChe = ChePII::hoTen(self::TEN_THAT);

        // Mở cửa sổ xác thực hồ sơ.
        $this->actingAs($this->user)
            ->withSession([HoSoXacThuc::KHOA => ['user_id' => $this->user->id, 'luc' => time()]])
            ->get(route('storefront.home'))
            ->assertOk()
            ->assertSee($tenChe, false);

        // Và ở mọi trang khác nữa — header không phụ thuộc route hay cửa sổ xác thực.
        foreach ([route('lien-he.form'), route('tintuc.danhsach'), route('storefront.cart.index')] as $url) {
            $response = $this->actingAs($this->user)
                ->withSession([HoSoXacThuc::KHOA => ['user_id' => $this->user->id, 'luc' => time()]])
                ->get($url);
            $this->assertKhongLoPII($response);
            $response->assertSee($tenChe, false);
        }
    }

    public function test_ten_tac_gia_binh_luan_duoc_che(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $chuDe = ChuDe::create(['ten_chude' => 'Tin test', 'slug' => 'tin-test-'.uniqid()]);
        $baiViet = BaiViet::create([
            'topic_id' => $chuDe->id,
            'user_id' => $admin->id,
            'tieu_de' => 'Bài viết thử',
            'slug' => 'bai-viet-thu-'.uniqid(),
            'tom_tat' => 'Tóm tắt',
            'noi_dung' => '<p>Nội dung</p>',
            'kiem_duyet' => true,
            'kich_hoat' => true,
        ]);
        BinhLuanBaiViet::create([
            'post_id' => $baiViet->id,
            'user_id' => $this->user->id,
            'noi_dung' => 'Bình luận thử',
            'kiem_duyet' => true,
            'kich_hoat' => true,
        ]);

        $response = $this->actingAs($this->user)->get(route('tintuc.chitiet', [$chuDe->slug, $baiViet->slug]));
        $response->assertOk();
        $this->assertKhongLoPII($response);
        $response->assertSee(ChePII::hoTen(self::TEN_THAT), false);
    }

    public function test_form_lien_he_de_trong_va_gui_van_gan_user_id(): void
    {
        $this->actingAs($this->user)->get(route('lien-he.form'))
            ->assertOk()
            ->assertSee('id="ho_ten" name="ho_ten" type="text" value=""', false)
            ->assertSee('id="so_dien_thoai" name="so_dien_thoai" type="tel" value=""', false);

        $this->actingAs($this->user)->post(route('lien-he.gui'), [
            'ho_ten' => 'Tên Khách Nhập Lại',
            'so_dien_thoai' => '0999888777',
            'loai' => 'lien_he',
            'chu_de' => 'Hỏi bảo hành',
            'noi_dung' => 'Cần hỗ trợ.',
        ])->assertRedirect(route('lien-he.cam-on'));

        $this->assertDatabaseHas('lien_he', [
            'ho_ten' => 'Tên Khách Nhập Lại',
            'user_id' => $this->user->id,
        ]);

        // Trang cảm ơn không in lại dữ liệu đã gửi.
        $this->get(route('lien-he.cam-on'))
            ->assertOk()
            ->assertDontSee('Tên Khách Nhập Lại')
            ->assertDontSee('0999888777');
    }

    public function test_avatar_khong_dung_chu_cai_dau_ten_that(): void
    {
        // Header dùng icon chung (fa-user), không render chữ cái đầu tên.
        $response = $this->actingAs($this->user)->get(route('storefront.home'));
        $response->assertDontSee('>'.mb_substr(self::TEN_THAT, 0, 1, 'UTF-8').'<', false);
    }
}
