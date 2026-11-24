<?php

namespace Tests\Feature;

use App\Exports\SanPhamExport;
use App\Models\LoaiSanPham;
use App\Models\SanPham;
use App\Models\User;
use App\Services\AnhTuUrlService;
use App\Services\SanPhamImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

/**
 * GD1 — Anh qua URL: SSRF (mock resolver/fetcher, KHONG goi mang that),
 * validate noi dung, tich hop import/export.
 */
class ImportAnhUrlTest extends TestCase
{
    use RefreshDatabase;

    private array $tempFiles = [];

    private ?LoaiSanPham $loaiCache = null;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
        parent::tearDown();
    }

    // ---------- Helpers ----------

    private function loai(): LoaiSanPham
    {
        return $this->loaiCache ??= LoaiSanPham::create(['ten_loai' => 'Củ sạc', 'ma_loai' => 'CS']);
    }

    private function user(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function pngBytes(int $w = 4, int $h = 4, int $mau = 0): string
    {
        $img = imagecreatetruecolor($w, $h);
        $c = imagecolorallocate($img, 200 + $mau, 100, 50 + $mau);
        imagefill($img, 0, 0, $c);
        ob_start();
        imagepng($img);
        $s = (string) ob_get_clean();
        imagedestroy($img);

        return $s;
    }

    /** Mock fetcher tra ve $body cho moi request (theo url => body). */
    private function fetcherMap(array $map): callable
    {
        return function (string $url, string $ip, int $port, int $maxBytes, int $timeout) use ($map): array {
            foreach ($map as $khop => $traVe) {
                if (str_contains($url, $khop)) {
                    return is_callable($traVe) ? $traVe($url) : $traVe;
                }
            }

            return ['status' => 404, 'location' => null, 'body' => '', 'error' => null];
        };
    }

    private function serviceAnh(array $mapUrl, array $dns = ['cdn.example.com' => ['93.184.216.34']]): AnhTuUrlService
    {
        $resolver = function (string $host) use ($dns): array {
            return $dns[$host] ?? ['93.184.216.34'];
        };

        return new AnhTuUrlService($resolver, $this->fetcherMap($mapUrl));
    }

    private function ganService(AnhTuUrlService $svc): SanPhamImportService
    {
        app()->instance(AnhTuUrlService::class, $svc);

        return app(SanPhamImportService::class);
    }

    private function makeXlsxHeader(array $header, array $dataRows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([$header], null, 'A1');
        foreach ($dataRows as $r => $row) {
            $sheet->fromArray([$row], null, 'A'.($r + 2));
        }
        $path = storage_path('app/test-anh-'.uniqid().'.xlsx');
        (new XlsxWriter($spreadsheet))->save($path);
        $this->tempFiles[] = $path;

        return new UploadedFile($path, 'test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function ok200(string $body): array
    {
        return ['status' => 200, 'location' => null, 'body' => $body, 'error' => null];
    }

    // ---------- SSRF: tu choi IP/noi bo ----------

    public function test_ssrf_chan_ip_noi_bo_cac_dang(): void
    {
        $svc = new AnhTuUrlService(fn () => ['93.184.216.34'], $this->fetcherMap([]));
        foreach ([
            'http://127.0.0.1/a.png',
            'http://10.0.0.5/a.png',
            'http://172.16.0.1/a.png',
            'http://192.168.1.1/a.png',
            'http://169.254.169.254/latest/meta-data',
            'http://100.64.0.1/a.png',
            'http://0.0.0.0/a.png',
            'http://[::1]/a.png',
            'http://[fd00::1]/a.png',
            'http://[fe80::1]/a.png',
            'http://[::ffff:127.0.0.1]/a.png',
            'http://2130706433/a.png',          // 127.0.0.1 dang thap phan
            'http://0x7f000001/a.png',          // hex
            'http://0177.0.0.1/a.png',          // octal
        ] as $url) {
            $kq = $svc->taiVe($url);
            $this->assertFalse($kq['ok'], "phai chan: {$url}");
        }
    }

    public function test_ssrf_chan_khi_dns_tra_ip_noi_bo(): void
    {
        $svc = new AnhTuUrlService(
            fn (string $host) => ['192.168.0.10'],
            $this->fetcherMap(['ok.png' => $this->ok200($this->pngBytes())])
        );
        $kq = $svc->taiVe('https://noi-bo.example.com/ok.png');
        $this->assertFalse($kq['ok']);
        $this->assertStringContainsString('nội bộ', $kq['loi']);
    }

    public function test_ssrf_chan_redirect_toi_ip_noi_bo(): void
    {
        $svc = new AnhTuUrlService(
            fn () => ['93.184.216.34'],
            $this->fetcherMap([
                'red' => ['status' => 302, 'location' => 'http://169.254.169.254/meta', 'body' => '', 'error' => null],
            ])
        );
        $kq = $svc->taiVe('https://cdn.example.com/red.png');
        $this->assertFalse($kq['ok']);
    }

    public function test_ssrf_tu_choi_scheme_cong_userinfo(): void
    {
        $svc = new AnhTuUrlService(fn () => ['93.184.216.34'], $this->fetcherMap([]));
        $this->assertNotNull($svc->kiemTraUrl('ftp://cdn.example.com/a.png'));
        $this->assertNotNull($svc->kiemTraUrl('https://user:pass@cdn.example.com/a.png'));
        $this->assertNotNull($svc->kiemTraUrl('https://cdn.example.com:8443/a.png'));
        $this->assertNull($svc->kiemTraUrl('https://cdn.example.com/a.png'));
        $this->assertNull($svc->kiemTraUrl('http://cdn.example.com:80/a.png'));
        $this->assertNull($svc->kiemTraUrl(''));
    }

    public function test_ssrf_fetcher_duoc_ghim_vao_ip_da_phan_giai(): void
    {
        $ipNhan = null;
        $fetcher = function ($url, $ip, $port, $max, $t) use (&$ipNhan) {
            $ipNhan = $ip;

            return ['status' => 200, 'location' => null, 'body' => $this->pngBytes(), 'error' => null];
        };
        $svc = new AnhTuUrlService(fn () => ['93.184.216.34'], $fetcher);
        $kq = $svc->taiVe('https://cdn.example.com/a.png');
        $this->assertTrue($kq['ok']);
        $this->assertSame('93.184.216.34', $ipNhan); // CURLOPT_RESOLVE pin
    }

    public function test_ssrf_ten_mien_khong_phan_giai_duoc(): void
    {
        $svc = new AnhTuUrlService(fn () => [], $this->fetcherMap([]));
        $kq = $svc->taiVe('https://khong-ton-tai.invalid/a.png');
        $this->assertFalse($kq['ok']);
    }

    // ---------- Validate noi dung anh ----------

    public function test_tu_choi_file_gia_duoi_va_svg(): void
    {
        $svc = $this->serviceAnh([
            'gia' => $this->ok200('<?php echo 1; ?>'),
            'svg' => $this->ok200('<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/></svg>'),
            'png' => $this->ok200($this->pngBytes()),
        ]);
        $this->assertFalse($svc->taiVe('https://cdn.example.com/gia.png')['ok']);
        $this->assertFalse($svc->taiVe('https://cdn.example.com/svg.png')['ok']); // SVG bi tu choi
        $this->assertTrue($svc->taiVe('https://cdn.example.com/png.png')['ok']);
    }

    public function test_tu_choi_anh_qua_lon_va_qua_nhieu_diem(): void
    {
        $svc = $this->serviceAnh([
            'lon' => $this->ok200(str_repeat('x', 2 * 1024 * 1024 + 10)),
            'rong' => $this->ok200($this->pngBytes(6001, 1)),
        ]);
        $kq = $svc->taiVe('https://cdn.example.com/lon.png');
        $this->assertFalse($kq['ok']);
        $this->assertStringContainsString('MB', $kq['loi']);
        $kq2 = $svc->taiVe('https://cdn.example.com/rong.png');
        $this->assertFalse($kq2['ok']);
        $this->assertStringContainsString('6000', $kq2['loi']);
    }

    public function test_timeout_va_loi_http(): void
    {
        $svc = $this->serviceAnh([
            'treo' => ['status' => 0, 'location' => null, 'body' => '', 'error' => 'Operation timed out'],
            'loi500' => $this->ok200(''),
        ]);
        // sua status cho 500
        $svc = new AnhTuUrlService(fn () => ['93.184.216.34'], function ($url) {
            if (str_contains($url, 'treo')) {
                return ['status' => 0, 'location' => null, 'body' => '', 'error' => 'Operation timed out'];
            }

            return ['status' => 500, 'location' => null, 'body' => '', 'error' => null];
        });
        $kq = $svc->taiVe('https://cdn.example.com/treo.png');
        $this->assertFalse($kq['ok']);
        $this->assertStringContainsString('thời gian', $kq['loi']);
        $kq2 = $svc->taiVe('https://cdn.example.com/loi500.png');
        $this->assertFalse($kq2['ok']);
        $this->assertStringContainsString('500', $kq2['loi']);
    }

    public function test_thanh_cong_luu_file_va_sha256_chong_trung(): void
    {
        $bytes = $this->pngBytes(8, 8);
        $svc = $this->serviceAnh(['a.png' => $this->ok200($bytes), 'b.png' => $this->ok200($bytes)]);
        $kq1 = $svc->taiVe('https://cdn.example.com/a.png');
        $kq2 = $svc->taiVe('https://cdn.example.com/b.png');
        $this->assertTrue($kq1['ok']);
        $this->assertTrue($kq2['ok']);
        $this->assertSame($kq1['duong_dan'], $kq2['duong_dan']); // cung noi dung -> cung file
        $this->assertMatchesRegularExpression('#^sanpham/anh-[0-9a-f]{64}\.png$#', $kq1['duong_dan']);
        Storage::disk('public')->assertExists($kq1['duong_dan']);
    }

    // ---------- Tich hop import ----------

    private function fileImportCoAnh(string $sku, ?string $url, array $them = []): UploadedFile
    {
        $this->loai();

        return $this->makeXlsxHeader(
            ['sku', 'ten_sanpham', 'danh_muc', 'don_vi', 'gia_ban', 'hinh_anh_url'],
            [[$sku, 'SP ảnh', 'CS', 'Cái', 100000, $url] + $them]
        );
    }

    public function test_preview_khong_goi_mang_va_xac_nhan_tai_anh(): void
    {
        $goiMang = 0;
        $resolver = fn () => ['93.184.216.34'];
        $fetcher = function ($url, $ip, $port, $max, $t) use (&$goiMang) {
            $goiMang++;

            return ['status' => 200, 'location' => null, 'body' => $this->pngBytes(5, 5), 'error' => null];
        };
        $service = $this->ganService(new AnhTuUrlService($resolver, $fetcher));

        $file = $this->fileImportCoAnh('SKU-ANH-1', 'https://cdn.example.com/a.png');
        $doc = $service->docFile($file);
        $kh = $service->lapKeHoach($doc);
        $this->assertTrue($kh['ok'], json_encode($kh['errors'], JSON_UNESCAPED_UNICODE));
        $this->assertSame(0, $goiMang, 'preview khong duoc goi mang');

        $kq = $service->ghiKeHoach($kh['plan']);
        $this->assertSame(1, $goiMang);
        $this->assertSame(1, $kq['anh_thanh_cong']);
        $sp = SanPham::where('sku', 'SKU-ANH-1')->firstOrFail();
        $this->assertSame('https://cdn.example.com/a.png', $sp->hinh_anh_nguon);
        $this->assertMatchesRegularExpression('#^sanpham/anh-[0-9a-f]{64}\.png$#', (string) $sp->hinh_anh);
        $this->assertNotNull($sp->hinh_anh_tai_luc);
        Storage::disk('public')->assertExists($sp->hinh_anh);
    }

    public function test_url_trong_giu_anh_va_doi_url_thay_anh_xoa_file_cu(): void
    {
        $service = $this->ganService($this->serviceAnh([
            'cu.png' => $this->ok200($this->pngBytes(4, 4, 1)),
            'moi.png' => $this->ok200($this->pngBytes(4, 4, 9)),
        ]));

        // Lan 1: tao voi anh cu
        $kh1 = $service->lapKeHoach($service->docFile(
            $this->fileImportCoAnh('SKU-ANH-2', 'https://cdn.example.com/cu.png')
        ));
        $service->ghiKeHoach($kh1['plan']);
        $sp = SanPham::where('sku', 'SKU-ANH-2')->firstOrFail();
        $anhCu = $sp->hinh_anh;
        $this->assertNotNull($anhCu);

        // Lan 2: URL trong -> khong doi anh
        $kh2 = $service->lapKeHoach($service->docFile(
            $this->fileImportCoAnh('SKU-ANH-2', '')
        ));
        $row2 = collect($kh2['rows'])->firstWhere('sku', 'SKU-ANH-2');
        $this->assertSame('skip', $row2['action']);

        // Lan 3: URL moi -> thay anh, xoa file cu
        $kh3 = $service->lapKeHoach($service->docFile(
            $this->fileImportCoAnh('SKU-ANH-2', 'https://cdn.example.com/moi.png')
        ));
        $service->ghiKeHoach($kh3['plan']);
        $sp->refresh();
        $this->assertNotSame($anhCu, $sp->hinh_anh);
        $this->assertSame('https://cdn.example.com/moi.png', $sp->hinh_anh_nguon);
        Storage::disk('public')->assertMissing($anhCu);
        Storage::disk('public')->assertExists($sp->hinh_anh);
    }

    public function test_anh_loi_khong_lam_hong_dong(): void
    {
        $service = $this->ganService(new AnhTuUrlService(
            fn () => ['93.184.216.34'],
            $this->fetcherMap(['hong' => ['status' => 404, 'location' => null, 'body' => '', 'error' => null]])
        ));
        $file = $this->fileImportCoAnh('SKU-ANH-3', 'https://cdn.example.com/hong.png');
        $kh = $service->lapKeHoach($service->docFile($file));
        $this->assertTrue($kh['ok']);
        $kq = $service->ghiKeHoach($kh['plan']);
        $this->assertSame(1, $kq['tao_moi']); // SP van duoc tao
        $this->assertSame(0, $kq['anh_thanh_cong']);
        $this->assertArrayHasKey(2, $kq['anh_loi']); // dong 2 (sau header)
        $sp = SanPham::where('sku', 'SKU-ANH-3')->firstOrFail();
        $this->assertNull($sp->hinh_anh);
    }

    public function test_gioi_han_20_anh_moi_luot(): void
    {
        $service = $this->ganService($this->serviceAnh([]));
        $rows = [];
        for ($i = 1; $i <= 21; $i++) {
            $rows[] = ["SKU-N{$i}", "SP {$i}", 'CS', 'Cái', 1000, 'https://cdn.example.com/a.png'];
        }
        $file = $this->makeXlsxHeader(['sku', 'ten_sanpham', 'danh_muc', 'don_vi', 'gia_ban', 'hinh_anh_url'], $rows);
        $kh = $service->lapKeHoach($service->docFile($file));
        $this->assertFalse($kh['ok']);
        $this->assertStringContainsString('20', implode(' ', $kh['errors']));
    }

    public function test_thieu_cot_db_thi_bo_qua_anh_url(): void
    {
        Schema::table('products', function ($t) {
            $t->dropColumn(['hinh_anh_nguon', 'hinh_anh_sha256', 'hinh_anh_tai_luc']);
        });
        \App\Support\KiemTraSchema::datLai();

        $service = $this->ganService($this->serviceAnh(['a.png' => $this->ok200($this->pngBytes())]));
        $this->assertFalse($service->coCotAnh());
        $file = $this->fileImportCoAnh('SKU-ANH-4', 'https://cdn.example.com/a.png');
        $kh = $service->lapKeHoach($service->docFile($file));
        $this->assertTrue($kh['ok']);
        $kq = $service->ghiKeHoach($kh['plan']);
        $this->assertSame(1, $kq['tao_moi']);
        $this->assertSame(0, $kq['anh_thanh_cong']);
        $sp = SanPham::where('sku', 'SKU-ANH-4')->firstOrFail();
        $this->assertNull($sp->hinh_anh);

        \App\Support\KiemTraSchema::datLai(); // don cache cho test sau trong cung process
    }

    public function test_vong_xuat_nhap_co_cot_anh_url(): void
    {
        $this->loai();
        // SP co anh tu URL
        SanPham::create([
            'category_id' => LoaiSanPham::first()->id,
            'sku' => 'SKU-RT-ANH', 'ten_sanpham' => 'SP roundtrip ảnh', 'don_vi' => 'Cái',
            'gia_ban' => 99000, 'hinh_anh' => 'sanpham/anh-abc.png',
            'hinh_anh_nguon' => 'https://cdn.example.com/rt.png',
            'hinh_anh_sha256' => str_repeat('a', 64), 'hinh_anh_tai_luc' => now(),
        ]);
        $service = $this->ganService($this->serviceAnh([])); // khong can tai: URL trung nguon

        $export = new SanPhamExport();
        $headings = $export->headings();
        $this->assertContains('hinh_anh_url', $headings);
        $rows = $export->collection()->values();
        $this->assertSame('https://cdn.example.com/rt.png', $rows[0][count($headings) - 1]);

        // Dua vao file xlsx roi import lai -> toan "Khong doi"
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([$headings], null, 'A1');
        $r = 2;
        foreach ($rows as $row) {
            $sheet->fromArray([$row], null, 'A'.$r++);
        }
        $path = storage_path('app/test-rt-anh-'.uniqid().'.xlsx');
        (new XlsxWriter($spreadsheet))->save($path);
        $this->tempFiles[] = $path;
        $file = new UploadedFile($path, 'rt.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $kh = $service->lapKeHoach($service->docFile($file));
        $this->assertTrue($kh['ok'], json_encode(array_slice($kh['errors'], 0, 3), JSON_UNESCAPED_UNICODE));
        $this->assertSame(0, $kh['counts']['update']);
        $this->assertSame(0, $kh['counts']['error']);
        $this->assertSame(1, $kh['counts']['skip']);
    }

    // ---------- Bo sung GD1: curl flags, query strip, URL noi bo ----------

    public function test_curl_options_giu_ssl_va_tat_proxy(): void
    {
        $svc = new AnhTuUrlService();
        $body = '';
        $location = null;
        $status = 0;
        $opts = $svc->curlOptions('https://cdn.example.com/a.png', '93.184.216.34', 443, 1024, 10, $body, $location, $status);
        $this->assertSame(['cdn.example.com:443:93.184.216.34'], $opts[CURLOPT_RESOLVE]);
        $this->assertTrue($opts[CURLOPT_SSL_VERIFYPEER]);
        $this->assertSame(2, $opts[CURLOPT_SSL_VERIFYHOST]);
        $this->assertSame('', $opts[CURLOPT_PROXY]);
        $this->assertSame('*', $opts[CURLOPT_NOPROXY]);
        $this->assertFalse($opts[CURLOPT_FOLLOWLOCATION]);
    }

    public function test_nguon_luu_bo_query_va_cat_500(): void
    {
        $svc = new AnhTuUrlService();
        $dai = 'https://cdn.example.com/'.str_repeat('a', 600).'?token=BI-MAT&sig=xyz#frag';
        $luu = $svc->chuanHoaUrlLuu($dai);
        $this->assertStringNotContainsString('token', $luu);
        $this->assertStringNotContainsString('BI-MAT', $luu);
        $this->assertStringNotContainsString('#', $luu);
        $this->assertLessThanOrEqual(500, mb_strlen($luu));
    }

    public function test_url_noi_bo_khong_tai_mang_va_round_trip_anh_cuc_bo(): void
    {
        config(['app.url' => 'https://shop.test']);
        // SP co anh cuc bo sanpham/anh-x.png
        $this->loai();
        Storage::disk('public')->put('sanpham/anh-x.png', $this->pngBytes());
        $sp = SanPham::create([
            'category_id' => LoaiSanPham::first()->id, 'sku' => 'SKU-NB',
            'ten_sanpham' => 'SP nội bộ', 'don_vi' => 'Cái', 'gia_ban' => 50000,
            'hinh_anh' => 'sanpham/anh-x.png',
            'hinh_anh_nguon' => 'https://shop.test/storage/sanpham/anh-x.png',
            'hinh_anh_sha256' => str_repeat('b', 64), 'hinh_anh_tai_luc' => now(),
        ]);
        $goiMang = 0;
        $service = $this->ganService(new AnhTuUrlService(
            fn () => ['93.184.216.34'],
            function () use (&$goiMang) { $goiMang++; return ['status' => 200, 'location' => null, 'body' => '', 'error' => null]; }
        ));

        // File xuat co URL APP_URL/storage/... -> import -> khong doi, 0 tai mang
        $file = $this->makeXlsxHeader(
            ['sku', 'ten_sanpham', 'danh_muc', 'don_vi', 'gia_ban', 'hinh_anh_url'],
            [['SKU-NB', 'SP nội bộ', 'CS', 'Cái', 50000, 'https://shop.test/storage/sanpham/anh-x.png']]
        );
        $kh = $service->lapKeHoach($service->docFile($file));
        $this->assertTrue($kh['ok']);
        $this->assertSame('skip', $kh['rows'][0]['action']);
        $this->assertSame(0, $goiMang);
    }

    public function test_url_noi_bo_file_khac_bi_tu_choi(): void
    {
        config(['app.url' => 'https://shop.test']);
        $this->loai();
        Storage::disk('public')->put('sanpham/anh-khac.png', $this->pngBytes());
        SanPham::create([
            'category_id' => LoaiSanPham::first()->id, 'sku' => 'SKU-NB2',
            'ten_sanpham' => 'SP NB2', 'don_vi' => 'Cái', 'gia_ban' => 50000,
            'hinh_anh' => 'sanpham/anh-cua-noi.png',
        ]);
        Storage::disk('public')->put('sanpham/anh-cua-noi.png', $this->pngBytes(3, 3));
        $service = $this->ganService($this->serviceAnh([]));
        $file = $this->makeXlsxHeader(
            ['sku', 'hinh_anh_url'],
            [['SKU-NB2', 'https://shop.test/storage/sanpham/anh-khac.png']]
        );
        $kh = $service->lapKeHoach($service->docFile($file));
        $this->assertTrue($kh['ok']);
        // anh khong doi (file khac bi tu choi) -> skip; canh bao co mat
        $this->assertSame('skip', $kh['rows'][0]['action']);
        $this->assertNotEmpty($kh['rows'][0]['warnings']);
    }

    public function test_traversal_va_host_khac_van_bi_chan(): void
    {
        config(['app.url' => 'https://shop.test']);
        $svc = new AnhTuUrlService(fn () => ['93.184.216.34'], $this->fetcherMap([]));
        $this->assertNull($svc->laUrlNoiBo('https://shop.test/storage/sanpham/../config.php'));
        $this->assertNull($svc->laUrlNoiBo('https://shop.test/storage/%2e%2e/x.png'));
        $this->assertNull($svc->laUrlNoiBo('https://shop.test/storage/sanpham/../../.env'));
        $this->assertNull($svc->laUrlNoiBo('https://shop.test/storage/evil.php'));
        $this->assertSame('sanpham/anh-x.png', $svc->laUrlNoiBo('https://shop.test/storage/sanpham/anh-x.png'));
        // host giong APP_URL nhung scheme khac / path khac -> khong phai noi bo
        $this->assertNull($svc->laUrlNoiBo('http://shop.test/storage/sanpham/anh-x.png'));
        $this->assertNull($svc->laUrlNoiBo('https://shop.test/khac/anh-x.png'));
        // host la 127.0.0.1 khong khop APP_URL -> di qua SSRF nhu cu
        $kq = $svc->taiVe('https://127.0.0.1/storage/sanpham/anh-x.png');
        $this->assertFalse($kq['ok']);
        // APP_URL khac host -> URL shop.test la ngoai, van qua mang/chan
        config(['app.url' => 'https://khac.test']);
        $this->assertNull($svc->laUrlNoiBo('https://shop.test/storage/sanpham/anh-x.png'));
    }

    public function test_anh_thong_ke_lenh(): void
    {
        $this->loai();
        Storage::disk('public')->put('sanpham/anh-dung.png', $this->pngBytes());
        Storage::disk('public')->put('sanpham/anh-mo-coi.png', $this->pngBytes());
        SanPham::create([
            'category_id' => LoaiSanPham::first()->id, 'sku' => 'SKU-TK',
            'ten_sanpham' => 'SP TK', 'don_vi' => 'Cái', 'gia_ban' => 1000,
            'hinh_anh' => 'sanpham/anh-dung.png',
        ]);
        $this->artisan('anh:thong-ke')->assertSuccessful();
    }
}
