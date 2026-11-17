<?php

namespace App\Http\Controllers;

use App\Models\LienHe;
use App\Services\NhatKyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LienHeAdminController extends Controller
{
    public function getDanhSach(Request $request): View
    {
        $loai = $request->query('loai');
        $loai = is_string($loai) && in_array($loai, LienHe::LOAI, true) ? $loai : '';
        $trangThai = $request->query('trang_thai');
        $trangThai = is_string($trangThai) && in_array($trangThai, LienHe::TRANG_THAI, true) ? $trangThai : '';

        $query = LienHe::query()->latest('id');
        if ($loai !== '') {
            $query->where('loai', $loai);
        }
        if ($trangThai !== '') {
            $query->where('trang_thai', $trangThai);
        }

        return view('lienhe.danhsach', [
            'lienHes' => $query->paginate(20)->withQueryString(),
            'loai' => $loai,
            'trangThai' => $trangThai,
        ]);
    }

    public function getChiTiet(LienHe $lienHe): View
    {
        $lienHe->load('user');

        return view('lienhe.chitiet', compact('lienHe'));
    }

    public function postCapNhat(Request $request, LienHe $lienHe): RedirectResponse
    {
        $validated = $request->validate([
            'trang_thai' => ['required', 'string', 'in:'.implode(',', LienHe::TRANG_THAI)],
            'ghi_chu_noibo' => ['nullable', 'string', 'max:2000'],
        ], [
            'trang_thai.required' => 'Vui lòng chọn trạng thái.',
            'trang_thai.in' => 'Trạng thái không hợp lệ.',
        ]);

        $trangThaiCu = $lienHe->trang_thai;
        $ghiChuCu = $lienHe->ghi_chu_noibo;
        $trangThaiMoi = $validated['trang_thai'];
        $ghiChuMoi = isset($validated['ghi_chu_noibo']) ? trim($validated['ghi_chu_noibo']) : null;

        $lienHe->update([
            'trang_thai' => $trangThaiMoi,
            'ghi_chu_noibo' => $ghiChuMoi,
        ]);

        // Audit: mô tả chỉ gồm mã loại + chuyển trạng thái, KHÔNG chứa PII
        // (họ tên/SĐT/email/nội dung) theo quy ước che PII của NhatKyService.
        if ($trangThaiCu !== $trangThaiMoi) {
            NhatKyService::ghi(
                $request, 'cap_nhat_lien_he', 'lien_he', $lienHe->id,
                'Liên hệ #'.$lienHe->id.' ('.$lienHe->nhanLoai().'): trạng thái '.$trangThaiCu.' → '.$trangThaiMoi
            );
        }
        if ($ghiChuMoi !== $ghiChuCu && $ghiChuMoi !== null && $ghiChuMoi !== '') {
            NhatKyService::ghi(
                $request, 'ghi_chu_lien_he', 'lien_he', $lienHe->id,
                'Cập nhật ghi chú nội bộ cho liên hệ #'.$lienHe->id
            );
        }

        return back()->with('success', 'Đã cập nhật liên hệ.');
    }
}
