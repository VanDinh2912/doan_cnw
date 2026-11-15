<?php

namespace Tests\Feature;

use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\LoaiSanPham;
use App\Models\SanPham;
use App\Models\User;
use App\Rules\AnhSanPhamUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SanPhamAnhUrlTest extends TestCase
{
    use RefreshDatabase;

    private static int $dem = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function nguoiDung(string $role): User
    {
        return User::create([
            'name' => 'Test '.$role,
            'email' => $role.'-anh@test.local',
            'password' => bcrypt('12345678'),
            'role' => $role,
        ]);
    }

    private function sanPham(array $them = []): SanPham
    {
        self::$dem++;

        return SanPham::create(array_merge([
            'category_id' => LoaiSanPham::create(['ten_loai' => 'Loại '.self::$dem])->id,
            'sku' => 'SKU-ANH-'.self::$dem,
            'ten_sanpham' => 'Sản phẩm ảnh '.self::$dem,
            'don_vi' => 'Cái',
            'gia_nhap' => 1000,
            'gia_ban' => 2000,
            'soluong' => 10,
            'soluong_toithieu' => 5,
            'trang_thai' => true,
        ], $them));
    }

    private function duLieuForm(SanPham|string $sku, array $them = []): array
    {
        return array_merge([
            'category_id' => LoaiSanPham::firstOrCreate(['ten_loai' => 'Loại chung'])->id,
            'sku' => is_string($sku) ? $sku : $sku->sku,
            'ten_sanpham' => 'Sản phẩm test',
            'don_vi' => 'Cái',
            'gia_nhap' => 1000,
            'gia_ban' => 2000,
            'soluong' => 10,
            'soluong_toithieu' => 5,
            'trang_thai' => 1,
        ], $them);
    }

    public function test_gioi_han_do_dai_url_anh_theo_cot_that_va_khong_bao_gio_500(): void
    {
        // SQLite không ghi độ dài varchar → rule lùi về fallback 150 (an toàn,
        // trùng DB thật đang VARCHAR(150)). Đường 500 chỉ kiểm trên MySQL-copy.
        $this->assertSame(AnhSanPhamUrl::MAX_DO_DAI, AnhSanPhamUrl::gioiHanDoDai());
        $this->assertStringContainsString('150', AnhSanPhamUrl::thongBaoQuaDai());
    }

    public function test_them_san_pham_bang_url_https_hop_le(): void
    {
        $admin = $this->nguoiDung('admin');

        $this->actingAs($admin)->post(route('sanpham.postthem'), $this->duLieuForm('URL-001', [
            'hinh_anh_url' => 'https://cdn.example.com/image/abc123',
        ]))->assertRedirect(route('sanpham.danhsach'));

        $sanPham = SanPham::where('sku', 'URL-001')->firstOrFail();
        $this->assertSame('https://cdn.example.com/image/abc123', $sanPham->hinh_anh);
        $this->assertSame('https://cdn.example.com/image/abc123', $sanPham->anh_url);
    }

    public static function urlLoiProvider(): array
    {
        return [
            ['javascript:alert(1)'],
            ['data:image/png;base64,AAAA'],
            ['http://cdn.example.com/a.png'],
            ['ftp://host/a.png'],
            ['//evil.com/a.png'],
            ['https://user:pass@host/a.png'],
            ['https://host/a b.png'],
            ['https://cdn.example.com/'.str_repeat('a', 232)],
            [['https://cdn.example.com/a.png']],
        ];
    }

    #[DataProvider('urlLoiProvider')]
    public function test_them_san_pham_tu_choi_url_loi(mixed $url): void
    {
        $admin = $this->nguoiDung('admin');

        $this->from(route('sanpham.them'))
            ->actingAs($admin)
            ->post(route('sanpham.postthem'), $this->duLieuForm('URL-LOI', [
                'hinh_anh_url' => $url,
            ]))
            ->assertRedirect(route('sanpham.them'))
            ->assertSessionHasErrors('hinh_anh_url');

        $this->assertDatabaseMissing('products', ['sku' => 'URL-LOI']);
    }

    public function test_tai_tep_anh_van_hoat_dong_nhu_cu(): void
    {
        Storage::fake('public');
        $admin = $this->nguoiDung('admin');

        $this->actingAs($admin)->post(route('sanpham.postthem'), $this->duLieuForm('FILE-001', [
            'hinh_anh' => UploadedFile::fake()->image('anh.jpg', 100, 100),
        ]))->assertRedirect();

        $sanPham = SanPham::where('sku', 'FILE-001')->firstOrFail();
        $this->assertStringStartsWith('sanpham/', $sanPham->hinh_anh);
        Storage::disk('public')->assertExists($sanPham->hinh_anh);
        $this->assertSame(Storage::disk('public')->url($sanPham->hinh_anh), $sanPham->anh_url);
    }

    public function test_gui_ca_tep_va_url_thi_uu_tien_tep(): void
    {
        Storage::fake('public');
        $admin = $this->nguoiDung('admin');

        $this->actingAs($admin)->post(route('sanpham.postthem'), $this->duLieuForm('BOTH-001', [
            'hinh_anh' => UploadedFile::fake()->image('anh.jpg', 100, 100),
            'hinh_anh_url' => 'https://cdn.example.com/a.png',
        ]))->assertRedirect();

        $sanPham = SanPham::where('sku', 'BOTH-001')->firstOrFail();
        $this->assertStringStartsWith('sanpham/', $sanPham->hinh_anh);
        $this->assertStringContainsString('ưu tiên', session('success'));
    }

    public function test_doi_tep_sang_url_xoa_tep_cu(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('sanpham/cu.jpg', 'x');
        $admin = $this->nguoiDung('admin');
        $sanPham = $this->sanPham(['hinh_anh' => 'sanpham/cu.jpg']);

        $this->actingAs($admin)->put(route('sanpham.postsua', $sanPham), $this->duLieuForm($sanPham, [
            'hinh_anh_url' => 'https://cdn.example.com/moi.png',
        ]))->assertRedirect();

        $this->assertSame('https://cdn.example.com/moi.png', $sanPham->fresh()->hinh_anh);
        Storage::disk('public')->assertMissing('sanpham/cu.jpg');
    }

    public function test_doi_url_sang_tep(): void
    {
        Storage::fake('public');
        $admin = $this->nguoiDung('admin');
        $sanPham = $this->sanPham(['hinh_anh' => 'https://cdn.example.com/cu.png']);

        $this->actingAs($admin)->put(route('sanpham.postsua', $sanPham), $this->duLieuForm($sanPham, [
            'hinh_anh' => UploadedFile::fake()->image('moi.jpg', 100, 100),
        ]))->assertRedirect();

        $moi = $sanPham->fresh()->hinh_anh;
        $this->assertStringStartsWith('sanpham/', $moi);
        Storage::disk('public')->assertExists($moi);
    }

    public function test_doi_url_sang_url_khong_xoa_gi_tren_disk(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('sanpham/giu-lai.jpg', 'x');
        $admin = $this->nguoiDung('admin');
        $sanPham = $this->sanPham(['hinh_anh' => 'https://cdn.example.com/cu.png']);

        $this->actingAs($admin)->put(route('sanpham.postsua', $sanPham), $this->duLieuForm($sanPham, [
            'hinh_anh_url' => 'https://cdn.example.com/moi.png',
        ]))->assertRedirect();

        $this->assertSame('https://cdn.example.com/moi.png', $sanPham->fresh()->hinh_anh);
        Storage::disk('public')->assertExists('sanpham/giu-lai.jpg');
    }

    public function test_sua_khong_gui_truong_anh_thi_giu_nguyen(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('sanpham/giu.jpg', 'x');
        $admin = $this->nguoiDung('admin');
        $sanPham = $this->sanPham(['hinh_anh' => 'sanpham/giu.jpg']);

        $this->actingAs($admin)->put(route('sanpham.postsua', $sanPham), $this->duLieuForm($sanPham, [
            'ten_sanpham' => 'Tên mới',
        ]))->assertRedirect();

        $this->assertSame('sanpham/giu.jpg', $sanPham->fresh()->hinh_anh);
        Storage::disk('public')->assertExists('sanpham/giu.jpg');
    }

    public function test_nut_xoa_anh_go_tep_va_dat_null(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('sanpham/xoa.jpg', 'x');
        $admin = $this->nguoiDung('admin');
        $sanPham = $this->sanPham(['hinh_anh' => 'sanpham/xoa.jpg']);

        $this->actingAs($admin)->put(route('sanpham.postsua', $sanPham), $this->duLieuForm($sanPham, [
            'xoa_anh' => 1,
        ]))->assertRedirect();

        $sanPhamMoi = $sanPham->fresh();
        $this->assertNull($sanPhamMoi->hinh_anh);
        $this->assertSame(asset('img/no-image.svg'), $sanPhamMoi->anh_url);
        Storage::disk('public')->assertMissing('sanpham/xoa.jpg');
    }

    public function test_cac_trang_render_img_voi_url_va_escape_dung(): void
    {
        $admin = $this->nguoiDung('admin');
        $url = 'https://cdn.example.com/a.png?a=1&b=2';
        $sanPham = $this->sanPham(['hinh_anh' => $url]);

        $srcEscaped = 'src="https://cdn.example.com/a.png?a=1&amp;b=2"';

        $this->actingAs($admin)->get(route('sanpham.danhsach'))
            ->assertOk()->assertSee($srcEscaped, false);

        $this->get(route('storefront.home'))->assertOk()->assertSee($srcEscaped, false);
        $this->get(route('storefront.product', $sanPham))->assertOk()->assertSee($srcEscaped, false);
        $this->get(route('storefront.category', $sanPham->loaiSanPham))->assertOk()->assertSee($srcEscaped, false);
        $this->get(route('storefront.search', ['q' => $sanPham->ten_sanpham]))->assertOk()->assertSee($srcEscaped, false);

        $this->get(route('storefront.cart.add', $sanPham));
        $this->get(route('storefront.cart.index'))->assertOk()->assertSee($srcEscaped, false);
    }

    public function test_url_chua_ky_tu_la_van_duoc_escape_trong_view(): void
    {
        $admin = $this->nguoiDung('admin');
        $sanPham = $this->sanPham();
        // Dữ liệu bẩn giả lập (không qua validate): view vẫn phải escape.
        $sanPham->forceFill(['hinh_anh' => 'https://x.com/a.png" onload="alert(1)'])->save();

        $this->actingAs($admin)->get(route('sanpham.danhsach'))
            ->assertOk()
            ->assertDontSee('onload="alert(1)"', false)
            ->assertSee('&quot; onload=&quot;alert(1)', false);
    }

    public function test_pos_timkiem_tra_anh_url(): void
    {
        $thungan = $this->nguoiDung('thungan');
        $sanPham = $this->sanPham(['hinh_anh' => 'https://cdn.example.com/pos.png']);

        $response = $this->actingAs($thungan)
            ->getJson(route('sanpham.timkiem', ['q' => $sanPham->sku]));

        $response->assertOk();
        $this->assertSame('https://cdn.example.com/pos.png', $response->json('0.anh_url'));
    }

    public function test_thu_ngan_khong_duoc_them_san_pham(): void
    {
        $thungan = $this->nguoiDung('thungan');

        $this->actingAs($thungan)->post(route('sanpham.postthem'), $this->duLieuForm('ROLE-001'))
            ->assertForbidden();
    }

    public function test_thu_kho_duoc_them_san_pham_voi_url(): void
    {
        $thukho = $this->nguoiDung('thukho');

        $this->actingAs($thukho)->post(route('sanpham.postthem'), $this->duLieuForm('ROLE-002', [
            'hinh_anh_url' => 'https://cdn.example.com/kho.png',
        ]))->assertRedirect();

        $this->assertDatabaseHas('products', ['sku' => 'ROLE-002', 'hinh_anh' => 'https://cdn.example.com/kho.png']);
    }

    public function test_khach_hang_khong_vao_duoc_quan_ly_san_pham(): void
    {
        $khach = $this->nguoiDung('khachhang');

        $this->actingAs($khach)->get(route('sanpham.danhsach'))
            ->assertRedirect(route('storefront.home'));
    }

    public function test_xoa_san_pham_anh_url_khong_loi_va_khong_xoa_tep(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('sanpham/giu-lai.jpg', 'x');
        $admin = $this->nguoiDung('admin');
        $sanPham = $this->sanPham(['hinh_anh' => 'https://cdn.example.com/xoa.png']);

        $this->actingAs($admin)->delete(route('sanpham.xoa', $sanPham))
            ->assertRedirect(route('sanpham.danhsach'));

        $this->assertDatabaseMissing('products', ['id' => $sanPham->id]);
        Storage::disk('public')->assertExists('sanpham/giu-lai.jpg');
    }

    public function test_xoa_san_pham_da_co_giao_dich_bi_chan_khong_500(): void
    {
        $admin = $this->nguoiDung('admin');
        $sanPham = $this->sanPham(['hinh_anh' => 'https://cdn.example.com/a.png']);
        $donHang = DonHang::create([
            'ma_hoadon' => 'HD-ANH-001',
            'user_id' => $admin->id,
            'tong_tien' => 2000,
            'tong_thanh_toan' => 2000,
            'tien_khach_dua' => 2000,
            'tien_thua' => 0,
            'trang_thai' => 'da_thanh_toan',
        ]);
        ChiTietDonHang::create([
            'invoice_id' => $donHang->id,
            'product_id' => $sanPham->id,
            'soluong' => 1,
            'don_gia' => 2000,
            'gia_von' => 1000,
            'thanh_tien' => 2000,
        ]);

        $this->actingAs($admin)->delete(route('sanpham.xoa', $sanPham))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('products', ['id' => $sanPham->id]);
    }

    public function test_xoa_san_pham_nam_trong_kiem_ke_bi_chan_khong_500(): void
    {
        $admin = $this->nguoiDung('admin');
        $thukho = $this->nguoiDung('thukho');
        $sanPham = $this->sanPham();

        $this->actingAs($thukho)->post(route('kiemke.store'), [])->assertRedirect();

        $this->actingAs($admin)->delete(route('sanpham.xoa', $sanPham))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('products', ['id' => $sanPham->id]);
    }
}