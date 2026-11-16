<?php

namespace App\Http\Controllers;

use App\Models\BinhLuanBaiViet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BinhLuanBaiVietController extends Controller
{
    public function getDanhSach(Request $request): View
    {
        $query = BinhLuanBaiViet::with(['baiViet', 'nguoiDung'])->latest('id');

        $loc = $request->string('loc')->toString();
        if ($loc === 'cho_duyet') {
            $query->where('kiem_duyet', false);
        } elseif ($loc === 'da_duyet') {
            $query->where('kiem_duyet', true)->where('kich_hoat', true);
        } elseif ($loc === 'da_an') {
            $query->where('kich_hoat', false);
        }

        return view('binhluan.danhsach', [
            'binhLuans' => $query->paginate(20)->withQueryString(),
            'loc' => $loc,
            'soChoDuyet' => BinhLuanBaiViet::where('kiem_duyet', false)->count(),
        ]);
    }

    public function postDuyet(BinhLuanBaiViet $binhLuan): RedirectResponse
    {
        $binhLuan->update(['kiem_duyet' => true, 'kich_hoat' => true]);

        return back()->with('success', 'Đã duyệt bình luận.');
    }

    public function postAn(BinhLuanBaiViet $binhLuan): RedirectResponse
    {
        $binhLuan->update(['kich_hoat' => ! $binhLuan->kich_hoat]);

        return back()->with('success', $binhLuan->kich_hoat ? 'Đã hiện bình luận.' : 'Đã ẩn bình luận.');
    }

    public function getXoa(BinhLuanBaiViet $binhLuan): RedirectResponse
    {
        $binhLuan->delete();

        return back()->with('success', 'Đã xóa bình luận.');
    }
}