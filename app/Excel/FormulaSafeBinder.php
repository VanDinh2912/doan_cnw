<?php

namespace App\Excel;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/**
 * Value binder an toan cho Excel: chuoi bat dau bang = + - @ (hoac tab/CR)
 * bi Excel hieu la cong thuc khi mo file -> ep kieu TYPE_STRING de hien
 * thi nguyen van, chong CSV/Excel formula injection.
 *
 * Cot truyen vao $forceTextCols luon duoc ghi kieu chuoi (giu so 0 dau
 * cho sku/barcode trong file mau import).
 */
class FormulaSafeBinder extends DefaultValueBinder
{
    /** @param array<int, string> $forceTextCols ky tu cot (A, B, ...) */
    public function __construct(private readonly array $forceTextCols = []) {}

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if ($value === null) {
            return parent::bindValue($cell, $value);
        }

        if (in_array($cell->getColumn(), $this->forceTextCols, true)) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        if (is_string($value) && $value !== '' && preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
