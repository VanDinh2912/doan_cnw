<?php

namespace Tests\Feature;

use App\Models\LoaiSanPham;
use App\Models\SanPham;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AnhUrlSweepTest extends TestCase
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
        self::$dem++;

        return User::create([
            'name' => 'Test '.$role,
            'email' => 'sweep-'.self::$dem.'-'.$role.'@test.local',
            'password' => bcrypt('12345678'),
            'role' => $role,
        ]);
    }

    private function duLieuForm(string $sku, array $them = []): array
    {
        return array_merge([
            'category_id' => LoaiSanPham::firstOrCreate(['ten_loai' => 'Loại sweep'])->id,
            'sku' => $sku,
            'ten_sanpham' => 'Sản phẩm sweep',
            'don_vi' => 'Cái',
            'gia_nhap' => 1000,
            'gia_ban' => 2000,
            'soluong' => 10,
            'soluong_toithieu' => 5,
            'trang_thai' => 1,
        ], $them);
    }

    /**
     * Mỗi ca: [nhãn, giá trị gửi lên, kỳ vọng 'tu_choi'|'chap_nhan'].
     * Hợp đồng chung: KHÔNG bao giờ 500 — hoặc 302 thành công, hoặc 302/422
     * kèm lỗi validate hinh_anh_url tiếng Việt. Ca 'tu_choi' dài >150 ký tự
     * là spec đúng theo cột thật VARCHAR(150) của MySQL.
     */
    public static function danhSachQuet(): array
    {
        $aclkLog = 'https://www.google.com/aclk?sa=L&ai=DChsSEwjPwNa-84GYAxUsbg8CHYM4IvIYACICCAEQARoCdGI&co=1&ase=2&gclid=Cj0KCQiAwdDXBhDuARIsAFdLukl-lvO5EhZke5IBDTESQ2fgajd5Icbq1yoRBTD9WhaIAw4kP3SRww4aAlgKEALw_wcB&cid=CAASWuRoDS3SSrOF5luprpzqQCiHwbb3SCGSGN7gJSgfLd0qdZamEz1Y';

        return [
            'trang web thường' => ['https://example.com/trang', 'chap_nhan'],
            'aclk 255 ký tự trong log' => [$aclkLog, 'tu_choi'],
            '151 ký tự' => ['https://cdn.example.com/'.str_repeat('a', 127), 'tu_choi'],
            '200 ký tự' => ['https://cdn.example.com/'.str_repeat('a', 176), 'tu_choi'],
            'đúng 150 ký tự' => ['https://cdn.example.com/'.str_repeat('a', 126), 'chap_nhan'],
            '256 ký tự' => ['https://cdn.example.com/'.str_repeat('a', 232), 'tu_choi'],
            'hơn 2000 ký tự' => ['https://cdn.example.com/'.str_repeat('a', 2100), 'tu_choi'],
            'khoảng trắng giữa' => ['https://x.com/a b.png', 'tu_choi'],
            'tab giữa' => ["https://x.com/a\tb.png", 'tu_choi'],
            'xuống dòng giữa' => ["https://x.com/a\nb.png", 'tu_choi'],
            'khoảng trắng hai đầu' => ['  https://x.com/a.png  ', 'chap_nhan'],
            'Unicode host và path' => ['https://例え.jp/画像.png', 'tu_choi'],
            'IDN punycode' => ['https://xn--r8jz45g.jp/a.png', 'chap_nhan'],
            'Unicode path' => ['https://x.com/ảnh-đẹp.png', 'tu_choi'],
            'percent hỏng' => ['https://x.com/%zz', 'chap_nhan'],
            'percent cụt' => ['https://x.com/%', 'chap_nhan'],
            'nháy kép' => ['https://x.com/a.png"onload="alert(1)', 'chap_nhan'],
            'nháy đơn' => ["https://x.com/a'b.png", 'chap_nhan'],
            'ngoặc nhọn' => ['https://x.com/<script>.png', 'chap_nhan'],
            'https trơn' => ['https://', 'tu_choi'],
            'ba gạch chéo' => ['https:///a.png', 'tu_choi'],
            'cổng chữ' => ['https://host:abc/', 'tu_choi'],
            'IPv6' => ['https://[::1]/a', 'chap_nhan'],
            'localhost' => ['https://localhost', 'chap_nhan'],
            'IP số' => ['https://127.0.0.1/a.png', 'chap_nhan'],
            'fragment' => ['https://x.com/a.png#frag', 'chap_nhan'],
            'null byte' => ["https://x.com/a\0.png", 'tu_choi'],
            'mảng thay vì chuỗi' => [['https://x.com/a.png'], 'tu_choi'],
            'userinfo' => ['https://user:pass@x.com/a.png', 'tu_choi'],
            'javascript' => ['javascript:alert(1)', 'tu_choi'],
            'data base64' => ['data:image/png;base64,AAAA', 'tu_choi'],
            'http' => ['http://x.com/a.png', 'tu_choi'],
            'rỗng' => ['', 'chap_nhan'],
            'null' => [null, 'chap_nhan'],
            'query multibyte' => ['https://x.com/a.png?x='.str_repeat('é', 126), 'tu_choi'],
        ];
    }

    #[DataProvider('danhSachQuet')]
    public function test_post_them_khong_bao_gio_500(mixed $giaTri, string $kyVong): void
    {
        $admin = $this->nguoiDung('admin');
        $sku = 'SWEEP-T-'.self::$dem;

        $response = $this->from(route('sanpham.them'))->actingAs($admin)
            ->post(route('sanpham.postthem'), $this->duLieuForm($sku, ['hinh_anh_url' => $giaTri]));

        $response->assertStatus(302);
        if ($kyVong === 'tu_choi') {
            $response->assertSessionHasErrors('hinh_anh_url');
            $this->assertNotSame('', session('errors')->first('hinh_anh_url'));
            $this->assertDatabaseMissing('products', ['sku' => $sku]);
        } else {
            $response->assertSessionHas('success');
            $sanPham = SanPham::where('sku', $sku)->firstOrFail();
            $mongDoi = is_string($giaTri) && trim($giaTri) !== '' ? trim($giaTri) : null;
            $this->assertSame($mongDoi, $sanPham->hinh_anh);
        }
    }

    #[DataProvider('danhSachQuet')]
    public function test_post_sua_khong_bao_gio_500(mixed $giaTri, string $kyVong): void
    {
        $admin = $this->nguoiDung('admin');
        $sanPham = SanPham::create($this->duLieuForm('SWEEP-S-'.self::$dem, [
            'hinh_anh' => 'https://cdn.example.com/goc.png',
        ]));

        $response = $this->from(route('sanpham.sua', $sanPham))->actingAs($admin)
            ->put(route('sanpham.postsua', $sanPham), $this->duLieuForm($sanPham->sku, ['hinh_anh_url' => $giaTri]));

        $response->assertStatus(302);
        if ($kyVong === 'tu_choi') {
            $response->assertSessionHasErrors('hinh_anh_url');
            $this->assertNotSame('', session('errors')->first('hinh_anh_url'));
            $this->assertSame('https://cdn.example.com/goc.png', $sanPham->fresh()->hinh_anh);
        } else {
            $response->assertSessionHas('success');
            $mongDoi = is_string($giaTri) && trim($giaTri) !== ''
                ? trim($giaTri)
                : 'https://cdn.example.com/goc.png';
            $this->assertSame($mongDoi, $sanPham->fresh()->hinh_anh);
        }
    }

    public static function urlBanLegacyProvider(): array
    {
        return [
            'aclk 255 legacy' => ['https://www.google.com/aclk?sa=L&ai=DChsSEwjPwNa-84GYAxUsbg8CHYM4IvIYACICCAEQARoCdGI&co=1&ase=2&gclid=Cj0KCQiAwdDXBhDuARIsAFdLukl-lvO5EhZke5IBDTESQ2fgajd5Icbq1yoRBTD9WhaIAw4kP3SRww4aAlgKEALw_wcB&cid=CAASWuRoDS3SSrOF5luprpzqQCiHwbb3SCGSGN7gJSgfLd0qdZamEz1Y'],
            'nháy kép' => ['https://x.com/a.png"onload="alert(1)'],
            'ngoặc nhọn' => ['https://x.com/<script>.png'],
            'Unicode' => ['https://例え.jp/画像.png'],
            'percent hỏng' => ['https://x.com/%zz'],
            'fragment' => ['https://x.com/a.png#frag'],
            'IPv6' => ['https://[::1]/a'],
            'null byte' => ["https://x.com/a\0.png"],
            'legacy storage' => ['storage/products/cu.svg'],
            'legacy upload' => ['upload/legacy.jpg'],
            'tệp tải lên' => ['sanpham/abc.jpg'],
        ];
    }

    #[DataProvider('urlBanLegacyProvider')]
    public function test_trang_hien_thi_san_pham_url_ban_legacy_khong_loi(string $giaTri): void
    {
        $admin = $this->nguoiDung('admin');
        $thungan = $this->nguoiDung('thungan');
        $sanPham = SanPham::create($this->duLieuForm('SWEEP-D-'.self::$dem));
        // Dữ liệu bẩn giả lập (không qua validate): mọi trang vẫn phải render.
        $sanPham->forceFill(['hinh_anh' => $giaTri])->save();

        $this->actingAs($admin)->get(route('sanpham.danhsach'))->assertOk();
        $this->get(route('storefront.home'))->assertOk();
        $this->get(route('storefront.product', $sanPham))->assertOk();
        $this->get(route('storefront.category', $sanPham->loaiSanPham))->assertOk();
        $this->get(route('storefront.search', ['q' => $sanPham->ten_sanpham]))->assertOk();
        $this->get(route('storefront.cart.add', $sanPham));
        $this->get(route('storefront.cart.index'))->assertOk();

        $pos = $this->actingAs($thungan)->getJson(route('sanpham.timkiem', ['q' => $sanPham->sku]));
        $pos->assertOk();
        $this->assertIsString($pos->json('0.anh_url'));
    }
}