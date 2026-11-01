<?php

namespace App\Services;

use App\Models\BienDongKho;
use App\Models\ChiTietHoaDonNhap;
use App\Models\HoaDonNhap;
use App\Models\SanPham;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class HoaDonNhapService
{
    /**
     * @return list<string>
     */
    public function xacNhanNhapKho(HoaDonNhap $hoaDon, User $nguoi, ?Carbon $luc = null): array
    {
        try {
            return DB::transaction(function () use ($hoaDon, $nguoi, $luc): array {
                $hoaDon = HoaDonNhap::query()->lockForUpdate()->with(['nhaCungCap', 'chiTiets'])->findOrFail($hoaDon->id);
                $this->kiemTraHoaDon($hoaDon);
                if ($hoaDon->trang_thai !== 'nhap_tam') {
                    throw new RuntimeException('Hóa đơn chỉ được xác nhận một lần.');
                }

                $warnings = [];
                foreach ($hoaDon->chiTiets->sortBy('product_id') as $item) {
                    $product = $item->product_id
                        ? SanPham::query()->lockForUpdate()->findOrFail($item->product_id)
                        : $this->taoSanPham($item);
                    if ((float) $product->gia_nhap > 0 && abs($item->don_gia - $product->gia_nhap) / $product->gia_nhap > 0.1) {
                        $warnings[] = "Giá nhập {$product->sku} lệch trên 10%.";
                    }
                    if ($product->gia_ban < $item->don_gia) {
                        $warnings[] = "Giá bán {$product->sku} thấp hơn giá nhập mới.";
                    }
                    $product->increment('soluong', $item->soluong);
                    $product->update(['gia_nhap' => $item->don_gia]);
                    $item->update(['product_id' => $product->id]);
                    BienDongKho::create([
                        'product_id' => $product->id,
                        'user_id' => $nguoi->id,
                        'invoice_id' => null,
                        'purchase_invoice_id' => $hoaDon->id,
                        'loai' => 'nhap',
                        'soluong' => $item->soluong,
                        'ghi_chu' => 'Nhập kho từ hóa đơn nhập '.$hoaDon->ma_phieu,
                        'created_at' => $luc ?? now(),
                        'updated_at' => $luc ?? now(),
                    ]);
                }
                $hoaDon->update([
                    'trang_thai' => 'da_nhap_kho',
                    'nguoi_nhap_id' => $nguoi->id,
                    'ngay_nhap_kho' => $luc ?? now(),
                ]);
                return $warnings;
            });
        } catch (\Throwable $exception) {
            Log::error('Xác nhận hóa đơn nhập thất bại', ['hoa_don_id' => $hoaDon->id, 'error' => $exception->getMessage()]);
            throw $exception;
        }
    }

    private function kiemTraHoaDon(HoaDonNhap $hoaDon): void
    {
        if (! $hoaDon->nhaCungCap?->ten_nhacungcap || ! $hoaDon->nhaCungCap->ma_so_thue) {
            throw new RuntimeException('Vui lòng bổ sung mã số thuế nhà cung cấp trước.');
        }
        if (! $hoaDon->so_hoadon || ! $hoaDon->ky_hieu || ! $hoaDon->ngay_hoadon || $hoaDon->ngay_hoadon->isFuture()) {
            throw new RuntimeException('Hóa đơn thiếu thông tin hoặc ngày hóa đơn không hợp lệ.');
        }
        if (! in_array((float) $hoaDon->thue_suat, [0.0, 5.0, 8.0, 10.0], true)) {
            throw new RuntimeException('Thuế suất không hợp lệ.');
        }
        if ($hoaDon->chiTiets->isEmpty()) {
            throw new RuntimeException('Hóa đơn phải có ít nhất một mặt hàng.');
        }
        $seen = [];
        $total = 0;
        foreach ($hoaDon->chiTiets as $item) {
            if ($item->soluong < 1 || $item->don_gia < 0 || ! $item->ten_hang || ! $item->don_vi) {
                throw new RuntimeException('Chi tiết hóa đơn không hợp lệ.');
            }
            if ($item->product_id && isset($seen[$item->product_id])) {
                throw new RuntimeException('Hóa đơn có sản phẩm trùng dòng.');
            }
            if ($item->product_id) {
                $seen[$item->product_id] = true;
            }
            $line = $item->soluong * $item->don_gia;
            if ((int) $item->thanh_tien !== $line) {
                throw new RuntimeException('Thành tiền hóa đơn không khớp.');
            }
            $total += $line;
        }
        $tax = (int) round($total * ((float) $hoaDon->thue_suat) / 100);
        if ((int) $hoaDon->tong_tien_hang !== $total || (int) $hoaDon->tien_thue !== $tax || (int) $hoaDon->tong_thanh_toan !== $total + $tax) {
            throw new RuntimeException('Tổng tiền hóa đơn không khớp chi tiết.');
        }
    }

    private function taoSanPham(ChiTietHoaDonNhap $item): SanPham
    {
        if (! $item->category_id) {
            throw new RuntimeException('Dòng hàng chưa có sản phẩm hoặc loại sản phẩm.');
        }
        $category = $item->loai()->lockForUpdate()->firstOrFail();
        $next = SanPham::where('category_id', $category->id)->lockForUpdate()->count() + 1;
        $sku = sprintf('%s-%04d', $category->ma_loai, $next);
        while (SanPham::where('sku', $sku)->exists()) {
            $next++;
            $sku = sprintf('%s-%04d', $category->ma_loai, $next);
        }
        $giaBan = $item->gia_ban_de_xuat ?: (int) (ceil(($item->don_gia * (float) $category->he_so_lai) / 1000) * 1000);
        return SanPham::create([
            'category_id' => $category->id, 'sku' => $sku, 'ten_sanpham' => $item->ten_hang,
            'don_vi' => $item->don_vi, 'gia_nhap' => $item->don_gia, 'gia_ban' => $giaBan,
            'soluong' => 0, 'soluong_toithieu' => 5, 'trang_thai' => true,
        ]);
    }
}
