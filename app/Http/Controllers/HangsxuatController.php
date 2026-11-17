<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Services\NhatKyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class HangsxuatController extends Controller
{
    private const MIME_LOGO_HOP_LE = ['image/jpeg', 'image/png', 'image/webp'];

    private const DUNG_LUONG_LOGO_TOI_DA = 2048; // KB

    public function getDanhSach(): View
    {
        $laAdmin = auth()->user()?->role === 'admin';

        return view('hangsxuat.danhsach', [
            'hangs' => Brand::withCount('sanPhams')->orderBy('ten')->get(),
            'laAdmin' => $laAdmin,
        ]);
    }

    public function getThem(): View
    {
        return view('hangsxuat.form', ['hang' => new Brand()]);
    }

    public function postThem(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules(null), $this->messages());
        $logo = $this->luuLogo($request);

        $hang = Brand::create([
            'ten' => trim($validated['ten']),
            'slug' => Brand::slugDuyNhat(trim($validated['ten'])),
            'logo' => $logo,
            'kich_hoat' => $request->boolean('kich_hoat', true),
        ]);

        NhatKyService::ghi($request, 'tao_hang_san_xuat', 'brand', $hang->id, 'Tạo hãng '.$hang->ten);

        return redirect()->route('hangsxuat.danhsach')->with('success', 'Đã thêm hãng sản xuất.');
    }

    public function getSua(Brand $hang): View
    {
        return view('hangsxuat.form', ['hang' => $hang]);
    }

    public function postSua(Request $request, Brand $hang): RedirectResponse
    {
        $validated = $request->validate($this->rules($hang), $this->messages());

        // Slug giữ nguyên sau khi tạo (URL/logo đã gắn); chỉ đổi nếu admin chủ động nhập slug mới.
        $slugMoi = isset($validated['slug']) && trim((string) $validated['slug']) !== ''
            ? Brand::slugDuyNhat(trim((string) $validated['slug']), $hang->id)
            : $hang->slug;

        if ($request->hasFile('logo')) {
            $this->xoaLogoCu($hang->logo);
            $logo = $this->luuLogo($request);
        } elseif ($request->boolean('xoa_logo')) {
            $this->xoaLogoCu($hang->logo);
            $logo = null;
        } else {
            $logo = $hang->logo;
        }

        $hang->update([
            'ten' => trim($validated['ten']),
            'slug' => $slugMoi,
            'logo' => $logo,
            'kich_hoat' => $request->boolean('kich_hoat'),
        ]);

        NhatKyService::ghi($request, 'sua_hang_san_xuat', 'brand', $hang->id, 'Sửa hãng '.$hang->ten);

        return redirect()->route('hangsxuat.danhsach')->with('success', 'Đã cập nhật hãng sản xuất.');
    }

    public function getXoa(Request $request, Brand $hang): RedirectResponse
    {
        if ($hang->sanPhams()->exists()) {
            return back()->with('error', 'Không thể xóa hãng đang có sản phẩm. Hãy tắt "Kích hoạt" để ẩn hãng thay thế.');
        }

        $this->xoaLogoCu($hang->logo);
        $ten = $hang->ten;
        $hang->delete();

        NhatKyService::ghi($request, 'xoa_hang_san_xuat', 'brand', null, 'Xóa hãng '.$ten);

        return redirect()->route('hangsxuat.danhsach')->with('success', 'Đã xóa hãng sản xuất.');
    }

    private function rules(?Brand $hang): array
    {
        return [
            'ten' => ['required', 'string', 'max:100', 'unique:brands,ten'.($hang ? ','.$hang->id : '')],
            'slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.self::DUNG_LUONG_LOGO_TOI_DA],
            'kich_hoat' => ['nullable', 'boolean'],
            'xoa_logo' => ['nullable', 'boolean'],
        ];
    }

    private function messages(): array
    {
        return [
            'ten.required' => 'Vui lòng nhập tên hãng.',
            'ten.unique' => 'Tên hãng đã tồn tại.',
            'logo.mimes' => 'Logo chỉ chấp nhận JPG, PNG hoặc WebP.',
            'logo.max' => 'Logo tối đa 2MB.',
        ];
    }

    /**
     * Lưu logo: kiểm tra MIME thật theo nội dung file (không chỉ đuôi),
     * whitelist jpg/png/webp, tên ngẫu nhiên. Trả về path public hoặc null.
     */
    private function luuLogo(Request $request): ?string
    {
        $file = $request->file('logo');
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            return null;
        }

        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::MIME_LOGO_HOP_LE, true)) {
            return null;
        }

        $tenFile = Str::random(24).'.'.match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $file->storeAs('brands', $tenFile, 'public');

        return 'brands/'.$tenFile;
    }

    private function xoaLogoCu(?string $path): void
    {
        if ($path && str_starts_with($path, 'brands/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
