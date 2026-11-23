<?php

namespace App\Http\Controllers;

use App\Exports\SanPhamExport;
use App\Exports\SanPhamImportTemplateExport;
use App\Services\NhatKyService;
use App\Services\SanPhamImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SanPhamExcelController extends Controller
{
    private const MIME_XLSX = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
        'application/x-zip-compressed',
        'application/octet-stream',
    ];

    public function __construct(private readonly SanPhamImportService $import) {}

    /** GET /sanpham/xuat-excel — admin + thu kho (nhom route). */
    public function getXuatExcel(): BinaryFileResponse
    {
        // GD1: canh bao khi APP_URL la localhost — cot hinh_anh_url trong file
        // xuat se tro ve may nay va khong the tai lai (SSRF chan IP noi bo).
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            session()->flash('warning', 'APP_URL đang là localhost — cột hinh_anh_url trong file xuất trỏ về máy này; URL nội bộ sẽ bị từ chối khi import lại. Hãy đặt APP_URL public trước khi import file này ở nơi khác.');
        }

        return Excel::download(new SanPhamExport, 'sanpham-'.now()->format('Ymd-His').'.xlsx');
    }

    /** GET /sanpham/import-mau — file mau (admin). */
    public function getFileMau(): BinaryFileResponse
    {
        return Excel::download(new SanPhamImportTemplateExport, 'mau-import-sanpham.xlsx');
    }

    /** GET /sanpham/import-excel — form upload (admin). */
    public function getForm(): View
    {
        return view('sanpham.import');
    }

    /** POST /sanpham/import-excel — doc file, hien preview (admin). */
    public function postUpload(Request $request): View|RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:2048'],
        ], [
            'file.required' => 'Vui lòng chọn file .xlsx.',
            'file.max' => 'File vượt quá 2MB.',
        ]);

        $file = $validated['file'];
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $mime = (string) $file->getMimeType();
        $okMime = $ext === 'xlsx' && in_array($mime, self::MIME_XLSX, true);
        if (! $okMime) {
            return redirect()->route('sanpham.import')
                ->with('error', 'Chỉ chấp nhận file .xlsx thật (kiểm tra phần mở rộng và định dạng).')
                ->withInput();
        }

        $doc = $this->import->docFile($file);
        if (isset($doc['header_loi'])) {
            return redirect()->route('sanpham.import')
                ->with('error', $doc['header_loi'])
                ->withInput();
        }

        // GD1: file co cot hinh_anh_url nhung DB chua co cot metadata -> bo qua em
        if (isset($doc['cot']['nhan']['hinh_anh_url']) && ! $this->import->coCotAnh()) {
            $doc['cot']['bo_qua'][] = 'hinh_anh_url (chưa cập nhật CSDL — bỏ qua)';
            unset($doc['cot']['nhan']['hinh_anh_url']);
        }

        $keHoach = $this->import->lapKeHoach($doc);
        if (! $keHoach['ok']) {
            return view('sanpham.import-preview', [
                'token' => null,
                'loiFile' => $keHoach['errors'],
                'rows' => $keHoach['rows'],
                'counts' => $keHoach['counts'],
                'cot' => $doc['cot'] ?? null,
                'fileName' => $file->getClientOriginalName(),
            ]);
        }

        $token = $this->import->luuPreview(
            (int) $request->user()->id,
            $file->getClientOriginalName(),
            $doc['file_hash'],
            (int) $file->getSize(),
            $keHoach,
            $doc
        );

        return view('sanpham.import-preview', [
            'token' => $token,
            'loiFile' => [],
            'rows' => $keHoach['rows'],
            'counts' => $keHoach['counts'],
            'cot' => $doc['cot'] ?? null,
            'fileName' => $file->getClientOriginalName(),
        ]);
    }

    /** POST /sanpham/import-excel/xac-nhan — ghi du lieu (admin). */
    public function postXacNhan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'size:48'],
        ]);

        $ketQua = $this->import->xacNhan($validated['token'], (int) $request->user()->id);
        if (! $ketQua['ok']) {
            if (($ketQua['cam'] ?? null) === 403) {
                abort(403, $ketQua['loi']);
            }

            return redirect()->route('sanpham.import')->with('error', $ketQua['loi']);
        }

        $preview = $ketQua['preview'];
        $kq = $ketQua['ket_qua'];
        $soAnhLoi = count($kq['anh_loi'] ?? []);
        NhatKyService::ghi(
            $request,
            'import_san_pham',
            'SanPham',
            null,
            sprintf(
                'Import Excel %s (%d KB, sha256 %s): tạo mới %d, cập nhật %d, ảnh tải %d lỗi %d',
                $preview['file_name'] ?? '?',
                (int) round(((int) ($preview['file_size'] ?? 0)) / 1024),
                substr((string) ($preview['file_hash'] ?? ''), 0, 12),
                $kq['tao_moi'],
                $kq['cap_nhat'],
                (int) ($kq['anh_thanh_cong'] ?? 0),
                $soAnhLoi
            ),
            null,
            null,
            'web',
            'import_'.$preview['file_hash']
        );

        $thongBao = "Import thành công: tạo mới {$kq['tao_moi']} sản phẩm (trạng thái NGỪNG BÁN, cần nhập kho rồi bật 'Đang bán'), cập nhật {$kq['cap_nhat']} sản phẩm.";
        if (($kq['anh_thanh_cong'] ?? 0) > 0) {
            $thongBao .= " Ảnh tải về: {$kq['anh_thanh_cong']}.";
        }
        if ($soAnhLoi > 0) {
            $chiTiet = [];
            foreach (array_slice($kq['anh_loi'] ?? [], 0, 5, true) as $dongAnh => $lyDoAnh) {
                $chiTiet[] = "Dòng {$dongAnh}: {$lyDoAnh}";
            }
            $thongBao .= " {$soAnhLoi} ảnh tải lỗi — sản phẩm vẫn được nhập, ảnh giữ nguyên/cũ."
                .($chiTiet !== [] ? ' '.implode(' | ', $chiTiet).($soAnhLoi > 5 ? ' | …' : '') : '');
            return redirect()->route('sanpham.danhsach')->with('warning', $thongBao);
        }

        return redirect()->route('sanpham.danhsach')->with('success', $thongBao);
    }
}
