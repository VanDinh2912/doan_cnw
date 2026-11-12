<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class TepAnhSanPham
{
    /**
     * Chỉ xóa tệp cục bộ nằm trong các thư mục ảnh sản phẩm.
     * URL và đường dẫn lạ được bỏ qua để không xóa nhầm dữ liệu ngoài phạm vi.
     */
    public static function xoaNeuLaTepCucBo(?string $duongDan): void
    {
        if ($duongDan === null || $duongDan === '') {
            return;
        }
        if (str_contains($duongDan, '://') || str_contains($duongDan, '..') || str_contains($duongDan, '\\')) {
            return;
        }

        if (str_starts_with($duongDan, 'sanpham/') || str_starts_with($duongDan, 'products/')) {
            Storage::disk('public')->delete($duongDan);
            return;
        }

        if (str_starts_with($duongDan, 'storage/products/')) {
            Storage::disk('public')->delete(substr($duongDan, strlen('storage/')));
            return;
        }

        if (str_starts_with($duongDan, 'upload/')) {
            $thuMuc = realpath(public_path('upload'));
            $tep = realpath(public_path($duongDan));
            if ($thuMuc !== false && $tep !== false
                && str_starts_with($tep, $thuMuc.DIRECTORY_SEPARATOR)
                && is_file($tep)) {
                @unlink($tep);
            }
        }
    }
}