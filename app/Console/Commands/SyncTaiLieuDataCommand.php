<?php

namespace App\Console\Commands;

use App\Models\ChiTietHoaDonNhap;
use App\Models\HoaDonNhap;
use App\Models\LoaiSanPham;
use App\Models\NhaCungCap;
use App\Models\SanPham;
use App\Models\User;
use App\Services\HoaDonNhapService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SyncTaiLieuDataCommand extends Command
{
    protected $signature = 'app:sync-tailieu-data {--seed-stock : Cấp tồn kho mẫu cho toàn bộ sản phẩm nếu đang bằng 0}';
    protected $description = 'Nạp toàn bộ dữ liệu mẫu còn thiếu từ TaiLieu (File A, B, v.v...) vào Database';

    public function handle(): int
    {
        $this->info('=== BẮT ĐẦU ĐỒNG BỘ DỮ LIỆU TỪ TAILIEU ===');

        DB::beginTransaction();
        try {
            // 1. Đồng bộ file A (15 mặt hàng)
            $this->importFile('TaiLieu/ThongTinHangHoa/du-lieu-mau-hoa-don-nhap-A-15-mat-hang.xlsx', 'Hóa đơn mẫu A');

            // 2. Đồng bộ file B (12 mặt hàng)
            $this->importFile('TaiLieu/ThongTinHangHoa/du-lieu-mau-hoa-don-nhap-B-12-mat-hang.xlsx', 'Hóa đơn mẫu B');

            // 3. Nếu các sản phẩm chưa có tồn kho, cấp tồn kho thực tế cho sản phẩm để hiển thị trên storefront
            if ($this->option('seed-stock')) {
                $this->assignInitialStock();
            }

            DB::commit();
            $this->info('✅ ĐỒNG BỘ DỮ LIỆU THÀNH CÔNG!');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Lỗi khi nạp dữ liệu: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return self::FAILURE;
        }
    }

    private function importFile(string $filePath, string $label): void
    {
        $fullPath = base_path($filePath);
        if (!file_exists($fullPath)) {
            $this->warn("Tệp không tồn tại: {$filePath}");
            return;
        }

        $this->info("Đang đọc {$label}: {$filePath}...");

        // Nhà cung cấp
        $supplierRows = $this->readSheetRows($fullPath, 'suppliers');
        foreach ($supplierRows as $row) {
            if (empty($row['id']) || empty($row['ten_nhacungcap'])) {
                continue;
            }
            $id = (int) $row['id'];
            NhaCungCap::updateOrCreate(
                ['id' => $id],
                [
                    'ma_so_thue' => (string) ($row['ma_so_thue'] ?? ''),
                    'ten_nhacungcap' => $row['ten_nhacungcap'],
                    'nguoi_lienhe' => $row['nguoi_lienhe'] ?? null,
                    'so_dienthoai' => $row['so_dienthoai'] ?? null,
                    'dia_chi' => $row['dia_chi'] ?? null,
                    'ghi_chu' => $row['ghi_chu'] ?? null,
                    'created_at' => $row['created_at'] ?? now(),
                    'updated_at' => $row['updated_at'] ?? now(),
                ]
            );
        }

        // Sản phẩm mới
        $productRows = $this->readSheetRows($fullPath, 'products');
        $addedProducts = 0;
        foreach ($productRows as $row) {
            if (empty($row['id']) || empty($row['sku'])) {
                continue;
            }
            $id = (int) $row['id'];
            $barcode = trim((string) ($row['barcode'] ?? ''));

            SanPham::updateOrCreate(
                ['id' => $id],
                [
                    'category_id' => (int) $row['category_id'],
                    'sku' => (string) $row['sku'],
                    'barcode' => $barcode === '' ? null : $barcode,
                    'ten_sanpham' => $row['ten_sanpham'],
                    'don_vi' => $row['don_vi'] ?? 'Cái',
                    'gia_nhap' => $this->parseNumber($row['gia_nhap']),
                    'gia_ban' => $this->parseNumber($row['gia_ban']),
                    'soluong' => 0,
                    'soluong_toithieu' => (int) ($row['soluong_toithieu'] ?? 5),
                    'hinh_anh' => $row['hinh_anh'] ?? null,
                    'trang_thai' => (bool) ($row['trang_thai'] ?? 1),
                    'created_at' => $row['created_at'] ?? now(),
                    'updated_at' => $row['updated_at'] ?? now(),
                ]
            );
            $addedProducts++;
        }
        $this->info("-> Cập nhật/thêm {$addedProducts} sản phẩm từ {$label}");

        // Hóa đơn nhập
        $invoiceRows = $this->readSheetRows($fullPath, 'purchase_invoices');
        $itemRows = $this->readSheetRows($fullPath, 'purchase_invoice_items');

        $defaultUser = User::first() ?? User::create([
            'name' => 'Quản trị viên',
            'email' => 'admin@example.com',
            'password' => bcrypt('123456'),
            'role' => 'admin'
        ]);

        foreach ($invoiceRows as $inv) {
            if (empty($inv['id']) || empty($inv['ma_phieu'])) {
                continue;
            }
            $invId = (int) $inv['id'];
            $userId = (int) ($inv['user_id'] ?? $defaultUser->id);
            if (!User::find($userId)) {
                $userId = $defaultUser->id;
            }

            $invoice = HoaDonNhap::updateOrCreate(
                ['id' => $invId],
                [
                    'ma_phieu' => $inv['ma_phieu'],
                    'so_hoadon' => (string) ($inv['so_hoadon'] ?? ''),
                    'ky_hieu' => $inv['ky_hieu'] ?? '1C26TMM',
                    'mau_so' => $inv['mau_so'] ?? '1',
                    'ngay_hoadon' => $inv['ngay_hoadon'] ?? now(),
                    'supplier_id' => (int) $inv['supplier_id'],
                    'user_id' => $userId,
                    'trang_thai' => 'nhap_tam',
                    'hinh_thuc_thanh_toan' => $inv['hinh_thuc_thanh_toan'] ?? 'chuyenkhoan',
                    'tong_tien_hang' => $this->parseNumber($inv['tong_tien_hang']),
                    'thue_suat' => $this->parseNumber($inv['thue_suat']),
                    'tien_thue' => $this->parseNumber($inv['tien_thue']),
                    'tong_thanh_toan' => $this->parseNumber($inv['tong_thanh_toan']),
                    'file_goc' => $inv['file_goc'] ?? null,
                    'ghi_chu' => $inv['ghi_chu'] ?? '',
                    'created_at' => $inv['created_at'] ?? now(),
                    'updated_at' => $inv['updated_at'] ?? now(),
                ]
            );

            // Chi tiết hóa đơn
            foreach ($itemRows as $item) {
                if (empty($item['id']) || (int) $item['purchase_invoice_id'] !== $invId) {
                    continue;
                }
                ChiTietHoaDonNhap::updateOrCreate(
                    ['id' => (int) $item['id']],
                    [
                        'purchase_invoice_id' => $invoice->id,
                        'stt' => (int) $item['stt'],
                        'product_id' => (int) $item['product_id'],
                        'ten_hang' => $item['ten_hang'],
                        'don_vi' => $item['don_vi'],
                        'soluong' => (int) $item['soluong'],
                        'don_gia' => $this->parseNumber($item['don_gia']),
                        'thanh_tien' => $this->parseNumber($item['thanh_tien']),
                    ]
                );
            }

            // Thực hiện xác nhận nhập kho để cập nhật tồn kho và tạo biến động kho
            try {
                $service = app(HoaDonNhapService::class);
                $service->xacNhanNhapKho($invoice, $defaultUser, Carbon::now());
                $this->info("-> Đã xác nhận nhập kho hóa đơn: {$invoice->ma_phieu}");
            } catch (\Throwable $e) {
                $this->warn("Không thể tự động duyệt kho cho {$invoice->ma_phieu}: " . $e->getMessage());
            }
        }
    }

    private function assignInitialStock(): void
    {
        $this->info('Đang kiểm tra và cấp tồn kho mẫu cho các sản phẩm chưa có tồn kho...');
        $products = SanPham::all();
        $updated = 0;
        foreach ($products as $p) {
            if ($p->soluong <= 0) {
                // Cấp tồn kho ngẫu nhiên từ 15 đến 60 cái
                $qty = rand(15, 60);
                $p->update(['soluong' => $qty]);
                $updated++;
            }
        }
        $this->info("-> Đã cấp tồn kho cho {$updated} sản phẩm.");
    }

    private function readSheetRows(string $file, string $sheetName): array
    {
        $reader = IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(true);
        $wb = $reader->load($file);
        $ws = $wb->getSheetByName($sheetName);
        if (!$ws) {
            return [];
        }
        $rows = $ws->toArray(null, true, true, true);
        if (empty($rows)) {
            return [];
        }
        $headers = array_map(static fn($v) => trim((string) $v), array_shift($rows));
        $res = [];
        foreach ($rows as $row) {
            $item = [];
            foreach ($headers as $col => $header) {
                if ($header !== '') {
                    $item[$header] = $row[$col] ?? null;
                }
            }
            $res[] = $item;
        }
        return $res;
    }

    private function parseNumber(mixed $val): int|float
    {
        if ($val === null || $val === '') {
            return 0;
        }
        $val = str_replace([',', ' '], '', (string) $val);
        return str_contains($val, '.') ? (float) $val : (int) $val;
    }
}
