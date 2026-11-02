<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DonHang;
use App\Models\PaymentWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentWebhookController extends Controller
{
    /**
     * POST /api/webhook/thanhtoan
     *
     * Nhận thông báo thanh toán từ cổng thanh toán bên ngoài (VietQR / ngân hàng / ví).
     *
     * Payload mẫu:
     * {
     *   "ma_giao_dich": "FT26110200012345",
     *   "so_tien": 500000,
     *   "nha_cung_cap": "vietqr",
     *   "ma_hoadon": "HD20261102-0001",
     *   "signature": "<HMAC-SHA256 hex>"
     * }
     */
    public function postThanhToan(Request $request): JsonResponse
    {
        // --- 1. Xác thực chữ ký (Signature Verification) ---
        $secret = config('pos.webhook_secret');
        if ($secret) {
            $payload  = $request->getContent();
            $expected = hash_hmac('sha256', $payload, $secret);
            $received = (string) $request->header('X-Webhook-Signature', '');

            if (! hash_equals($expected, $received)) {
                Log::warning('Webhook: chữ ký không hợp lệ.', ['ip' => $request->ip()]);

                return response()->json(['error' => 'Chữ ký không hợp lệ.'], 403);
            }
        }

        // --- 2. Validate dữ liệu đầu vào ---
        $validated = $request->validate([
            'ma_giao_dich' => ['required', 'string', 'max:80'],
            'so_tien'      => ['required', 'integer', 'min:1'],
            'nha_cung_cap' => ['required', 'string', 'max:30'],
            'ma_hoadon'    => ['required', 'string', 'max:40'],
        ]);

        // --- 3. Idempotent check: đã xử lý giao dịch này chưa? ---
        if (PaymentWebhook::where('ma_giao_dich_ngoai', $validated['ma_giao_dich'])->exists()) {
            return response()->json(['message' => 'Giao dịch đã được xử lý.'], 200);
        }

        try {
            $result = DB::transaction(function () use ($validated) {
                $donHang = DonHang::query()
                    ->where('ma_hoadon', $validated['ma_hoadon'])
                    ->lockForUpdate()
                    ->first();

                if (! $donHang) {
                    return ['error' => 'Không tìm thấy hóa đơn.', 'code' => 404];
                }

                // Kiểm tra số tiền khớp
                if ((int) $donHang->tong_thanh_toan !== (int) $validated['so_tien']) {
                    PaymentWebhook::create([
                        'nha_cung_cap'      => $validated['nha_cung_cap'],
                        'ma_giao_dich_ngoai' => $validated['ma_giao_dich'],
                        'invoice_id'        => $donHang->id,
                        'so_tien'           => $validated['so_tien'],
                        'ket_qua'           => 'failed',
                    ]);

                    return ['error' => 'Số tiền không khớp.', 'code' => 422];
                }

                // Ghi nhận giao dịch thành công
                PaymentWebhook::create([
                    'nha_cung_cap'      => $validated['nha_cung_cap'],
                    'ma_giao_dich_ngoai' => $validated['ma_giao_dich'],
                    'invoice_id'        => $donHang->id,
                    'so_tien'           => $validated['so_tien'],
                    'ket_qua'           => 'success',
                ]);

                // Cập nhật mã giao dịch ngân hàng vào hóa đơn
                $donHang->update(['ma_giao_dich' => $validated['ma_giao_dich']]);

                return null; // thành công
            }, 2);

            if ($result !== null) {
                return response()->json(['error' => $result['error']], $result['code']);
            }

            return response()->json(['message' => 'Xác nhận thanh toán thành công.'], 200);
        } catch (Throwable $e) {
            Log::error('Webhook xử lý thất bại.', ['message' => $e->getMessage()]);

            return response()->json(['error' => 'Lỗi hệ thống.'], 500);
        }
    }
}
