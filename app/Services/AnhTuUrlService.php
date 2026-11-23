<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Tai anh san pham tu URL (giai doan 1) — CHONG SSRF DAY DU:
 *  - Chi http/https, cong 80/443, khong userinfo.
 *  - Tu choi IP literal dang thap phan/hex/octal; ten mien phai phan giai
 *    ra IP cong cong (chan 127/8, 10/8, 172.16/12, 192.168/16, 169.254/16,
 *    100.64/10, 0.0.0.0/8, ::1, fc00::/7, fe80::/10, ::ffff:IPv4 noi bo,
 *    dia chi metadata dam may) tru khi ten mien nam trong danh sach tin cay.
 *  - Ket noi GHIM vao IP da kiem (CURLOPT_RESOLVE) chong DNS rebinding;
 *    tu lap redirect toi da 3 lan va kiem IP tung buoc.
 *  - Chi nhan jpg/png/webp that (finfo + getimagesize, khong tin Content-Type,
 *    khong nhan SVG), <= 2MB, moi canh <= 6000px; xu ly lai bang GD
 *    (bo metadata, ma hoa lai), ten file theo sha256 -> khong luu trung.
 *
 * Resolver DNS va HTTP client INJECTABLE de test khong goi mang that:
 *   new AnhTuUrlService(fn($host)=>['93.184.216.34'], fn($url,$ip,$port,$max,$t)=>[...])
 */
class AnhTuUrlService
{
    public const MAX_CHUYEN_HUONG = 3;

    /** @var callable(string): array<int,string>|null */
    private $resolver;

    /** @var callable(string,string,int,int,int): array|null */
    private $fetcher;

    public function __construct(?callable $resolver = null, ?callable $fetcher = null)
    {
        $this->resolver = $resolver;
        $this->fetcher = $fetcher;
    }

    // ---------- API ----------

    /**
     * Kiem tra DINH DANG URL (preview — KHONG goi mang, khong phan giai DNS).
     * Tra ve null neu hop le, nguoc lai tra thong bao loi tieng Viet.
     */
    public function kiemTraUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null; // trong = giu anh cu, khong phai loi
        }
        $parts = $this->tachUrl($url);
        if (is_string($parts)) {
            return $parts;
        }
        if (! in_array($parts['scheme'], ['http', 'https'], true)) {
            return 'URL ảnh chỉ hỗ trợ http:// hoặc https://.';
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return 'URL ảnh không được chứa thông tin đăng nhập.';
        }
        $port = $parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80);
        if (! in_array($port, [80, 443], true)) {
            return 'URL ảnh chỉ được dùng cổng 80 hoặc 443.';
        }
        if (empty($parts['host'])) {
            return 'URL ảnh thiếu tên miền.';
        }
        if (mb_strlen($url) > 500) {
            return 'URL ảnh quá dài (tối đa 500 ký tự).';
        }

        return null;
    }

    /**
     * Tai + xu ly + luu anh cuc bo.
     * @return array{ok:bool, duong_dan:?string, sha256:?string, loi:?string}
     */
    public function taiVe(string $url): array
    {
        $url = trim($url);
        $loi = $this->kiemTraUrl($url);
        if ($loi !== null) {
            return $this->thatBai($loi);
        }

        $gioiHan = (array) config('anh_url.gioi_han', []);
        $maxByte = (int) ($gioiHan['kich_thuoc_byte'] ?? 2 * 1024 * 1024);
        $maxCanh = (int) ($gioiHan['canh_px'] ?? 6000);
        $maxRedirect = (int) ($gioiHan['chuyen_huong'] ?? self::MAX_CHUYEN_HUONG);
        $timeoutTong = (int) ($gioiHan['timeout_tong'] ?? 15);
        $deadline = microtime(true) + $timeoutTong;

        $hienTai = $url;
        $body = null;
        $statusCuoi = 0;

        for ($buoc = 0; $buoc <= $maxRedirect; $buoc++) {
            $parts = parse_url($hienTai);
            $hostTho = isset($parts['host']) ? trim($parts['host'], '[]') : '';
            $port = $parts['port'] ?? (strtolower((string) ($parts['scheme'] ?? '')) === 'https' ? 443 : 80);

            $ipKetQua = $this->ipCongKhaiCua($hostTho);
            if (! $ipKetQua['ok']) {
                return $this->thatBai($ipKetQua['loi']);
            }

            $conLai = (int) ceil($deadline - microtime(true));
            if ($conLai <= 0) {
                return $this->thatBai('Tải ảnh quá thời gian cho phép ('. $timeoutTong .'s).');
            }

            $res = ($this->layFetcher())($hienTai, $ipKetQua['ips'][0], $port, $maxByte, $conLai);
            $statusCuoi = (int) ($res['status'] ?? 0);

            if (in_array($statusCuoi, [301, 302, 303, 307, 308], true) && ! empty($res['location'])) {
                $dich = $this->giaiQuyetUrlTuongDoi($hienTai, (string) $res['location']);
                if ($dich === null) {
                    return $this->thatBai('URL chuyển hướng không hợp lệ.');
                }
                $loiUrl = $this->kiemTraUrl($dich);
                if ($loiUrl !== null) {
                    return $this->thatBai('URL chuyển hướng: '.$loiUrl);
                }
                $hienTai = $dich;
                continue;
            }

            if (! empty($res['error']) && ($res['body'] ?? '') === '') {
                return $this->thatBai($this->dichLoiKetNoi((string) $res['error']));
            }
            if ($statusCuoi !== 200) {
                return $this->thatBai("Máy chủ trả về mã HTTP {$statusCuoi}.");
            }
            $body = (string) ($res['body'] ?? '');
            break;
        }

        if ($body === null) {
            return $this->thatBai('URL chuyển hướng quá nhiều lần (tối đa '.$maxRedirect.').');
        }

        // Gioi han kich thuoc NGAY KHI DOC (fetcher da cat o maxByte+1)
        if (strlen($body) > $maxByte) {
            return $this->thatBai('Ảnh vượt quá '.round($maxByte / 1048576, 1).'MB.');
        }

        // Dinh dang: tin noi dung that (finfo), KHONG tin header/duoi file
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_buffer($finfo, $body) : false;
        if ($finfo) {
            finfo_close($finfo);
        }
        $mimeHopLe = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (! is_string($mime) || ! isset($mimeHopLe[$mime])) {
            return $this->thatBai('Nội dung không phải ảnh JPG/PNG/WEBP hợp lệ (SVG và định dạng khác bị từ chối).');
        }

        $info = @getimagesizefromstring($body);
        if ($info === false || ! in_array($info['mime'] ?? '', array_keys($mimeHopLe), true)) {
            return $this->thatBai('Ảnh không đọc được kích thước — có thể file bị lỗi.');
        }
        if ((int) $info[0] > $maxCanh || (int) $info[1] > $maxCanh) {
            return $this->thatBai("Ảnh vượt quá {$maxCanh}px mỗi cạnh ({$info[0]}×{$info[1]}px).");
        }

        // Xu ly lai bang GD: bo metadata (EXIF/XMP), ma hoa lai cung dinh dang
        if (! function_exists('imagecreatefromstring')) {
            return $this->thatBai('Máy chủ thiếu extension GD — không xử lý được ảnh.');
        }
        $img = @imagecreatefromstring($body);
        if ($img === false) {
            return $this->thatBai('Không giải mã được ảnh bằng GD.');
        }
        ob_start();
        try {
            match (true) {
                $info[2] === IMAGETYPE_JPEG => imagejpeg($img, null, 90),
                $info[2] === IMAGETYPE_PNG => imagepng($img, null, 6),
                $info[2] === IMAGETYPE_WEBP => imagewebp($img, null, 90),
                default => false,
            };
            $daXuLy = (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            imagedestroy($img);

            return $this->thatBai('Xử lý ảnh thất bại: '.$e->getMessage());
        }
        imagedestroy($img);
        if ($daXuLy === '') {
            return $this->thatBai('Xử lý ảnh thất bại — ảnh rỗng sau khi mã hóa lại.');
        }

        $sha = hash('sha256', $daXuLy);
        $duoi = $mimeHopLe[$mime];
        $duongDan = 'sanpham/anh-'.$sha.'.'.$duoi;
        if (! Storage::disk('public')->exists($duongDan)) {
            Storage::disk('public')->put($duongDan, $daXuLy);
        }

        return ['ok' => true, 'duong_dan' => $duongDan, 'sha256' => $sha, 'loi' => null];
    }

    // ---------- URL / IP ----------

    /** @return array{scheme?:string,host?:string,port?:int,user?:string,pass?:string}|string */
    private function tachUrl(string $url): array|string
    {
        if (preg_match('/\s/u', $url) === 1 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return 'URL ảnh không hợp lệ.';
        }
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return 'URL ảnh không hợp lệ.';
        }

        return $parts;
    }

    /**
     * Phan giai host -> danh sach IP cong khai, hoac bao loi.
     * @return array{ok:bool, ips?:array<int,string>, loi?:string}
     */
    private function ipCongKhaiCua(string $host): array
    {
        $host = trim($host);
        if ($host === '') {
            return ['ok' => false, 'loi' => 'URL ảnh thiếu tên miền.'];
        }

        // IP literal (ke ca dang ?:1, [::1]) — kiem truc tiep, khong can DNS
        $hostCheck = trim($host, '[]');
        if (@inet_pton($hostCheck) !== false) {
            return $this->kiemIps([$hostCheck], $hostCheck);
        }

        // Dang IP viet tat: toan so+cham (vd 2130706433, 0177.0.0.1) hoac hex 0x...
        // -> khong phai ten mien hop le, tu choi luon (curl co the hieu thanh IP)
        if (preg_match('/^[\d.]+$/', $hostCheck) === 1 || preg_match('/0x[0-9a-f]/i', $hostCheck) === 1) {
            return ['ok' => false, 'loi' => 'Địa chỉ IP dạng viết tắt không được hỗ trợ — hãy dùng tên miền hoặc IP đầy đủ.'];
        }

        $ips = array_values(array_unique(($this->layResolver())($hostCheck)));
        $ips = array_values(array_filter($ips, fn ($ip) => @inet_pton((string) $ip) !== false));
        if ($ips === []) {
            return ['ok' => false, 'loi' => "Không phân giải được tên miền '{$hostCheck}'."];
        }

        return $this->kiemIps($ips, $hostCheck);
    }

    /**
     * Kiem toan bo IP da phan giai cong khai (tru ten mien tin cay).
     * @param  array<int,string>  $ips
     * @return array{ok:bool, ips?:array<int,string>, loi?:string}
     */
    private function kiemIps(array $ips, ?string $host = null): array
    {
        $host = $host ?? '';
        $tinCay = $this->laTenMienTinCay($host);
        foreach ($ips as $ip) {
            if (! $tinCay && $this->laIpNoiBo((string) $ip)) {
                return ['ok' => false, 'loi' => 'Địa chỉ IP nội bộ/đặc biệt không được phép tải ảnh.'];
            }
        }

        return ['ok' => true, 'ips' => $ips];
    }

    private function laTenMienTinCay(string $host): bool
    {
        $host = mb_strtolower(trim($host, '[]'));
        foreach ((array) config('anh_url.ten_mien_tin_cay', []) as $mien) {
            $mien = mb_strtolower(trim((string) $mien));
            if ($mien !== '' && ($host === $mien || str_ends_with($host, '.'.$mien))) {
                return true;
            }
        }

        return false;
    }

    /** Chan day du dai IP noi bo/dac biet (IPv4 + IPv6 + IPv4-mapped). */
    public function laIpNoiBo(string $ip): bool
    {
        $ip = trim($ip, '[]');
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return true; // khong phai IP hop le -> chan
        }
        if (strlen($packed) === 4) {
            return $this->laIpv4NoiBo($packed);
        }

        // IPv6
        $b = array_values(unpack('C16', $packed));
        // :: (unspecified) va ::1 (loopback)
        if ($packed === @inet_pton('::1') || $packed === @inet_pton('::')) {
            return true;
        }
        // fc00::/7 (unique local)
        if (($b[0] & 0xFE) === 0xFC) {
            return true;
        }
        // fe80::/10 (link-local)
        if ($b[0] === 0xFE && ($b[1] & 0xC0) === 0x80) {
            return true;
        }
        // ::ffff:a.b.c.d (IPv4-mapped) hoac ::a.b.c.d (compatible)
        $v4Map = ($b[0] === 0 && $b[1] === 0 && $b[2] === 0 && $b[3] === 0
            && $b[4] === 0 && $b[5] === 0 && $b[6] === 0 && $b[7] === 0
            && $b[8] === 0 && $b[9] === 0 && $b[10] === 0xFF && $b[11] === 0xFF)
            || (array_sum(array_slice($b, 0, 12)) === 0);
        if ($v4Map) {
            $v4 = implode('.', array_slice($b, 12));

            return $this->laIpv4NoiBo(inet_pton($v4));
        }

        return false;
    }

    private function laIpv4NoiBo(string $packed4): bool
    {
        $n = unpack('N', $packed4)[1]; // unsigned 32-bit
        // 0.0.0.0/8, 10/8, 100.64/10, 127/8, 169.254/16, 172.16/12, 192.168/16
        $khoang = [
            [0x00000000, 0x00FFFFFF],
            [0x0A000000, 0x0AFFFFFF],
            [0x64400000, 0x647FFFFF],
            [0x7F000000, 0x7FFFFFFF],
            [0xA9FE0000, 0xA9FEFFFF],
            [0xAC100000, 0xAC1FFFFF],
            [0xC0A80000, 0xC0A8FFFF],
        ];
        foreach ($khoang as [$min, $max]) {
            if ($n >= $min && $n <= $max) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int,string> */
    private function layResolver(): callable
    {
        return $this->resolver ??= function (string $host): array {
            $ips = [];
            $recs = @dns_get_record($host, DNS_A + DNS_AAAA) ?: [];
            foreach ($recs as $r) {
                $ip = $r['ip'] ?? $r['ipv6'] ?? null;
                if (is_string($ip) && $ip !== '') {
                    $ips[] = $ip;
                }
            }
            if ($ips === []) {
                $ip = @gethostbyname($host);
                if (is_string($ip) && $ip !== $host && @inet_pton($ip) !== false) {
                    $ips[] = $ip;
                }
            }

            return array_values(array_unique($ips));
        };
    }

    /** @return callable(string,string,int,int,int): array */
    private function layFetcher(): callable
    {
        return $this->fetcher ??= function (string $url, string $ip, int $port, int $maxBytes, int $timeout): array {
            $host = trim((string) parse_url($url, PHP_URL_HOST), '[]');
            $body = '';
            $location = null;
            $status = 0;
            $ch = curl_init($url);
            if ($ch === false) {
                return ['status' => 0, 'location' => null, 'body' => '', 'error' => 'curl_init thất bại'];
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => max(1, min(5, $timeout)),
                CURLOPT_TIMEOUT => max(1, $timeout),
                CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"],
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'LaraShop-ImageImport/1.0',
                CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$location, &$status): int {
                    $len = strlen($line);
                    if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $line, $m) === 1) {
                        $status = (int) $m[1];
                    } elseif (stripos($line, 'location:') === 0) {
                        $location = trim(substr($line, 9));
                    }

                    return $len;
                },
                CURLOPT_WRITEFUNCTION => function ($ch, string $data) use (&$body, $maxBytes): int {
                    $body .= $data;
                    if (strlen($body) > $maxBytes) {
                        return 0; // cat som -> curl bao loi write, ta nhan dien qua strlen
                    }

                    return strlen($data);
                },
            ]);
            curl_exec($ch);
            $loiCurl = curl_error($ch);
            curl_close($ch);

            // Neu body da vuot maxBytes thi day la "qua lon", khong phai loi mang
            $quaLon = strlen($body) > $maxBytes;
            if ($loiCurl !== '' && ! $quaLon && $body === '') {
                return ['status' => $status, 'location' => $location, 'body' => '', 'error' => $loiCurl];
            }

            return ['status' => $status, 'location' => $location, 'body' => $body, 'error' => null];
        };
    }

    private function giaiQuyetUrlTuongDoi(string $base, string $dich): ?string
    {
        if (preg_match('#^https?://#i', $dich) === 1) {
            return $dich;
        }
        $parts = parse_url($base);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }
        $goc = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        if (str_starts_with($dich, '/')) {
            return $goc.$dich;
        }
        $thuMuc = preg_replace('#/[^/]*$#', '/', (string) parse_url($base, PHP_URL_PATH));

        return $goc.$thuMuc.$dich;
    }

    private function dichLoiKetNoi(string $loi): string
    {
        return match (true) {
            str_contains($loi, 'timed out') || str_contains($loi, 'Timeout') => 'Tải ảnh quá thời gian cho phép.',
            str_contains($loi, 'resolve') || str_contains($loi, 'Could not resolve') => 'Không kết nối được tới máy chủ ảnh.',
            default => 'Không tải được ảnh: '.$loi,
        };
    }

    /** @return array{ok:false, duong_dan:null, sha256:null, loi:string} */
    private function thatBai(string $loi): array
    {
        return ['ok' => false, 'duong_dan' => null, 'sha256' => null, 'loi' => $loi];
    }
}
