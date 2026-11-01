<?php

namespace Database\Seeders;

use App\Models\ChiTietHoaDonNhap;
use App\Models\HoaDonNhap;
use App\Models\KhachHang;
use App\Models\LoaiSanPham;
use App\Models\NhaCungCap;
use App\Models\SanPham;
use App\Models\User;
use App\Services\HoaDonNhapService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class DuLieuMau300Seeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedCategories();
            $this->seedProducts();
            $this->seedSuppliers();
            $this->seedCustomers();
            $this->seedPurchaseInvoice();
        });
    }

    private function seedCategories(): void
    {
        foreach ($this->rows('TaiLieu/ThongTinHangHoa/LoaiHangHoa.xlsx', 'categories') as $row) {
            LoaiSanPham::create([
                'id' => (int) $row['id'], 'ma_loai' => $row['ma_loai'], 'ten_loai' => $row['ten_loai'],
                'mo_ta' => $row['mo_ta'] ?: null, 'tu_khoa' => $row['tu_khoa'] ?: null,
                'he_so_lai' => $this->number($row['he_so_lai']),
                'created_at' => $row['created_at'], 'updated_at' => $row['updated_at'],
            ]);
        }
    }

    private function seedProducts(): void
    {
        foreach ($this->rows('TaiLieu/ThongTinHangHoa/LoaiHangHoa.xlsx', 'products') as $row) {
            SanPham::create([
                'id' => (int) $row['id'], 'category_id' => (int) $row['category_id'], 'sku' => (string) $row['sku'],
                'barcode' => trim((string) $row['barcode']) === '' ? null : (string) $row['barcode'],
                'ten_sanpham' => $row['ten_sanpham'], 'don_vi' => $row['don_vi'],
                'gia_nhap' => $this->number($row['gia_nhap']), 'gia_ban' => $this->number($row['gia_ban']),
                'soluong' => 0, 'soluong_toithieu' => (int) $row['soluong_toithieu'],
                'hinh_anh' => $row['hinh_anh'] ?: null, 'trang_thai' => (bool) $row['trang_thai'],
                'created_at' => $row['created_at'], 'updated_at' => $row['updated_at'],
            ]);
        }
    }

    private function seedSuppliers(): void
    {
        foreach ($this->rows('TaiLieu/ThongTinHangHoa/LoaiHangHoa.xlsx', 'suppliers') as $row) {
            NhaCungCap::create([
                'id' => (int) $row['id'], 'ma_so_thue' => (string) $row['ma_so_thue'],
                'ten_nhacungcap' => $row['ten_nhacungcap'], 'nguoi_lienhe' => $row['nguoi_lienhe'] ?: null,
                'so_dienthoai' => $row['so_dienthoai'] ?: null, 'dia_chi' => $row['dia_chi'] ?: null,
                'ghi_chu' => $row['ghi_chu'] ?: null, 'created_at' => $row['created_at'], 'updated_at' => $row['updated_at'],
            ]);
        }
    }

    private function seedCustomers(): void
    {
        foreach ($this->rows('TaiLieu/ThongTinKhachHang/khach-hang-mau-100.xlsx', 'customers') as $row) {
            KhachHang::create([
                'ho_ten' => $row['ho_ten'], 'so_dienthoai' => (string) $row['so_dienthoai'],
                'dia_chi' => $row['dia_chi'] ?: null, 'diem_tichluy' => 0,
                'created_at' => $row['created_at'], 'updated_at' => $row['updated_at'],
            ]);
        }
    }

    private function seedPurchaseInvoice(): void
    {
        $rows = $this->rows('TaiLieu/ThongTinHangHoa/LoaiHangHoa.xlsx', 'purchase_invoices');
        $source = $rows[0];
        $invoice = HoaDonNhap::create([
            'id' => (int) $source['id'], 'ma_phieu' => $source['ma_phieu'], 'so_hoadon' => (string) $source['so_hoadon'],
            'ky_hieu' => $source['ky_hieu'], 'mau_so' => $source['mau_so'] ?: null,
            'ngay_hoadon' => $source['ngay_hoadon'], 'supplier_id' => (int) $source['supplier_id'],
            'user_id' => (int) $source['user_id'], 'trang_thai' => 'nhap_tam', 'hinh_thuc_thanh_toan' => $source['hinh_thuc_thanh_toan'],
            'tong_tien_hang' => $this->number($source['tong_tien_hang']), 'thue_suat' => $this->number($source['thue_suat']),
            'tien_thue' => $this->number($source['tien_thue']), 'tong_thanh_toan' => $this->number($source['tong_thanh_toan']),
            'file_goc' => 'hoadonnhap/hoa-don-mau-300-mat-hang-phu-kien-dien-thoai.pdf',
            'ghi_chu' => $source['ghi_chu'], 'created_at' => $source['created_at'], 'updated_at' => $source['updated_at'],
        ]);
        foreach ($this->rows('TaiLieu/ThongTinHangHoa/LoaiHangHoa.xlsx', 'purchase_invoice_items') as $row) {
            ChiTietHoaDonNhap::create([
                'id' => (int) $row['id'], 'purchase_invoice_id' => $invoice->id, 'stt' => (int) $row['stt'],
                'product_id' => (int) $row['product_id'], 'ten_hang' => $row['ten_hang'], 'don_vi' => $row['don_vi'],
                'soluong' => (int) $row['soluong'], 'don_gia' => $this->number($row['don_gia']),
                'thanh_tien' => $this->number($row['thanh_tien']),
            ]);
        }
        Storage::disk('public')->put(
            'hoadonnhap/hoa-don-mau-300-mat-hang-phu-kien-dien-thoai.pdf',
            file_get_contents(base_path('TaiLieu/ThongTinHangHoa/hoa-don-mau-300-mat-hang-phu-kien-dien-thoai.pdf'))
        );
        $luc = Carbon::parse($source['ngay_nhap_kho']);
        app(HoaDonNhapService::class)->xacNhanNhapKho($invoice, User::findOrFail((int) $source['nguoi_nhap_id']), $luc);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(string $file, string $sheet): array
    {
        $worksheet = IOFactory::load(base_path($file))->getSheetByName($sheet);
        if ($worksheet === null) {
            throw new \RuntimeException("Thiếu sheet {$sheet} trong {$file}.");
        }
        $rows = $worksheet->toArray(null, true, true, true);
        $headers = array_map(static fn ($value): string => trim((string) $value), array_shift($rows));
        $result = [];
        foreach ($rows as $row) {
            $item = [];
            foreach ($headers as $column => $header) {
                if ($header !== '') {
                    $item[$header] = $row[$column] ?? null;
                }
            }
            $result[] = $item;
        }
        return $result;
    }

    private function number(mixed $value): int|float
    {
        $value = str_replace(',', '', (string) $value);
        return str_contains($value, '.') ? (float) $value : (int) $value;
    }
}
