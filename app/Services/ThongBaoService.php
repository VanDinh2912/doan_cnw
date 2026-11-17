<?php

namespace App\Services;

use App\Models\DonHang;
use App\Models\LienHe;
use App\Support\CheThongTin;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ThongBaoService
{
    /**
     * Gửi tin nhắn thông báo qua Telegram Bot API.
     * Tự động tắt tính năng nếu chưa cấu hình token hoặc chat_id.
     * Bọc try/catch với timeout 5s, không bao giờ làm gián đoạn luồng thanh toán.
     */
    public function guiTelegram(string $noiDung): bool
    {
        $token = (string) config('pos.telegram_bot_token');
        $chatId = (string) config('pos.telegram_chat_id');

        if ($token === '' || $chatId === '') {
            return false;
        }

        try {
            $response = Http::timeout(5)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $noiDung,
                'parse_mode' => 'HTML',
            ]);

            if (! $response->successful()) {
                Log::warning('Telegram notification failed', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 300),
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            // Guzzle nhét nguyên URL (kèm bot token) vào message của exception,
            // nên phải che trước khi ghi log.
            Log::warning('Telegram notification exception', [
                'loai' => $e::class,
                'message' => $this->cheToken($e->getMessage()),
            ]);

            return false;
        }
    }

    private function cheToken(string $vanBan): string
    {
        $token = (string) config('pos.telegram_bot_token');

        return $token === '' ? $vanBan : str_replace($token, '***', $vanBan);
    }

    /**
     * Thông báo thanh toán thành công.
     */
    public function thongBaoThanhToanThanhCong(int $soTien, string $maHoaDon, ?string $ghiChu = null): void
    {
        $gio = now()->format('H:i:s d/m/Y');
        $soTienFmt = number_format($soTien, 0, ',', '.');
        $msg = "✅ <b>Đã nhận {$soTienFmt} đ</b>\nHóa đơn: <b>{$maHoaDon}</b>\nThời gian: {$gio}";
        if ($ghiChu) {
            $msg .= "\nGhi chú: {$ghiChu}";
        }

        $this->guiTelegram($msg);
    }

    // Escape ghi chú khách + che SĐT trước khi ghép tin Telegram HTML (tránh XSS/lộ PII).
    public function thongBaoDonOnlineMoi(DonHang $don): void
    {
        $soTienFmt = number_format((float) $don->tong_thanh_toan, 0, ',', '.');
        $sdtChe = CheThongTin::soDienThoai($don->so_dienthoai_dat);
        $hanGiu = $don->han_giu?->format('H:i d/m/Y') ?? '—';
        $msg = "<b>ĐƠN ONLINE MỚI</b>\n"
            ."Mã đơn: <b>{$don->ma_hoadon}</b>\n"
            .'Khách: '.e($don->ten_khach_dat)."\n"
            ."SĐT: {$sdtChe}\n"
            ."Tổng: {$soTienFmt} đ\n"
            ."Hạn giữ: {$hanGiu}";
        if ($don->hinh_thuc_nhan === 'giao_tan_noi') {
            // Không đưa địa chỉ đầy đủ vào Telegram; SĐT người nhận che một phần.
            $msg .= "\nHình thức: Giao tận nơi"
                ."\nNgười nhận: ".e($don->ten_nguoi_nhan)
                ."\nSĐT nhận: ".CheThongTin::soDienThoai($don->sdt_nguoi_nhan);
        }
        if ($don->ghi_chu_khach) {
            $msg .= "\nGhi chú: ".e($don->ghi_chu_khach);
        }

        $this->guiTelegram($msg);
    }

    /**
     * Đơn online giao tận nơi đã thanh toán (webhook hoặc thu ngân xác nhận).
     * Chỉ gồm mã đơn, tổng, hình thức, người nhận + SĐT che; KHÔNG địa chỉ đầy đủ.
     */
    public function thongBaoDonGiaoDaThanhToan(DonHang $don): void
    {
        $soTienFmt = number_format((float) $don->tong_thanh_toan, 0, ',', '.');
        $msg = "<b>ĐƠN GIAO TẬN NƠI ĐÃ THANH TOÁN</b>\n"
            ."Mã đơn: <b>{$don->ma_hoadon}</b>\n"
            ."Tổng: {$soTienFmt} đ\n"
            ."Hình thức: Giao tận nơi\n"
            ."Người nhận: ".e($don->ten_nguoi_nhan)."\n"
            ."SĐT nhận: ".CheThongTin::soDienThoai($don->sdt_nguoi_nhan);

        $this->guiTelegram($msg);
    }

    /**
     * P3 HM4 — báo admin: đơn đã thanh toán bị hủy, CẦN HOÀN TIỀN THỦ CÔNG.
     * Chỉ mã đơn, tổng, lý do rút gọn (escape); KHÔNG SĐT/tên/địa chỉ.
     */
    public function thongBaoHuyDonCanHoanTien(DonHang $don, string $lyDo): void
    {
        $soTienFmt = number_format((float) $don->tong_thanh_toan, 0, ',', '.');
        $lyDoRutGon = \Illuminate\Support\Str::limit(trim($lyDo), 80, '…');
        $msg = "<b>ĐƠN ĐÃ THANH TOÁN BỊ HỦY — CẦN HOÀN TIỀN THỦ CÔNG</b>\n"
            ."Mã đơn: <b>{$don->ma_hoadon}</b>\n"
            ."Tổng: {$soTienFmt} đ\n"
            ."Lý do: ".e($lyDoRutGon)."\n"
            ."Xử lý tại: Đơn online → Đơn cần hoàn tiền";

        $this->guiTelegram($msg);
    }

    /**
     * Cảnh báo khách chuyển thiếu tiền.
     */
    public function canhBaoThieuTien(int $soTienChuyen, int $tongThanhToan, string $maHoaDon, string $maGiaoDich): void
    {
        $gio = now()->format('H:i:s d/m/Y');
        $chuyenFmt = number_format($soTienChuyen, 0, ',', '.');
        $canFmt = number_format($tongThanhToan, 0, ',', '.');
        $msg = "⚠️ <b>CẢNH BÁO: CHUYỂN THIẾU TIỀN</b>\nHóa đơn: <b>{$maHoaDon}</b>\nCần thanh toán: {$canFmt} đ\nThực nhận: {$chuyenFmt} đ\nMã GD: {$maGiaoDich}\nThời gian: {$gio}";

        $this->guiTelegram($msg);
    }

    /**
     * Cảnh báo tiền về cho đơn đã quá hạn chờ thanh toán nhưng chưa bị hủy.
     * Đơn vẫn được hoàn tất (hàng đang giữ, tiền đã về); thu ngân cần biết để đối soát.
     */
    public function canhBaoThanhToanTreHan(int $soTien, string $maHoaDon, string $maGiaoDich, int $soPhutHan): void
    {
        $gio = now()->format('H:i:s d/m/Y');
        $soTienFmt = number_format($soTien, 0, ',', '.');
        $msg = "⚠️ <b>THANH TOÁN TRỄ HẠN</b>\nHóa đơn: <b>{$maHoaDon}</b>\nSố tiền: {$soTienFmt} đ\nMã GD: {$maGiaoDich}\nHạn chờ: {$soPhutHan} phút\nĐơn quá hạn nhưng chưa bị hủy, tiền đã về nên vẫn hoàn tất.\nThời gian: {$gio}";

        $this->guiTelegram($msg);
    }

    /**
     * Cảnh báo giao dịch không tìm thấy hóa đơn khớp.
     */
    public function canhBaoKhongKhopHoaDon(int $soTien, string $noiDung, string $maGiaoDich): void
    {
        $gio = now()->format('H:i:s d/m/Y');
        $soTienFmt = number_format($soTien, 0, ',', '.');
        $msg = "⚠️ <b>CẢNH BÁO: KHÔNG TÌM THẤY HÓA ĐƠN</b>\nSố tiền: {$soTienFmt} đ\nMã GD: {$maGiaoDich}\nNội dung: {$noiDung}\nThời gian: {$gio}";

        $this->guiTelegram($msg);
    }

    /**
     * Gộp phần giao dịch vượt giới hạn thông báo của một lần đối soát.
     */
    public function canhBaoGopGiaoDichThieuWebhook(int $soGiaoDich, int $tongTien): void
    {
        $gio = now()->format('H:i:s d/m/Y');
        $tongTienFmt = number_format($tongTien, 0, ',', '.');
        $msg = "⚠️ <b>CẢNH BÁO: GIAO DỊCH THIẾU WEBHOOK</b>\nCòn {$soGiaoDich} giao dịch thiếu webhook chưa gửi chi tiết (tổng {$tongTienFmt} đ).\nXem mục Đối soát để xử lý.\nThời gian: {$gio}";

        $this->guiTelegram($msg);
    }

    /**
     * Cảnh báo giao dịch có trên SePay API nhưng chưa nhận webhook.
     * Không gửi nội dung chuyển khoản hay số tài khoản.
     */
    public function canhBaoGiaoDichThieuWebhook(int $soTien, ?string $maHoaDon, string $maThamChieu, string $lyDo): void
    {
        $gio = now()->format('H:i:s d/m/Y');
        $soTienFmt = number_format($soTien, 0, ',', '.');
        $hoaDon = $maHoaDon ?? 'không rõ';
        $msg = "⚠️ <b>CẢNH BÁO: GIAO DỊCH THIẾU WEBHOOK</b>\nSố tiền: {$soTienFmt} đ\nHóa đơn: {$hoaDon}\nMã tham chiếu: {$maThamChieu}\nLý do: {$lyDo}\nThời gian: {$gio}";

        $this->guiTelegram($msg);
    }

    /**
     * Thông báo liên hệ/góp ý/khiếu nại/ứng tuyển mới.
     * CHỈ gồm loại + chủ đề + mã liên hệ — KHÔNG họ tên/SĐT/email/nội dung
     * (tránh lộ PII trong group Telegram). Chủ đề do khách nhập → escape HTML.
     */
    public function thongBaoLienHeMoi(LienHe $lienHe): void
    {
        $msg = "<b>LIÊN HỆ MỚI #{$lienHe->id}</b>\n"
            .'Loại: '.$lienHe->nhanLoai()."\n"
            .'Chủ đề: '.e($lienHe->chu_de);

        $this->guiTelegram($msg);
    }
}
