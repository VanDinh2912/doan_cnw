<?php

namespace App\Http\Controllers;

use App\Models\BienDongKho;
use App\Models\ChiTietPhieuTra;
use App\Models\DonHang;
use App\Models\PhieuTra;
use App\Models\SanPham;
use App\Services\NhatKyService;
use App\Support\KiemTraSchema;
use Brick\Math\BigInteger;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class TraHangController extends Controller
{
    public function getChon(Request $request, DonHang $donHang): View
    {
        abort_unless($donHang->trang_thai === 'da_thanh_toan', 422, 'Chỉ được trả hàng từ hóa đơn đã thanh toán.');
        $donHang->load(['chiTietDonHangs.sanPham', 'khachHang']);

        // Phân rã tiền hoàn cho phiếu trả: tiền hàng sau giảm giá + phí giao riêng.
        $phiGiaoHang = KiemTraSchema::coCot('invoices', 'phi_giao_hang')
            ? (float) ($donHang->phi_giao_hang ?? 0) : 0.0;
        $coCotTienHoanShip = KiemTraSchema::coCot('returns', 'tien_hoan_phi_giao');
        $daHoanPhiGiao = $coCotTienHoanShip && PhieuTra::query()
            ->where('invoice_id', $donHang->id)
            ->whereIn('trang_thai', ['hoan_thanh', 'cho_duyet'])
            ->where('tien_hoan_phi_giao', '>', 0)
            ->exists();

        return view('trahang.chon', [
            'donHang' => $donHang,
            'laAdmin' => $request->user()?->role === 'admin',
            'phiGiaoHang' => $phiGiaoHang,
            'tienHangDaThanhToan' => max(0.0, (float) $donHang->tong_thanh_toan - $phiGiaoHang),
            'daHoanPhiGiao' => $daHoanPhiGiao,
        ]);
    }

    public function postTraHang(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'hinh_thuc_hoan' => ['required', 'in:tienmat,chuyenkhoan,doi_hang'],
            'ly_do' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array'],
            'items.*.invoice_detail_id' => ['nullable', 'integer', 'distinct', 'exists:invoice_details,id'],
            'items.*.soluong' => ['nullable', 'integer', 'min:0'],
            'items.*.tinh_trang' => ['nullable', 'in:con_tot,loi_hong'],
            'hoan_phi_giao' => ['nullable', 'in:0,1'],
        ]);
        $validated['items'] = collect($validated['items'])
            ->filter(fn (array $item): bool => (int) ($item['soluong'] ?? 0) > 0)
            ->values()->all();
        abort_if($validated['items'] === [], 422, 'Vui lòng chọn ít nhất một sản phẩm để trả.');

        $phieuTra = DB::transaction(function () use ($validated, $request): PhieuTra {
            $donHang = DonHang::query()->lockForUpdate()
                ->with('khachHang')->findOrFail($validated['invoice_id']);
            abort_unless($donHang->trang_thai === 'da_thanh_toan', 422, 'Hóa đơn chưa thanh toán.');
            abort_if($donHang->created_at->lt(now()->subDays(config('pos.han_tra_ngay', 7))), 422, 'Hóa đơn đã quá thời hạn trả.');

            $chiTietIds = collect($validated['items'])->pluck('invoice_detail_id')->unique();
            $chiTiets = $donHang->chiTietDonHangs()->whereIn('id', $chiTietIds)->lockForUpdate()->get()->keyBy('id');
            abort_if($chiTiets->count() !== $chiTietIds->count(), 422, 'Chi tiết trả không thuộc hóa đơn.');

            // Quy tắc hoàn phí giao (chốt 2026-11-16): tiền hoàn tính trên TIỀN HÀNG
            // sau giảm giá, KHÔNG gồm phí giao. Chỉ admin tick "Hoàn cả phí giao"
            // (bắt buộc lý do, một lần/đơn) mới cộng thêm phi_giao_hang.
            // Guard code-đi-trước-schema: thiếu cột → hành vi cũ (không hoàn ship).
            $coCotTienHoanShip = KiemTraSchema::coCot('returns', 'tien_hoan_phi_giao');
            $muonHoanShip = (bool) ($validated['hoan_phi_giao'] ?? false);
            if ($muonHoanShip && ! $coCotTienHoanShip) {
                abort(422, 'Tính năng hoàn phí giao chưa sẵn sàng (thiếu migration cột tien_hoan_phi_giao).');
            }
            $phiGiaoHang = KiemTraSchema::coCot('invoices', 'phi_giao_hang')
                ? (float) ($donHang->phi_giao_hang ?? 0) : 0.0;
            if ($muonHoanShip) {
                abort_unless($request->user()->role === 'admin', 403, 'Chỉ quản trị viên được hoàn phí giao hàng.');
                abort_if($phiGiaoHang <= 0, 422, 'Đơn này không có phí giao hàng.');
                abort_if(trim((string) ($validated['ly_do'] ?? '')) === '', 422, 'Vui lòng nhập lý do khi hoàn phí giao hàng.');
                $daHoanShip = PhieuTra::query()
                    ->where('invoice_id', $donHang->id)
                    ->whereIn('trang_thai', ['hoan_thanh', 'cho_duyet'])
                    ->where('tien_hoan_phi_giao', '>', 0)
                    ->exists();
                abort_if($daHoanShip, 422, 'Đơn này đã hoàn phí giao hàng.');
            }
            $tienHoanPhiGiao = $muonHoanShip ? (int) round($phiGiaoHang) : 0;
            // Tỷ lệ trên tiền hàng đã trả: (tong_thanh_toan - phí ship) / tong_tien.
            // Đơn không ship / thiếu cột → tỷ lệ giống hành vi cũ.
            $tyLeHang = max(0.0, (float) $donHang->tong_thanh_toan - $phiGiaoHang)
                / max(1.0, (float) $donHang->tong_tien);

            $tongHoan = 0;
            $giaTriHangTra = 0;
            $items = [];
            foreach ($validated['items'] as $item) {
                $chiTiet = $chiTiets[$item['invoice_detail_id']];
                $daTra = (int) ChiTietPhieuTra::query()
                    ->where('invoice_detail_id', $chiTiet->id)
                    ->whereHas('phieuTra', fn ($query) => $query->whereIn('trang_thai', ['hoan_thanh', 'cho_duyet']))
                    ->sum('soluong');
                $soLuongCon = $chiTiet->soluong - $daTra;
                abort_if($item['soluong'] > $soLuongCon, 422, 'Số lượng trả vượt số lượng còn được trả.');

                $tienDong = (int) round($item['soluong'] * $chiTiet->don_gia * $tyLeHang);
                $tongHoan += $tienDong;
                $giaTriHangTra += (int) $item['soluong'] * (int) $chiTiet->don_gia;
                $items[] = [$chiTiet, $item, $tienDong];
            }

            // Tổng hoàn tích lũy không vượt số khách đã thanh toán (kể cả phí ship).
            $daHoan = (float) PhieuTra::query()
                ->where('invoice_id', $donHang->id)
                ->whereIn('trang_thai', ['hoan_thanh', 'cho_duyet'])
                ->sum('tong_hoan');
            $conLai = (float) $donHang->tong_thanh_toan - $daHoan;
            abort_if($conLai < -1, 422, 'Hóa đơn đã hoàn đủ số tiền thanh toán.');
            $tongHoan = min($tongHoan + $tienHoanPhiGiao, max(0, (int) round($conLai)));

            // Điểm thu hồi theo phần TIỀN HÀNG hoàn (không gồm phí ship — điểm
            // tích lũy vốn tính trên tiền hàng sau giảm giá, không gồm ship).
            $diemThuHoi = (int) floor(max(0, $tongHoan - $tienHoanPhiGiao) / max(1, config('pos.diem_moi_dong', 100000)));
            $diemHoan = self::tinhDiemHoan($donHang, $giaTriHangTra);
            $duyetNgay = $tongHoan <= config('pos.nguong_tra_khong_duyet', 500000);
            $thuocTinhPhieu = [
                'invoice_id' => $donHang->id,
                'user_id' => $request->user()->id,
                'customer_id' => $donHang->customer_id,
                'tong_hoan' => $tongHoan,
                'diem_thu_hoi' => $diemThuHoi,
                'diem_hoan' => $diemHoan,
                'hinh_thuc_hoan' => $validated['hinh_thuc_hoan'],
                'trang_thai' => $duyetNgay ? 'hoan_thanh' : 'cho_duyet',
                'ly_do' => $validated['ly_do'] ?? null,
            ];
            if ($coCotTienHoanShip) {
                $thuocTinhPhieu['tien_hoan_phi_giao'] = $tienHoanPhiGiao;
            }
            $phieuTra = null;
            for ($lanThu = 1; $lanThu <= 5; $lanThu++) {
                try {
                    $phieuTra = PhieuTra::create($thuocTinhPhieu + ['ma_phieu' => self::sinhMaPhieuTra()]);
                    break;
                } catch (QueryException $exception) {
                    // Giao dịch khác có thể chèn cùng mã giữa lúc dò và lúc tạo; thử lại với mã mới.
                    if ($lanThu === 5 || ! self::loiTrungMaPhieu($exception)) {
                        throw $exception;
                    }
                }
            }
            foreach ($items as [$chiTiet, $item, $tienDong]) {
                $phieuTra->chiTiets()->create([
                    'invoice_detail_id' => $chiTiet->id,
                    'product_id' => $chiTiet->product_id,
                    'soluong' => $item['soluong'],
                    'don_gia' => $chiTiet->don_gia,
                    'gia_von' => $chiTiet->gia_von,
                    'thanh_tien' => $tienDong,
                    'tinh_trang' => $item['tinh_trang'],
                ]);
            }
            if ($duyetNgay) {
                self::apDung($phieuTra, $request);
            }

            return $phieuTra;
        });

        NhatKyService::ghi($request, 'tao_phieu_tra', 'phieu_tra', $phieuTra->id, 'Tạo phiếu '.$phieuTra->ma_phieu);
        if ((int) ($phieuTra->tien_hoan_phi_giao ?? 0) > 0) {
            NhatKyService::ghi(
                $request, 'hoan_phi_giao_hang', 'phieu_tra', $phieuTra->id,
                'Hoàn phí giao hàng trong phiếu '.$phieuTra->ma_phieu.' (đơn '.$phieuTra->donHang->ma_hoadon.')'
            );
        }

        return redirect()->route('trahang.xem', $phieuTra)->with('success', 'Đã tạo phiếu trả hàng.');
    }

    public function getDanhSach(): View
    {
        return view('trahang.danhsach', [
            'phieuTras' => PhieuTra::with(['donHang', 'user'])->latest()->paginate(20),
        ]);
    }

    public function getXem(PhieuTra $phieuTra): View
    {
        $phieuTra->load(['donHang', 'chiTiets.sanPham', 'user']);

        return view('trahang.xem', compact('phieuTra'));
    }

    public function postDuyet(Request $request, PhieuTra $phieuTra): RedirectResponse
    {
        abort_unless($phieuTra->trang_thai === 'cho_duyet', 422, 'Phiếu không ở trạng thái chờ duyệt.');
        DB::transaction(function () use ($phieuTra, $request): void {
            $phieuTra = PhieuTra::query()->lockForUpdate()->findOrFail($phieuTra->id);
            abort_unless($phieuTra->trang_thai === 'cho_duyet', 422, 'Phiếu không ở trạng thái chờ duyệt.');
            self::apDung($phieuTra, $request);
        });

        NhatKyService::ghi($request, 'duyet_phieu_tra', 'phieu_tra', $phieuTra->id, 'Duyệt phiếu '.$phieuTra->ma_phieu);

        return back()->with('success', 'Đã duyệt phiếu trả hàng.');
    }

    private static function sinhMaPhieuTra(): string
    {
        $tienTo = 'TH'.now()->format('YmdHis');
        $daDung = PhieuTra::query()
            ->where('ma_phieu', 'like', $tienTo.'%')
            ->pluck('ma_phieu')
            ->map(fn (string $ma): int => (int) substr($ma, -2))
            ->all();
        // Quét tiến từ điểm ngẫu nhiên (quay vòng 10..99) để luôn ra mã khác
        // kể cả khi bộ sinh số lặp lại giá trị; giữ định dạng TH+YmdHis+2 số.
        $batDau = random_int(10, 99);
        for ($buoc = 0; $buoc < 90; $buoc++) {
            $hauTo = 10 + (($batDau - 10 + $buoc) % 90);
            if (! in_array($hauTo, $daDung, true)) {
                return $tienTo.$hauTo;
            }
        }

        throw new RuntimeException('Đã dùng hết 90 mã phiếu trả trong giây '.now()->format('YmdHis'));
    }

    private static function loiTrungMaPhieu(QueryException $exception): bool
    {
        return (string) $exception->getCode() === '23000'
            && str_contains($exception->getMessage(), 'ma_phieu');
    }

    private static function tinhDiemHoan(DonHang $donHang, int $giaTriHangTra): int
    {
        $diemGoc = (int) $donHang->diem_su_dung;
        if ($diemGoc <= 0) {
            return 0;
        }

        $daCamKet = (int) PhieuTra::query()
            ->where('invoice_id', $donHang->id)
            ->whereIn('trang_thai', ['hoan_thanh', 'cho_duyet'])
            ->sum('diem_hoan');
        $giaTriDaTra = ChiTietPhieuTra::query()
            ->whereHas('phieuTra', fn ($query) => $query
                ->where('invoice_id', $donHang->id)
                ->whereIn('trang_thai', ['hoan_thanh', 'cho_duyet']))
            ->get(['soluong', 'don_gia'])
            ->sum(fn ($item): int => (int) $item->soluong * (int) $item->don_gia);

        // Giá trị gốc cộng dồn tránh mất điểm do tiền hoàn bằng 0 hoặc làm tròn từng dòng.
        $tongTien = max(1, (int) $donHang->tong_tien);
        $giaTriLuyKe = min($tongTien, $giaTriDaTra + $giaTriHangTra);
        $diemLuyKe = BigInteger::of($diemGoc)
            ->multipliedBy($giaTriLuyKe)->quotient($tongTien)->toInt();

        return max(0, min($diemGoc - $daCamKet, $diemLuyKe - $daCamKet));
    }

    private static function apDung(PhieuTra $phieuTra, Request $request): void
    {
        $phieuTra->load(['chiTiets', 'donHang']);
        foreach ($phieuTra->chiTiets->sortBy('product_id') as $item) {
            $sanPham = SanPham::query()->lockForUpdate()->findOrFail($item->product_id);
            if ($item->tinh_trang === 'con_tot') {
                $sanPham->increment('soluong', $item->soluong);
                $loai = 'trahang';
            } else {
                $sanPham->increment('soluong_hong', $item->soluong);
                $loai = 'trahang_hong';
            }
            BienDongKho::create([
                'product_id' => $sanPham->id,
                'user_id' => $request->user()->id,
                'invoice_id' => $phieuTra->invoice_id,
                'return_id' => $phieuTra->id,
                'loai' => $loai,
                'soluong' => $item->soluong,
                'ghi_chu' => 'Trả hàng từ '.$phieuTra->ma_phieu,
            ]);
        }
        $diemThuHoi = (int) $phieuTra->diem_thu_hoi;
        $diemHoan = (int) $phieuTra->diem_hoan;
        if ($phieuTra->donHang->customer_id && ($diemThuHoi > 0 || $diemHoan > 0)) {
            $customer = $phieuTra->donHang->khachHang()->lockForUpdate()->first();
            if ($customer) {
                $soDu = (int) $customer->diem_tichluy;
                $thuHoiThucTe = min($soDu, $diemThuHoi);
                $customer->diem_tichluy = $soDu - $thuHoiThucTe + $diemHoan;
                $customer->save();

                if ($diemHoan > 0) {
                    NhatKyService::ghi(
                        $request,
                        'hoan_diem_su_dung',
                        'phieu_tra',
                        $phieuTra->id,
                        'Hoàn '.$diemHoan.' điểm đã dùng cho phiếu '.$phieuTra->ma_phieu
                            .' (thu hồi '.$thuHoiThucTe.'/'.$diemThuHoi.' điểm, số dư '.$soDu.' → '.$customer->diem_tichluy.')'
                    );
                }
            }
        }
        $phieuTra->update(['trang_thai' => 'hoan_thanh']);
    }
}
