<?php

namespace App\Services;

use App\Models\SanPham;
use App\Models\User;
use App\Models\YeuCauDuyet;
use App\Support\KiemTraSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mã duyệt hai-người cho thao tác M2 (nguyên tắc: người làm ≠ người duyệt).
 *
 * - Mã 6 số sinh ngẫu nhiên, CHỈ lưu HMAC-SHA256(ma|yc_id|nhan_vien_id, APP_KEY).
 * - Ràng buộc: ten_route + doi_tuong + tham_so_duyet (tham số đã duyệt).
 * - Dùng nguyên tử: lockForUpdate trong transaction của middleware/controller.
 * - Bộ đếm sai RIÊNG (RateLimiter), không đụng bộ đếm PIN.
 * - Mọi truy cập bảng đều qua KiemTraSchema::coBang — thiếu bảng thì các
 *   hàm trả về trạng thái an toàn, không lỗi.
 */
class MaDuyetService
{
    public const TT_CHO_CAP = YeuCauDuyet::TT_CHO_CAP;
    public const TT_DA_CAP = YeuCauDuyet::TT_DA_CAP;
    public const TT_DA_DUNG = YeuCauDuyet::TT_DA_DUNG;
    public const TT_HET_HAN = YeuCauDuyet::TT_HET_HAN;
    public const TT_TU_CHOI = YeuCauDuyet::TT_TU_CHOI;

    private const TOI_DA_CHO = 5;          // yêu cầu chờ tối đa / nhân viên
    private const TUOI_TOI_DA_GIO = 24;    // yêu cầu chờ quá 24h tự hết hạn
    private const SAI_TOI_DA = 5;          // sai 5 lần / 15 phút
    private const SAI_GIO_PHUT = 15;

    /**
     * Cờ hiệu lực: PIN bật + mã duyệt bật + bảng tồn tại.
     * Thiếu một điều kiện -> M2 tạm hạ M1 (PIN thường), không lỗi.
     */
    public static function bat(): bool
    {
        return PinService::bat()
            && (bool) config('pos.ma_duyet_bat', true)
            && KiemTraSchema::coBang('yeu_cau_duyet');
    }

    /** Bảng đã tồn tại chưa (dùng cho he-thong:kiem-tra / công tắc). */
    public static function coBang(): bool
    {
        return KiemTraSchema::coBang('yeu_cau_duyet');
    }

    public static function hetHanPhut(): int
    {
        return max(2, min(30, (int) config('pos.ma_duyet_het_han_phut', 10)));
    }

    // ------------------------------------------------------------------
    // Route M2 được phép tạo yêu cầu — đồng bộ với config/pin_muc.php
    // (service không đọc config phân loại để tránh vòng lặp; test kiểm
    //  hai danh sách khớp nhau).
    // ------------------------------------------------------------------
    public const ROUTE_M2 = [
        'trahang.luu',
        'donhang.xacnhan',
        'khachhang.xoa',
        'sanpham.xoa',
        'sanpham.postsua',       // chỉ khi đổi gia_ban/trang_thai_ban
        'loaisanpham.xoa',
        'hangsxuat.xoa',
        'nhaphang.nhacungcap.xoa',
        'nhaphang.donnhap.trangthai',
        'kho.postnhapkho',
        'hoadonnhap.xacnhan',
        'hoadonnhap.huy',
        'donhang.thanhtoan',     // chỉ khi vượt ngưỡng giá trị
        'donhang.taoqr',         // chỉ khi vượt ngưỡng điểm
    ];

    /** Role được phép gọi từng route M2 (phải khớp middleware route). */
    private const ROLE_ROUTE = [
        'trahang.luu' => ['admin', 'thungan'],
        'donhang.xacnhan' => ['admin', 'thungan'],
        'khachhang.xoa' => ['admin', 'thungan'],
        'sanpham.xoa' => ['admin', 'thukho'],
        'sanpham.postsua' => ['admin', 'thukho'],
        'loaisanpham.xoa' => ['admin', 'thukho'],
        'hangsxuat.xoa' => ['admin'],
        'nhaphang.nhacungcap.xoa' => ['admin', 'thukho'],
        'nhaphang.donnhap.trangthai' => ['admin', 'thukho'],
        'kho.postnhapkho' => ['admin', 'thukho'],
        'hoadonnhap.xacnhan' => ['admin', 'thukho'],
        'hoadonnhap.huy' => ['admin', 'thukho'],
        'donhang.thanhtoan' => ['admin', 'thungan'],
        'donhang.taoqr' => ['admin', 'thungan'],
    ];

    /**
     * Quy tắc so sánh tham số đã duyệt khi verify:
     * 'eq' = phải bằng; 'lte' = giá trị request <= giá trị đã duyệt.
     */
    private const RANG_BUOC = [
        'sanpham.postsua' => ['gia_ban_moi' => 'eq', 'trang_thai_ban_moi' => 'eq'],
        'donhang.thanhtoan' => ['giam_gia_phantram' => 'lte', 'diem_su_dung' => 'lte', 'tong_gio' => 'lte'],
        'donhang.taoqr' => ['giam_gia_phantram' => 'lte', 'diem_su_dung' => 'lte', 'tong_gio' => 'lte'],
    ];

    /**
     * Route M2 tĩnh -> [loai_doi_tuong, ten_tham_so_route].
     * Route không có đối tượng cố định (trahang.luu, kho.postnhapkho,
     * donhang.thanhtoan/taoqr) ràng buộc qua tham_so_duyet.
     */
    private const DOI_TUONG_ROUTE = [
        'khachhang.xoa' => ['khach_hang', 'khachHang'],
        'sanpham.xoa' => ['san_pham', 'sanPham'],
        'sanpham.postsua' => ['san_pham', 'sanPham'],
        'loaisanpham.xoa' => ['loai_san_pham', 'loai'],
        'hangsxuat.xoa' => ['hang_san_xuat', 'hang'],
        'nhaphang.nhacungcap.xoa' => ['nha_cung_cap', 'nhaCungCap'],
        'nhaphang.donnhap.trangthai' => ['don_nhap', 'donNhap'],
        'hoadonnhap.xacnhan' => ['hoa_don_nhap', 'hoaDon'],
        'hoadonnhap.huy' => ['hoa_don_nhap', 'hoaDon'],
        'donhang.xacnhan' => ['don_hang', 'donHang'],
    ];

    /**
     * Định danh thao tác từ request hiện tại: [loai, id, thamSoRequest].
     * thamSoRequest chỉ gồm tham số RÀNG BUỘC (server chuẩn hóa tên).
     */
    public static function dinhDanh(Request $request, string $tenRoute): array
    {
        $loai = $id = null;
        if (isset(self::DOI_TUONG_ROUTE[$tenRoute])) {
            [$loai, $param] = self::DOI_TUONG_ROUTE[$tenRoute];
            $model = $request->route($param);
            $id = $model ? (int) ($model->id ?? $model) : null;
        }

        return [$loai, $id, self::thamSoRequest($request, $tenRoute)];
    }

    /**
     * Lấy [loai, id] từ route object đã match (dùng cho endpoint /gui —
     * tham số route là id thô, không qua model binding).
     */
    public static function dinhDanhRoute(\Illuminate\Routing\Route $route): array
    {
        $tenRoute = (string) $route->getName();
        if (! isset(self::DOI_TUONG_ROUTE[$tenRoute])) {
            return [null, null];
        }
        [$loai, $param] = self::DOI_TUONG_ROUTE[$tenRoute];
        $id = $route->parameter($param);

        return [$loai, $id !== null ? (int) $id : null];
    }

    /** Tham số request chuẩn hóa theo route — dùng cho cả tạo yêu cầu lẫn verify. */
    public static function thamSoRequest(Request $request, string $tenRoute): array
    {
        $raw = match ($tenRoute) {
            'sanpham.postsua' => [
                'gia_ban_moi' => $request->input('gia_ban'),
                'trang_thai_ban_moi' => $request->has('trang_thai') ? ($request->boolean('trang_thai') ? '1' : '0') : null,
            ],
            'donhang.thanhtoan', 'donhang.taoqr' => self::thamSoPos($request),
            default => [],
        };

        return array_filter($raw, fn ($v) => $v !== null && $v !== '');
    }

    /** Tham số POS: giảm giá %, điểm dùng, tổng giỏ từ session lúc gọi. */
    private static function thamSoPos(Request $request): array
    {
        $gio = collect($request->session()->get('gio_hang', []));
        $tong = $gio->sum(fn ($i) => (float) ($i['gia_ban'] ?? 0) * (int) ($i['soluong'] ?? 0));

        return [
            'giam_gia_phantram' => $request->input('giam_gia_phantram'),
            'diem_su_dung' => $request->input('diem_su_dung'),
            'tong_gio' => $tong > 0 ? $tong : null,
        ];
    }

    /** Nhãn tiếng Việt cho mô tả (server sinh, client không gửi). */
    private const NHAN_ROUTE = [
        'trahang.luu' => 'Tạo phiếu trả hàng',
        'donhang.xacnhan' => 'Xác nhận thanh toán QR thủ công',
        'khachhang.xoa' => 'Xóa hồ sơ khách hàng',
        'sanpham.xoa' => 'Xóa sản phẩm',
        'sanpham.postsua' => 'Sửa sản phẩm (giá/trạng thái bán)',
        'loaisanpham.xoa' => 'Xóa loại sản phẩm',
        'hangsxuat.xoa' => 'Xóa hãng sản xuất',
        'nhaphang.nhacungcap.xoa' => 'Xóa nhà cung cấp',
        'nhaphang.donnhap.trangthai' => 'Đổi trạng thái đơn nhập',
        'kho.postnhapkho' => 'Nhập kho trực tiếp',
        'hoadonnhap.xacnhan' => 'Xác nhận hóa đơn nhập',
        'hoadonnhap.huy' => 'Hủy hóa đơn nhập',
        'donhang.thanhtoan' => 'Thanh toán POS vượt ngưỡng',
        'donhang.taoqr' => 'Tạo QR thanh toán vượt ngưỡng điểm',
    ];

    // ------------------------------------------------------------------
    // Tạo yêu cầu (phía nhân viên)
    // ------------------------------------------------------------------

    /**
     * Tạo/tái-dùng yêu cầu chờ. Server tự sinh mo_ta_thao_tac.
     *
     * @param array $thamSoThamSo tham số ràng buộc do SERVER tính
     *        (không nhận mô tả/tùy ý từ client ngoài định danh thao tác).
     * @return array{yc:?YeuCauDuyet, loi:?string, ma:string}
     */
    public static function taoYeuCau(
        User $user,
        string $tenRoute,
        string $method,
        ?string $doiTuongLoai,
        ?int $doiTuongId,
        array $thamSo,
        Request $request
    ): array {
        if (! self::bat()) {
            return ['yc' => null, 'loi' => 'Tính năng mã duyệt chưa bật.', 'ma' => 'CHUA_BAT'];
        }
        if (! in_array($tenRoute, self::ROUTE_M2, true)) {
            return ['yc' => null, 'loi' => 'Thao tác không hợp lệ.', 'ma' => 'ROUTE_KHONG_HOP_LE'];
        }
        $roles = self::ROLE_ROUTE[$tenRoute] ?? [];
        if (! in_array($user->role, $roles, true)) {
            return ['yc' => null, 'loi' => 'Bạn không có quyền thực hiện thao tác này.', 'ma' => 'KHONG_CO_QUYEN'];
        }
        if ($doiTuongId !== null && ! self::doiTuongTonTai($doiTuongLoai, $doiTuongId)) {
            return ['yc' => null, 'loi' => 'Đối tượng không tồn tại.', 'ma' => 'DOI_TUONG_KHONG_TON_TAI'];
        }

        // Hết hạn lazy các yêu cầu chờ quá 24h của chính user.
        YeuCauDuyet::where('nhan_vien_id', $user->id)
            ->where('trang_thai', self::TT_CHO_CAP)
            ->where('created_at', '<', now()->subHours(self::TUOI_TOI_DA_GIO))
            ->update(['trang_thai' => self::TT_HET_HAN, 'khoa_trung' => null]);

        $khoa = self::khoaTrong($user->id, $tenRoute, $doiTuongLoai, $doiTuongId, $thamSo);

        return DB::transaction(function () use ($user, $tenRoute, $method, $doiTuongLoai, $doiTuongId, $thamSo, $khoa, $request) {
            // Chống trùng: cùng thao tác còn chờ -> tái dùng.
            $co = YeuCauDuyet::where('khoa_trung', $khoa)
                ->whereIn('trang_thai', [self::TT_CHO_CAP, self::TT_DA_CAP])
                ->lockForUpdate()->first();
            if ($co) {
                return ['yc' => $co, 'loi' => null, 'ma' => 'DA_CO_YEU_CAU'];
            }
            $cho = YeuCauDuyet::where('nhan_vien_id', $user->id)
                ->where('trang_thai', self::TT_CHO_CAP)->count();
            if ($cho >= self::TOI_DA_CHO) {
                return ['yc' => null, 'loi' => 'Bạn đang có quá nhiều yêu cầu chờ duyệt.', 'ma' => 'QUA_NHIEU_YEU_CAU'];
            }
            $yc = YeuCauDuyet::create([
                'nhan_vien_id' => $user->id,
                'ten_route' => $tenRoute,
                'method' => strtoupper($method),
                'doi_tuong_loai' => $doiTuongLoai,
                'doi_tuong_id' => $doiTuongId,
                'mo_ta_thao_tac' => self::moTa($tenRoute, $doiTuongLoai, $doiTuongId, $thamSo),
                'tham_so_duyet' => $thamSo ?: null,
                'trang_thai' => self::TT_CHO_CAP,
                'khoa_trung' => $khoa,
                'created_at' => now(),
            ]);
            self::audit($request, 'ma_duyet_yeu_cau', $yc->id, "Yêu cầu duyệt #{$yc->id}: {$yc->mo_ta_thao_tac}");

            return ['yc' => $yc, 'loi' => null, 'ma' => 'OK'];
        });
    }

    private static function khoaTrong(int $uid, string $route, ?string $loai, ?int $id, array $thamSo): string
    {
        return hash('sha256', implode('|', [
            $uid, $route, 'POST', $loai ?? '', $id ?? '',
            json_encode($thamSo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]));
    }

    private static function doiTuongTonTai(?string $loai, int $id): bool
    {
        return match ($loai) {
            'san_pham' => SanPham::whereKey($id)->exists(),
            'khach_hang' => \App\Models\KhachHang::whereKey($id)->exists(),
            'loai_san_pham' => \App\Models\LoaiSanPham::whereKey($id)->exists(),
            'hang_san_xuat' => \App\Models\HangSanXuat::whereKey($id)->exists(),
            'nha_cung_cap' => \App\Models\NhaCungCap::whereKey($id)->exists(),
            'don_nhap' => \App\Models\DonNhap::whereKey($id)->exists(),
            'hoa_don_nhap' => \App\Models\HoaDonNhap::whereKey($id)->exists(),
            'phieu_tra' => \App\Models\PhieuTra::whereKey($id)->exists(),
            'don_hang' => \App\Models\DonHang::whereKey($id)->exists(),
            default => true, // loại không map được -> không chặn (route tự validate)
        };
    }

    /** Mô tả thao tác do SERVER sinh — client không được gửi mo_ta. */
    public static function moTa(string $tenRoute, ?string $loai, ?int $id, array $thamSo): string
    {
        $nhan = self::NHAN_ROUTE[$tenRoute] ?? $tenRoute;
        $doiTuong = '';
        if ($loai && $id) {
            $ten = self::tenDoiTuong($loai, $id);
            $doiTuong = " — {$loai} #{$id}".($ten ? " ({$ten})" : '');
        }
        $them = '';
        if (isset($thamSo['gia_ban_moi'])) {
            $them .= ' — giá mới '.number_format((float) $thamSo['gia_ban_moi'], 0, ',', '.').'đ';
        }
        if (isset($thamSo['trang_thai_ban_moi'])) {
            $them .= ' — trạng thái bán: '.$thamSo['trang_thai_ban_moi'];
        }
        if (isset($thamSo['giam_gia_phantram'])) {
            $them .= ' — giảm '.rtrim(rtrim(number_format((float) $thamSo['giam_gia_phantram'], 2, ',', '.'), '0'), ',').'%';
        }
        if (isset($thamSo['diem_su_dung'])) {
            $them .= ' — dùng '.$thamSo['diem_su_dung'].' điểm';
        }
        if (isset($thamSo['tong_gio'])) {
            $them .= ' — tổng giỏ '.number_format((float) $thamSo['tong_gio'], 0, ',', '.').'đ';
        }

        return "{$nhan}{$doiTuong}{$them}";
    }

    private static function tenDoiTuong(string $loai, int $id): ?string
    {
        try {
            return match ($loai) {
                'san_pham' => SanPham::whereKey($id)->value('ten'),
                'khach_hang' => \App\Models\KhachHang::whereKey($id)->value('ho_ten'),
                'loai_san_pham' => \App\Models\LoaiSanPham::whereKey($id)->value('ten'),
                'hang_san_xuat' => \App\Models\HangSanXuat::whereKey($id)->value('ten'),
                'nha_cung_cap' => \App\Models\NhaCungCap::whereKey($id)->value('ten'),
                'don_nhap' => 'Đơn nhập #'.$id,
                'hoa_don_nhap' => 'Hóa đơn nhập #'.$id,
                'phieu_tra' => 'Phiếu trả #'.$id,
                'don_hang' => 'Đơn hàng #'.$id,
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    // ------------------------------------------------------------------
    // Admin: cấp / từ chối
    // ------------------------------------------------------------------

    /**
     * Cấp mã một lần. PIN admin kiểm ở caller (middleware pin.muc mức A).
     * Trả mã CHỈ ĐÚNG MỘT LẦN — không lưu/log/session.
     */
    public static function capMa(User $admin, int $yeuCauId, Request $request): array
    {
        if (! self::bat()) {
            return ['ok' => false, 'ma' => 'CHUA_BAT', 'message' => 'Tính năng chưa bật.'];
        }
        if ($admin->role !== 'admin') {
            return ['ok' => false, 'ma' => 'KHONG_CO_QUYEN', 'message' => 'Chỉ admin được cấp mã.'];
        }
        $yc = YeuCauDuyet::whereKey($yeuCauId)->lockForUpdate()->first();
        if (! $yc || $yc->trang_thai !== self::TT_CHO_CAP) {
            return ['ok' => false, 'ma' => 'KHONG_HOP_LE', 'message' => 'Yêu cầu không còn chờ duyệt.'];
        }
        if ((int) $yc->nhan_vien_id === (int) $admin->id) {
            return ['ok' => false, 'ma' => 'TU_CHOI', 'message' => 'Không thể cấp mã cho chính mình.'];
        }

        $ma = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $yc->forceFill([
            'trang_thai' => self::TT_DA_CAP,
            'ma_hash' => self::bamMa($ma, $yc->id, $yc->nhan_vien_id),
            'admin_cap_id' => $admin->id,
            'cap_luc' => now(),
            'het_han_luc' => now()->addMinutes(self::hetHanPhut()),
        ])->save();
        self::audit($request, 'ma_duyet_cap', $yc->id, "Cấp mã duyệt #{$yc->id} cho NV #{$yc->nhan_vien_id}");

        return ['ok' => true, 'ma' => 'OK', 'ma_hien' => $ma, 'het_han_luc' => $yc->het_han_luc];
    }

    public static function tuChoi(User $admin, int $yeuCauId, string $lyDo, Request $request): array
    {
        if (! self::bat() || $admin->role !== 'admin') {
            return ['ok' => false, 'ma' => 'KHONG_CO_QUYEN'];
        }
        $yc = YeuCauDuyet::whereKey($yeuCauId)->lockForUpdate()->first();
        if (! $yc || $yc->trang_thai !== self::TT_CHO_CAP) {
            return ['ok' => false, 'ma' => 'KHONG_HOP_LE'];
        }
        $yc->forceFill([
            'trang_thai' => self::TT_TU_CHOI,
            'khoa_trung' => null,
            'tu_choi_luc' => now(),
            'ly_do_tu_choi' => mb_substr(trim($lyDo), 0, 255) ?: null,
        ])->save();
        self::audit($request, 'ma_duyet_tu_choi', $yc->id, "Từ chối yêu cầu #{$yc->id}");

        return ['ok' => true, 'ma' => 'OK'];
    }

    // ------------------------------------------------------------------
    // Verify + dùng nguyên tử (gọi trong transaction của request gốc)
    // ------------------------------------------------------------------

    /**
     * Kiểm mã và KHÓA dòng yêu cầu trong transaction hiện tại.
     * Trả YeuCauDuyet đã lock khi hợp lệ; null khi sai/hết hạn/khóa.
     * KHÔNG đánh da_dung ở đây — caller gọi danhDauDaDung() sau khi
     * thao tác thành công trong cùng transaction.
     */
    public static function xacNhanMa(User $user, string $ma, string $tenRoute, ?string $loai, ?int $id, array $thamSoRequest, Request $request): ?YeuCauDuyet
    {
        if (! self::bat() || $ma === '') {
            return null;
        }
        $khoa = RateLimiter::tooManyAttempts('ma_duyet_sai:'.$user->id, self::SAI_TOI_DA);
        if ($khoa) {
            return null;
        }

        $yc = YeuCauDuyet::where('nhan_vien_id', $user->id)
            ->where('ten_route', $tenRoute)
            ->where('trang_thai', self::TT_DA_CAP)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $hopLe = $yc
            && $yc->het_han_luc && $yc->het_han_luc->isFuture()
            && hash_equals((string) $yc->ma_hash, self::bamMa($ma, $yc->id, $yc->nhan_vien_id))
            && self::thamSoKhop($yc->tham_so_duyet ?? [], $thamSoRequest, $tenRoute)
            && ($yc->doi_tuong_loai === $loai) && ((int) $yc->doi_tuong_id === (int) $id);

        if (! $hopLe) {
            RateLimiter::hit('ma_duyet_sai:'.$user->id, self::SAI_GIO_PHUT * 60);
            self::audit($request, 'ma_duyet_sai', $yc?->id, "Nhập mã duyệt sai/không khớp cho {$tenRoute}");

            return null;
        }
        RateLimiter::clear('ma_duyet_sai:'.$user->id);

        return $yc;
    }

    /** Đánh dấu đã dùng — gọi sau khi thao tác thành công, cùng transaction. */
    public static function danhDauDaDung(YeuCauDuyet $yc, Request $request): void
    {
        $yc->forceFill([
            'trang_thai' => self::TT_DA_DUNG,
            'dung_luc' => now(),
            'khoa_trung' => null,
            'ma_hash' => null, // đốt hash sau khi dùng — không cần giữ
        ])->save();
        self::audit($request, 'ma_duyet_dung', $yc->id, "Đã dùng mã duyệt #{$yc->id} cho {$yc->ten_route}");
    }

    /** Hết hạn lazy cho danh sách admin (không cần cron). */
    public static function donHetHan(): void
    {
        if (! self::coBang()) {
            return;
        }
        YeuCauDuyet::where('trang_thai', self::TT_DA_CAP)
            ->where('het_han_luc', '<', now())
            ->update(['trang_thai' => self::TT_HET_HAN, 'khoa_trung' => null]);
    }

    public static function conKhoa(User $user): bool
    {
        return RateLimiter::tooManyAttempts('ma_duyet_sai:'.$user->id, self::SAI_TOI_DA);
    }

    /**
     * Xác thực leo thang M2 có điều kiện TRONG controller (route nền K/M1).
     * PHẢI gọi bên trong DB::transaction của thao tác; khi trả 'yc' thì caller
     * gọi danhDauDaDung() sau khi thao tác thành công trong cùng transaction.
     *
     * @return array{ok:bool, yc:?YeuCauDuyet, response:?\Symfony\Component\HttpFoundation\Response}
     */
    public static function xacNhanM2(Request $request, string $tenRoute, ?string $loai, ?int $id, array $thamSo): array
    {
        $user = $request->user();
        if ($user && $user->role === 'admin') {
            // Admin: M2 = PIN riêng mức nghiêm trọng mỗi lần.
            if (PinService::kiemTraTokenNt($user, $request)) {
                return ['ok' => true, 'yc' => null, 'response' => null];
            }

            return ['ok' => false, 'yc' => null, 'response' => self::chan($request, 'CAN_PIN', 'nghiemtrong', 'Cần xác nhận PIN để tiếp tục.')];
        }
        if (! self::bat()) {
            // Hạ mức tạm (tắt mã duyệt / thiếu bảng): PIN thường như cũ.
            if ($user && PinService::daXacNhanThuong($user)) {
                return ['ok' => true, 'yc' => null, 'response' => null];
            }

            return ['ok' => false, 'yc' => null, 'response' => self::chan($request, 'CAN_PIN', 'thuong', 'Cần xác nhận PIN để tiếp tục.')];
        }
        if (self::conKhoa($user)) {
            return ['ok' => false, 'yc' => null, 'response' => self::chan($request, 'CAN_MA_DUYET', '', 'Mã duyệt tạm khóa do nhập sai nhiều lần.')];
        }
        $ma = trim((string) $request->input('_ma_duyet', ''));
        if ($ma === '') {
            return ['ok' => false, 'yc' => null, 'response' => self::chan($request, 'CAN_MA_DUYET', '', 'Thao tác này cần quản lý duyệt.')];
        }
        $yc = self::xacNhanMa($user, $ma, $tenRoute, $loai, $id, $thamSo, $request);
        if (! $yc) {
            return ['ok' => false, 'yc' => null, 'response' => self::chan($request, 'CAN_MA_DUYET', '', 'Mã duyệt không đúng hoặc đã hết hạn.')];
        }

        return ['ok' => true, 'yc' => $yc, 'response' => null];
    }

    /** Đánh dấu mã đã dùng nếu kết quả xacNhanM2 có yc (gọi sau thao tác OK). */
    public static function ketThucXacNhan(array $kq, Request $request): void
    {
        if (! empty($kq['yc'])) {
            self::danhDauDaDung($kq['yc'], $request);
        }
    }

    /** Response 423 (JSON) hoặc redirect-back kèm cờ để pin.js mở lại dialog. */
    private static function chan(Request $request, string $ma, string $muc, string $msg): Response
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ma' => $ma, 'muc' => $muc, 'message' => $msg], 423);
        }
        $flash = $ma === 'CAN_MA_DUYET' ? 'can_ma_duyet' : ($muc === 'nghiemtrong' ? 'can_pin_nt' : 'can_pin');

        return redirect()->back()->withInput()->with($flash, $msg)->with('pin_resubmit', $request->path());
    }

    // ------------------------------------------------------------------

    private static function bamMa(string $ma, int $ycId, int $nhanVienId): string
    {
        return hash_hmac('sha256', $ma.'|'.$ycId.'|'.$nhanVienId, (string) config('app.key'));
    }

    /** So tham số request với tham số đã duyệt theo RANG_BUOC của route. */
    private static function thamSoKhop(array $daDuyet, array $request, string $tenRoute): bool
    {
        $luat = self::RANG_BUOC[$tenRoute] ?? [];
        foreach ($daDuyet as $k => $vDuyet) {
            $kieu = $luat[$k] ?? 'eq';
            $vReq = $request[$k] ?? null;
            if ($kieu === 'lte') {
                if ($vReq === null || ! is_numeric($vReq) || ! is_numeric($vDuyet) || (float) $vReq > (float) $vDuyet) {
                    return false;
                }
            } elseif ((string) $vReq !== (string) $vDuyet) {
                return false;
            }
        }

        return true;
    }

    private static function audit(Request $request, string $hanhDong, ?int $ycId, string $moTa): void
    {
        try {
            NhatKyService::ghi($request, $hanhDong, 'yeu_cau_duyet', $ycId, $moTa, null, null, 'web');
        } catch (\Throwable) {
            // audit không được chặn nghiệp vụ
        }
    }
}
