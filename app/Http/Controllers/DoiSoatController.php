<?php

namespace App\Http\Controllers;

use App\Models\PaymentWebhook;
use App\Models\XacNhanThuCong;
use App\Services\NhatKyService;
use App\Support\KiemTraSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DoiSoatController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'trang_thai_xu_ly' => ['nullable', 'in:chua_xu_ly,da_xu_ly,tat_ca'],
            'tu_ngay' => ['nullable', 'date'],
            'den_ngay' => ['nullable', 'date'],
        ]);

        $trangThai = $validated['trang_thai_xu_ly'] ?? 'chua_xu_ly';

        $webhooks = PaymentWebhook::with(['donHang', 'nguoiXuLy'])
            ->where('ket_qua', 'can_doi_soat')
            ->when($trangThai !== 'tat_ca', fn ($q) => $q->where('trang_thai_xu_ly', $trangThai))
            ->when(! empty($validated['tu_ngay']), fn ($q) => $q->where('created_at', '>=', $validated['tu_ngay'].' 00:00:00'))
            ->when(! empty($validated['den_ngay']), fn ($q) => $q->where('created_at', '<=', $validated['den_ngay'].' 23:59:59'))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        // M5: banner "SePay đang TẮT" khi cả webhook key lẫn API token đều trống.
        // Chỉ đọc cờ cấu hình, không đọc/in giá trị khóa.
        $sepayBat = filled(config('pos.sepay_webhook_api_key')) || filled(config('pos.sepay_api_token'));

        return view('doisoat.index', compact('webhooks', 'trangThai', 'sepayBat') + [
            'coBangXacNhanThuCong' => KiemTraSchema::coBang('xac_nhan_thu_cong'),
        ]);
    }

    public function show(PaymentWebhook $webhook): View
    {
        abort_unless($webhook->ket_qua === 'can_doi_soat', 404);
        $webhook->load(['donHang', 'nguoiXuLy']);

        return view('doisoat.show', compact('webhook'));
    }

    public function xuLy(Request $request, PaymentWebhook $webhook): RedirectResponse
    {
        abort_unless($webhook->ket_qua === 'can_doi_soat', 404);

        $validated = $request->validate([
            'ghi_chu' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        DB::transaction(function () use ($webhook, $validated, $request): void {
            $locked = PaymentWebhook::query()->lockForUpdate()->findOrFail($webhook->id);
            abort_unless($locked->trang_thai_xu_ly === 'chua_xu_ly', 422, 'Giao dịch đã được đánh dấu xử lý trước đó.');

            $locked->update([
                'trang_thai_xu_ly' => 'da_xu_ly',
                'ghi_chu_xu_ly' => $validated['ghi_chu'],
                'nguoi_xu_ly_id' => $request->user()->id,
                'thoi_gian_xu_ly' => now(),
            ]);
        });

        NhatKyService::ghi(
            $request,
            'doi_soat_danh_dau',
            'payment_webhook',
            $webhook->id,
            'Đánh dấu đã xử lý giao dịch '.$webhook->ma_giao_dich_ngoai
                .' ('.$webhook->ly_do.'): '.$validated['ghi_chu']
        );

        return redirect()->route('doisoat.index')->with('success', 'Đã đánh dấu giao dịch cần đối soát là đã xử lý.');
    }

    /**
     * M5 — Soát các lần XÁC NHẬN THANH TOÁN THỦ CÔNG (không qua webhook).
     * Mặc định lọc "chưa đối chiếu"; chỉ đọc được khi bảng đã migrate.
     */
    public function thuCong(Request $request): View
    {
        abort_unless(KiemTraSchema::coBang('xac_nhan_thu_cong'), 404);

        $validated = $request->validate([
            'trang_thai_soat' => ['nullable', 'in:chua,daxong,all'],
            'tu_ngay' => ['nullable', 'date'],
            'den_ngay' => ['nullable', 'date'],
        ]);

        $trangThai = $validated['trang_thai_soat'] ?? 'chua';
        $query = XacNhanThuCong::with(['donHang', 'nguoiXacNhan', 'adminDuyet', 'soatBoi', 'yeuCauDuyet'])
            ->when($trangThai === 'chua', fn ($q) => $q->whereNull('soat_luc'))
            ->when($trangThai === 'daxong', fn ($q) => $q->whereNotNull('soat_luc'))
            ->when(! empty($validated['tu_ngay']), fn ($q) => $q->whereDate('xac_nhan_luc', '>=', $validated['tu_ngay']))
            ->when(! empty($validated['den_ngay']), fn ($q) => $q->whereDate('xac_nhan_luc', '<=', $validated['den_ngay']))
            ->orderByDesc('xac_nhan_luc');

        $tongNgay = (clone $query)->sum('so_tien');
        $soDongNgay = (clone $query)->count();

        return view('doisoat.thu-cong', [
            'ds' => $query->paginate(30)->withQueryString(),
            'trangThai' => $trangThai,
            'tuNgay' => $validated['tu_ngay'] ?? '',
            'denNgay' => $validated['den_ngay'] ?? '',
            'tongNgay' => $tongNgay,
            'soDongNgay' => $soDongNgay,
        ]);
    }

    /** Đánh dấu đã đối chiếu sao kê — mức A (pin.muc). */
    public function soatXong(Request $request, XacNhanThuCong $xacNhan): RedirectResponse
    {
        abort_unless(KiemTraSchema::coBang('xac_nhan_thu_cong'), 404);

        $validated = $request->validate([
            'ghi_chu_soat' => ['nullable', 'string', 'max:500'],
        ]);

        $xacNhan->update([
            'soat_boi_id' => $request->user()->id,
            'soat_luc' => now(),
            'ghi_chu_soat' => $validated['ghi_chu_soat'] ?? null,
        ]);

        NhatKyService::ghi(
            $request, 'xac_nhan_thu_cong_soat', 'xac_nhan_thu_cong', $xacNhan->id,
            'Đã đối chiếu sao kê xác nhận thủ công #'.$xacNhan->id
                .' (hóa đơn '.$xacNhan->donHang?->ma_hoadon.')'
        );

        return back()->with('success', 'Đã đánh dấu đã đối chiếu sao kê.');
    }
}
