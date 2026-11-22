<?php

namespace App\Support;

/**
 * Che PII hiển thị cho khách hàng chưa xác thực lại.
 * Che ở SERVER: giá trị thật không được đưa vào HTML.
 * Tất cả dùng mb_* để an toàn với tiếng Việt có dấu.
 */
class ChePII
{
    // "Nguyễn Văn An" -> "N*** V*** A***"
    public static function hoTen(?string $hoTen): string
    {
        $hoTen = trim((string) $hoTen);
        if ($hoTen === '') {
            return 'Chưa cập nhật';
        }

        $tu = preg_split('/\s+/u', $hoTen) ?: [];
        $ketQua = [];
        foreach ($tu as $t) {
            $kyTuDau = mb_substr($t, 0, 1, 'UTF-8');
            $ketQua[] = $kyTuDau.'***';
        }

        return implode(' ', $ketQua);
    }

    /**
     * Tên rút gọn cho hiển thị CÔNG KHAI (bình luận): "Nguyễn Văn An" -> "An N."
     * Chỉ lộ tên gọi + chữ cái đầu họ — đủ nhận diện, không lộ họ tên đầy đủ.
     */
    public static function tenRutGon(?string $hoTen): string
    {
        $tu = preg_split('/\s+/u', trim((string) $hoTen)) ?: [];
        $tu = array_values(array_filter($tu, fn ($t) => $t !== ''));
        if ($tu === []) {
            return 'Người dùng';
        }
        if (count($tu) === 1) {
            return $tu[0];
        }

        $ten = end($tu);
        $dauHo = mb_substr($tu[0], 0, 1, 'UTF-8');

        return $ten.' '.$dauHo.'.';
    }

    // "0901234567" -> "090****567" (3 đầu + **** + 3 cuối)
    public static function soDienThoai(?string $sdt): string
    {
        $sdt = trim((string) $sdt);
        if ($sdt === '') {
            return 'Chưa cập nhật';
        }

        $kyTu = preg_split('//u', $sdt, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $doDai = count($kyTu);
        if ($doDai < 7) {
            return str_repeat('*', $doDai);
        }

        return mb_substr($sdt, 0, 3, 'UTF-8').'****'.mb_substr($sdt, -3, null, 'UTF-8');
    }

    // "nguyenvana@gmail.com" -> "ng***@gmail.com"
    public static function email(?string $email): string
    {
        $email = trim((string) $email);
        if ($email === '' || ! str_contains($email, '@')) {
            return 'Chưa cập nhật';
        }

        [$local, $domain] = explode('@', $email, 2);
        $local = trim($local);
        if ($local === '') {
            return '***@'.$domain;
        }

        // Giữ tối đa 2 ký tự đầu phần local.
        $hienThi = mb_substr($local, 0, min(2, mb_strlen($local, 'UTF-8')), 'UTF-8');

        return $hienThi.'***@'.$domain;
    }

    // "123 Lê Lợi, P. Bến Nghé, Q.1, TP.HCM" -> "***, TP.HCM"
    // Giữ phần cuối (thường là tỉnh/thành); phần còn lại che.
    public static function diaChi(?string $diaChi): string
    {
        $diaChi = trim((string) $diaChi);
        if ($diaChi === '') {
            return 'Chưa cập nhật';
        }

        $phan = array_values(array_filter(array_map('trim', explode(',', $diaChi))));
        if (count($phan) <= 1) {
            // Không có dấu phẩy: chỉ giữ vài ký tự cuối làm gợi ý tỉnh/thành.
            $duoi = mb_substr($diaChi, -20, null, 'UTF-8');

            return '***, '.$duoi;
        }

        $cuoi = array_pop($phan);

        return '***, '.$cuoi;
    }
}
