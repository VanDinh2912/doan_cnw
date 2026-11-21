<?php

namespace App\Rules;

use App\Support\TraCuuDns;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Lọc email "thực tế" cho form khách công khai.
 * Các lớp theo thứ tự rẻ -> đắt: chuẩn hóa/định dạng -> tên miền
 * ví dụ -> disposable -> gõ nhầm (gợi ý) -> DNS (fail-open).
 * KHÔNG tự sửa input; chỉ từ chối kèm gợi ý khi gõ nhầm.
 */
class EmailThucTe implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // 'nullable' đã lọc null/rỗng chuỗi trước rule này.
        if ($value === null || trim((string) $value) === '') {
            return;
        }

        $email = trim((string) $value);

        // ---- a. Chuẩn hóa kiểm tra: độ dài, định dạng, ký tự lạ ----
        if (mb_strlen($email) > 150) {
            $fail('Email không được dài quá 150 ký tự.');

            return;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $fail('Email không hợp lệ.');

            return;
        }

        [$local, $domainGoc] = explode('@', $email, 2);
        $local = trim($local);
        $domain = strtolower(trim($domainGoc));

        // Phần local: không rỗng, không toàn ký tự lặp ("aaaa@..."),
        // không dấu chấm đầu/cuối hoặc hai chấm liền nhau.
        if ($local === ''
            || mb_strlen($local) > 64
            || preg_match('/^(.)\1{3,}$/u', $local) === 1
            || str_starts_with($local, '.')
            || str_ends_with($local, '.')
            || str_contains($local, '..')) {
            $fail('Email không hợp lệ.');

            return;
        }

        // ---- Ngoại lệ môi trường test: bỏ qua toàn bộ lớp domain ----
        if (app()->environment('testing')
            && in_array($domain, (array) config('email_rac.cho_phep_khi_test', []), true)) {
            return;
        }

        // ---- b. Tên miền phải có dấu chấm + TLD hợp lệ ----
        if (! str_contains($domain, '.') || str_ends_with($domain, '.')) {
            $fail('Tên miền email không hợp lệ.');

            return;
        }

        $tld = substr($domain, (int) strrpos($domain, '.') + 1);
        if (preg_match('/^[a-z]{2,63}$/', $tld) !== 1
            || in_array($tld, (array) config('email_rac.tld_cam', []), true)) {
            $fail('Tên miền email không hợp lệ.');

            return;
        }

        // Khớp tên miền chính xác hoặc tên miền con của nó
        // (foo.mailinator.com vẫn bị chặn).
        $khopDomain = function (string $domain, array $danhSach): bool {
            foreach ($danhSach as $biCam) {
                if ($domain === $biCam || str_ends_with($domain, '.'.$biCam)) {
                    return true;
                }
            }

            return false;
        };

        // Tên miền ví dụ/giữ chỗ.
        if ($khopDomain($domain, (array) config('email_rac.ten_mien_vi_du', []))) {
            $fail('Email thuộc tên miền ví dụ, không sử dụng được.');

            return;
        }

        // ---- c. Disposable / email dùng một lần ----
        if ($khopDomain($domain, (array) config('email_rac.disposable', []))) {
            $fail('Email dùng một lần (temporary) không được chấp nhận. Vui lòng dùng email chính thức.');

            return;
        }

        // ---- d. Gợi ý gõ nhầm tên miền phổ biến (không tự sửa) ----
        $goiY = $this->goiYChinhTa($domain);
        if ($goiY !== null) {
            $fail("Có phải ý bạn là {$local}@{$goiY}?");

            return;
        }

        // ---- e. DNS: MX, fallback A; fail-open khi lỗi mạng ----
        if (! config('email_rac.kiem_tra_dns', true)) {
            return;
        }

        $dns = app(TraCuuDns::class)->nhanThuDuoc($domain);
        if ($dns === false) {
            $fail('Tên miền email không tồn tại hoặc không nhận thư. Vui lòng kiểm tra lại.');
        }
        // null = lỗi mạng -> cho qua.
    }

    private function goiYChinhTa(string $domain): ?string
    {
        $map = (array) config('email_rac.goi_y_chinh_ta', []);
        $lower = strtolower($domain);

        // Map chính xác (key map đã lowercase).
        foreach ($map as $sai => $dung) {
            if ($lower === strtolower($sai)) {
                return $dung;
            }
        }

        // Domain đã là nhà cung cấp phổ biến -> không bao giờ gợi ý.
        if (in_array($lower, (array) config('email_rac.ten_mien_pho_bien', []), true)) {
            return null;
        }

        // Gần giống nhà cung cấp phổ biến (levenshtein <= 2) — bắt các
        // biến thể không nằm trong map.
        $totNhat = null;
        $khoangCach = PHP_INT_MAX;
        foreach ((array) config('email_rac.ten_mien_pho_bien', []) as $phoBien) {
            if ($lower === $phoBien) {
                continue; // domain đã đúng — không gợi ý
            }
            $d = levenshtein($lower, $phoBien);
            if ($d < $khoangCach) {
                $khoangCach = $d;
                $totNhat = $phoBien;
            }
        }

        return $khoangCach > 0 && $khoangCach <= 2 ? $totNhat : null;
    }
}
