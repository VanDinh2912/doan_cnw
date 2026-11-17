<?php

namespace App\Exports\Concerns;

use App\Excel\FormulaSafeBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;

/**
 * Dung cho export can WithCustomValueBinder: uy quyen bindValue cho
 * FormulaSafeBinder (chong formula injection). Lop export co the dinh
 * nghia `protected array $cotEpChuoi = ['A','B']` de ep kieu chuoi
 * cho cac cot nhay (sku/barcode giu so 0 dau).
 */
trait FormulaSafeExport
{
    private ?FormulaSafeBinder $formulaSafeBinder = null;

    public function bindValue(Cell $cell, mixed $value): bool
    {
        $cotEpChuoi = $this->cotEpChuoi ?? [];
        $this->formulaSafeBinder ??= new FormulaSafeBinder($cotEpChuoi);

        return $this->formulaSafeBinder->bindValue($cell, $value);
    }
}
