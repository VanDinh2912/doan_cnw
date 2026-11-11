<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Lỗi khi gọi SePay API. Mọi thông điệp ở đây được tạo cố định hoặc đã che bí mật;
 * không bao giờ chứa token hay header Authorization.
 */
class SepayApiException extends RuntimeException
{
    public static function thieuToken(): self
    {
        return new self('Chưa cấu hình SEPAY_API_TOKEN — không thể gọi SePay API.');
    }

    public static function phienBanKhongHoTro(string $phienBan): self
    {
        return new self("SEPAY_API_VERSION={$phienBan} không được hỗ trợ; đối soát chỉ dùng v2 (v1 đã ngừng phát triển).");
    }

    public static function chuaXacThuc(): self
    {
        return new self('SePay API trả 401 Unauthorized — kiểm tra SEPAY_API_TOKEN.');
    }

    public static function loiMayChu(int $status): self
    {
        return new self("SePay API lỗi máy chủ (HTTP {$status}) sau khi thử lại.");
    }

    public static function quaGioiHan(): self
    {
        return new self('SePay API trả HTTP 429 — đã thử lại nhưng vẫn bị giới hạn tốc độ.');
    }

    public static function phanHoiKhongHopLe(int $status): self
    {
        return new self("SePay API trả HTTP {$status} — không xử lý được phản hồi.");
    }

    public static function phanHoiSaiCauTruc(): self
    {
        return new self('Phản hồi SePay API không đúng cấu trúc (thiếu mảng data).');
    }

    public static function khongKetNoi(string $chiTietDaChe): self
    {
        return new self('Không kết nối được SePay API: '.$chiTietDaChe);
    }
}
