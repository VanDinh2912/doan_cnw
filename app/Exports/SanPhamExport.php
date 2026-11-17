<?php

namespace App\Exports;

use App\Exports\Concerns\FormulaSafeExport;
use App\Models\SanPham;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SanPhamExport implements FromCollection, WithHeadings, WithCustomValueBinder
{
    use FormulaSafeExport;

    protected array $cotEpChuoi = ['A', 'B']; // sku, barcode giu so 0 dau

    public function collection(): Collection
    {
        return SanPham::with(['loaiSanPham', 'hangSanXuat'])
            ->orderBy('ten_sanpham')
            ->get()
            ->map(fn (SanPham $sp): array => [
                $sp->sku,
                $sp->barcode,
                $sp->ten_sanpham,
                $sp->loaiSanPham?->ma_loai,
                $sp->hangSanXuat?->ten,
                $sp->don_vi,
                (float) $sp->gia_ban,
                (int) $sp->soluong,
                $sp->trang_thai ? 'Đang bán' : 'Ngừng bán',
            ]);
    }

    public function headings(): array
    {
        return ['sku', 'barcode', 'ten_sanpham', 'danh_muc', 'hang', 'don_vi', 'gia_ban', 'soluong', 'trang_thai'];
    }
}
