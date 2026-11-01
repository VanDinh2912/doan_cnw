<?php

namespace App\Http\Controllers;

use App\Models\BienDongKho;
use App\Models\ChiTietPhieuTra;
use App\Models\DonHang;
use App\Models\PhieuTra;
use App\Models\SanPham;
use App\Services\NhatKyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class TraHangController extends Controller
{
    public function getChon(DonHang $donHang): View
    {
        abort_unless($donHang->trang_thai === 'da_thanh_toan', 422, 'Chỉ được trả hàng từ hóa đơn đã thanh toán.');
        $donHang->load(['chiTietDonHangs.sanPham', 'khachHang']);

        return view('trahang.chon', compact('donHang'));
    }

    public function postTraHang(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'hinh_thuc_hoan' => ['required', 'in:tienmat,chuyenkhoan,doi_hang'],
            'ly_do' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array'],
            'items.*.invoice_detail_id' => ['nullable', 'integer', 'exists:invoice_details,id'],
            'items.*.soluong' => ['nullable', 'integer', 'min:0'],
            'items.*.tinh_trang' => ['nullable', 'in:con_tot,loi_hong'],
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

            $tongHoan = 0;
            $items = [];
            foreach ($validated['items'] as $item) {
                $chiTiet = $chiTiets[$item['invoice_detail_id']];
                $daTra = (int) ChiTietPhieuTra::query()
                    ->where('invoice_detail_id', $chiTiet->id)
                    ->whereHas('phieuTra', fn ($query) => $query->where('trang_thai', 'hoan_thanh'))
                    ->sum('soluong');
                $soLuongCon = $chiTiet->soluong - $daTra;
                abort_if($item['soluong'] > $soLuongCon, 422, 'Số lượng trả vượt số lượng còn được trả.');

                $tienDong = (int) round($item['soluong'] * $chiTiet->don_gia
                    * ((float) $donHang->tong_thanh_toan / max(1, (float) $donHang->tong_tien)));
                $tongHoan += $tienDong;
                $items[] = [$chiTiet, $item, $tienDong];
            }

            $diemThuHoi = (int) floor($tongHoan / max(1, config('pos.diem_moi_dong', 100000)));
            $duyetNgay = $tongHoan <= config('pos.nguong_tra_khong_duyet', 500000);
            $phieuTra = PhieuTra::create([
                'ma_phieu' => 'TH'.now()->format('YmdHis').random_int(10, 99),
                'invoice_id' => $donHang->id,
                'user_id' => $request->user()->id,
                'customer_id' => $donHang->customer_id,
                'tong_hoan' => $tongHoan,
                'diem_thu_hoi' => $diemThuHoi,
                'hinh_thuc_hoan' => $validated['hinh_thuc_hoan'],
                'trang_thai' => $duyetNgay ? 'hoan_thanh' : 'cho_duyet',
                'ly_do' => $validated['ly_do'] ?? null,
            ]);
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
            self::apDung($phieuTra, $request);
        });

        NhatKyService::ghi($request, 'duyet_phieu_tra', 'phieu_tra', $phieuTra->id, 'Duyệt phiếu '.$phieuTra->ma_phieu);

        return back()->with('success', 'Đã duyệt phiếu trả hàng.');
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
        if ($phieuTra->donHang->customer_id && $phieuTra->diem_thu_hoi > 0) {
            $customer = $phieuTra->donHang->khachHang()->lockForUpdate()->first();
            if ($customer) {
                $customer->decrement('diem_tichluy', min($customer->diem_tichluy, $phieuTra->diem_thu_hoi));
            }
        }
        $phieuTra->update(['trang_thai' => 'hoan_thanh']);
    }
}
