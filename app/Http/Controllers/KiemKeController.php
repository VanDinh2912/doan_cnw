<?php

namespace App\Http\Controllers;

use App\Models\BienDongKho;
use App\Models\ChiTietKiemKe;
use App\Models\PhieuKiemKe;
use App\Models\SanPham;
use App\Services\NhatKyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class KiemKeController extends Controller
{
    public function index(): View
    {
        return view('kiemke.index', ['phieuKies' => PhieuKiemKe::with('user')->latest()->paginate(15)]);
    }

    public function create(): View
    {
        return view('kiemke.create', ['sanPhams' => SanPham::where('trang_thai', true)->orderBy('ten_sanpham')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['ghi_chu' => ['nullable', 'string', 'max:255']]);
        $phieu = DB::transaction(function () use ($validated, $request): PhieuKiemKe {
            $phieu = PhieuKiemKe::create([
                'ma_phieu' => 'KK'.now()->format('YmdHis').random_int(10, 99),
                'user_id' => $request->user()->id,
                'trang_thai' => 'dang_kiem',
                'ghi_chu' => $validated['ghi_chu'] ?? null,
            ]);
            $now = now()->addSecond();
            foreach (SanPham::where('trang_thai', true)->get() as $product) {
                $phieu->chiTiets()->create([
                    'product_id' => $product->id,
                    'soluong_he_thong' => $product->soluong,
                    'thoi_diem_dem' => $now,
                ]);
            }
            return $phieu;
        });
        return redirect()->route('kiemke.show', $phieu)->with('success', 'Đã bắt đầu kiểm kê.');
    }

    public function show(PhieuKiemKe $phieu): View
    {
        $phieu->load('chiTiets.sanPham');
        return view('kiemke.show', compact('phieu'));
    }

    public function submit(Request $request, PhieuKiemKe $phieu): RedirectResponse
    {
        abort_unless($phieu->trang_thai === 'dang_kiem', 422, 'Phiếu không ở trạng thái đang kiểm.');
        $validated = $request->validate(['soluong_thuc' => ['required', 'array'], 'soluong_thuc.*' => ['required', 'integer', 'min:0']]);
        $phieu->load('chiTiets');
        foreach ($phieu->chiTiets as $item) {
            $actual = (int) ($validated['soluong_thuc'][$item->id] ?? $item->soluong_he_thong);
            $systemQuantity = (int) SanPham::query()->findOrFail($item->product_id)->soluong;
            $item->update(['soluong_thuc' => $actual, 'chenh_lech' => $actual - $systemQuantity]);
        }
        $phieu->update(['trang_thai' => 'cho_duyet']);
        return back()->with('success', 'Đã gửi phiếu chờ duyệt.');
    }

    public function approve(Request $request, PhieuKiemKe $phieu): RedirectResponse
    {
        DB::transaction(function () use ($request, $phieu): void {
            $phieu = PhieuKiemKe::query()->lockForUpdate()->with('chiTiets')->findOrFail($phieu->id);
            abort_unless($phieu->trang_thai === 'cho_duyet', 422, 'Phiếu chưa sẵn sàng duyệt.');
            foreach ($phieu->chiTiets->sortBy('product_id') as $item) {
                if ((int) $item->chenh_lech === 0) continue;
                $product = SanPham::query()->lockForUpdate()->findOrFail($item->product_id);
                $product->increment('soluong', $item->chenh_lech);
                BienDongKho::create([
                    'product_id' => $product->id, 'user_id' => $request->user()->id,
                    'loai' => 'dieuchinh', 'soluong' => $item->chenh_lech,
                    'stocktake_id' => $phieu->id, 'ghi_chu' => 'Điều chỉnh từ '.$phieu->ma_phieu,
                ]);
            }
            $phieu->update(['trang_thai' => 'da_duyet']);
        });
        NhatKyService::ghi($request, 'duyet_kiem_ke', 'phieu_kiem_ke', $phieu->id, 'Duyệt '.$phieu->ma_phieu);
        return back()->with('success', 'Đã duyệt kiểm kê.');
    }
}
