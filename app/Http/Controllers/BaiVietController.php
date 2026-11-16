<?php

namespace App\Http\Controllers;

use App\Models\BaiViet;
use App\Models\ChuDe;
use App\Support\LocHtml;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BaiVietController extends Controller
{
    public function getDanhSach(Request $request): View
    {
        $query = BaiViet::with(['chuDe', 'tacGia'])->latest('id');

        if ($request->filled('q')) {
            $query->where('tieu_de', 'like', '%'.$request->string('q')->toString().'%');
        }
        if ($request->filled('kiem_duyet')) {
            $query->where('kiem_duyet', $request->boolean('kiem_duyet'));
        }

        return view('baiviet.danhsach', [
            'baiViets' => $query->paginate(15)->withQueryString(),
        ]);
    }

    public function getThem(): View
    {
        return view('baiviet.form', [
            'baiViet' => new BaiViet(['kiem_duyet' => true, 'kich_hoat' => true]),
            'chuDes' => ChuDe::orderBy('ten_chude')->get(),
        ]);
    }

    public function postThem(Request $request): RedirectResponse
    {
        $validated = $this->validateBaiViet($request);

        $validated['noi_dung'] = LocHtml::lamSach($validated['noi_dung']);
        $validated['slug'] = $this->taoSlug($validated['tieu_de']);
        $validated['user_id'] = $request->user()->id;
        $validated['kiem_duyet'] = $request->boolean('kiem_duyet');
        $validated['kich_hoat'] = $request->boolean('kich_hoat');

        if ($request->hasFile('hinh_anh')) {
            $validated['hinh_anh'] = $request->file('hinh_anh')->store('tintuc', 'public');
        }

        BaiViet::create($validated);

        return redirect()->route('baiviet.danhsach')->with('success', 'Đã thêm bài viết.');
    }

    public function getSua(BaiViet $baiViet): View
    {
        return view('baiviet.form', [
            'baiViet' => $baiViet,
            'chuDes' => ChuDe::orderBy('ten_chude')->get(),
        ]);
    }

    public function postSua(Request $request, BaiViet $baiViet): RedirectResponse
    {
        $validated = $this->validateBaiViet($request);

        $validated['noi_dung'] = LocHtml::lamSach($validated['noi_dung']);
        $validated['kiem_duyet'] = $request->boolean('kiem_duyet');
        $validated['kich_hoat'] = $request->boolean('kich_hoat');

        if ($validated['tieu_de'] !== $baiViet->tieu_de) {
            $validated['slug'] = $this->taoSlug($validated['tieu_de'], $baiViet->id);
        }

        if ($request->hasFile('hinh_anh')) {
            $this->xoaAnhCu($baiViet->hinh_anh);
            $validated['hinh_anh'] = $request->file('hinh_anh')->store('tintuc', 'public');
        } elseif ($request->boolean('xoa_anh')) {
            $this->xoaAnhCu($baiViet->hinh_anh);
            $validated['hinh_anh'] = null;
        }

        $baiViet->update($validated);

        return redirect()->route('baiviet.danhsach')->with('success', 'Đã cập nhật bài viết.');
    }

    public function postDuyet(BaiViet $baiViet): RedirectResponse
    {
        $baiViet->update(['kiem_duyet' => ! $baiViet->kiem_duyet]);

        return back()->with('success', $baiViet->kiem_duyet ? 'Đã duyệt bài viết.' : 'Đã bỏ duyệt bài viết.');
    }

    public function postKichHoat(BaiViet $baiViet): RedirectResponse
    {
        $baiViet->update(['kich_hoat' => ! $baiViet->kich_hoat]);

        return back()->with('success', $baiViet->kich_hoat ? 'Đã hiện bài viết.' : 'Đã ẩn bài viết.');
    }

    public function getXoa(BaiViet $baiViet): RedirectResponse
    {
        $this->xoaAnhCu($baiViet->hinh_anh);
        $baiViet->delete();

        return redirect()->route('baiviet.danhsach')->with('success', 'Đã xóa bài viết.');
    }

    private function validateBaiViet(Request $request): array
    {
        return $request->validate([
            'topic_id' => ['required', 'integer', 'exists:news_topics,id'],
            'tieu_de' => ['required', 'string', 'max:200'],
            'tom_tat' => ['nullable', 'string', 'max:500'],
            'noi_dung' => ['required', 'string'],
            'hinh_anh' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'kiem_duyet' => ['nullable', 'boolean'],
            'kich_hoat' => ['nullable', 'boolean'],
        ], [
            'topic_id.required' => 'Vui lòng chọn chủ đề.',
            'topic_id.exists' => 'Chủ đề không tồn tại.',
            'tieu_de.required' => 'Vui lòng nhập tiêu đề.',
            'tieu_de.max' => 'Tiêu đề không được vượt quá 200 ký tự.',
            'tom_tat.max' => 'Tóm tắt không được vượt quá 500 ký tự.',
            'noi_dung.required' => 'Vui lòng nhập nội dung.',
            'hinh_anh.image' => 'Ảnh đại diện phải là tệp hình ảnh.',
            'hinh_anh.mimes' => 'Ảnh đại diện chỉ chấp nhận jpg, jpeg, png, webp.',
            'hinh_anh.max' => 'Ảnh đại diện tối đa 2MB.',
        ]);
    }

    private function xoaAnhCu(?string $duongDan): void
    {
        if ($duongDan && str_starts_with($duongDan, 'tintuc/') && ! str_contains($duongDan, '..')) {
            Storage::disk('public')->delete($duongDan);
        }
    }

    private function taoSlug(string $tieuDe, ?int $boQuaId = null): string
    {
        $goc = Str::slug($tieuDe);
        $slug = $goc;
        $i = 1;
        while (BaiViet::where('slug', $slug)->when($boQuaId, fn ($q) => $q->where('id', '!=', $boQuaId))->exists()) {
            $slug = $goc.'-'.(++$i);
        }

        return $slug;
    }
}