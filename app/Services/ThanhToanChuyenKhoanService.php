<?php

namespace App\Services;

use App\Models\BienDongKho;
use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\SanPham;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ThanhToanChuyenKhoanService
{
    /**
     * Tạo hóa đơn ở trạng thái cho_thanh_toan và giữ hàng ngay lập tức.
     *
     * @param array $gioHang Danh sách sản phẩm trong giỏ [['id' => 1, 'soluong' => 2], ...]
     * @param array $thongTinKhach Thông tin giảm giá, customer_id, diem_su_dung
     * @param mixed $user Người thực hiện (thu ngân / admin)
     */
    public function taoHoaDonChoThanhToan(array $gioHang, array $thongTinKhach, $user): DonHang
    {
        $gioHangSorted = collect($gioHang)
            ->sortBy(fn (array $item, string|int $key): int => (int) ($item['id'] ?? $key))
            ->all();

        if (empty($gioHangSorted)) {
            throw new RuntimeException('Giỏ hàng đang trống.');
        }

        return DB::transaction(function () use ($gioHangSorted, $thongTinKhach, $user): DonHang {
            $tongTien = 0;
            $sanPhams = [];

            // 1. Khóa sản phẩm theo thứ tự product_id tăng dần để tránh deadlock
            foreach ($gioHangSorted as $item) {
                $sanPham = SanPham::query()->lockForUpdate()->find($item['id']);
                if (! $sanPham || ! $sanPham->trang_thai || $item['soluong'] > $sanPham->soluong) {
                    throw new RuntimeException('Tồn kho đã thay đổi, vui lòng kiểm tra lại giỏ hàng.');
                }

                $soLuong = (int) $item['soluong'];
                $thanhTien = (float) $sanPham->gia_ban * $soLuong;
                $tongTien += $thanhTien;
                $sanPhams[] = [$sanPham, $soLuong, $thanhTien];
            }

            // 2. Tính giảm giá & khóa khách hàng
            $giamGiaPhanTram = min(100, max(0, (float) ($thongTinKhach['giam_gia_phantram'] ?? 0)));
            $tienGiam = round($tongTien * $giamGiaPhanTram / 100);

            $khachHang = null;
            $diemSuDung = (int) ($thongTinKhach['diem_su_dung'] ?? 0);
            $tienGiamDiem = 0;

            if (! empty($thongTinKhach['customer_id'])) {
                $khachHang = KhachHang::query()->lockForUpdate()->findOrFail($thongTinKhach['customer_id']);
                if ($diemSuDung > $khachHang->diem_tichluy) {
                    throw new RuntimeException('Số điểm sử dụng vượt quá điểm hiện có.');
                }
                $tienGiamDiem = $diemSuDung * config('pos.gia_tri_diem');
                if ($tienGiam + $tienGiamDiem > $tongTien) {
                    throw new RuntimeException('Số điểm sử dụng vượt quá giá trị hóa đơn.');
                }
            } elseif ($diemSuDung > 0) {
                throw new RuntimeException('Cần chọn khách hàng trước khi dùng điểm.');
            }

            $tongThanhToan = $tongTien - $tienGiam - $tienGiamDiem;
            $diemTichLuyNhan = $khachHang ? (int) floor($tongThanhToan / config('pos.diem_moi_dong')) : 0;

            // 3. Sinh mã hóa đơn S7
            $maHoaDon = $this->taoMaHoaDon();

            // 4. Tạo hóa đơn trạng thái cho_thanh_toan
            $donHang = DonHang::create([
                'ma_hoadon' => $maHoaDon,
                'user_id' => $user->id,
                'customer_id' => $khachHang?->id,
                'tong_tien' => $tongTien,
                'giam_gia_phantram' => $giamGiaPhanTram,
                'tien_giam' => $tienGiam,
                'diem_su_dung' => $diemSuDung,
                'tien_giam_diem' => $tienGiamDiem,
                'diem_tichluy_nhan' => $diemTichLuyNhan,
                'tong_thanh_toan' => $tongThanhToan,
                'tien_khach_dua' => 0,
                'tien_thua' => 0,
                'phuong_thuc' => 'chuyenkhoan',
                'trang_thai' => 'cho_thanh_toan',
                'thoi_gian_thanh_toan' => null,
            ]);

            // 5. Trừ tồn kho và ghi movement loại ban ngay lập tức (giữ hàng)
            foreach ($sanPhams as [$sanPham, $soLuong, $thanhTien]) {
                ChiTietDonHang::create([
                    'invoice_id' => $donHang->id,
                    'product_id' => $sanPham->id,
                    'soluong' => $soLuong,
                    'don_gia' => $sanPham->gia_ban,
                    'gia_von' => $sanPham->gia_nhap,
                    'thanh_tien' => $thanhTien,
                ]);

                $sanPham->decrement('soluong', $soLuong);

                BienDongKho::create([
                    'product_id' => $sanPham->id,
                    'user_id' => $user->id,
                    'invoice_id' => $donHang->id,
                    'loai' => 'ban',
                    'soluong' => -$soLuong,
                    'ghi_chu' => 'Bán hàng '.$donHang->ma_hoadon,
                ]);
            }

            // 6. Trừ điểm đã dùng của khách (điểm thưởng nhận chỉ cộng khi da_thanh_toan)
            if ($khachHang && $diemSuDung > 0) {
                $khachHang->decrement('diem_tichluy', $diemSuDung);
            }

            return $donHang;
        }, 2);
    }

    /**
     * Hoàn tất thanh toán chuyển khoản: chuyển trạng thái da_thanh_toan, cộng điểm tích lũy.
     * Dùng chung cho cả Webhook tự động và nút bấm xác nhận thủ công của thu ngân.
     *
     * Thứ tự khóa: sản phẩm (product_id tăng dần) -> khách hàng -> hóa đơn.
     *
     * @return array ['success' => bool, 'already_processed' => bool, 'don_hang' => ?DonHang, 'message' => string]
     */
    public function hoanTatThanhToan(DonHang $donHang, ?string $maGiaoDich = null, $nguoi = null, ?string $ghiChu = null): array
    {
        $invoiceId = $donHang->id;

        // Đọc không khóa để lấy danh sách cần khóa theo thứ tự chuẩn
        $unlocked = DonHang::with('chiTietDonHangs')->find($invoiceId);
        if (! $unlocked) {
            return [
                'success' => false,
                'already_processed' => true,
                'don_hang' => null,
                'message' => 'Hóa đơn không tồn tại.',
            ];
        }

        if ($unlocked->trang_thai !== 'cho_thanh_toan') {
            return [
                'success' => false,
                'already_processed' => true,
                'don_hang' => $unlocked,
                'message' => 'Hóa đơn đã được xử lý trước đó (trạng thái: '.$unlocked->trang_thai.').',
            ];
        }

        $customerId = $unlocked->customer_id;
        $productIds = $unlocked->chiTietDonHangs->pluck('product_id')->sort()->unique()->values()->all();

        return DB::transaction(function () use ($invoiceId, $customerId, $productIds, $maGiaoDich, $ghiChu): array {
            // 1. Khóa sản phẩm theo product_id tăng dần
            if (! empty($productIds)) {
                SanPham::whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get();
            }

            // 2. Khóa khách hàng
            $customer = null;
            if ($customerId) {
                $customer = KhachHang::where('id', $customerId)->lockForUpdate()->first();
            }

            // 3. Khóa hóa đơn và kiểm tra lại trạng thái
            $lockedInvoice = DonHang::where('id', $invoiceId)->lockForUpdate()->first();
            if (! $lockedInvoice || $lockedInvoice->trang_thai !== 'cho_thanh_toan') {
                return [
                    'success' => false,
                    'already_processed' => true,
                    'don_hang' => $lockedInvoice,
                    'message' => 'Hóa đơn đã được xử lý trước đó.',
                ];
            }

            // 4. Cập nhật trạng thái da_thanh_toan
            $updateData = [
                'trang_thai' => 'da_thanh_toan',
                'thoi_gian_thanh_toan' => now(),
                'tien_khach_dua' => $lockedInvoice->tong_thanh_toan,
                'tien_thua' => 0,
            ];

            if ($maGiaoDich) {
                $updateData['ma_giaodich'] = $maGiaoDich;
                $updateData['ma_giao_dich'] = $maGiaoDich;
            }

            if ($ghiChu) {
                $updateData['ghi_chu'] = $lockedInvoice->ghi_chu
                    ? $lockedInvoice->ghi_chu.' | '.$ghiChu
                    : $ghiChu;
            }

            $lockedInvoice->update($updateData);

            // 5. Cộng điểm tích lũy nhận (đúng 1 lần)
            if ($customer && $lockedInvoice->diem_tichluy_nhan > 0) {
                $customer->increment('diem_tichluy', $lockedInvoice->diem_tichluy_nhan);
            }

            return [
                'success' => true,
                'already_processed' => false,
                'don_hang' => $lockedInvoice,
                'message' => 'Xác nhận thanh toán thành công.',
            ];
        }, 2);
    }

    /**
     * Hủy hóa đơn chờ thanh toán: hoàn kho bằng movement loại dieuchinh (soluong dương),
     * hoàn điểm đã dùng của khách, đặt trạng thái da_huy.
     *
     * Thứ tự khóa: sản phẩm (product_id tăng dần) -> khách hàng -> hóa đơn.
     *
     * @return array ['success' => bool, 'don_hang' => ?DonHang, 'message' => string]
     */
    public function huyChoThanhToan(DonHang $donHang, string $lyDo = 'Hủy chờ thanh toán'): array
    {
        $invoiceId = $donHang->id;

        $unlocked = DonHang::with('chiTietDonHangs')->find($invoiceId);
        if (! $unlocked || $unlocked->trang_thai !== 'cho_thanh_toan') {
            return [
                'success' => false,
                'don_hang' => $unlocked,
                'message' => 'Chỉ có thể hủy hóa đơn ở trạng thái chờ thanh toán.',
            ];
        }

        $customerId = $unlocked->customer_id;
        $chiTiets = $unlocked->chiTietDonHangs;
        $productIds = $chiTiets->pluck('product_id')->sort()->unique()->values()->all();

        return DB::transaction(function () use ($invoiceId, $customerId, $productIds, $chiTiets, $lyDo): array {
            // 1. Khóa sản phẩm theo thứ tự tăng dần
            $products = collect();
            if (! empty($productIds)) {
                $products = SanPham::whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            }

            // 2. Khóa khách hàng
            $customer = null;
            if ($customerId) {
                $customer = KhachHang::where('id', $customerId)->lockForUpdate()->first();
            }

            // 3. Khóa hóa đơn và kiểm tra lại trạng thái
            $lockedInvoice = DonHang::where('id', $invoiceId)->lockForUpdate()->first();
            if (! $lockedInvoice || $lockedInvoice->trang_thai !== 'cho_thanh_toan') {
                return [
                    'success' => false,
                    'don_hang' => $lockedInvoice,
                    'message' => 'Hóa đơn không ở trạng thái chờ thanh toán.',
                ];
            }

            $userId = auth()->id() ?? $lockedInvoice->user_id;

            // 4. Hoàn kho: cộng lại tồn và ghi movement dieuchinh số lượng dương
            foreach ($chiTiets as $item) {
                $sanPham = $products->get($item->product_id);
                if ($sanPham) {
                    $sanPham->increment('soluong', $item->soluong);
                }

                BienDongKho::create([
                    'product_id' => $item->product_id,
                    'user_id' => $userId,
                    'invoice_id' => $lockedInvoice->id,
                    'loai' => 'dieuchinh',
                    'soluong' => $item->soluong, // số lượng dương
                    'ghi_chu' => $lyDo,
                ]);
            }

            // 5. Hoàn điểm đã dùng cho khách
            if ($customer && $lockedInvoice->diem_su_dung > 0) {
                $customer->increment('diem_tichluy', $lockedInvoice->diem_su_dung);
            }

            // 6. Đổi trạng thái sang da_huy
            $lockedInvoice->update([
                'trang_thai' => 'da_huy',
                'ghi_chu' => $lockedInvoice->ghi_chu ? $lockedInvoice->ghi_chu.' | '.$lyDo : $lyDo,
            ]);

            return [
                'success' => true,
                'don_hang' => $lockedInvoice,
                'message' => 'Đã hủy hóa đơn chờ thanh toán và hoàn trả tồn kho, điểm.',
            ];
        }, 2);
    }

    /**
     * Sinh mã hóa đơn theo định dạng HD{YYYYMMDD}-{XXXX}
     */
    private function taoMaHoaDon(): string
    {
        $ngay = now()->format('Ymd');
        $cuoiCung = DonHang::query()
            ->where('ma_hoadon', 'like', 'HD'.$ngay.'-%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('ma_hoadon');
        $soThuTu = $cuoiCung ? ((int) substr($cuoiCung, -4)) + 1 : 1;

        return sprintf('HD%s-%04d', $ngay, $soThuTu);
    }
}
