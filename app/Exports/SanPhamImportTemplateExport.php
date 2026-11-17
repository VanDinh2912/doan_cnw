<?php

namespace App\Exports;

use App\Exports\Concerns\FormulaSafeExport;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * File mau import san pham: 7 cot dung thu tu header ma importer doi hoi.
 * Cot A (sku) va B (barcode) duoc ep kieu chuoi de giu so 0 dau.
 */
class SanPhamImportTemplateExport implements FromCollection, WithHeadings, WithCustomValueBinder
{
    use FormulaSafeExport;

    protected array $cotEpChuoi = ['A', 'B'];

    public function collection(): Collection
    {
        return collect([
            ['0011', '8936000000111', 'Cáp sạc nhanh 20W Type-C', 'CAP', 'Hoco', 'Cái', 199000],
            ['SP-MAU-002', '', 'Pin dự phòng 10000mAh', 'PIN', 'Borofone', 'Cái', 290000],
        ]);
    }

    public function headings(): array
    {
        return ['sku', 'barcode', 'ten_sanpham', 'danh_muc', 'hang', 'don_vi', 'gia_ban'];
    }
}
