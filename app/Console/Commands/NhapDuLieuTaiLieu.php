<?php

namespace App\Console\Commands;

use App\Models\KhachHang;
use App\Models\NhaCungCap;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class NhapDuLieuTaiLieu extends Command
{
    protected $signature = 'documents:import {--customers} {--suppliers}';
    protected $description = 'Nhập dữ liệu khách hàng và nhà cung cấp từ TaiLieu';

    public function handle(): int
    {
        $importCustomers = $this->option('customers') || (! $this->option('customers') && ! $this->option('suppliers'));
        $importSuppliers = $this->option('suppliers') || (! $this->option('customers') && ! $this->option('suppliers'));

        if ($importCustomers) {
            $this->importCustomers();
        }
        if ($importSuppliers) {
            $this->importSuppliers();
        }

        $this->warn('Chưa nhập đơn nhập hàng: file tham chiếu product_id 1–300, nhưng PDF hàng hóa không có đủ SKU, barcode và giá bán để tạo 300 sản phẩm an toàn.');
        $this->line('Các dòng đơn nhập cần được nhập sau khi có bảng đối chiếu sản phẩm chính thức.');

        return self::SUCCESS;
    }

    private function importCustomers(): void
    {
        $file = base_path('TaiLieu/ThongTinKhachHang/khach-hang-mau-100.xlsx');
        $rows = $this->sheetRows($file, 'customers');
        $inserted = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $phone = trim((string) ($row['so_dienthoai'] ?? ''));
            $name = trim((string) ($row['ho_ten'] ?? ''));
            if ($name === '' || $phone === '') {
                $skipped++;
                continue;
            }
            if (KhachHang::where('so_dienthoai', $phone)->exists()) {
                $skipped++;
                continue;
            }
            KhachHang::create([
                'ho_ten' => $name,
                'so_dienthoai' => $phone,
                'dia_chi' => $row['dia_chi'] ?? null,
                'diem_tichluy' => max(0, (int) ($row['diem_tichluy'] ?? 0)),
                'created_at' => $row['created_at'] ?? now(),
                'updated_at' => $row['updated_at'] ?? now(),
            ]);
            $inserted++;
        }

        $this->info("Khách hàng: thêm {$inserted}, bỏ qua {$skipped}.");
    }

    private function importSuppliers(): void
    {
        $file = base_path('TaiLieu/ThongTinNhaCungCap/nha-cung-cap-va-don-nhap-mau.xlsx');
        $rows = $this->sheetRows($file, 'suppliers');
        $inserted = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $name = trim((string) ($row['ten_nhacungcap'] ?? ''));
            $phone = trim((string) ($row['so_dienthoai'] ?? ''));
            if ($name === '') {
                $skipped++;
                continue;
            }
            $query = NhaCungCap::where('ten_nhacungcap', $name);
            if ($phone !== '') {
                $query->where('so_dienthoai', $phone);
            }
            if ($query->exists()) {
                $skipped++;
                continue;
            }
            NhaCungCap::create([
                'ten_nhacungcap' => $name,
                'nguoi_lienhe' => $row['nguoi_lienhe'] ?? null,
                'so_dienthoai' => $phone ?: null,
                'dia_chi' => $row['dia_chi'] ?? null,
                'ghi_chu' => $row['ghi_chu'] ?? null,
                'created_at' => $row['created_at'] ?? now(),
                'updated_at' => $row['updated_at'] ?? now(),
            ]);
            $inserted++;
        }

        $this->info("Nhà cung cấp: thêm {$inserted}, bỏ qua {$skipped}.");
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sheetRows(string $file, string $sheetName): array
    {
        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getSheetByName($sheetName);
        if ($sheet === null) {
            throw new \RuntimeException("Không tìm thấy sheet {$sheetName} trong {$file}.");
        }
        $rows = $sheet->toArray(null, true, true, true);
        $headers = array_map(static fn ($value): string => trim((string) $value), array_shift($rows));
        $result = [];
        foreach ($rows as $row) {
            $item = [];
            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $item[$header] = $row[$index] ?? null;
                }
            }
            $result[] = $item;
        }
        return $result;
    }
}
