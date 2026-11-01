<?php

namespace App\Http\Controllers;

use App\Exports\HoaDonExport;
use App\Exports\XuatNhapTonExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class BaoCaoController extends Controller
{
    public function getHoaDon(Request $request, string $dinhDang)
    {
        [$tuNgay, $denNgay] = $this->khoangNgay($request);
        $export = new HoaDonExport($tuNgay, $denNgay);
        if ($dinhDang === 'xlsx') {
            return Excel::download($export, 'hoa-don.xlsx');
        }
        abort_unless($dinhDang === 'pdf', 404);
        return Pdf::loadView('baocao.hoadon', ['rows' => $export->collection(), 'tuNgay' => $tuNgay, 'denNgay' => $denNgay])
            ->setPaper('a4')->download('hoa-don.pdf');
    }

    public function getXuatNhapTon(Request $request, string $dinhDang)
    {
        [$tuNgay, $denNgay] = $this->khoangNgay($request);
        $export = new XuatNhapTonExport($tuNgay, $denNgay);
        if ($dinhDang === 'xlsx') {
            return Excel::download($export, 'xuat-nhap-ton.xlsx');
        }
        abort_unless($dinhDang === 'pdf', 404);
        return Pdf::loadView('baocao.xuatnhapton', ['rows' => $export->collection(), 'tuNgay' => $tuNgay, 'denNgay' => $denNgay])
            ->setPaper('a4')->download('xuat-nhap-ton.pdf');
    }

    private function khoangNgay(Request $request): array
    {
        $validated = $request->validate([
            'tu' => ['nullable', 'date'],
            'den' => ['nullable', 'date', 'after_or_equal:tu'],
        ]);
        return [$validated['tu'] ?? Date::now()->toDateString(), $validated['den'] ?? Date::now()->toDateString()];
    }
}
