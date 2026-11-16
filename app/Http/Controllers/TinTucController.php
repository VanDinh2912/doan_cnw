<?php

namespace App\Http\Controllers;

use App\Models\BaiViet;
use App\Models\BinhLuanBaiViet;
use App\Models\ChuDe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tin tức phía khách (guest): danh sách / theo chủ đề / chi tiết + bình luận.
 * Chỉ bài đã duyệt và đang hiện mới xem được; bình luận phải đăng nhập và
 * chờ admin duyệt trước khi hiển thị.
 */
class TinTucController extends Controller
{
    public function getDanhSach(): View
    {
        return view('storefront.tintuc.danhsach', [
            'baiViets' => BaiViet::daDang()->with('chuDe')->latest('id')->paginate(10)->withQueryString(),
            'chuDes' => ChuDe::orderBy('ten_chude')->get(),
            'chuDeHienTai' => null,
        ]);
    }

    public function getTheoChuDe(string $chude_slug): View
    {
        $chuDe = ChuDe::where('slug', $chude_slug)->firstOrFail();

        return view('storefront.tintuc.danhsach', [
            'baiViets' => BaiViet::daDang()->where('topic_id', $chuDe->id)
                ->with('chuDe')->latest('id')->paginate(10)->withQueryString(),
            'chuDes' => ChuDe::orderBy('ten_chude')->get(),
            'chuDeHienTai' => $chuDe,
        ]);
    }

    public function getChiTiet(string $chude_slug, string $bv_slug): View
    {
        $chuDe = ChuDe::where('slug', $chude_slug)->firstOrFail();
        $baiViet = BaiViet::daDang()->with('tacGia')->where('topic_id', $chuDe->id)
            ->where('slug', $bv_slug)->firstOrFail();

        // Tăng lượt xem bằng query builder để không bắn sự kiện audit mỗi lượt xem.
        BaiViet::whereKey($baiViet->id)->increment('luot_xem');
        $baiViet->luot_xem++;

        return view('storefront.tintuc.chitiet', [
            'chuDe' => $chuDe,
            'baiViet' => $baiViet,
            'binhLuans' => $baiViet->binhLuans()->hienThi()->with('nguoiDung')->latest('id')->get(),
            'lienQuan' => BaiViet::daDang()->where('topic_id', $chuDe->id)
                ->where('id', '!=', $baiViet->id)->latest('id')->take(4)->get(),
        ]);
    }

    public function postBinhLuan(Request $request, string $chude_slug, string $bv_slug): RedirectResponse
    {
        $chuDe = ChuDe::where('slug', $chude_slug)->firstOrFail();
        $baiViet = BaiViet::daDang()->where('topic_id', $chuDe->id)
            ->where('slug', $bv_slug)->firstOrFail();

        $validated = $request->validate([
            'noi_dung' => ['required', 'string', 'max:1000'],
        ], [
            'noi_dung.required' => 'Vui lòng nhập nội dung bình luận.',
            'noi_dung.max' => 'Bình luận không được vượt quá 1000 ký tự.',
        ]);

        BinhLuanBaiViet::create([
            'post_id' => $baiViet->id,
            'user_id' => $request->user()->id,
            'noi_dung' => $validated['noi_dung'],
            'kiem_duyet' => false,
            'kich_hoat' => true,
        ]);

        return back()->with('success', 'Bình luận của bạn đã được gửi và đang chờ kiểm duyệt.');
    }
}