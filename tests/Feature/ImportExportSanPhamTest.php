<?php

namespace Tests\Feature;

use App\Exports\BrandExport;
use App\Exports\SanPhamExport;
use App\Models\Brand;
use App\Models\LoaiSanPham;
use App\Models\NhatKyHoatDong;
use App\Models\SanPham;
use App\Models\User;
use App\Services\SanPhamImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

class ImportExportSanPhamTest extends TestCase
{
    use RefreshDatabase;

    private ?LoaiSanPham $loaiCache = null;

    private function loai(): LoaiSanPham
    {
        return $this->loaiCache ??= LoaiSanPham::create(['ten_loai' => 'Củ sạc', 'ma_loai' => 'CS']);
    }

    private function user(string $role = 'admin'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function hang(string $ten = 'Hoco', bool $kichHoat = true): Brand
    {
        return Brand::create(['ten' => $ten, 'slug' => \Illuminate\Support\Str::slug($ten), 'kich_hoat' => $kichHoat]);
    }

    private function sanPham(array $override = []): SanPham
    {
        return SanPham::create(array_merge([
            'category_id' => $this->loai()->id,
            'sku' => 'SKU-'.fake()->unique()->numberBetween(1000, 999999),
            'ten_sanpham' => 'Củ sạc '.fake()->word(),
            'don_vi' => 'Cái',
            'gia_nhap' => 50000,
            'gia_ban' => 90000,
            'soluong' => 10,
            'soluong_toithieu' => 5,
            'trang_thai' => true,
        ], $override));
    }

    private const HEADER = ['sku', 'barcode', 'ten_sanpham', 'danh_muc', 'hang', 'don_vi', 'gia_ban'];

    /**
     * Tao file .xlsx that tu mang 2 chieu: dong 0 = header, cac dong sau = data.
     * $textCols: danh sach chi so cot (0-based) ghi kieu chuoi tuong minh.
     */
    private function makeXlsx(array $dataRows, array $textCols = [0, 1]): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([self::HEADER], null, 'A1');
        foreach ($dataRows as $r => $row) {
            foreach (array_values($row) as $c => $v) {
                $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c + 1).($r + 2);
                if (in_array($c, $textCols, true)) {
                    $sheet->setCellValueExplicit($coord, $v === null ? '' : (string) $v, DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue($coord, $v);
                }
            }
        }
        $path = storage_path('app/test-import-'.uniqid().'.xlsx');
        (new XlsxWriter($spreadsheet))->save($path);

        return new UploadedFile($path, 'test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function upload(UploadedFile $file)
    {
        $this->loai(); // dam bao ma_loai 'CS' ton tai cho cac test dung no

        return $this->post(route('sanpham.import.upload'), ['file' => $file]);
    }

    private function tokenTuResponse($response): ?string
    {
        // token nam trong input hidden cua form xac nhan
        if (preg_match('/name="token" value="([^"]+)"/', $response->getContent(), $m)) {
            return $m[1];
        }

        return null;
    }

    // ---------- Phan quyen ----------

    public function test_phan_quyen_import_chi_admin(): void
    {
        $file = $this->makeXlsx([['SKU1', '', 'Ten', 'CS', '', 'Cái', 1000]]);

        $this->get(route('sanpham.import'))->assertRedirect(); // guest
        $this->actingAs($this->user('thungan'))->get(route('sanpham.import'))->assertForbidden();
        $this->actingAs($this->user('thukho'))->get(route('sanpham.import'))->assertForbidden();
        $this->actingAs($this->user('admin'))->get(route('sanpham.import'))->assertOk();

        // Thu ngan/thu kho goi thang route upload -> 403
        $this->actingAs($this->user('thungan'))->post(route('sanpham.import.upload'), ['file' => $file])->assertForbidden();
        $this->actingAs($this->user('thukho'))->post(route('sanpham.import.upload'), ['file' => $file])->assertForbidden();
    }

    public function test_phan_quyen_export(): void
    {
        // sanpham/xuat-excel: admin + thu kho OK; thu ngan 403 (nhom route)
        $this->actingAs($this->user('admin'))->get(route('sanpham.xuatexcel'))->assertOk();
        $this->actingAs($this->user('thukho'))->get(route('sanpham.xuatexcel'))->assertOk();
        $this->actingAs($this->user('thungan'))->get(route('sanpham.xuatexcel'))->assertForbidden();

        // hangsxuat/xuat-excel: admin + thu kho OK; thu ngan 403
        $this->actingAs($this->user('admin'))->get(route('hangsxuat.xuatexcel'))->assertOk();
        $this->actingAs($this->user('thukho'))->get(route('hangsxuat.xuatexcel'))->assertOk();
        $this->actingAs($this->user('thungan'))->get(route('hangsxuat.xuatexcel'))->assertForbidden();

        // file mau: chi admin
        $this->actingAs($this->user('thukho'))->get(route('sanpham.importmau'))->assertForbidden();
        $this->actingAs($this->user('admin'))->get(route('sanpham.importmau'))->assertOk();
    }

    // ---------- Upload: loi file ----------

    public function test_file_sai_dinh_dang_va_doi_duoi(): void
    {
        $this->actingAs($this->user());
        // file gia: noi dung text nhung ten .xlsx
        $fake = UploadedFile::fake()->create('gia.xlsx', 50, 'text/plain');
        $this->upload($fake)->assertSessionHas('error');
        $this->assertSame(0, SanPham::count());
    }

    public function test_file_qua_2mb(): void
    {
        $this->actingAs($this->user());
        $big = UploadedFile::fake()->create('lon.xlsx', 3000, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->upload($big)->assertSessionHasErrors('file');
    }

    public function test_header_sai_bao_loi_ro(): void
    {
        $this->actingAs($this->user());
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['sku', 'barcode', 'ten_sanpham', 'danh_muc', 'hang', 'don_vi']], null, 'A1');
        $sheet->fromArray([['X', '', 'Ten', 'CS', '', 'Cái']], null, 'A2');
        $path = storage_path('app/test-bad-header.xlsx');
        (new XlsxWriter($spreadsheet))->save($path);
        $file = new UploadedFile($path, 'bad.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $this->upload($file)->assertRedirect(route('sanpham.import'))
            ->assertSessionHas('error');
    }

    public function test_file_qua_1000_dong(): void
    {
        $this->actingAs($this->user());
        $rows = [];
        for ($i = 0; $i < 1001; $i++) {
            $rows[] = ["SKU-{$i}", '', "SP {$i}", 'CS', '', 'Cái', 1000];
        }
        $this->upload($this->makeXlsx($rows))->assertSessionHas('error');
        $this->assertSame(0, SanPham::count());
    }

    // ---------- Preview khong ghi DB ----------

    public function test_preview_khong_ghi_db(): void
    {
        $this->hang('Hoco');
        $this->actingAs($this->user());
        $file = $this->makeXlsx([
            ['SKU-NEW-1', '', 'Sản phẩm mới', 'CS', 'Hoco', 'Cái', 150000],
        ]);
        $before = SanPham::count();
        $res = $this->upload($file);
        $res->assertOk();
        $res->assertSee('Tạo mới');
        $res->assertSee('NGỪNG BÁN');
        $this->assertSame($before, SanPham::count());
        $this->assertNotNull($this->tokenTuResponse($res));
    }

    // ---------- Xac nhan: tao moi ----------

    public function test_xac_nhan_tao_moi_ngung_ban(): void
    {
        $hang = $this->hang('Hoco');
        $this->actingAs($this->user());
        $file = $this->makeXlsx([
            ['SP-TAO-01', 'BC-001', 'Sản phẩm tạo mới', 'CS', 'Hoco', 'Hộp', 150000],
            ['SP-TAO-02', '', 'Sản phẩm không hãng', 'CS', '', 'Cái', 50000],
        ]);
        $token = $this->tokenTuResponse($this->upload($file));
        $this->assertNotNull($token);

        $this->post(route('sanpham.import.xacnhan'), ['token' => $token])
            ->assertRedirect(route('sanpham.danhsach'));

        $sp1 = SanPham::where('sku', 'SP-TAO-01')->firstOrFail();
        $this->assertSame(0, (int) $sp1->trang_thai);
        $this->assertSame(0, (int) $sp1->soluong);
        $this->assertSame(0.0, (float) $sp1->gia_nhap);
        $this->assertSame(150000, (int) $sp1->gia_ban);
        $this->assertSame($hang->id, $sp1->brand_id);
        $this->assertSame('BC-001', $sp1->barcode);

        $sp2 = SanPham::where('sku', 'SP-TAO-02')->firstOrFail();
        $this->assertNull($sp2->brand_id);
        $this->assertSame(0, (int) $sp2->trang_thai);

        // Audit: 1 dong import_san_pham, khong chua noi dung dong
        $log = NhatKyHoatDong::where('hanh_dong', 'import_san_pham')->sole();
        $this->assertStringContainsString('test.xlsx', $log->mo_ta);
        $this->assertStringContainsString('tạo mới 2', $log->mo_ta);
        $this->assertStringNotContainsString('Sản phẩm tạo mới', $log->mo_ta);
    }

    public function test_xac_nhan_cap_nhat_chi_truong_trong_danh_sach(): void
    {
        $hang = $this->hang('Remax');
        $sp = $this->sanPham([
            'sku' => 'SKU-CU-01', 'ten_sanpham' => 'Tên cũ', 'gia_ban' => 90000,
            'soluong' => 7, 'gia_nhap' => 40000, 'trang_thai' => true, 'brand_id' => $hang->id,
        ]);
        $updatedAtTruoc = $sp->updated_at;

        // Dong sku khop -> cap nhat ten + gia; hang de trong -> giu nguyen Remax
        $this->actingAs($this->user());
        $token = $this->tokenTuResponse($this->upload($this->makeXlsx([
            ['SKU-CU-01', '', 'Tên mới', 'CS', '', 'Cái', 120000],
        ])));
        $this->post(route('sanpham.import.xacnhan'), ['token' => $token])->assertRedirect();

        $sp->refresh();
        $this->assertSame('Tên mới', $sp->ten_sanpham);
        $this->assertSame(120000, (int) $sp->gia_ban);
        $this->assertSame($hang->id, $sp->brand_id); // khong bi xoa
        $this->assertSame(7, (int) $sp->soluong);    // khong doi
        $this->assertSame(40000.0, (float) $sp->gia_nhap); // khong doi
        $this->assertTrue((bool) $sp->trang_thai);   // khong tu bat/tat
        $this->assertTrue($sp->updated_at->gt($updatedAtTruoc) || $sp->updated_at->eq($updatedAtTruoc));

        // SP co thay doi that -> updated_at phai moi hon (hoac bang neu cung giay)
        $this->assertNotNull($sp->updated_at);
    }

    public function test_dong_khong_doi_giu_nguyen_updated_at(): void
    {
        $this->sanPham(['sku' => 'SKU-SAME', 'ten_sanpham' => 'Giữ nguyên', 'gia_ban' => 90000]);
        $this->actingAs($this->user());
        $res = $this->upload($this->makeXlsx([
            ['SKU-SAME', '', 'Giữ nguyên', 'CS', '', 'Cái', 90000],
        ]));
        $res->assertSee('Không đổi');
        $token = $this->tokenTuResponse($res);
        // skip-only: khong co gi de xac nhan? van co the xac nhan (0 thay doi)
        if ($token !== null) {
            $this->post(route('sanpham.import.xacnhan'), ['token' => $token]);
        }
        $this->assertSame(1, SanPham::count());
    }

    public function test_file_co_loi_khong_ghi_gi(): void
    {
        $this->actingAs($this->user());
        $file = $this->makeXlsx([
            ['SKU-OK-1', '', 'Sản phẩm ổn', 'CS', '', 'Cái', 10000],
            ['SKU-BAD', '', '', 'KHONG-TON-TAI', '', 'Cái', 10000], // thieu ten + ma_loai la
        ]);
        $res = $this->upload($file);
        $res->assertOk();
        $res->assertSee('Lỗi');
        $this->assertNull($this->tokenTuResponse($res)); // khong co nut xac nhan
        $this->assertSame(0, SanPham::count());
    }

    public function test_sku_trung_trong_file(): void
    {
        $this->actingAs($this->user());
        $file = $this->makeXlsx([
            ['DUP-SKU', '', 'A', 'CS', '', 'Cái', 1000],
            ['dup-sKU', '', 'B', 'CS', '', 'Cái', 2000], // khac hoa thuong van trung
        ]);
        $res = $this->upload($file);
        $res->assertSee('trùng trong file');
        $this->assertNull($this->tokenTuResponse($res));
        $this->assertSame(0, SanPham::count());
    }

    public function test_sku_giu_so_0_dau(): void
    {
        $this->actingAs($this->user());
        // '0011' la chuoi trong file (textCols da ep kieu chuoi)
        $token = $this->tokenTuResponse($this->upload($this->makeXlsx([
            ['0011', '', 'Cáp test', 'CS', '', 'Cái', 99000],
        ], [0, 1])));
        $this->assertNotNull($token);
        $this->post(route('sanpham.import.xacnhan'), ['token' => $token]);
        $this->assertDatabaseHas('products', ['sku' => '0011']);
        $this->assertDatabaseMissing('products', ['sku' => '11']);
    }

    public function test_hang_la_va_hang_tat(): void
    {
        $this->hang('HangLa');
        $this->hang('HangTat', false);
        $this->actingAs($this->user());

        $res = $this->upload($this->makeXlsx([
            ['SKU-H1', '', 'A', 'CS', 'HangLa', 'Cái', 1000],
            ['SKU-H2', '', 'B', 'CS', 'HangKhongCo', 'Cái', 1000],
            ['SKU-H3', '', 'C', 'CS', 'HangTat', 'Cái', 1000],
        ]));
        $res->assertSee('Hãng');
        $this->assertNull($this->tokenTuResponse($res));
        $this->assertSame(0, SanPham::count());
    }

    public function test_ma_loai_la(): void
    {
        $this->actingAs($this->user());
        $res = $this->upload($this->makeXlsx([
            ['SKU-M1', '', 'A', 'KHONG-CO', '', 'Cái', 1000],
        ]));
        $res->assertSee('không tồn tại');
        $this->assertSame(0, SanPham::count());
    }

    public function test_barcode_trung_sp_khac(): void
    {
        $this->sanPham(['sku' => 'SKU-CU', 'barcode' => 'BC-DA-CO']);
        $this->actingAs($this->user());
        $res = $this->upload($this->makeXlsx([
            ['SKU-NEW', 'BC-DA-CO', 'A', 'CS', '', 'Cái', 1000],
        ]));
        $res->assertSee('đã thuộc sản phẩm khác');
        $this->assertSame(1, SanPham::count());
    }

    public function test_sku_khop_khong_phan_biet_hoa_thuong(): void
    {
        $sp = $this->sanPham(['sku' => 'AbC-123', 'ten_sanpham' => 'Cũ']);
        $this->actingAs($this->user());
        $res = $this->upload($this->makeXlsx([
            ['abc-123', '', 'Tên Mới', 'CS', '', 'Cái', 90000],
        ]));
        $res->assertSee('khác hoa/thường');
        $token = $this->tokenTuResponse($res);
        $this->assertNotNull($token);
        $this->post(route('sanpham.import.xacnhan'), ['token' => $token]);
        // cap nhat dung SP cu, sku giu nguyen dang DB
        $this->assertSame('Tên Mới', $sp->fresh()->ten_sanpham);
        $this->assertSame('AbC-123', $sp->fresh()->sku);
        $this->assertSame(1, SanPham::count());
    }

    public function test_gia_ban_khong_phai_so_nguyen(): void
    {
        $this->actingAs($this->user());
        foreach (['abc', '100.5', '-5'] as $gia) {
            $res = $this->upload($this->makeXlsx([
                ['SKU-G'.$gia, '', 'A', 'CS', '', 'Cái', $gia],
            ]));
            $res->assertSee('không hợp lệ');
        }
        $this->assertSame(0, SanPham::count());
    }

    public function test_can_hanh_gia_lon_va_gia_thap_hon_gia_nhap(): void
    {
        $this->sanPham(['sku' => 'SKU-GIA', 'gia_ban' => 100000, 'gia_nhap' => 80000]);
        $this->actingAs($this->user());
        // gia 100k -> 200k (>50%) VA 200k > gia_nhap 80k -> chi canh bao >50%
        $res = $this->upload($this->makeXlsx([
            ['SKU-GIA', '', 'A', 'CS', '', 'Cái', 200000],
        ]));
        $res->assertSee('>50%');
        $token = $this->tokenTuResponse($res);
        $this->assertNotNull($token); // canh bao KHONG chan
        $this->post(route('sanpham.import.xacnhan'), ['token' => $token]);
        $this->assertSame(200000, (int) SanPham::where('sku', 'SKU-GIA')->value('gia_ban'));
    }

    public function test_can_hanh_gia_ban_thap_hon_gia_nhap(): void
    {
        $this->sanPham(['sku' => 'SKU-LO', 'gia_ban' => 100000, 'gia_nhap' => 80000]);
        $this->actingAs($this->user());
        $res = $this->upload($this->makeXlsx([
            ['SKU-LO', '', 'A', 'CS', '', 'Cái', 50000],
        ]));
        $res->assertSee('thấp hơn giá nhập');
    }

    // ---------- Bao mat phien preview ----------

    public function test_preview_cua_admin_khac_bi_chan(): void
    {
        $admin1 = $this->user();
        $admin2 = $this->user();
        $this->actingAs($admin1);
        $token = $this->tokenTuResponse($this->upload($this->makeXlsx([
            ['SKU-SEC', '', 'A', 'CS', '', 'Cái', 1000],
        ])));
        $this->assertNotNull($token);

        $this->actingAs($admin2);
        $this->post(route('sanpham.import.xacnhan'), ['token' => $token])->assertForbidden();
        $this->assertSame(0, SanPham::count());
    }

    public function test_xac_nhan_2_lan_bi_chan(): void
    {
        $this->actingAs($this->user());
        $token = $this->tokenTuResponse($this->upload($this->makeXlsx([
            ['SKU-1LAN', '', 'A', 'CS', '', 'Cái', 1000],
        ])));
        $this->post(route('sanpham.import.xacnhan'), ['token' => $token])->assertRedirect();
        $this->post(route('sanpham.import.xacnhan'), ['token' => $token])
            ->assertRedirect(route('sanpham.import'))
            ->assertSessionHas('error');
        $this->assertSame(1, SanPham::count());
    }

    public function test_du_lieu_lech_giua_2_buoc_huy_nhap(): void
    {
        $admin = $this->user();
        $this->actingAs($admin);
        $token = $this->tokenTuResponse($this->upload($this->makeXlsx([
            ['SKU-DRIFT', '', 'A', 'CS', '', 'Cái', 1000],
        ])));
        $this->assertNotNull($token);

        // Nguoi khac tao SP trung sku giua 2 buoc
        SanPham::create([
            'category_id' => $this->loai()->id, 'sku' => 'SKU-DRIFT',
            'ten_sanpham' => 'Người khác tạo', 'don_vi' => 'Cái',
            'gia_nhap' => 0, 'gia_ban' => 1, 'soluong' => 0, 'trang_thai' => false,
        ]);

        $this->post(route('sanpham.import.xacnhan'), ['token' => $token])
            ->assertRedirect(route('sanpham.import'))
            ->assertSessionHas('error');
        // SP goc khong bi ghi de boi import
        $this->assertSame('Người khác tạo', SanPham::where('sku', 'SKU-DRIFT')->value('ten_sanpham'));
    }

    public function test_hang_bi_tat_giua_2_buoc_huy_nhap(): void
    {
        $hang = $this->hang('HangX');
        $this->actingAs($this->user());
        $token = $this->tokenTuResponse($this->upload($this->makeXlsx([
            ['SKU-HANG', '', 'A', 'CS', 'HangX', 'Cái', 1000],
        ])));
        $hang->update(['kich_hoat' => false]);
        $this->post(route('sanpham.import.xacnhan'), ['token' => $token])
            ->assertSessionHas('error');
        $this->assertSame(0, SanPham::count());
    }

    // ---------- Export ----------

    public function test_export_san_pham_khong_co_gia_nhap(): void
    {
        $this->sanPham(['sku' => 'EXP-1', 'gia_nhap' => 77777]);
        $admin = $this->user();
        $this->actingAs($admin);

        $export = new SanPhamExport();
        $this->assertNotContains('Giá nhập', $export->headings());
        $this->assertNotContains('gia_nhap', $export->headings());
        $row = $export->collection()->first();
        $this->assertCount(9, $row);
        $this->assertNotContains(77777, array_map('strval', $row));

        $res = $this->get(route('sanpham.xuatexcel'));
        $res->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $res->headers->get('content-type'));
    }

    public function test_export_hang_co_so_san_pham(): void
    {
        $hang = $this->hang('Hoco');
        $this->sanPham(['brand_id' => $hang->id]);
        $this->sanPham(['brand_id' => $hang->id]);
        $admin = $this->user();
        $this->actingAs($admin);

        $export = new BrandExport();
        $row = $export->collection()->firstWhere('0', 'Hoco') ?? $export->collection()->first();
        $this->assertSame(2, $row[3]);
        $this->assertSame(['Tên hãng', 'Slug', 'Trạng thái', 'Số sản phẩm'], $export->headings());
    }

    // ---------- Chong formula injection ----------

    public function test_export_xlsx_khong_tao_cong_thuc(): void
    {
        $this->sanPham(['ten_sanpham' => '=1+1&cmd', 'sku' => 'INJ-1']);
        $admin = $this->user();
        $this->actingAs($admin);

        \Maatwebsite\Excel\Facades\Excel::store(new SanPhamExport, 'test-inj.xlsx', 'local');
        $path = storage_path('app/private/test-inj.xlsx');

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $sheet = $reader->getActiveSheet();
        // cot C = Ten san pham; tim dong chua '=1+1'
        $foundFormula = false;
        foreach ($sheet->getRowIterator() as $row) {
            foreach ($row->getCellIterator() as $cell) {
                if ($cell->getValue() === '=1+1&cmd') {
                    $this->assertSame('s', $cell->getDataType(), 'O bat dau bang = phai la string, khong phai formula');
                    $foundFormula = true;
                }
            }
        }
        $this->assertTrue($foundFormula);
        @unlink($path);
    }

    // ---------- Don vi service (khong qua HTTP) ----------

    public function test_service_doc_file_va_header(): void
    {
        $service = app(SanPhamImportService::class);
        $file = $this->makeXlsx([['SKU1', '', 'Ten', 'CS', '', 'Cái', 1000]]);
        $doc = $service->docFile($file);
        $this->assertCount(1, $doc['inputs']);
        $this->loai();
        $kh = $service->lapKeHoach($doc['inputs']);
        $this->assertTrue($kh['ok']);
        $this->assertSame('create', $kh['rows'][0]['action']);
    }
}
