<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Tra cứu DNS cho rule EmailThucTe.
 * Trả về: true = tên miền nhận thư/resolve được,
 *         false = chắc chắn không tồn tại/không nhận thư,
 *         null = không xác định (lỗi mạng -> fail-open).
 * Kết quả cache theo tên miền để không tra lặp.
 */
class TraCuuDns
{
    public function nhanThuDuoc(string $domain): ?bool
    {
        $gio = max(1, (int) config('email_rac.dns_cache_gio', 6));

        // Cache cả null bằng sentinel để tránh tra lặp khi mạng lỗi.
        $cached = Cache::get('email_dns:'.$domain);
        if ($cached !== null) {
            return $cached === 'unknown' ? null : (bool) $cached;
        }

        $ketQua = $this->truyVan($domain);
        Cache::put('email_dns:'.$domain, $ketQua === null ? 'unknown' : $ketQua, now()->addHours($gio));

        return $ketQua;
    }

    private function truyVan(string $domain): ?bool
    {
        // 1) MX record -> tên miền nhận thư.
        $mx = $this->dns($domain, DNS_MX);
        if (is_array($mx) && $mx !== []) {
            return true;
        }

        // 2) Không MX -> chấp nhận A/AAAA (tên miền tồn tại, có thể nhận thư).
        $a = $this->dns($domain, DNS_A + DNS_AAAA);
        if (is_array($a) && $a !== []) {
            return true;
        }

        // 3) Không có bản ghi nào -> phân biệt NXDOMAIN vs lỗi mạng:
        //    tra một tên miền control chắc chắn tồn tại.
        $control = $this->dns('gmail.com', DNS_A);
        if ($control === false) {
            // Resolver không trả lời cả domain control -> lỗi mạng/DNS
            // của máy chủ -> fail-open.
            Log::info('EmailThucTe: DNS không phản hồi, cho qua (fail-open)', ['domain' => $domain]);

            return null;
        }

        // Control OK mà target không có MX/A -> tên miền không tồn tại
        // hoặc không nhận thư -> từ chối.
        return false;
    }

    /**
     * @return array<int, array>|false  mảng bản ghi, [] khi không có bản ghi
     *                                  loại đó, false khi truy vấn lỗi.
     */
    private function dns(string $domain, int $type): array|false
    {
        $kq = @dns_get_record($domain, $type);

        return is_array($kq) ? $kq : false;
    }
}
