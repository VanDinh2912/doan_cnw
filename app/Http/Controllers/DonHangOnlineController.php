<?php

namespace App\Http\Controllers;

use App\Models\DonHang;
use App\Models\NhatKyHoatDong;
use App\Services\NhatKyService;
use App\Services\ThanhToanChuyenKhoanService;
use App\Services\ThongBaoService;
use App\Support\CheThongTin;
use App\Support\KiemTraSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Màn hình nhân viên xử lý đơn online (P3): danh sách lọc/phân trang,
 * chi tiết đầy đủ (SĐT/địa chỉ chỉ cho nhân viên, no-store), badge số đơn
 * cần xử lý. Guard schema: DB thiếu cột online → thông báo "Cần áp migration".
 */
class DonHangOnlineController extends Controller
{
    private const PHAN_TRANG = 20;

    public function getDanhSach(Request $request): View
    {
        if (! $this->coCotOnline()) {
            return view('donhangonline.danhsach', [
                'thieuMigration' => true,
                'donHangs' => null,
            ]);
        }

        $validated = $request->validate([
            'hinh_thuc_nhan' => ['nullable', 'in:tai_cua_hang,giao_tan_noi'],
            'trang_thai' => ['nullable', 'in:cho_thanh_toan,da_thanh_toan,da_huy'],
            'trang_thai_giao' => ['nullable', 'string', 'max:50'],
            'tu_ngay' => ['nullable', 'date'],
            'den_ngay' => ['nullable', 'date', 'after_or_equal:tu_ngay'],
            'tim' => ['nullable', 'string', 'max:50'],
        ], [
            'den_ngay.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
        ]);

        $truyVan = DonHang::where('kenh', 'online')->with('khachHang')->latest();

        if (! empty($validated['hinh_thuc_nhan']) && KiemTraSchema::coCot('invoices', 'hinh_thuc_nhan')) {
            $truyVan->where('hinh_thuc_nhan', $validated['hinh_thuc_nhan']);
        }
        if (! empty($validated['trang_thai'])) {
            $truyVan->where('trang_thai', $validated['trang_thai']);
        }
        if (! empty($validated['trang_thai_giao']) && KiemTraSchema::coCot('invoices', 'trang_thai_giao')) {
            $truyVan->where('trang_thai_giao', $validated['trang_thai_giao']);
        }
        if (! empty($validated['tu_ngay'])) {
            $truyVan->whereDate('created_at', '>=', $validated['tu_ngay']);
        }
        if (! empty($validated['den_ngay'])) {
            $truyVan->whereDate('created_at', '<=', $validated['den_ngay']);
        }
        if (! empty($validated['tim'])) {
            $kw = '%'.$validated['tim'].'%';
            $truyVan->where(function ($q) use ($kw): void {
                $q->where('ma_hoadon', 'like', $kw)
                    ->orWhere('ma_tra_cuu', 'like', $kw)
                    ->orWhere('ten_khach_dat', 'like', $kw)
                    ->orWhere('ten_nguoi_nhan', 'like', $kw)
                    ->orWhere('so_dienthoai_dat', 'like', $kw)
                    ->orWhere('sdt_nguoi_nhan', 'like', $kw);
            });
        }

        return view('donhangonline.danhsach', [
            'thieuMigration' => false,
            'donHangs' => $truyVan->paginate(self::PHAN_TRANG)->withQueryString(),
            'filters' => $validated,
            'coCotGiao' => KiemTraSchema::coCot('invoices', 'trang_thai_giao'),
        ]);
    }

    /** Máy trạng thái giao hợp lệ (P3 HM2): chỉ đi tới, không nhảy bậc/ngược. */
    private const CHUYEN_TRANG_THAI = [
        'cho_xac_nhan' => 'da_xac_nhan',
        'da_xac_nhan' => 'dang_giao',
        'dang_giao' => 'da_giao',
    ];

    public function getChiTiet(DonHang $donHang): View|\Illuminate\Http\Response
    {
        abort_unless($donHang->kenh === 'online', 404);
        $donHang->load(['chiTietDonHangs.sanPham', 'khachHang']);

        $lichSu = KiemTraSchema::coBang('activity_logs')
            ? NhatKyHoatDong::where('doi_tuong', 'don_hang')
                ->where('doi_tuong_id', $donHang->id)
                ->where('hanh_dong', 'chuyen_trang_thai_giao')
                ->latest('id')->limit(20)->get()
            : collect();

        // Địa chỉ/SĐT đầy đủ chỉ cho nhân viên → cấm cache mọi tầng.
        return response()
            ->view('donhangonline.chitiet', [
                'don' => $donHang,
                'lichSu' => $lichSu,
                'trangThaiKe' => self::CHUYEN_TRANG_THAI[$donHang->trang_thai_giao] ?? null,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * Chuyển trạng thái giao (POST + CSRF). Idempotent: đã ở trạng thái đích
     * → thông báo "đã được xử lý"; sai thứ tự/chưa thanh toán → từ chối.
     * Điểm tích lũy đã cộng đúng 1 lần khi thanh toán (R2) → KHÔNG cộng lại ở đây.
     */
    public function postChuyenTrangThai(Request $request, DonHang $donHang): RedirectResponse
    {
        $validated = $request->validate([
            'trang_thai_giao_moi' => ['required', 'in:da_xac_nhan,dang_giao,da_giao'],
        ]);

        if ($donHang->kenh !== 'online' || $donHang->hinh_thuc_nhan !== 'giao_tan_noi') {
            return back()->with('error', 'Chỉ đơn online giao tận nơi mới chuyển trạng thái giao.');
        }
        if (! KiemTraSchema::coCot('invoices', 'trang_thai_giao')) {
            return back()->with('error', 'Cần áp migration cột trạng thái giao trước.');
        }

        $moi = $validated['trang_thai_giao_moi'];

        $ketQua = DB::transaction(function () use ($donHang, $moi): string {
            $don = DonHang::whereKey($donHang->id)->lockForUpdate()->firstOrFail();

            if ($don->trang_thai !== 'da_thanh_toan') {
                return 'err:chua_thanh_toan';
            }
            $choPhep = self::CHUYEN_TRANG_THAI[$don->trang_thai_giao] ?? null;
            if ($choPhep === null || $choPhep !== $moi) {
                return $don->trang_thai_giao === $moi ? 'ok' : 'err:sai_trang_thai';
            }

            $cu = $don->trang_thai_giao;
            $don->update(['trang_thai_giao' => $moi]);
            return 'ok:'.$cu;
        });

        if ($ketQua === 'err:chua_thanh_toan') {
            return back()->with('error', 'Đơn chưa thanh toán, không thể chuyển trạng thái giao.');
        }
        if ($ketQua === 'err:sai_trang_thai') {
            return back()->with('error', 'Trạng thái giao không hợp lệ hoặc đã được người khác cập nhật. Vui lòng tải lại trang.');
        }
        if ($ketQua === 'ok') {
            return back()->with('success', 'Trạng thái giao đã được xử lý trước đó.');
        }

        [, $cu] = explode(':', $ketQua, 2);
        NhatKyService::ghi(
            $request, 'chuyen_trang_thai_giao', 'don_hang', $donHang->id,
            'Đơn '.$donHang->ma_hoadon.': '.$cu.' → '.$moi,
            ['trang_thai_giao' => $cu],
            ['trang_thai_giao' => $moi],
            'web'
        );

        return back()->with('success', 'Đã chuyển trạng thái giao: '.$moi.'.');
    }

    /**
     * Badge menu: số đơn cần xử lý = giao tận nơi chờ xác nhận
     * + pickup online còn chờ thanh toán chưa quá hạn.
     */
    public function getSoLuongCanXuLy(): JsonResponse
    {
        $soLuong = 0;
        if ($this->coCotOnline()) {
            $soLuong += DonHang::where('kenh', 'online')
                ->where('trang_thai', 'da_thanh_toan')
                ->where('hinh_thuc_nhan', 'giao_tan_noi')
                ->where('trang_thai_giao', 'cho_xac_nhan')
                ->count();

            $soLuong += DonHang::where('kenh', 'online')
                ->where('phuong_thuc', 'tienmat')
                ->where('trang_thai', 'cho_thanh_toan')
                ->where(function ($q): void {
                    $q->whereNull('han_giu')->orWhere('han_giu', '>', now());
                })
                ->count();
        }

        return response()->json(['so_luong' => $soLuong])->header('Cache-Control', 'no-store');
    }

    /**
     * HM4 — hủy đơn ĐÃ thanh toán (route giới hạn role:admin).
     * Bắt buộc lý do; hoàn kho + thu hồi điểm + cờ cần hoàn tiền thủ công.
     * Không hoàn tiền tự động, không gọi API ngân hàng.
     */
    public function postHuyDonDaThanhToan(Request $request, DonHang $donHang, ThanhToanChuyenKhoanService $service): RedirectResponse
    {
        $validated = $request->validate([
            'ly_do' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'ly_do.required' => 'Vui lòng nhập lý do hủy đơn.',
            'ly_do.min' => 'Lý do hủy phải có ít nhất 5 ký tự.',
        ]);

        if ($donHang->kenh !== 'online') {
            return back()->with('error', 'Chỉ hủy đơn online tại màn hình này.');
        }

        $lyDo = trim($validated['ly_do']);
        $res = $service->huyDonDaThanhToan($donHang, $lyDo);

        if (! $res['success']) {
            return back()->with('error', $res['message']);
        }

        NhatKyService::ghi(
            $request, 'huy_don_da_thanh_toan', 'don_hang', $donHang->id,
            'Hủy đơn đã thanh toán '.$donHang->ma_hoadon.' — cần hoàn tiền thủ công',
            ['trang_thai' => 'da_thanh_toan'],
            ['trang_thai' => 'da_huy', 'can_hoan_tien' => $res['thieuCotCanHoanTien'] ? null : true],
            'web'
        );

        DB::afterCommit(function () use ($donHang, $lyDo): void {
            app(ThongBaoService::class)->thongBaoHuyDonCanHoanTien($donHang, $lyDo);
        });

        return back()->with('success', $res['message']);
    }

    /** Danh sách đơn cần hoàn tiền thủ công (admin). */
    public function getDonCanHoanTien(): View|\Illuminate\Http\Response
    {
        if (! KiemTraSchema::coCot('invoices', 'can_hoan_tien')) {
            return response()->view('donhangonline.canhoantien', [
                'thieuMigration' => true,
                'donHangs' => null,
            ]);
        }

        return view('donhangonline.canhoantien', [
            'thieuMigration' => false,
            'donHangs' => DonHang::where('can_hoan_tien', true)
                ->with('khachHang')->latest('updated_at')->paginate(self::PHAN_TRANG)->withQueryString(),
        ]);
    }

    /** Đánh dấu đã hoàn tiền thủ công (admin, bắt buộc ghi chú). */
    public function postDanhDauDaHoanTien(Request $request, DonHang $donHang): RedirectResponse
    {
        $validated = $request->validate([
            'ghi_chu' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'ghi_chu.required' => 'Vui lòng nhập ghi chú khi đánh dấu đã hoàn tiền.',
        ]);

        if (! KiemTraSchema::coCot('invoices', 'can_hoan_tien')) {
            return back()->with('error', 'Cần áp migration cột can_hoan_tien trước khi đánh dấu hoàn tiền.');
        }
        if (! $donHang->can_hoan_tien) {
            return back()->with('error', 'Đơn này không nằm trong danh sách cần hoàn tiền.');
        }

        $ghiChu = trim($validated['ghi_chu']);
        $update = ['can_hoan_tien' => false];
        if (KiemTraSchema::coCot('invoices', 'ghi_chu_noi_bo')) {
            $update['ghi_chu_noi_bo'] = trim('Đã hoàn tiền: '.$ghiChu.' | '.($donHang->ghi_chu_noi_bo ?? ''));
        }
        DonHang::whereKey($donHang->id)->update($update);

        NhatKyService::ghi(
            $request, 'danh_dau_da_hoan_tien', 'don_hang', $donHang->id,
            'Đánh dấu đã hoàn tiền đơn '.$donHang->ma_hoadon,
            ['can_hoan_tien' => true],
            ['can_hoan_tien' => false],
            'web'
        );

        return back()->with('success', 'Đã đánh dấu hoàn tiền cho đơn '.$donHang->ma_hoadon.'.');
    }

    /**
     * HM5 — phiếu giao hàng in cho đơn giao tận nơi (nhân viên).
     * Không giá vốn/lợi nhuận; no-store; ghi nhật ký lần in (không PII).
     */
    public function getPhieuGiao(Request $request, DonHang $donHang): View|\Illuminate\Http\Response
    {
        abort_unless($donHang->kenh === 'online' && $donHang->hinh_thuc_nhan === 'giao_tan_noi', 404);
        $donHang->load(['chiTietDonHangs.sanPham', 'khachHang']);

        NhatKyService::ghi(
            $request, 'in_phieu_giao', 'don_hang', $donHang->id,
            'In phiếu giao hàng đơn '.$donHang->ma_hoadon, null, null, 'web'
        );

        return response()
            ->view('donhangonline.phieugiaohang', ['don' => $donHang])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    private function coCotOnline(): bool
    {
        return KiemTraSchema::coBang('invoices')
            && KiemTraSchema::coCot('invoices', 'kenh')
            && KiemTraSchema::coCot('invoices', 'hinh_thuc_nhan');
    }

    /** Mask SĐT cho danh sách (dùng chung view). */
    public static function maskSdt(?string $sdt): string
    {
        return CheThongTin::soDienThoai($sdt);
    }
}
