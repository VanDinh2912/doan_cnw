<?php

namespace App\Services;

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
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::warning('Telegram notification exception: '.$e->getMessage());

            return false;
        }
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
     * Cảnh báo giao dịch không tìm thấy hóa đơn khớp.
     */
    public function canhBaoKhongKhopHoaDon(int $soTien, string $noiDung, string $maGiaoDich): void
    {
        $gio = now()->format('H:i:s d/m/Y');
        $soTienFmt = number_format($soTien, 0, ',', '.');
        $msg = "⚠️ <b>CẢNH BÁO: KHÔNG TÌM THẤY HÓA ĐƠN</b>\nSố tiền: {$soTienFmt} đ\nMã GD: {$maGiaoDich}\nNội dung: {$noiDung}\nThời gian: {$gio}";

        $this->guiTelegram($msg);
    }
}
