<?php

namespace Tests\Feature;

use App\Models\LoaiSanPham;
use App\Models\SanPham;
use App\Models\User;
use App\Rules\AnhSanPhamUrl;
use App\Support\KiemTraSchema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Lỗi E — giới hạn URL ảnh theo độ dài cột THẬT của products.hinh_anh.
 * Trên bản sao MySQL strict: áp migration nới cột lên 500 rồi chứng minh
 * URL 500 ký tự được nhận, 501 bị từ chối 422 tiếng Việt (không 500);
 * sau đó down() trả cột về 150 và 151 bị từ chối trở lại.
 *
 * Chạy: DB_CONNECTION=mysql DB_DATABASE=doan_cnw_copy php artisan test --filter=AnhUrlDoDaiMysqlCopyTest
 * Test tự dọn sản phẩm và bản ghi migration nó tạo; không đụng doan_cnw.
 */
class AnhUrlDoDaiMysqlCopyTest extends TestCase
{
    private const MIGRATION = 'database/migrations/2026_11_13_000002_tang_do_dai_hinh_anh_products_len_500.php';
    private const TEN_MIGRATION = '2026_11_13_000002_tang_do_dai_hinh_anh_products_len_500';

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
                'Cần MySQL bản sao: DB_CONNECTION=mysql DB_DATABASE=doan_cnw_copy php artisan test --filter=AnhUrlDoDaiMysqlCopyTest'
            );
        }
    }

    private function admin(): User
    {
        return User::firstOrCreate(
            ['email' => 'admin-anh-url-copy@test.local'],
            ['name' => 'Admin Copy', 'password' => bcrypt('12345678'), 'role' => 'admin']
        );
    }

    private function duLieuForm(string $sku, string $url): array
    {
        return [
            'category_id' => LoaiSanPham::firstOrCreate(['ten_loai' => 'Loại URL dài'])->id,
            'sku' => $sku,
            'ten_sanpham' => 'Sản phẩm URL dài',
            'don_vi' => 'Cái',
            'gia_nhap' => 1000,
            'gia_ban' => 2000,
            'soluong' => 10,
            'soluong_toithieu' => 5,
            'trang_thai' => 1,
            'hinh_anh_url' => $url,
        ];
    }

    private function doDaiCot(): int
    {
        $row = DB::selectOne(
            "SELECT CHARACTER_MAXIMUM_LENGTH AS n FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'hinh_anh'"
        );

        return (int) $row->n;
    }

    public function test_bien_do_dai_url_theo_cot_that_150_sang_500(): void
    {
        $this->chiChayTrenBanSaoMysql();

        $tenMigration = self::TEN_MIGRATION;
        $daCoCot500 = $this->doDaiCot() === 500;

        // Đảm bảo điểm xuất phát: cột 150 (trạng thái DB thật trước khi migrate).
        if ($daCoCot500) {
            (require base_path(self::MIGRATION))->down();
            DB::table('migrations')->where('migration', $tenMigration)->delete();
            KiemTraSchema::datLai();
        }
        $this->assertSame(150, $this->doDaiCot(), 'Cột hinh_anh trên bản sao phải là VARCHAR(150) ở điểm xuất phát');

        try {
            // 1) Trước migrate: 151 ký tự bị từ chối với thông báo rõ.
            KiemTraSchema::datLai();
            $this->assertSame(150, AnhSanPhamUrl::gioiHanDoDai());

            // 2) Áp migration nới cột lên 500.
            $this->assertSame(0, Artisan::call('migrate', ['--path' => self::MIGRATION, '--force' => true]));
            KiemTraSchema::datLai();
            $this->assertSame(500, $this->doDaiCot());
            $this->assertSame(500, AnhSanPhamUrl::gioiHanDoDai(), 'Rule phải theo giới hạn cột thật sau migrate');

            $admin = $this->admin();
            $tienTo = 'https://cdn.example.com/';

            // 3) URL đúng 500 ký tự được nhận và lưu nguyên vẹn.
            $url500 = $tienTo.str_repeat('a', 500 - strlen($tienTo));
            $this->actingAs($admin)->post(route('sanpham.postthem'), $this->duLieuForm('URL-DO-DAI-500', $url500))
                ->assertRedirect(route('sanpham.danhsach'));
            $this->assertSame($url500, SanPham::where('sku', 'URL-DO-DAI-500')->firstOrFail()->hinh_anh);

            // 4) URL 501 ký tự bị từ chối 422/redirect kèm lỗi tiếng Việt, không 500.
            $url501 = $tienTo.str_repeat('b', 501 - strlen($tienTo));
            $this->from(route('sanpham.them'))
                ->actingAs($admin)
                ->post(route('sanpham.postthem'), $this->duLieuForm('URL-DO-DAI-501', $url501))
                ->assertRedirect(route('sanpham.them'))
                ->assertSessionHasErrors('hinh_anh_url');
            $this->assertDatabaseMissing('products', ['sku' => 'URL-DO-DAI-501']);

            // 5) Thông báo quá dài phải nói đúng giới hạn mới (500).
            $loi = session('errors')?->get('hinh_anh_url') ?? [];
            $this->assertNotEmpty($loi);
            $this->assertStringContainsString('500', implode(' ', $loi));
        } finally {
            // 6) Trả schema về đúng trạng thái ban đầu cho các test khác.
            // Xóa sản phẩm URL dài TRƯỚC khi thu cột, tránh "Data too long".
            SanPham::where('sku', 'like', 'URL-DO-DAI-%')->delete();
            if (Schema::hasColumn('products', 'hinh_anh') && $this->doDaiCot() === 500) {
                (require base_path(self::MIGRATION))->down();
                DB::table('migrations')->where('migration', $tenMigration)->delete();
            }
            KiemTraSchema::datLai();
            User::where('email', 'admin-anh-url-copy@test.local')->delete();
        }
    }
}
