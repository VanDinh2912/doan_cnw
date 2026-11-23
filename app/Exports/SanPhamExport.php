<?php

namespace App\Exports;

use App\Exports\Concerns\FormulaSafeExport;
use App\Models\SanPham;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
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
            ->map(function (SanPham $sp): array {
                // GD1: cot hinh_anh_url — uu tien nguon goc, fallback URL https cu,
                // cuoi cung dung APP_URL + duong dan tep cuc bo.
                $anhUrl = null;
                $nguon = $sp->getAttribute('hinh_anh_nguon');
                if (is_string($nguon) && $nguon !== '') {
                    $anhUrl = $nguon;
                } elseif (is_string($sp->hinh_anh) && str_starts_with($sp->hinh_anh, 'http')) {
                    $anhUrl = $sp->hinh_anh;
                } elseif (is_string($sp->hinh_anh) && $sp->hinh_anh !== '') {
                    $url = Storage::disk('public')->url($sp->hinh_anh);
                    $anhUrl = str_starts_with($url, 'http') ? $url : rtrim((string) config('app.url'), '/').$url;
                }

                return [
                    $sp->sku,
                    $sp->barcode,
                    $sp->ten_sanpham,
                    $sp->loaiSanPham?->ma_loai,
                    $sp->hangSanXuat?->ten,
                    $sp->loaiSanPham?->ten_loai,
                    $sp->don_vi,
                    (float) $sp->gia_ban,
                    (int) $sp->soluong,
                    $sp->trang_thai ? 'Đang bán' : 'Ngừng bán',
                    $anhUrl,
                ];
            });
    }

    public function headings(): array
    {
        // ten_loai/soluong/trang_thai la cot CHI DOC — import bo qua.
        // hinh_anh_url: nhap lai duoc (tai ve cuc bo khi xac nhan).
        return ['sku', 'barcode', 'ten_sanpham', 'danh_muc', 'hang', 'ten_loai', 'don_vi', 'gia_ban', 'soluong', 'trang_thai', 'hinh_anh_url'];
    }
}
