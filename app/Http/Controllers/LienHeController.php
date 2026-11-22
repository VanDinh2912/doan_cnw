<?php

namespace App\Http\Controllers;

use App\Models\LienHe;
use App\Rules\EmailThucTe;
use App\Services\ThongBaoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class LienHeController extends Controller
{
    public function getForm(Request $request): View
    {
        $loai = $request->query('loai');
        $loai = is_string($loai) && in_array($loai, LienHe::LOAI, true) ? $loai : 'lien_he';

        $viTri = $request->query('vitri');
        $viTriOptions = (array) config('trang_tinh.tuyen_dung.vi_tri', []);
        $viTri = is_string($viTri) && array_key_exists($viTri, $viTriOptions) ? $viTri : null;

        // Không điền sẵn họ tên/SĐT/email cho khách đăng nhập — họ phải nhập lại.
        // user_id vẫn được gắn khi lưu (postGui), chỉ bỏ prefill hiển thị.
        return view('storefront.lien-he', [
            'loaiMacDinh' => $loai,
            'viTriMacDinh' => $viTri,
            'loaiOptions' => LienHe::LOAI,
            'viTriOptions' => $viTriOptions,
        ]);
    }

    public function getCamOn(): View
    {
        return view('storefront.lien-he-cam-on');
    }

    public function getTuyenDung(): View
    {
        return view('storefront.tuyen-dung', [
            'viTriOptions' => (array) config('trang_tinh.tuyen_dung.vi_tri', []),
        ]);
    }

    public function getTrangTinh(string $slug): View
    {
        $pages = (array) config('trang_tinh.pages', []);
        abort_unless(isset($pages[$slug]), 404);

        return view('storefront.trang-tinh', [
            'slug' => $slug,
            'page' => $pages[$slug],
        ]);
    }

    public function postGui(Request $request, ThongBaoService $thongBao): RedirectResponse
    {
        // Honeypot giống form đặt hàng: tên trung tính, người thật không thấy.
        if (trim((string) $request->input('truong_phu', '')) !== '') {
            return back()->withInput()->with('error', 'Không gửi được, vui lòng thử lại.');
        }

        $validated = $request->validate([
            'ho_ten' => ['required', 'string', 'max:100'],
            'so_dien_thoai' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'max:150', new EmailThucTe()],
            'loai' => ['required', 'string', 'in:'.implode(',', LienHe::LOAI)],
            'vi_tri' => ['nullable', 'string', 'max:100'],
            'chu_de' => ['required', 'string', 'max:150'],
            'noi_dung' => ['required', 'string', 'max:2000'],
        ], [
            'ho_ten.required' => 'Vui lòng nhập họ tên.',
            'loai.required' => 'Vui lòng chọn loại liên hệ.',
            'loai.in' => 'Loại liên hệ không hợp lệ.',
            'chu_de.required' => 'Vui lòng nhập chủ đề.',
            'noi_dung.required' => 'Vui lòng nhập nội dung.',
            'noi_dung.max' => 'Nội dung tối đa 2000 ký tự.',
            'chu_de.max' => 'Chủ đề tối đa 150 ký tự.',
            'email.email' => 'Email không hợp lệ.',
        ]);

        $soDienThoai = preg_replace('/[\s.\-]/', '', trim((string) ($validated['so_dien_thoai'] ?? '')));
        $email = strtolower(trim((string) ($validated['email'] ?? '')));

        if ($soDienThoai === '' && $email === '') {
            return back()->withInput()
                ->withErrors(['lien_he' => 'Vui lòng nhập số điện thoại hoặc email để chúng tôi phản hồi.']);
        }
        if ($soDienThoai !== '' && ! preg_match('/^0\d{9,10}$/', $soDienThoai)) {
            return back()->withInput()
                ->withErrors(['so_dien_thoai' => 'Số điện thoại phải bắt đầu bằng 0 và gồm 10-11 chữ số.']);
        }

        // Whitelist vị trí tuyển dụng; giá trị lạ bị bỏ qua.
        $viTriOptions = (array) config('trang_tinh.tuyen_dung.vi_tri', []);
        $viTri = $validated['vi_tri'] ?? null;
        $viTri = is_string($viTri) && array_key_exists($viTri, $viTriOptions) ? $viTri : null;

        $lienHe = LienHe::create([
            'ho_ten' => trim($validated['ho_ten']),
            'so_dien_thoai' => $soDienThoai !== '' ? $soDienThoai : null,
            'email' => $email !== '' ? $email : null,
            'loai' => $validated['loai'],
            'vi_tri' => $viTri,
            'chu_de' => trim($validated['chu_de']),
            'noi_dung' => trim($validated['noi_dung']),
            'trang_thai' => 'moi',
            'user_id' => $request->user()?->id,
        ]);

        // Telegram chỉ báo loại + chủ đề + mã — không PII. Lỗi không chặn lưu.
        try {
            $thongBao->thongBaoLienHeMoi($lienHe);
        } catch (\Throwable $exception) {
            Log::warning('Gửi thông báo liên hệ mới thất bại', ['lien_he_id' => $lienHe->id, 'error' => $exception->getMessage()]);
        }

        return redirect()->route('lien-he.cam-on');
    }
}
