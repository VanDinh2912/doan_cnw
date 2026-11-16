<?php

namespace App\Http\Controllers;

use App\Models\DonHang;
use App\Models\PaymentWebhook;
use App\Services\Payment\SepayWebhook;
use App\Services\ThanhToanChuyenKhoanService;
use App\Services\ThongBaoService;
use App\Support\KiemTraSchema;
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

        // 4. Chống trùng lặp (Idempotent) chỉ dựa trên id giao dịch của webhook.
        // Mã tham chiếu có thể rỗng hoặc dùng lại cho giao dịch khác, nên tuyệt đối
        // không dùng nó để bỏ qua một webhook.
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
            $this->ghiCanDoiSoat($txn, null, null, 'khong_tim_thay_ma_hoadon');

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
            $this->ghiCanDoiSoat($txn, null, $maHoaDon, 'hoadon_khong_ton_tai');

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
            $this->ghiCanDoiSoat($txn, $donHang->id, $maHoaDon, 'don_da_huy');

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
            $this->ghiCanDoiSoat($txn, $donHang->id, $maHoaDon, 'thieu_tien');

            return response()->json(['success' => true, 'message' => 'Số tiền không đủ, giữ nguyên chờ xử lý.']);
        }

        // Trường hợp đủ tiền hoặc chuyển dư: hoàn tất thanh toán
        $ghiChuDu = null;
        if ($soTienChuyen > $tongThanhToan) {
            $du = $soTienChuyen - $tongThanhToan;
            $ghiChuDu = 'khách chuyển dư '.number_format($du, 0, ',', '.').' đ';
        }

        // Đơn đã quá hạn chờ nhưng chưa bị hủy: vẫn hoàn tất (hàng đang giữ, tiền đã về),
        // song phải để lại dấu vết trên đơn và báo thu ngân.
        $soPhutHan = (int) config('pos.cho_thanh_toan_phut', 15);
        $treHan = $donHang->created_at->lt(now()->subMinutes($soPhutHan));
        if ($treHan) {
            $ghiChuTre = 'Thanh toán trễ hạn (hạn chờ '.$soPhutHan.' phút), tiền về '.now()->format('H:i:s d/m/Y');
            $ghiChuDu = $ghiChuDu ? $ghiChuDu.' | '.$ghiChuTre : $ghiChuTre;
        }

        try {
            $res = $this->thanhToanService->hoanTatThanhToan($donHang, $txn['ma_giaodich'], null, $ghiChuDu);

            if ($res['success']) {
                // Ghi nhận webhook log; gộp với bản ghi báo cáo API nếu cùng mã tham chiếu.
                $this->ghiNhanWebhook($txn, $donHang->id, $maHoaDon, 'success', null);

                // Thông báo Telegram sau khi giao dịch đã hoàn tất
                DB::afterCommit(function () use ($soTienChuyen, $maHoaDon, $ghiChuDu, $treHan, $txn, $soPhutHan, $donHang) {
                    $this->thongBaoService->thongBaoThanhToanThanhCong($soTienChuyen, $maHoaDon, $ghiChuDu);
                    if ($donHang->hinh_thuc_nhan === 'giao_tan_noi') {
                        $this->thongBaoService->thongBaoDonGiaoDaThanhToan($donHang);
                    }
                    if ($treHan) {
                        $this->thongBaoService->canhBaoThanhToanTreHan($soTienChuyen, $maHoaDon, $txn['ma_giaodich'], $soPhutHan);
                    }
                });

                return response()->json(['success' => true, 'message' => 'Xác nhận thanh toán thành công.']);
            }

            // Tiền đã về nhưng đơn không hoàn tất được (đổi trạng thái giữa lúc kiểm tra và khóa)
            $this->ghiCanDoiSoat($txn, $donHang->id, $maHoaDon, 'don_khong_hoan_tat_duoc');

            return response()->json(['success' => true, 'message' => $res['message']]);
        } catch (Throwable $e) {
            Log::error('SePay Webhook: Lỗi xử lý giao dịch', ['message' => $e->getMessage()]);
            $this->ghiCanDoiSoat($txn, $donHang->id, $maHoaDon, 'loi_he_thong');

            return response()->json(['success' => true, 'message' => 'Lỗi hệ thống nhưng đã ghi nhận.']);
        }
    }

    /**
     * Ghi mọi giao dịch tiền về mà không hoàn tất được đơn vào hàng chờ đối soát.
     * Idempotent theo ma_giao_dich_ngoai (unique) và gộp theo ma_tham_chieu để
     * không tạo bản ghi trùng khi webhook đến sau báo cáo đối soát API.
     * Payload chỉ chứa nội dung chuyển khoản, không bao gồm header xác thực.
     */
    private function ghiCanDoiSoat(array $txn, ?int $invoiceId, ?string $maHoaDon, string $lyDo): void
    {
        $this->ghiNhanWebhook($txn, $invoiceId, $maHoaDon, 'can_doi_soat', $lyDo);
    }

    private function ghiNhanWebhook(array $txn, ?int $invoiceId, ?string $maHoaDon, string $ketQua, ?string $lyDo): PaymentWebhook
    {
        $maNgoai = (string) $txn['ma_giaodich'];
        // Guard: DB chưa áp migration ma_tham_chieu vẫn ghi webhook bình thường.
        $coCotMaThamChieu = KiemTraSchema::coCot('payment_webhooks', 'ma_tham_chieu');
        $maThamChieu = $coCotMaThamChieu
            ? $this->chuanHoaMaThamChieu((string) ($txn['reference_code'] ?? ''))
            : null;

        $duLieu = [
            'nha_cung_cap' => 'sepay',
            'ma_giao_dich_ngoai' => $maNgoai,
        ];
        if ($coCotMaThamChieu) {
            $duLieu['ma_tham_chieu'] = $maThamChieu;
        }
        $duLieu += [
            'invoice_id' => $invoiceId,
            'so_tien' => (int) $txn['so_tien'],
            'ket_qua' => $ketQua,
            'ly_do' => $lyDo,
            'ma_hoadon' => $maHoaDon,
            'noi_dung' => mb_substr((string) $txn['noi_dung'], 0, 255),
            'payload' => $txn,
            'trang_thai_xu_ly' => $ketQua === 'success' ? 'da_xu_ly' : 'chua_xu_ly',
        ];

        if ($ketQua === 'success') {
            $duLieu['ghi_chu_xu_ly'] = 'Webhook xác nhận thanh toán';
            $duLieu['thoi_gian_xu_ly'] = now();
        }

        $banGhi = PaymentWebhook::where('ma_giao_dich_ngoai', $maNgoai)->first();

        // Mã tham chiếu chỉ dùng để gộp hiển thị vào bản ghi báo cáo của API,
        // không bao giờ gộp/ghi đè bản ghi webhook của giao dịch khác.
        if ($banGhi === null && $maThamChieu !== null && $coCotMaThamChieu) {
            $banGhi = PaymentWebhook::query()
                ->where('nha_cung_cap', 'sepay_api')
                ->where('ket_qua', 'can_doi_soat')
                ->where('ma_tham_chieu', $maThamChieu)
                ->where('so_tien', (int) $txn['so_tien'])
                ->first();
        }

        if ($banGhi !== null) {
            $banGhi->update($duLieu);

            return $banGhi;
        }

        return PaymentWebhook::create($duLieu);
    }

    private function chuanHoaMaThamChieu(string $ma): ?string
    {
        $ma = trim($ma);

        return $ma === '' ? null : mb_substr($ma, 0, 100);
    }
}
