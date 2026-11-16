<?php

namespace App\Exports;

use App\Models\DonHang;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class HoaDonExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly ?string $tuNgay, private readonly ?string $denNgay) {}

    public function collection(): Collection
    {
        return $this->query()->get()->map(fn (DonHang $donHang) => [
            $donHang->ma_hoadon, $donHang->created_at->format('Y-m-d H:i:s'),
            $donHang->user?->name ?? 'Đơn online', $donHang->tong_tien, $donHang->tien_giam,
            (float) ($donHang->phi_giao_hang ?? 0), $donHang->tong_thanh_toan, $donHang->phuong_thuc,
        ]);
    }

    public function headings(): array
    {
        return ['Mã hóa đơn', 'Thời gian', 'Thu ngân', 'Tổng tiền', 'Tiền giảm', 'Phí giao hàng', 'Thanh toán', 'Phương thức'];
    }

    private function query()
    {
        return DonHang::with('user')->where('trang_thai', 'da_thanh_toan')
            ->when($this->tuNgay, fn ($query) => $query->whereDate('created_at', '>=', $this->tuNgay))
            ->when($this->denNgay, fn ($query) => $query->whereDate('created_at', '<=', $this->denNgay))
            ->latest();
    }
}
