<?php

namespace App\Exports;

use App\Exports\Concerns\FormulaSafeExport;
use App\Models\Brand;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;

class BrandExport implements FromCollection, WithHeadings, WithCustomValueBinder
{
    use FormulaSafeExport;

    public function collection(): Collection
    {
        return Brand::withCount('sanPhams')
            ->orderBy('ten')
            ->get()
            ->map(fn (Brand $hang): array => [
                $hang->ten,
                $hang->slug,
                $hang->kich_hoat ? 'Đang bán' : 'Ngừng',
                $hang->san_phams_count,
            ]);
    }

    public function headings(): array
    {
        return ['Tên hãng', 'Slug', 'Trạng thái', 'Số sản phẩm'];
    }
}
