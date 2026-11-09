<?php

namespace App\Http\Controllers;

use App\Models\PaymentWebhook;
use App\Services\NhatKyService;
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

        return view('doisoat.index', compact('webhooks', 'trangThai'));
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
}
