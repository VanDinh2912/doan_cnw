<?php

namespace App\Console\Commands;

use App\Models\SanPham;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ScrapeProductImagesCommand extends Command
{
    protected $signature = 'app:scrape-product-images {--limit=0 : Giới hạn số lượng sản phẩm cần cập nhật ảnh (0 là tất cả)} {--force : Ghi đè ảnh cũ nếu đã có}';
    protected $description = 'Tạo ảnh minh hoạ SVG đồng bộ theo danh mục, hiển thị nhãn hiệu và model của từng sản phẩm (không phụ thuộc mạng)';

    /** Màu nhấn theo category_id. */
    private array $palette = [
        1 => '#2563eb', 2 => '#0891b2', 3 => '#7c3aed', 4 => '#059669',
        5 => '#db2777', 6 => '#9333ea', 7 => '#0d9488', 8 => '#ea580c',
        9 => '#4f46e5', 10 => '#dc2626', 11 => '#64748b', 12 => '#0284c7',
    ];

    public function handle(): int
    {
        $this->info('=== TẠO ẢNH MINH HOẠ SẢN PHẨM THEO DANH MỤC ===');

        $storageDir = storage_path('app/public/products');
        if (!File::isDirectory($storageDir)) {
            File::makeDirectory($storageDir, 0755, true);
        }

        $query = SanPham::query()->with('loaiSanPham');
        if (!$this->option('force')) {
            $query->where(function ($q) {
                $q->whereNull('hinh_anh')
                  ->orWhere('hinh_anh', '')
                  ->orWhere('hinh_anh', 'like', '%no-image%');
            });
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $products = $query->get();
        $total = $products->count();

        $this->info("Tổng số sản phẩm cần tạo ảnh: {$total}");
        if ($total === 0) {
            $this->info('Tất cả sản phẩm đã có ảnh!');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($products as $sanPham) {
            $slug = Str::slug($sanPham->sku);
            File::put($storageDir . DIRECTORY_SEPARATOR . $slug . '.svg', $this->buildSvg($sanPham));

            // Xoá ảnh .jpg cũ (nếu có) để không còn file mồ côi.
            $oldJpg = $storageDir . DIRECTORY_SEPARATOR . $slug . '.jpg';
            if (File::exists($oldJpg)) {
                File::delete($oldJpg);
            }

            $sanPham->hinh_anh = 'storage/products/' . $slug . '.svg';
            $sanPham->save();
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Đã tạo ảnh cho {$total} sản phẩm.");

        return self::SUCCESS;
    }

    /** Trích nhãn hiệu, model và thông số chính từ tên sản phẩm rồi dựng SVG 400x400. */
    private function buildSvg(SanPham $p): string
    {
        $name = (string) $p->ten_sanpham;
        $cat = (int) $p->category_id;
        $color = $this->palette[$cat] ?? '#2563eb';
        $catName = $p->loaiSanPham?->ten_loai ?? 'Phụ kiện';

        preg_match('/(?:Nhãn hiệu|Hiệu)\s*:?\s*([^.]+)\./u', $name, $mBrand);
        preg_match('/Model\s*:?\s*([^.]+)\./u', $name, $mModel);
        preg_match('/\d{2,3}\s?W\b|\d{4,6}\s?mAh/iu', $name, $mSpec);

        $brand = trim($mBrand[1] ?? '') ?: $catName;
        $model = trim($mModel[1] ?? '');
        $spec = trim($mSpec[0] ?? '');

        $e = fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $brandSize = mb_strlen($brand) > 14 ? 20 : 28;
        $modelSize = mb_strlen($model) > 16 ? 16 : 20;
        $badgeW = max(70, mb_strlen($catName) * 8 + 24);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400" width="400" height="400" role="img" aria-label="' . $e($brand . ' ' . $model) . '">';
        $svg .= '<rect width="400" height="400" fill="#ffffff"/>';
        $svg .= '<circle cx="200" cy="150" r="105" fill="' . $color . '" opacity="0.08"/>';
        $svg .= '<rect x="20" y="20" width="' . $badgeW . '" height="26" rx="13" fill="' . $color . '" opacity="0.12"/>';
        $svg .= '<text x="' . (20 + $badgeW / 2) . '" y="38" fill="' . $color . '" font-family="Segoe UI, Roboto, Arial, sans-serif" font-size="12" font-weight="700" text-anchor="middle">' . $e($catName) . '</text>';
        $svg .= $this->icon($cat, $color);
        $svg .= '<text x="200" y="275" fill="#0f172a" font-family="Segoe UI, Roboto, Arial, sans-serif" font-size="' . $brandSize . '" font-weight="700" text-anchor="middle">' . $e($brand) . '</text>';
        if ($model !== '') {
            $svg .= '<text x="200" y="308" fill="' . $color . '" font-family="Segoe UI, Roboto, Arial, sans-serif" font-size="' . $modelSize . '" font-weight="600" text-anchor="middle">' . $e($model) . '</text>';
        }
        if ($spec !== '') {
            $specW = mb_strlen($spec) * 9 + 28;
            $svg .= '<rect x="' . (200 - $specW / 2) . '" y="326" width="' . $specW . '" height="28" rx="14" fill="#f1f5f9" stroke="#e2e8f0"/>';
            $svg .= '<text x="200" y="345" fill="#475569" font-family="Segoe UI, Roboto, Arial, sans-serif" font-size="14" font-weight="600" text-anchor="middle">' . $e($spec) . '</text>';
        }

        return $svg . '</svg>';
    }

    /** Biểu tượng hình học đơn giản cho từng danh mục, canh giữa quanh (200,150). */
    private function icon(int $cat, string $c): string
    {
        return match ($cat) {
            1 => '<rect x="160" y="110" width="80" height="90" rx="12" fill="' . $c . '"/><rect x="178" y="84" width="8" height="28" rx="3" fill="' . $c . '" opacity="0.6"/><rect x="214" y="84" width="8" height="28" rx="3" fill="' . $c . '" opacity="0.6"/>',
            2 => '<rect x="140" y="110" width="60" height="70" rx="10" fill="' . $c . '"/><path d="M200 150 C250 150 230 205 270 195" fill="none" stroke="' . $c . '" stroke-width="7" stroke-linecap="round"/><rect x="266" y="186" width="24" height="16" rx="4" fill="' . $c . '" opacity="0.7"/>',
            3 => '<path d="M135 190 C165 100 235 200 265 110" fill="none" stroke="' . $c . '" stroke-width="8" stroke-linecap="round"/><rect x="122" y="188" width="26" height="16" rx="4" fill="' . $c . '" opacity="0.7"/><rect x="252" y="96" width="26" height="16" rx="4" fill="' . $c . '" opacity="0.7"/>',
            4 => '<rect x="150" y="100" width="100" height="120" rx="14" fill="' . $c . '"/><rect x="185" y="88" width="30" height="12" rx="3" fill="' . $c . '" opacity="0.6"/><rect x="168" y="122" width="64" height="10" rx="5" fill="#fff"/><rect x="168" y="146" width="64" height="10" rx="5" fill="#fff"/><rect x="168" y="170" width="40" height="10" rx="5" fill="#fff"/>',
            5 => '<path d="M150 160 A50 50 0 0 1 250 160" fill="none" stroke="' . $c . '" stroke-width="10" stroke-linecap="round"/><rect x="138" y="150" width="24" height="50" rx="10" fill="' . $c . '"/><rect x="238" y="150" width="24" height="50" rx="10" fill="' . $c . '"/>',
            6 => '<circle cx="170" cy="135" r="26" fill="' . $c . '"/><rect x="164" y="150" width="12" height="55" rx="6" fill="' . $c . '" opacity="0.7"/><circle cx="230" cy="135" r="26" fill="' . $c . '"/><rect x="224" y="150" width="12" height="55" rx="6" fill="' . $c . '" opacity="0.7"/>',
            7 => '<circle cx="200" cy="150" r="62" fill="none" stroke="' . $c . '" stroke-width="8"/><circle cx="200" cy="150" r="36" fill="none" stroke="' . $c . '" stroke-width="8" opacity="0.6"/><circle cx="200" cy="150" r="10" fill="' . $c . '"/>',
            8 => '<rect x="170" y="100" width="60" height="90" rx="10" fill="' . $c . '"/><rect x="185" y="190" width="30" height="30" rx="4" fill="' . $c . '" opacity="0.6"/><circle cx="200" cy="145" r="10" fill="#fff"/>',
            9 => '<rect x="165" y="85" width="70" height="110" rx="10" fill="' . $c . '"/><rect x="195" y="195" width="10" height="20" fill="' . $c . '" opacity="0.6"/><rect x="150" y="212" width="100" height="10" rx="5" fill="' . $c . '" opacity="0.6"/>',
            10 => '<rect x="160" y="90" width="80" height="120" rx="16" fill="' . $c . '"/><circle cx="200" cy="135" r="22" fill="#fff" opacity="0.9"/><circle cx="200" cy="182" r="10" fill="#fff" opacity="0.9"/>',
            12 => '<rect x="165" y="90" width="70" height="120" rx="12" fill="none" stroke="' . $c . '" stroke-width="8"/><path d="M182 120 L218 156" stroke="' . $c . '" stroke-width="8" stroke-linecap="round" opacity="0.4"/>',
            default => '<circle cx="175" cy="125" r="15" fill="' . $c . '"/><circle cx="225" cy="125" r="15" fill="' . $c . '" opacity="0.7"/><circle cx="175" cy="175" r="15" fill="' . $c . '" opacity="0.7"/><circle cx="225" cy="175" r="15" fill="' . $c . '"/>',
        };
    }
}