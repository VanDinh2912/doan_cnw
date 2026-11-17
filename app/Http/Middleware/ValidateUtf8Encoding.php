<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chan request chua byte khong hop le UTF-8 o bat ky dau trong input
 * (query + body, key va value, mang long nhau). Nem ValidationException:
 * form web quay lai kem loi, request JSON/AJAX nhan 422.
 *
 * Chi ap dung cho nhom middleware "web" ( dang ky trong bootstrap/app.php);
 * route webhook /api/webhook/thanhtoan dung withoutMiddleware('web') nen
 * khong bi anh huong. File upload (UploadedFile) khong phai string/array
 * nen tu dong duoc bo qua.
 */
class ValidateUtf8Encoding
{
    private const THONG_BAO = 'Dữ liệu gửi lên chứa ký tự không hợp lệ.';

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->coByteLoi($request->all())) {
            throw ValidationException::withMessages(['utf8' => self::THONG_BAO]);
        }

        return $next($request);
    }

    /** Duyet de quy toan bo input: value string + key string + mang long nhau. */
    private function coByteLoi(mixed $data): bool
    {
        if (is_string($data)) {
            return ! mb_check_encoding($data, 'UTF-8');
        }
        if (! is_array($data)) {
            return false; // UploadedFile, null, so, bool... bo qua
        }
        foreach ($data as $key => $value) {
            if (is_string($key) && ! mb_check_encoding($key, 'UTF-8')) {
                return true;
            }
            if ($this->coByteLoi($value)) {
                return true;
            }
        }

        return false;
    }
}
