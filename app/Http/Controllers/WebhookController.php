<?php

namespace App\Http\Controllers;

use App\Models\DonHang;
use App\Models\PaymentWebhook;
use App\Services\Payment\SepayWebhook;
use App\Services\ThanhToanChuyenKhoanService;
use App\Services\ThongBaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class WebhookController extends Controller
{
    public function __construct(
        protected SepayWebhook $sepayWebhook,
        protected ThanhToanChuyenKhoanService $thanhToanService,
        protected ThongBaoService $thongBaoService
    ) {}

    /**
     * Tiếp nhận Webhook thanh toán từ SePay (hoặc cổng thanh toán bên ngoài).
     * POST /api/webhook/thanhtoan
     */
    public function postThanhToan(Request $request): JsonResponse
    {
        // 1. Xác thực API Key từ SePay (401 nếu sai, không làm gì thêm)
        if (! $this->sepayWebhook->xacThuc($request)) {
            Log::warning('SePay Webhook: Xác thực thất bại', ['ip' => $request->ip()]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // 2. Validate các trường bắt buộc trong payload
        $payload = $request->all();
        if (! $this->sepayWebhook->validate($payload)) {
            Log::warning('SePay Webhook: Dữ liệu payload không hợp lệ');

            return response()->json(['error' => 'Invalid payload'], 400);
        }

        // 3. Chuẩn hóa payload
        $txn = $this->sepayWebhook->chuanHoa($payload);

        // Chỉ xử lý giao dịch tiền vào (transferType = in)
        if ($txn['loai'] !== 'in') {
            return response()->json(['success' => true, 'message' => 'Bỏ qua giao dịch không phải tiền vào.']);
        }

        // 4. Chống trùng lặp (Idempotent): nếu mã giao dịch đã xử lý thì trả về thành công ngay
        if (DonHang::where('ma_giaodich', $txn['ma_giaodich'])->orWhere('ma_giao_dich', $txn['ma_giaodich'])->exists()) {
            return response()->json(['success' => true, 'message' => 'Giao dịch đã được xử lý trước đó.']);
        }

        // 5. Tìm mã hóa đơn từ nội dung chuyển khoản
        $maHoaDon = $this->sepayWebhook->timMaHoaDon($txn['noi_dung']);
        if (! $maHoaDon) {
            Log::warning('SePay Webhook: Không tìm thấy mã hóa đơn trong nội dung', [
                'ma_giaodich' => $txn['ma_giaodich'],
                'noi_dung' => $txn['noi_dung'],
                'thoi_gian' => $txn['thoi_gian'],
            ]);

            $this->thongBaoService->canhBaoKhongKhopHoaDon($txn['so_tien'], $txn['noi_dung'], $txn['ma_giaodich']);

            return response()->json(['success' => true, 'message' => 'Không tìm thấy mã hóa đơn hợp lệ.']);
        }

        // 6. Đối chiếu hóa đơn trong CSDL
        $donHang = DonHang::where('ma_hoadon', $maHoaDon)->first();
        if (! $donHang) {
            Log::warning('SePay Webhook: Hóa đơn không tồn tại', [
                'ma_giaodich' => $txn['ma_giaodich'],
                'ma_hoadon' => $maHoaDon,
                'thoi_gian' => $txn['thoi_gian'],
            ]);

            $this->thongBaoService->canhBaoKhongKhopHoaDon($txn['so_tien'], $txn['noi_dung'], $txn['ma_giaodich']);

            return response()->json(['success' => true, 'message' => 'Hóa đơn không tồn tại.']);
        }

        // Nếu hóa đơn đã hủy
        if ($donHang->trang_thai === 'da_huy') {
            Log::warning('SePay Webhook: Nhận tiền vào hóa đơn đã bị hủy', [
                'ma_giaodich' => $txn['ma_giaodich'],
                'ma_hoadon' => $maHoaDon,
                'thoi_gian' => $txn['thoi_gian'],
            ]);

            $this->thongBaoService->canhBaoKhongKhopHoaDon($txn['so_tien'], "Hóa đơn {$maHoaDon} đã bị hủy trước đó: ".$txn['noi_dung'], $txn['ma_giaodich']);

            return response()->json(['success' => true, 'message' => 'Hóa đơn đã bị hủy trước đó.']);
        }

        // Nếu hóa đơn đã được thanh toán
        if ($donHang->trang_thai === 'da_thanh_toan') {
            return response()->json(['success' => true, 'message' => 'Hóa đơn đã được thanh toán.']);
        }

        // 7. So sánh số tiền
        $tongThanhToan = (int) $donHang->tong_thanh_toan;
        $soTienChuyen = $txn['so_tien'];

        // Trường hợp thiếu tiền: không đổi trạng thái, log và cảnh báo Telegram
        if ($soTienChuyen < $tongThanhToan) {
            Log::warning('SePay Webhook: Khách chuyển thiếu tiền', [
                'ma_giaodich' => $txn['ma_giaodich'],
                'ma_hoadon' => $maHoaDon,
                'so_tien_chuyen' => $soTienChuyen,
                'tong_thanh_toan' => $tongThanhToan,
                'thoi_gian' => $txn['thoi_gian'],
            ]);

            $this->thongBaoService->canhBaoThieuTien($soTienChuyen, $tongThanhToan, $maHoaDon, $txn['ma_giaodich']);

            return response()->json(['success' => true, 'message' => 'Số tiền không đủ, giữ nguyên chờ xử lý.']);
        }

        // Trường hợp đủ tiền hoặc chuyển dư: hoàn tất thanh toán
        $ghiChuDu = null;
        if ($soTienChuyen > $tongThanhToan) {
            $du = $soTienChuyen - $tongThanhToan;
            $ghiChuDu = 'khách chuyển dư '.number_format($du, 0, ',', '.').' đ';
        }

        try {
            $res = $this->thanhToanService->hoanTatThanhToan($donHang, $txn['ma_giaodich'], null, $ghiChuDu);

            if ($res['success']) {
                // Ghi nhận webhook log
                PaymentWebhook::create([
                    'nha_cung_cap' => 'sepay',
                    'ma_giao_dich_ngoai' => $txn['ma_giaodich'],
                    'invoice_id' => $donHang->id,
                    'so_tien' => $soTienChuyen,
                    'ket_qua' => 'success',
                ]);

                // Thông báo Telegram sau khi giao dịch đã hoàn tất
                DB::afterCommit(function () use ($soTienChuyen, $maHoaDon, $ghiChuDu) {
                    $this->thongBaoService->thongBaoThanhToanThanhCong($soTienChuyen, $maHoaDon, $ghiChuDu);
                });
            }

            return response()->json(['success' => true, 'message' => 'Xác nhận thanh toán thành công.']);
        } catch (Throwable $e) {
            Log::error('SePay Webhook: Lỗi xử lý giao dịch', ['message' => $e->getMessage()]);

            return response()->json(['success' => true, 'message' => 'Lỗi hệ thống nhưng đã ghi nhận.']);
        }
    }
}
