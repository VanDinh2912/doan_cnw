<?php

namespace App\Exports;

use App\Models\SanPham;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class XuatNhapTonExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly string $tuNgay, private readonly string $denNgay) {}

    public function collection(): Collection
    {
        return SanPham::with(['bienDongKhos' => fn ($query) => $query->whereBetween('created_at', [$this->tuNgay.' 00:00:00', $this->denNgay.' 23:59:59'])])
            ->orderBy('ten_sanpham')->get()->map(function (SanPham $sanPham): array {
                $movements = $sanPham->bienDongKhos;
                $nhap = $movements->where('loai', 'nhap')->sum('soluong');
                $xuat = -$movements->where('loai', 'ban')->sum('soluong');
                $dieuchinh = $movements->where('loai', 'dieuchinh')->sum('soluong');
                return [$sanPham->sku, $sanPham->ten_sanpham, $sanPham->soluong - $nhap + $xuat - $dieuchinh, $nhap, $xuat, $dieuchinh, $sanPham->soluong];
            });
    }

    public function headings(): array
    {
        return ['SKU', 'Sản phẩm', 'Tồn đầu', 'Nhập', 'Xuất', 'Điều chỉnh', 'Tồn cuối'];
    }
}
