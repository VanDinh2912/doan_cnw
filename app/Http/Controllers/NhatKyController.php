<?php

namespace App\Http\Controllers;

use App\Models\NhatKyHoatDong;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NhatKyController extends Controller
{
    public function index(Request $request): View
    {
        return view('nhatky.index', [
            'nhatKys' => $this->loc($request)->with('user:id,name')->latest()->paginate(30)->withQueryString(),
            'hanhDongs' => NhatKyHoatDong::query()->select('hanh_dong')->distinct()->orderBy('hanh_dong')->pluck('hanh_dong'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->loc($request)->with('user:id,name')->latest()->limit(10000)->get();
        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['Thời gian', 'Người dùng', 'Hành động', 'Đối tượng', 'Nguồn', 'Mô tả']);
            foreach ($rows as $row) {
                fputcsv($out, [$row->created_at?->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i:s'), $row->user?->name ?? 'Hệ thống', $row->hanh_dong, $row->doi_tuong.'#'.$row->doi_tuong_id, $row->nguon ?? 'web', $row->mo_ta]);
            }
            fclose($out);
        }, 'nhat-ky-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function loc(Request $request)
    {
        return NhatKyHoatDong::query()
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', (int) $request->input('user_id')))
            ->when($request->filled('doi_tuong'), fn ($q) => $q->where('doi_tuong', 'like', '%'.addcslashes((string) $request->input('doi_tuong'), '%_\\').'%'))
            ->when($request->filled('doi_tuong_id'), fn ($q) => $q->where('doi_tuong_id', (int) $request->input('doi_tuong_id')))
            ->when($request->filled('hanh_dong'), fn ($q) => $q->where('hanh_dong', (string) $request->input('hanh_dong')))
            ->when($request->filled('nguon') && Schema::hasColumn('activity_logs', 'nguon'), fn ($q) => $q->where('nguon', (string) $request->input('nguon')))
            ->when($request->filled('tu_ngay'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('tu_ngay')))
            ->when($request->filled('den_ngay'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('den_ngay')));
    }
}
