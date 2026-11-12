<?php

namespace Tests\Feature;

use App\Models\LoaiSanPham;
use App\Models\NhatKyHoatDong;
use App\Models\SanPham;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use Tests\TestCase;

/**
 * Quét URL ảnh trên bản sao MySQL strict mode (cột hinh_anh VARCHAR(150) thật).
 * Không dùng RefreshDatabase để giữ nguyên schema của bản sao — migrate lại sẽ
 * tạo cột 255 theo migration và che mất lỗi "Data too long" của CSDL thật.
 * Chạy: DB_CONNECTION=mysql DB_DATABASE=doan_cnw_copy DB_USERNAME=root DB_PASSWORD=''
 *       php artisan test --filter=AnhUrlMysqlCopyTest
 * Test tự dọn sản phẩm và nhật ký nó tạo; không đụng doan_cnw.
 */
class AnhUrlMysqlCopyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function chiChayTrenBanSaoMysql(): void
    {
        $ketNoi = DB::connection();
        $database = (string) $ketNoi->getDatabaseName();

        if ($ketNoi->getDriverName() !== 'mysql' || ! str_ends_with($database, '_copy')) {
            $this->markTestSkipped(
                'Cần MySQL bản sao: DB_CONNECTION=mysql DB_DATABASE=doan_cnw_copy php artisan test --filter=AnhUrlMysqlCopyTest'
            );
        }
    }

    private function duLieuForm(string $sku, array $them = []): array
    {
        return array_merge([
            'category_id' => LoaiSanPham::query()->firstOrFail()->id,
            'sku' => $sku,
            'ten_sanpham' => 'Sản phẩm sweep MySQL',
            'don_vi' => 'Cái',
            'gia_nhap' => 1000,
            'gia_ban' => 2000,
            'soluong' => 10,
            'soluong_toithieu' => 5,
            'trang_thai' => 1,
        ], $them);
    }

    private function donDep(array $ids): void
    {
        if ($ids === []) {
            return;
        }
        NhatKyHoatDong::where('doi_tuong', 'san_pham')->whereIn('doi_tuong_id', $ids)->delete();
        SanPham::whereIn('id', $ids)->delete();
    }

    #[DataProviderExternal(AnhUrlSweepTest::class, 'danhSachQuet')]
    public function test_quet_them_va_sua_tren_mysql_strict(mixed $giaTri, string $kyVong): void
    {
        $this->chiChayTrenBanSaoMysql();
        $admin = User::where('role', 'admin')->firstOrFail();
        $ids = [];

        try {
            $skuThem = 'SWEEP-MT-'.uniqid();
            $them = $this->from(route('sanpham.them'))->actingAs($admin)
                ->post(route('sanpham.postthem'), $this->duLieuForm($skuThem, ['hinh_anh_url' => $giaTri]));
            $them->assertStatus(302);
            $sanPhamThem = SanPham::where('sku', $skuThem)->first();
            if ($kyVong === 'tu_choi') {
                $them->assertSessionHasErrors('hinh_anh_url');
                $this->assertNull($sanPhamThem);
            } else {
                $them->assertSessionHas('success');
                $this->assertNotNull($sanPhamThem);
                $ids[] = $sanPhamThem->id;
            }

            $sanPhamSua = SanPham::create($this->duLieuForm('SWEEP-MS-'.uniqid(), [
                'hinh_anh' => 'https://cdn.example.com/goc.png',
            ]));
            $ids[] = $sanPhamSua->id;
            $sua = $this->from(route('sanpham.sua', $sanPhamSua))->actingAs($admin)
                ->put(route('sanpham.postsua', $sanPhamSua), $this->duLieuForm($sanPhamSua->sku, ['hinh_anh_url' => $giaTri]));
            $sua->assertStatus(302);
            if ($kyVong === 'tu_choi') {
                $sua->assertSessionHasErrors('hinh_anh_url');
                $this->assertSame('https://cdn.example.com/goc.png', $sanPhamSua->fresh()->hinh_anh);
            } else {
                $sua->assertSessionHas('success');
            }
        } finally {
            $this->donDep($ids);
        }
    }

    public function test_url_150_ky_tu_luu_duoc_va_151_bi_tu_choi(): void
    {
        $this->chiChayTrenBanSaoMysql();
        $admin = User::where('role', 'admin')->firstOrFail();
        $ids = [];

        try {
            $sku = 'SWEEP-150-'.uniqid();
            $url150 = 'https://cdn.example.com/'.str_repeat('a', 126);
            $this->actingAs($admin)->post(route('sanpham.postthem'), $this->duLieuForm($sku, [
                'hinh_anh_url' => $url150,
            ]))->assertRedirect(route('sanpham.danhsach'));
            $sanPham = SanPham::where('sku', $sku)->firstOrFail();
            $ids[] = $sanPham->id;
            $this->assertSame($url150, $sanPham->hinh_anh);

            $url151 = 'https://cdn.example.com/'.str_repeat('a', 127);
            $this->from(route('sanpham.sua', $sanPham))->actingAs($admin)
                ->put(route('sanpham.postsua', $sanPham), $this->duLieuForm($sku, ['hinh_anh_url' => $url151]))
                ->assertSessionHasErrors('hinh_anh_url');
            $this->assertSame($url150, $sanPham->fresh()->hinh_anh);
        } finally {
            $this->donDep($ids);
        }
    }

    public function test_chuoi_multibyte_150_ky_tu_legacy_van_hien_thi_duoc(): void
    {
        $this->chiChayTrenBanSaoMysql();
        $admin = User::where('role', 'admin')->firstOrFail();
        $ids = [];

        try {
            $sanPham = SanPham::create($this->duLieuForm('SWEEP-MB-'.uniqid()));
            $ids[] = $sanPham->id;
            // 150 ký tự nhưng 286 byte: VARCHAR(150) utf8mb4 tính theo ký tự.
            $sanPham->forceFill(['hinh_anh' => 'https://x.com/'.str_repeat('é', 136)])->save();

            $this->actingAs($admin)->get(route('sanpham.danhsach'))->assertOk();
            $this->get(route('storefront.product', $sanPham))->assertOk();
        } finally {
            $this->donDep($ids);
        }
    }
}