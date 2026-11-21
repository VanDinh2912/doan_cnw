<?php

namespace App\Services;

use App\Models\BienDongKho;
use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\SanPham;
use App\Support\KiemTraSchema;
use App\Support\SoDienThoai;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ThanhToanChuyenKhoanService
{
    public function __construct(private GiuHangOnlineService $giuHangOnline)
    {
    }

    /**
     * Tạo hóa đơn ở trạng thái cho_thanh_toan và giữ hàng ngay lập tức.
     *
     * @param array $gioHang Danh sách sản phẩm trong giỏ [['id' => 1, 'soluong' => 2], ...]
     * @param array $thongTinKhach Thông tin giảm giá, customer_id, diem_su_dung
     * @param mixed $user Người thực hiện (thu ngân / admin); null với đơn online guest
     * @param array $online Thông tin đơn online (kenh, phuong_thuc, ma_tra_cuu, han_giu...); rỗng = POS
     * @param int|null $onlineCustomerId ID khách hàng cho đơn online (khách đã đăng nhập)
     */
    public function taoHoaDonChoThanhToan(array $gioHang, array $thongTinKhach, $user, array $online = [], ?int $onlineCustomerId = null): DonHang
    {
        $gioHangSorted = collect($gioHang)
            ->sortBy(fn (array $item, string|int $key): int => (int) ($item['id'] ?? $key))
            ->all();

        if (empty($gioHangSorted)) {
            throw new RuntimeException('Giỏ hàng đang trống.');
        }

        $laOnline = ($online['kenh'] ?? null) === 'online';
        $laOnlinePickup = $laOnline && ($online['phuong_thuc'] ?? null) === 'tienmat';

        return DB::transaction(function () use ($gioHangSorted, $thongTinKhach, $user, $online, $laOnline, $laOnlinePickup, $onlineCustomerId): DonHang {
            $tongTien = 0;
            $sanPhams = [];

            // 1. Khóa sản phẩm theo thứ tự product_id tăng dần để tránh deadlock
            foreach ($gioHangSorted as $item) {
                $sanPham = SanPham::query()->lockForUpdate()->find($item['id']);
                if (! $sanPham || ! $sanPham->trang_thai || $item['soluong'] > $sanPham->soluong) {
                    $tenSanPham = $sanPham?->ten_sanpham ?? ('#'.$item['id']);
                    throw new RuntimeException('Sản phẩm "'.$tenSanPham.'" đã hết hàng hoặc không đủ tồn kho, vui lòng kiểm tra lại giỏ hàng.');
                }

                $soLuong = (int) $item['soluong'];

                // Trần giữ pickup phải kiểm tra SAU khi đã khóa dòng sản phẩm để hai đơn
                // đồng thời không cùng vượt trần (SUM thấy phần giữ đã commit của đơn trước).
                if ($laOnlinePickup) {
                    $this->giuHangOnline->kiemTra($sanPham->id, $soLuong);
                }

                $thanhTien = (float) $sanPham->gia_ban * $soLuong;
                $tongTien += $thanhTien;
                $sanPhams[] = [$sanPham, $soLuong, $thanhTien];
            }

            // D12: tối đa online_max_don_mo đơn mở trên mỗi SĐT chuẩn hóa, áp cho
            // cả pickup lẫn QR/giao tận nơi (đếm theo phương thức của đơn mới).
            // Kiểm tra TRONG transaction sau khi khóa sản phẩm để hai đơn đồng thời
            // không cùng vượt giới hạn; thông báo chung, không lộ dữ liệu đơn khác.
            if ($laOnline && ! empty($online['so_dienthoai_dat'])) {
                $this->kiemTraGioiHanDonMo(
                    (string) $online['so_dienthoai_dat'],
                    (string) ($online['phuong_thuc'] ?? 'chuyenkhoan')
                );
            }

            // 2. Tính giảm giá & khóa khách hàng
            $giamGiaPhanTram = min(100, max(0, (float) ($thongTinKhach['giam_gia_phantram'] ?? 0)));
            $tienGiam = round($tongTien * $giamGiaPhanTram / 100);

            $khachHang = null;
            $diemSuDung = (int) ($thongTinKhach['diem_su_dung'] ?? 0);
            $tienGiamDiem = 0;

            // Ưu tiên customer_id từ online (khách đã đăng nhập)
            $customerId = $onlineCustomerId ?? ($thongTinKhach['customer_id'] ?? null);

            if (! empty($customerId)) {
                $khachHang = KhachHang::query()->lockForUpdate()->findOrFail($customerId);
                if ($diemSuDung > $khachHang->diem_tichluy) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'diem_su_dung' => ['Số điểm sử dụng vượt quá điểm hiện có.'],
                    ]);
                }
                $tienGiamDiem = $diemSuDung * config('pos.gia_tri_diem');
                if ($tienGiam + $tienGiamDiem > $tongTien) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'diem_su_dung' => ['Số điểm sử dụng vượt quá giá trị hóa đơn.'],
                    ]);
                }
            } elseif ($diemSuDung > 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'diem_su_dung' => ['Cần chọn khách hàng trước khi dùng điểm.'],
                ]);
            }

            $tongThanhToan = $tongTien - $tienGiam - $tienGiamDiem;
            $diemTichLuyNhan = $khachHang ? (int) floor($tongThanhToan / config('pos.diem_moi_dong')) : 0;

            // Phí giao hàng (D13): tính từ tiền hàng DB ở trên + config, không nhận
            // phí từ client/session. Miễn phí khi tiền hàng >= ngưỡng (nếu > 0).
            $phiGiaoHang = 0;
            if (($online['hinh_thuc_nhan'] ?? null) === 'giao_tan_noi') {
                $phiGiaoHang = (int) config('pos.phi_giao_hang', 30000);
                $mienPhiGiaoTu = (int) config('pos.mien_phi_giao_tu', 0);
                if ($mienPhiGiaoTu > 0 && $tongTien >= $mienPhiGiaoTu) {
                    $phiGiaoHang = 0;
                }
            }
            $tongThanhToan += $phiGiaoHang;

            // 3. Sinh mã hóa đơn S7
            $maHoaDon = $this->taoMaHoaDon();

            // 4. Tạo hóa đơn trạng thái cho_thanh_toan
            $duLieuDonHang = [
                'ma_hoadon' => $maHoaDon,
                'user_id' => $user?->id,
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
                'phuong_thuc' => $online['phuong_thuc'] ?? 'chuyenkhoan',
                'trang_thai' => 'cho_thanh_toan',
                'thoi_gian_thanh_toan' => null,
            ];

            // Cột online M1: chỉ ghi khi là đơn online VÀ DB đã có cột kenh.
            // Guard code-đi-trước-schema: POS ($online rỗng) không ghi cột online;
            // TODO: gỡ guard hasColumn sau khi M1-M3 đã áp lên DB thật doan_cnw.
            if (! empty($online) && Schema::hasColumn('invoices', 'kenh')) {
                if (isset($online['kenh'])) $duLieuDonHang['kenh'] = $online['kenh'];
                if (isset($online['ma_tra_cuu'])) $duLieuDonHang['ma_tra_cuu'] = $online['ma_tra_cuu'];
                if (isset($online['ten_khach_dat'])) $duLieuDonHang['ten_khach_dat'] = $online['ten_khach_dat'];
                if (isset($online['so_dienthoai_dat'])) $duLieuDonHang['so_dienthoai_dat'] = $online['so_dienthoai_dat'];
                if (isset($online['ghi_chu_khach'])) $duLieuDonHang['ghi_chu_khach'] = $online['ghi_chu_khach'];
                if (isset($online['han_giu'])) $duLieuDonHang['han_giu'] = $online['han_giu'];
                // email_dat: guard riêng vì cột thêm sau (migration 2026_11_17_000004);
                // code đi trước schema vẫn tạo đơn bình thường, chỉ thiếu email.
                if (isset($online['email_dat']) && Schema::hasColumn('invoices', 'email_dat')) {
                    $duLieuDonHang['email_dat'] = $online['email_dat'];
                }
            }

            // Cột giao hàng M3: chỉ ghi khi DB đã có cột (POS vẫn tạo QR trước khi áp M3).
            if (Schema::hasColumn('invoices', 'hinh_thuc_nhan')) {
                $duLieuDonHang['hinh_thuc_nhan'] = $online['hinh_thuc_nhan'] ?? 'tai_cua_hang';
                $duLieuDonHang['ten_nguoi_nhan'] = $online['ten_nguoi_nhan'] ?? null;
                $duLieuDonHang['sdt_nguoi_nhan'] = $online['sdt_nguoi_nhan'] ?? null;
                $duLieuDonHang['dia_chi_giao'] = $online['dia_chi_giao'] ?? null;
                $duLieuDonHang['phi_giao_hang'] = $phiGiaoHang;
                $duLieuDonHang['trang_thai_giao'] = null; // D15: null cho tới khi thanh toán
            }

            $donHang = DonHang::create($duLieuDonHang);

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
                    'user_id' => $user?->id,
                    'invoice_id' => $donHang->id,
                    'loai' => 'ban',
                    'soluong' => -$soLuong,
                    'ghi_chu' => 'Bán hàng '.$donHang->ma_hoadon,
                ]);
            }

            // 6. Trừ điểm đã dùng của khách (điểm thưởng nhận chỉ cộng khi da_thanh_toan)
            if ($khachHang && $diemSuDung > 0) {
                $soDuTruoc = (int) $khachHang->diem_tichluy;
                $khachHang->decrement('diem_tichluy', $diemSuDung);
                DiemService::ghi($khachHang->id, DiemService::LOAI_DUNG, -$diemSuDung,
                    $soDuTruoc - $diemSuDung,
                    ['invoice_id' => $donHang->id, 'user_id' => $user?->id,
                        'ly_do' => 'Giữ điểm khi tạo đơn chờ thanh toán',
                        'su_kien' => 'cho-thanh-toan:'.$donHang->id.':dung_diem']);
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

        return DB::transaction(function () use ($invoiceId, $customerId, $productIds, $maGiaoDich, $ghiChu, $nguoi): array {
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

            // D15: đơn online giao tận nơi → đặt trạng thái giao ngay khi tiền về.
            // POS_ONLINE_TU_DONG_XAC_NHAN=true → da_xac_nhan, false → cho_xac_nhan.
            // Guard: DB thiếu cột trang_thai_giao thì bỏ qua, vẫn hoàn tất đơn;
            // chỉ đặt khi đang null nên webhook lặp/manual confirm không ghi đè
            // trạng thái do nhân viên (P3) đã cập nhật.
            if (($lockedInvoice->kenh ?? null) === 'online'
                && ($lockedInvoice->hinh_thuc_nhan ?? null) === 'giao_tan_noi'
                && $lockedInvoice->trang_thai_giao === null
                && KiemTraSchema::coCot('invoices', 'trang_thai_giao')) {
                $updateData['trang_thai_giao'] = config('pos.online_tu_dong_xac_nhan')
                    ? 'da_xac_nhan'
                    : 'cho_xac_nhan';
            }

            $lockedInvoice->update($updateData);

            // 5. Cộng điểm tích lũy nhận (đúng 1 lần)
            if ($customer && $lockedInvoice->diem_tichluy_nhan > 0) {
                $soDuTruoc = (int) $customer->diem_tichluy;
                $customer->increment('diem_tichluy', $lockedInvoice->diem_tichluy_nhan);
                DiemService::ghi($customer->id, DiemService::LOAI_TICH, (int) $lockedInvoice->diem_tichluy_nhan,
                    $soDuTruoc + (int) $lockedInvoice->diem_tichluy_nhan,
                    ['invoice_id' => $lockedInvoice->id, 'user_id' => $nguoi?->id,
                        'su_kien' => 'xac-nhan-tt:'.$lockedInvoice->id.':tich_diem']);
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
                $soDuTruoc = (int) $customer->diem_tichluy;
                $customer->increment('diem_tichluy', $lockedInvoice->diem_su_dung);
                DiemService::ghi($customer->id, DiemService::LOAI_HOAN_DIEM_HUY, (int) $lockedInvoice->diem_su_dung,
                    $soDuTruoc + (int) $lockedInvoice->diem_su_dung,
                    ['invoice_id' => $lockedInvoice->id, 'user_id' => $userId,
                        'ly_do' => 'Hoàn điểm khi hủy đơn chờ thanh toán',
                        'su_kien' => 'huy-cho:'.$lockedInvoice->id.':hoan_diem']);
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
     * P3 HM4 — hủy đơn ĐÃ thanh toán (chỉ admin gọi từ controller).
     * Transaction + lockForUpdate: hoàn kho (movement dieuchinh dương, đúng 1 lần),
     * thu hồi điểm đã cộng (không âm), đặt da_huy + trang_thai_giao=da_huy_giao,
     * đánh dấu CẦN HOÀN TIỀN THỦ CÔNG. KHÔNG hoàn tiền tự động, không gọi API ngân hàng.
     *
     * @return array ['success' => bool, 'don_hang' => ?DonHang, 'message' => string, 'thieuCotCanHoanTien' => bool]
     */
    public function huyDonDaThanhToan(DonHang $donHang, string $lyDo): array
    {
        $invoiceId = $donHang->id;

        $unlocked = DonHang::with('chiTietDonHangs')->find($invoiceId);
        if (! $unlocked) {
            return ['success' => false, 'don_hang' => null, 'message' => 'Hóa đơn không tồn tại.', 'thieuCotCanHoanTien' => false];
        }
        if ($unlocked->trang_thai !== 'da_thanh_toan') {
            return [
                'success' => false,
                'don_hang' => $unlocked,
                'message' => 'Chỉ hủy được đơn đang ở trạng thái đã thanh toán.',
                'thieuCotCanHoanTien' => false,
            ];
        }

        $customerId = $unlocked->customer_id;
        $chiTiets = $unlocked->chiTietDonHangs;
        $productIds = $chiTiets->pluck('product_id')->sort()->unique()->values()->all();
        $coCotCanHoan = KiemTraSchema::coCot('invoices', 'can_hoan_tien');

        return DB::transaction(function () use ($invoiceId, $customerId, $productIds, $chiTiets, $lyDo, $coCotCanHoan): array {
            $products = collect();
            if (! empty($productIds)) {
                $products = SanPham::whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            }
            $customer = null;
            if ($customerId) {
                $customer = KhachHang::where('id', $customerId)->lockForUpdate()->first();
            }
            $lockedInvoice = DonHang::where('id', $invoiceId)->lockForUpdate()->first();
            if (! $lockedInvoice || $lockedInvoice->trang_thai !== 'da_thanh_toan') {
                return [
                    'success' => false,
                    'don_hang' => $lockedInvoice,
                    'message' => 'Đơn không còn ở trạng thái đã thanh toán (có thể vừa bị hủy).',
                    'thieuCotCanHoanTien' => false,
                ];
            }

            $userId = auth()->id() ?? $lockedInvoice->user_id;

            // 1. Hoàn kho đúng một lần (lock hóa đơn đảm bảo chỉ 1 transaction vào đây)
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
                    'soluong' => $item->soluong,
                    'ghi_chu' => 'Hủy đơn đã thanh toán: '.$lyDo,
                ]);
            }

            // 2. Thu hồi điểm đã cộng — không để số dư âm
            if ($customer && $lockedInvoice->diem_tichluy_nhan > 0) {
                $soDuTruoc = (int) $customer->diem_tichluy;
                $soDuSau = max(0, $soDuTruoc - (int) $lockedInvoice->diem_tichluy_nhan);
                $thuHoiThucTe = $soDuTruoc - $soDuSau;
                $customer->diem_tichluy = $soDuSau;
                $customer->save();
                if ($thuHoiThucTe > 0) {
                    DiemService::ghi($customer->id, DiemService::LOAI_THU_HOI_HUY, -$thuHoiThucTe,
                        $soDuSau,
                        ['invoice_id' => $lockedInvoice->id, 'user_id' => $userId,
                            'ly_do' => 'Thu hồi điểm khi hủy đơn đã thanh toán'
                                .($thuHoiThucTe < (int) $lockedInvoice->diem_tichluy_nhan
                                    ? ' (thiếu '.((int) $lockedInvoice->diem_tichluy_nhan - $thuHoiThucTe).' điểm do số dư không đủ)'
                                    : ''),
                            'su_kien' => 'huy-da-tt:'.$lockedInvoice->id.':thu_hoi']);
                }
            }

            // 3. Trạng thái + cờ hoàn tiền (guard thiếu cột: vẫn hủy được)
            $update = [
                'trang_thai' => 'da_huy',
                'ghi_chu' => $lockedInvoice->ghi_chu ? $lockedInvoice->ghi_chu.' | '.$lyDo : $lyDo,
            ];
            if (KiemTraSchema::coCot('invoices', 'trang_thai_giao')) {
                $update['trang_thai_giao'] = 'da_huy_giao';
            }
            if ($coCotCanHoan) {
                $update['can_hoan_tien'] = true;
            }
            if (KiemTraSchema::coCot('invoices', 'ghi_chu_noi_bo')) {
                $update['ghi_chu_noi_bo'] = trim('Lý do hủy: '.$lyDo.' | '.($lockedInvoice->ghi_chu_noi_bo ?? ''));
            }
            $lockedInvoice->update($update);

            return [
                'success' => true,
                'don_hang' => $lockedInvoice,
                'message' => 'Đã hủy đơn đã thanh toán, hoàn kho và thu hồi điểm.'
                    .($coCotCanHoan ? '' : ' (Thiếu cột can_hoan_tien — cần áp migration để đánh dấu hoàn tiền.)'),
                'thieuCotCanHoanTien' => ! $coCotCanHoan,
            ];
        }, 2);
    }

    /**
     * D12 — chặn ôm hàng: đếm đơn online đang mở (cho_thanh_toan) cùng phương
     * thức với đơn mới, của cùng SĐT chuẩn hóa; đủ online_max_don_mo thì từ chối
     * bằng thông báo chung. Gọi trong transaction sau lockForUpdate sản phẩm.
     * Đơn da_huy/da_thanh_toan và đơn POS không tính.
     */
    private function kiemTraGioiHanDonMo(string $sdt, string $phuongThuc): void
    {
        $gioiHan = (int) config('pos.online_max_don_mo', 3);
        $sdtChuanHoa = SoDienThoai::chuanHoa($sdt);

        $dangMo = DonHang::query()
            ->where('kenh', 'online')
            ->where('phuong_thuc', $phuongThuc)
            ->where('trang_thai', 'cho_thanh_toan')
            ->pluck('so_dienthoai_dat')
            ->filter(fn (?string $s): bool => SoDienThoai::chuanHoa($s) === $sdtChuanHoa)
            ->count();

        if ($dangMo >= $gioiHan) {
            throw new RuntimeException('Không thể tạo đơn hàng. Vui lòng thử lại.');
        }
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
