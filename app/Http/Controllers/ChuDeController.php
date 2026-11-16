<?php

namespace App\Http\Controllers;

use App\Models\ChuDe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ChuDeController extends Controller
{
    public function getDanhSach(): View
    {
        return view('chude.danhsach', [
            'chuDes' => ChuDe::withCount('baiViets')->orderBy('ten_chude')->get(),
        ]);
    }

    public function getThem(): View
    {
        return view('chude.form', ['chuDe' => new ChuDe()]);
    }

    public function postThem(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ten_chude' => ['required', 'string', 'max:100', 'unique:news_topics,ten_chude'],
            'mo_ta' => ['nullable', 'string', 'max:255'],
        ], $this->messages());

        $validated['slug'] = $this->taoSlug($validated['ten_chude']);
        ChuDe::create($validated);

        return redirect()->route('chude.danhsach')->with('success', 'Đã thêm chủ đề.');
    }

    public function getSua(ChuDe $chuDe): View
    {
        return view('chude.form', ['chuDe' => $chuDe]);
    }

    public function postSua(Request $request, ChuDe $chuDe): RedirectResponse
    {
        $validated = $request->validate([
            'ten_chude' => ['required', 'string', 'max:100', 'unique:news_topics,ten_chude,'.$chuDe->id],
            'mo_ta' => ['nullable', 'string', 'max:255'],
        ], $this->messages());

        if ($validated['ten_chude'] !== $chuDe->ten_chude) {
            $validated['slug'] = $this->taoSlug($validated['ten_chude'], $chuDe->id);
        }
        $chuDe->update($validated);

        return redirect()->route('chude.danhsach')->with('success', 'Đã cập nhật chủ đề.');
    }

    public function getXoa(ChuDe $chuDe): RedirectResponse
    {
        if ($chuDe->baiViets()->exists()) {
            return back()->with('error', 'Không thể xóa chủ đề đang có bài viết.');
        }

        $chuDe->delete();

        return redirect()->route('chude.danhsach')->with('success', 'Đã xóa chủ đề.');
    }

    private function taoSlug(string $ten, ?int $boQuaId = null): string
    {
        $goc = Str::slug($ten);
        $slug = $goc;
        $i = 1;
        while (ChuDe::where('slug', $slug)->when($boQuaId, fn ($q) => $q->where('id', '!=', $boQuaId))->exists()) {
            $slug = $goc.'-'.(++$i);
        }

        return $slug;
    }

    private function messages(): array
    {
        return [
            'ten_chude.required' => 'Vui lòng nhập tên chủ đề.',
            'ten_chude.max' => 'Tên chủ đề không được vượt quá 100 ký tự.',
            'ten_chude.unique' => 'Tên chủ đề đã tồn tại.',
            'mo_ta.max' => 'Mô tả không được vượt quá 255 ký tự.',
        ];
    }
}