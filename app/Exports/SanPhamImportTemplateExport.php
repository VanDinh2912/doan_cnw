<?php

namespace App\Exports;

use App\Exports\Concerns\FormulaSafeExport;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * File mau import san pham, 2 sheet:
 *  - "DuLieu": CHI header, khong co dong du lieu -> neu lo upload nguyen
 *    file se bi tu choi ("thieu dong du lieu"), khong the tao SP rac.
 *    Cot A (sku) va B (barcode) ep kieu chuoi de giu so 0 dau.
 *  - "HuongDan": ghi chu cach dien + 1 dong vi du duoi dang chuoi.
 */
class SanPhamImportTemplateExport implements Export, WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new MauImportSheetDuLieu,
            new MauImportSheetHuongDan,
        ];
    }
}

class MauImportSheetDuLieu implements FromCollection, WithHeadings, WithTitle, WithCustomValueBinder
{
    use FormulaSafeExport;

    protected array $cotEpChuoi = ['A', 'B'];

    public function title(): string
    {
        return 'DuLieu';
    }

    public function headings(): array
    {
        return ['sku', 'barcode', 'ten_sanpham', 'danh_muc', 'hang', 'don_vi', 'gia_ban'];
    }

    public function collection(): Collection
    {
        return collect();
    }
}

class MauImportSheetHuongDan implements FromCollection, WithTitle
{
    public function title(): string
    {
        return 'HuongDan';
    }

    public function collection(): Collection
    {
        return collect([
            ['File mẫu import sản phẩm — điền dữ liệu vào sheet "DuLieu" rồi xóa sheet này cũng được.'],
            [''],
            ['CỘT', 'BẮT BUỘC', 'QUY TẮC'],
            ['sku', 'Có', 'Mã định danh duy nhất. Chữ+số, tối đa 50 ký tự. Trùng SKU = cập nhật SP cũ.'],
            ['barcode', 'Không', 'Mã vạch, tối đa 50 ký tự, không trùng SP khác. Để trống = giữ nguyên khi cập nhật.'],
            ['ten_sanpham', 'Có', 'Tên sản phẩm, tối đa 255 ký tự.'],
            ['danh_muc', 'Có', 'MÃ LOẠI (ma_loai), ví dụ CAP, CS, PIN — KHÔNG điền tên loại. Xem trang Loại sản phẩm.'],
            ['hang', 'Không', 'Tên hãng đúng như trong mục Hãng sản xuất, hãng phải đang kích hoạt. Để trống khi tạo mới = không gán hãng; khi cập nhật = giữ nguyên hãng cũ.'],
            ['don_vi', 'Có', 'Đơn vị tính, ví dụ: Cái, Hộp, Mét.'],
            ['gia_ban', 'Có', 'Giá bán, số nguyên dương (VNĐ), ví dụ 199000.'],
            [''],
            ['SKU chưa tồn tại → TẠO MỚI ở trạng thái NGỪNG BÁN, tồn 0, giá nhập 0 (phải nhập kho rồi bật Đang bán).'],
            ['SKU đã tồn tại → CẬP NHẬT các trường trên; KHÔNG đổi tồn kho, giá nhập, trạng thái bán.'],
            [''],
            ['VÍ DỤ (chỉ để tham khảo, sheet DuLieu đang trống):'],
            ['sku', 'barcode', 'ten_sanpham', 'danh_muc', 'hang', 'don_vi', 'gia_ban'],
            ['0011', '8936000000111', 'Cáp sạc nhanh 20W Type-C', 'CAP', 'Hoco', 'Cái', 199000],
            ['SP-002', '', 'Pin dự phòng 10000mAh', 'PIN', 'Borofone', 'Cái', 290000],
        ]);
    }
}
