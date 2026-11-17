<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\SanPham;
use App\Services\NhatKyService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Gán products.brand_id bằng cách suy ra hãng từ ten_sanpham
 * ("Nhãn hiệu: X." / "Hiệu: X."). Mặc định DRY-RUN; chỉ ghi khi --apply.
 *
 * An toàn: chỉ ghi brand_id; bỏ qua SP đã có brand_id (gán tay);
 * SP không khớp hoặc khớp mơ hồ → giữ NULL; idempotent.
 */
class BackfillHangSanXuat extends Command
{
    protected $signature = 'app:backfill-product-brands {--apply : Ghi brand_id vào DB (mặc định chỉ in mapping)}';

    protected $description = 'Suy ra hãng sản xuất từ tên SP và gán products.brand_id; mặc định DRY-RUN';

    /** Chuẩn hóa: trim, bỏ dấu chấm/khoảng trắng cuối, gộp không phân biệt hoa thường. */
    private function chuanHoaTenHang(string $ten): string
    {
        return mb_strtolower(trim(rtrim(trim($ten), '. ')));
    }

    /** Trích các hãng được nhắc trong tên SP; >1 hãng khác nhau → mơ hồ. */
    private function trichHang(string $tenSanPham): array
    {
        // Hãng kết thúc ở dấu '.' hoặc ',' (vd "Nhãn hiệu: Hoco. Model: X." / "Nhãn hiệu: Borofone, Model : Y.").
        if (! preg_match_all('/(?:Nhãn hiệu|Hiệu)\s*:?\s*([^.,]+)[.,]/u', $tenSanPham, $m)) {
            return [];
        }

        $cacTen = [];
        foreach ($m[1] as $ten) {
            $chuan = $this->chuanHoaTenHang($ten);
            if ($chuan !== '' && ! in_array($chuan, $cacTen, true)) {
                $cacTen[] = $chuan;
            }
        }

        return $cacTen;
    }

    public function handle(): int
    {
        $apDung = (bool) $this->option('apply');
        if (! $apDung) {
            $this->line('DRY-RUN — không ghi thay đổi. Thêm --apply để áp dụng.');
        }

        $sanPhams = SanPham::query()
            ->whereNull('brand_id')
            ->orderBy('id')
            ->get(['id', 'sku', 'ten_sanpham']);

        // Bản đồ ten_chuan_hoa => ['ten_hien_thi' => biến thể phổ biến nhất, 'count' => n]
        $bangHang = [];
        $mapping = [];   // id => ['sanPham' => SP, 'hangChuan' => string|null, 'lyDo' => string|null]
        $daGanTay = SanPham::query()->whereNotNull('brand_id')->count();

        foreach ($sanPhams as $sp) {
            $cacHang = $this->trichHang((string) $sp->ten_sanpham);

            if (count($cacHang) === 0) {
                $mapping[$sp->id] = ['sp' => $sp, 'hang' => null, 'lyDo' => 'khong_khop'];

                continue;
            }
            if (count($cacHang) > 1) {
                $mapping[$sp->id] = ['sp' => $sp, 'hang' => null, 'lyDo' => 'mo_ho'];

                continue;
            }

            $hangChuan = $cacHang[0];
            $mapping[$sp->id] = ['sp' => $sp, 'hang' => $hangChuan, 'lyDo' => null];

            if (! isset($bangHang[$hangChuan])) {
                $bangHang[$hangChuan] = ['ten' => $this->tenHienThi($sp->ten_sanpham, $hangChuan), 'count' => 0, 'bienThe' => []];
            }
            $bangHang[$hangChuan]['count']++;
        }

        $this->inBangTongHop($bangHang);
        $this->inMappingDayDu($mapping);
        $this->inCanhBao($bangHang);
        $this->line(sprintf('SP đã có brand_id (bỏ qua, coi như gán tay): %d', $daGanTay));

        if (! $apDung) {
            return self::SUCCESS;
        }

        return $this->apDung($bangHang, $mapping);
    }

    /** Lấy biến thể tên hãng xuất hiện trong ten_sanpham (giữ nguyên hoa/thường gốc). */
    private function tenHienThi(string $tenSanPham, string $hangChuan): string
    {
        if (preg_match('/(?:Nhãn hiệu|Hiệu)\s*:?\s*([^.,]+)[.,]/u', $tenSanPham, $m)) {
            $ten = trim(rtrim(trim($m[1]), '. '));
            if ($this->chuanHoaTenHang($ten) === $hangChuan) {
                return $ten;
            }
        }

        return $hangChuan;
    }

    private function inBangTongHop(array $bangHang): void
    {
        $this->newLine();
        $this->info('=== TỔNG HỢP HÃNG ===');
        $this->table(
            ['Hãng (chuẩn hóa)', 'Tên hiển thị', 'Số SP'],
            collect($bangHang)
                ->map(fn ($h, $k) => [$k, $h['ten'], $h['count']])
                ->sortBy('2', SORT_NUMERIC)->reverse()->values()->all()
        );
    }

    private function inMappingDayDu(array $mapping): void
    {
        $this->newLine();
        $this->info('=== MAPPING CHI TIẾT (id | sku | tên rút gọn | hãng | ghi chú) ===');
        $rows = [];
        foreach ($mapping as $id => $row) {
            $ten = Str::limit((string) $row['sp']->ten_sanpham, 60);
            $hang = $row['hang'] ?? '—';
            $ghiChu = match ($row['lyDo']) {
                'khong_khop' => 'KHÔNG KHỚP → NULL',
                'mo_ho' => 'MƠ HỒ (>1 hãng) → NULL',
                default => '',
            };
            $rows[] = [$id, $row['sp']->sku, $ten, $hang, $ghiChu];
        }
        $this->table(['ID', 'SKU', 'Tên SP', 'Hãng', 'Ghi chú'], $rows);
    }

    private function inCanhBao(array $bangHang): void
    {
        $it = collect($bangHang)->filter(fn ($h) => $h['count'] <= 2);
        $this->newLine();
        if ($it->isEmpty()) {
            $this->info('Không có hãng hiếm (1-2 SP) — không có cảnh báo chính tả.');

            return;
        }
        $this->warn('=== CẢNH BÁO: hãng chỉ có 1-2 SP (kiểm tra lỗi chính tả?) ===');
        $this->table(
            ['Hãng chuẩn hóa', 'Tên hiển thị', 'Số SP'],
            $it->map(fn ($h, $k) => [$k, $h['ten'], $h['count']])->values()->all()
        );
    }

    private function apDung(array $bangHang, array $mapping): int
    {
        $soHangMoi = 0;
        $soSpGan = 0;

        DB::transaction(function () use ($bangHang, $mapping, &$soHangMoi, &$soSpGan): void {
            // Tạo hãng trước (firstOrCreate theo tên chuẩn hóa → idempotent).
            $brandTheoChuanHoa = [];
            foreach ($bangHang as $chuan => $info) {
                $brand = Brand::firstOrCreate(
                    ['ten' => $info['ten']],
                    ['slug' => Brand::slugDuyNhat($info['ten']), 'kich_hoat' => true]
                );
                $brandTheoChuanHoa[$chuan] = $brand->id;
                if ($brand->wasRecentlyCreated) {
                    $soHangMoi++;
                }
            }

            // Gán brand_id — chỉ cho SP còn brand_id NULL (idempotent, không ghi đè gán tay).
            foreach ($mapping as $id => $row) {
                if ($row['hang'] === null) {
                    continue;
                }
                $brandId = $brandTheoChuanHoa[$row['hang']] ?? null;
                if ($brandId === null) {
                    continue;
                }
                // DB::table (query builder thuần) — KHÔNG đụng updated_at của products.
                $capNhat = DB::table('products')
                    ->where('id', $id)
                    ->whereNull('brand_id')
                    ->update(['brand_id' => $brandId]);
                $soSpGan += $capNhat;
            }
        });

        // Một dòng audit tổng kết (không PII) — chỉ khi thực sự có ghi.
        if ($soSpGan > 0 || $soHangMoi > 0) {
            NhatKyService::ghi(
                Request::create('/', 'GET'),
                'backfill_hang_san_xuat',
                'product',
                null,
                sprintf('Backfill hãng SP: %d hãng, %d sản phẩm được gán', $soHangMoi, $soSpGan),
                nguon: 'console'
            );
        }

        $this->info(sprintf('ĐÃ ÁP DỤNG: %d hãng (mới/tìm thấy), %d sản phẩm được gán brand_id.', count($bangHang), $soSpGan));

        return self::SUCCESS;
    }
}
