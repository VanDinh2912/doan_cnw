<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\LoaiSanPham;
use App\Models\SanPham;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Import san pham tu file .xlsx theo quy trinh 2 buoc:
 *   1. docVaLapKeHoach() — doc file, validate toan bo, tra ve ke hoach
 *      (chua ghi DB). File co bat ky dong loi nao -> errors, khong lap ke hoach.
 *   2. ghiKeHoach() — ghi trong 1 transaction (tao moi + cap nhat).
 *
 * Chi tao moi / cap nhat: ten_sanpham, barcode, category_id (qua ma_loai),
 * brand_id (khop brands.ten, phai dang kich hoat), don_vi, gia_ban.
 * KHONG dong: soluong, gia_nhap, soluong_hong, soluong_toithieu, trang_thai.
 * San pham tao moi: trang_thai=0 (ngung ban), soluong=0, gia_nhap=0.
 */
class SanPhamImportService
{
    public const MAX_DONG = 1000;

    /** Header bat buoc (so sanh khong phan biet hoa thuong, cho phep moi thu tu). */
    public const HEADER = ['sku', 'barcode', 'ten_sanpham', 'danh_muc', 'hang', 'don_vi', 'gia_ban'];

    private const CACHE_PREFIX = 'import_sp:';
    public const TTL_GIAY = 900; // 15 phut

    /**
     * Doc file xlsx -> ['header_loi'=>?string, 'errors'=>[...], 'inputs'=>[...], 'file_hash'=>string]
     * inputs: moi phan tu = ['line'=>int Excel, 'sku','barcode','ten_sanpham','ma_loai','hang','don_vi','gia_ban']
     */
    public function docFile(UploadedFile $file): array
    {
        $hash = hash_file('sha256', $file->getRealPath()) ?: '';
        $base = ['file_hash' => $hash, 'errors' => [], 'inputs' => []];

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getSheet(0);
            // calculateFormulas=true: o cong thuc tra ve gia tri da tinh;
            // formatData=false: giu nguyen kieu (chuoi so khong bi them , .)
            $rows = $sheet->toArray(null, true, false, false);
            $spreadsheet->disconnectWorksheets();
        } catch (\Throwable) {
            return $base + ['header_loi' => 'Không đọc được file — chỉ chấp nhận .xlsx hợp lệ (tải file mẫu để biết định dạng).'];
        }

        // Gioi han kich thuoc: header + toi da MAX_DONG dong du lieu (+ dong trong du phong)
        if (count($rows) > self::MAX_DONG + 50) {
            return $base + ['header_loi' => 'File vượt quá '.self::MAX_DONG.' dòng dữ liệu — hãy chia nhỏ file.'];
        }

        if (count($rows) < 2) {
            return $base + ['header_loi' => 'File trống hoặc thiếu dòng dữ liệu (sau dòng tiêu đề).'];
        }

        // Dong 1 = header
        $header = array_map(
            fn ($c) => Str::lower(trim((string) $c)),
            array_values($rows[0] ?? [])
        );
        $header = array_values(array_filter($header, fn ($h) => $h !== ''));
        $thieu = array_diff(self::HEADER, $header);
        $thua = array_diff($header, self::HEADER);
        if ($thieu !== [] || $thua !== []) {
            $msg = 'Tiêu đề cột không đúng.';
            if ($thieu !== []) {
                $msg .= ' Thiếu: '.implode(', ', $thieu).'.';
            }
            if ($thua !== []) {
                $msg .= ' Thừa: '.implode(', ', $thua).'.';
            }
            $msg .= ' Cột bắt buộc: '.implode(', ', self::HEADER).'.';

            return $base + ['header_loi' => $msg];
        }

        // Map vi tri cot theo ten header (moi thu tu)
        $viTri = [];
        foreach (array_values($rows[0]) as $i => $tenCot) {
            $viTri[Str::lower(trim((string) $tenCot))] = $i;
        }

        $errors = [];
        $inputs = [];
        foreach ($rows as $idx => $row) {
            if ($idx === 0) {
                continue; // header
            }
            $line = $idx + 1; // so dong Excel that (1-based)
            $cells = array_values($row);
            $lay = fn (string $cot) => isset($viTri[$cot], $cells[$viTri[$cot]]) ? trim((string) $cells[$viTri[$cot]]) : '';

            // Bo qua dong trong hoan toan
            $raw = array_map(fn ($c) => trim((string) $c), $cells);
            if (implode('', $raw) === '') {
                continue;
            }

            $inputs[] = [
                'line' => $line,
                'sku' => $lay('sku'),
                'barcode' => $lay('barcode'),
                'ten_sanpham' => $lay('ten_sanpham'),
                'ma_loai' => $lay('danh_muc'),
                'hang' => $lay('hang'),
                'don_vi' => $lay('don_vi'),
                'gia_ban' => $lay('gia_ban'),
            ];
        }

        if ($inputs === []) {
            return $base + ['header_loi' => 'Không có dòng dữ liệu nào (chỉ có dòng tiêu đề hoặc toàn dòng trống).'];
        }
        if (count($inputs) > self::MAX_DONG) {
            return $base + ['header_loi' => 'File vượt quá '.self::MAX_DONG.' dòng dữ liệu — hãy chia nhỏ file.'];
        }

        return ['file_hash' => $hash, 'errors' => $errors, 'inputs' => $inputs];
    }

    /**
     * Kiem tra + lap ke hoach GHI (khong ghi DB).
     * Tra ve: ['ok'=>bool, 'rows'=>[...hien thi preview...], 'plan'=>[...du lieu ghi...], 'errors'=>[...], 'counts'=>[...], 'plan_hash'=>string]
     */
    public function lapKeHoach(array $inputs): array
    {
        $loaiTheoMa = LoaiSanPham::query()->get()->keyBy(fn ($l) => mb_strtolower((string) $l->ma_loai));
        $hangTheoTen = Brand::query()->get()->keyBy(fn ($b) => mb_strtolower((string) $b->ten));

        // San pham hien co: can sku (lower), barcode, cac truong se cap nhat
        $sanPham = SanPham::query()
            ->get(['id', 'sku', 'barcode', 'ten_sanpham', 'category_id', 'brand_id', 'don_vi', 'gia_ban', 'gia_nhap', 'soluong', 'trang_thai']);
        $theoSku = $sanPham->keyBy(fn ($p) => mb_strtolower((string) $p->sku));
        $barcodeDaDung = $sanPham->filter(fn ($p) => $p->barcode !== null && $p->barcode !== '')
            ->mapWithKeys(fn ($p) => [mb_strtolower((string) $p->barcode) => $p->id]);

        $errors = [];
        $rows = [];
        $plan = [];

        // Trung sku/barcode trong chinh file
        $skuTrongFile = [];
        $barcodeTrongFile = [];
        foreach ($inputs as $input) {
            $skuKey = mb_strtolower($input['sku']);
            if ($skuKey !== '') {
                $skuTrongFile[$skuKey][] = $input['line'];
            }
            if ($input['barcode'] !== '') {
                $barcodeTrongFile[mb_strtolower($input['barcode'])][] = $input['line'];
            }
        }
        foreach ($skuTrongFile as $key => $lines) {
            if (count($lines) > 1) {
                foreach ($lines as $line) {
                    $errors[] = "Dòng {$line}: SKU '{$key}' bị trùng trong file.";
                }
            }
        }
        foreach ($barcodeTrongFile as $key => $lines) {
            if (count($lines) > 1) {
                foreach ($lines as $line) {
                    $errors[] = "Dòng {$line}: barcode '{$key}' bị trùng trong file.";
                }
            }
        }

        foreach ($inputs as $input) {
            $line = $input['line'];
            $loi = [];
            $canhBao = [];

            // --- Validate tung truong ---
            $sku = trim($input['sku']);
            $barcode = trim($input['barcode']);
            $ten = trim($input['ten_sanpham']);
            $maLoai = trim($input['ma_loai']);
            $hangTen = trim($input['hang']);
            $donVi = trim($input['don_vi']);
            $giaBanRaw = trim($input['gia_ban']);

            if ($sku === '') {
                $loi[] = 'Thiếu SKU.';
            } elseif (mb_strlen($sku) > 50) {
                $loi[] = 'SKU quá 50 ký tự.';
            } elseif (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/-]{0,49}$/', $sku)) {
                $loi[] = "SKU '{$sku}' chứa ký tự không hợp lệ hoặc bắt đầu bằng = + - @.";
            }
            if ($barcode !== '' && mb_strlen($barcode) > 50) {
                $loi[] = 'Barcode quá 50 ký tự.';
            } elseif ($barcode !== '' && ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/-]{0,49}$/', $barcode)) {
                $loi[] = "Barcode '{$barcode}' chứa ký tự không hợp lệ hoặc bắt đầu bằng = + - @.";
            }
            if ($ten === '') {
                $loi[] = 'Thiếu tên sản phẩm.';
            } elseif (mb_strlen($ten) > 255) {
                $loi[] = 'Tên sản phẩm quá 255 ký tự.';
            }
            if ($maLoai === '') {
                $loi[] = 'Thiếu mã loại (ma_loai).';
            }
            if ($donVi === '') {
                $loi[] = 'Thiếu đơn vị tính.';
            } elseif (mb_strlen($donVi) > 20) {
                $loi[] = 'Đơn vị tính quá 20 ký tự.';
            }

            // gia_ban: so nguyen >= 0
            $giaBan = null;
            if ($giaBanRaw === '') {
                $loi[] = 'Thiếu giá bán.';
            } elseif (! is_numeric($giaBanRaw) || (float) $giaBanRaw != floor((float) $giaBanRaw) || (float) $giaBanRaw <= 0) {
                $loi[] = "Giá bán '{$giaBanRaw}' không hợp lệ — phải là số nguyên dương.";
            } else {
                $giaBan = (int) $giaBanRaw;
            }

            // Danh muc theo ma_loai (khong phan biet hoa thuong)
            $loai = $maLoai !== '' ? $loaiTheoMa->get(mb_strtolower($maLoai)) : null;
            if ($maLoai !== '' && ! $loai) {
                $loi[] = "Mã loại '{$maLoai}' không tồn tại.";
            }

            // Hang: co gia tri phai khop brands.ten + dang kich hoat;
            // trong -> xu ly rieng theo create/update o duoi
            $hang = null;
            $hangHopLe = true;
            if ($hangTen !== '') {
                $hang = $hangTheoTen->get(mb_strtolower($hangTen));
                if (! $hang) {
                    $loi[] = "Hãng '{$hangTen}' không tồn tại.";
                    $hangHopLe = false;
                } elseif (! $hang->kich_hoat) {
                    $loi[] = "Hãng '{$hangTen}' đang ở trạng thái ngừng — không gán được.";
                    $hangHopLe = false;
                }
            }

            // Barcode trung SP khac (tru SP dang duoc cap nhat)
            $spHienCo = $sku !== '' ? $theoSku->get(mb_strtolower($sku)) : null;
            if ($barcode !== '' && isset($barcodeDaDung[mb_strtolower($barcode)])) {
                $chu = $barcodeDaDung[mb_strtolower($barcode)];
                if ($spHienCo === null || $spHienCo->id !== $chu) {
                    $loi[] = "Barcode '{$barcode}' đã thuộc sản phẩm khác.";
                }
            }

            if ($loi !== []) {
                foreach ($loi as $l) {
                    $errors[] = "Dòng {$line}: {$l}";
                }
                $rows[] = [
                    'line' => $line, 'action' => 'error', 'sku' => $sku,
                    'ten_sanpham' => $ten, 'changes' => [], 'warnings' => [],
                    'note' => implode(' ', $loi),
                ];
                continue;
            }

            // Cap nhat: o hang de trong = giu nguyen hang hien tai (khong xoa nham);
            // Tao moi: de trong = khong gan hang.
            $brandIdMoi = $hangTen === ''
                ? ($spHienCo?->brand_id ?? null)
                : ($hangHopLe ? $hang?->id : null);

            $duLieuMoi = [
                'ten_sanpham' => $ten,
                // Barcode trong -> giu nguyen barcode cu (giong hang), tranh xoa nham
                'barcode' => $barcode !== '' ? $barcode : ($spHienCo?->barcode ?? null),
                'category_id' => $loai->id,
                'brand_id' => $brandIdMoi,
                'don_vi' => $donVi,
                'gia_ban' => $giaBan,
            ];

            if ($spHienCo === null) {
                // --- Tao moi ---
                $action = 'create';
                $changes = [];
                foreach ($duLieuMoi as $field => $new) {
                    $changes[] = ['field' => $field, 'old' => null, 'new' => $new];
                }
                $canhBao[] = 'Sẽ tạo ở trạng thái NGỪNG BÁN, tồn 0, giá nhập 0 — cần nhập kho rồi bật \'Đang bán\'.';
                if ($hang === null) {
                    $canhBao[] = 'Không gán hãng (ô hang để trống).';
                }
                $rows[] = [
                    'line' => $line, 'action' => $action, 'product_id' => null,
                    'sku' => $sku, 'ten_sanpham' => $ten,
                    'changes' => $changes, 'warnings' => $canhBao, 'note' => '',
                ];
                $plan[] = ['type' => 'create', 'line' => $line, 'input' => $input, 'data' => $duLieuMoi, 'product_id' => null];
                continue;
            }

            // --- Cap nhat ---
            $action = 'update';
            $changes = [];
            $mapField = [
                'ten_sanpham' => 'ten_sanpham',
                'barcode' => 'barcode',
                'category_id' => 'category_id',
                'brand_id' => 'brand_id',
                'don_vi' => 'don_vi',
                'gia_ban' => 'gia_ban',
            ];
            foreach ($mapField as $field => $cot) {
                $old = $spHienCo->{$cot};
                $new = $duLieuMoi[$field];
                // So sanh long nhau
                $giong = ($old === null && $new === null)
                    || ($old !== null && $new !== null && (string) $old === (string) $new);
                if (! $giong) {
                    $changes[] = ['field' => $field, 'old' => $old, 'new' => $new];
                }
            }

            // SKU khac hoa/thuong so voi DB -> canh bao (he thong giu SKU cu)
            if ($sku !== (string) $spHienCo->sku) {
                $canhBao[] = "SKU trong file '{$sku}' khác hoa/thường với '{$spHienCo->sku}' trong hệ thống — giữ nguyên SKU hiện có.";
            }

            // Canh bao gia
            $giaCu = (int) $spHienCo->gia_ban;
            $giaMoi = (int) $duLieuMoi['gia_ban'];
            if ($giaMoi !== $giaCu) {
                $chenh = $giaCu > 0 ? abs($giaMoi - $giaCu) / $giaCu : ($giaMoi > 0 ? 1.0 : 0.0);
                if ($chenh > 0.5) {
                    $canhBao[] = "Giá bán đổi mạnh: {$giaCu} → {$giaMoi} (>50%).";
                }
                $giaNhap = (float) ($spHienCo->gia_nhap ?? 0);
                if ($giaNhap > 0 && $giaMoi < $giaNhap) {
                    $canhBao[] = "Giá bán mới {$giaMoi} thấp hơn giá nhập {$giaNhap} — lỗ khi bán.";
                }
            }

            if ($changes === []) {
                $action = 'skip';
                $note = 'Không có thay đổi.';
            } else {
                $note = '';
            }

            $rows[] = [
                'line' => $line, 'action' => $action, 'product_id' => $spHienCo->id,
                'sku' => $sku, 'ten_sanpham' => $ten,
                'changes' => $changes, 'warnings' => $canhBao, 'note' => $note,
            ];
            if ($action === 'update') {
                $plan[] = ['type' => 'update', 'line' => $line, 'input' => $input, 'data' => $duLieuMoi, 'product_id' => $spHienCo->id];
            }
        }

        $counts = [
            'create' => count(array_filter($rows, fn ($r) => $r['action'] === 'create')),
            'update' => count(array_filter($rows, fn ($r) => $r['action'] === 'update')),
            'skip' => count(array_filter($rows, fn ($r) => $r['action'] === 'skip')),
            'error' => count(array_filter($rows, fn ($r) => $r['action'] === 'error')),
        ];

        // plan_hash: chu ky cua du lieu se ghi — xac nhan se lap lai va so sanh
        $plan_hash = hash('sha256', json_encode($plan, JSON_UNESCAPED_UNICODE));

        return [
            'ok' => $errors === [],
            'rows' => $rows,
            'plan' => $plan,
            'plan_hash' => $plan_hash,
            'errors' => $errors,
            'counts' => $counts,
            'inputs' => $inputs,
        ];
    }

    /**
     * Ghi ke hoach trong 1 transaction. Chi goi sau khi da xac nhan plan_hash.
     * Tra ve so dong da tao/cap nhat.
     */
    public function ghiKeHoach(array $plan): array
    {
        $daTao = 0;
        $daCapNhat = 0;
        $now = now();

        DB::transaction(function () use ($plan, &$daTao, &$daCapNhat, $now): void {
            foreach ($plan as $item) {
                if ($item['type'] === 'create') {
                    DB::table('products')->insert([
                        'category_id' => $item['data']['category_id'],
                        'sku' => $item['input']['sku'],
                        'barcode' => $item['data']['barcode'],
                        'ten_sanpham' => $item['data']['ten_sanpham'],
                        'don_vi' => $item['data']['don_vi'],
                        'gia_nhap' => 0,
                        'gia_ban' => $item['data']['gia_ban'],
                        'soluong' => 0,
                        'soluong_hong' => 0,
                        'soluong_toithieu' => 5,
                        'hinh_anh' => null,
                        'trang_thai' => 0,
                        'brand_id' => $item['data']['brand_id'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $daTao++;
                } else {
                    // data chua day du gia tri moi; tinh diff lai voi ban hien tai trong transaction
                    $sp = DB::table('products')->where('id', $item['product_id'])->lockForUpdate()->first();
                    if (! $sp) {
                        throw new RuntimeException("Sản phẩm #{$item['product_id']} không còn tồn tại.");
                    }
                    $capNhat = [];
                    foreach ($item['data'] as $field => $new) {
                        $old = $sp->{$field};
                        $giong = ($old === null && $new === null)
                            || ($old !== null && $new !== null && (string) $old === (string) $new);
                        if (! $giong) {
                            $capNhat[$field] = $new;
                        }
                    }
                    if ($capNhat !== []) {
                        $capNhat['updated_at'] = $now;
                        DB::table('products')->where('id', $item['product_id'])->update($capNhat);
                        $daCapNhat++;
                    }
                }
            }
        });

        return ['tao_moi' => $daTao, 'cap_nhat' => $daCapNhat];
    }

    /** Luu preview vao cache, tra ve token. */
    public function luuPreview(int $userId, string $fileName, string $fileHash, int $fileSize, array $keHoach): string
    {
        $token = Str::random(48);
        cache()->put(self::CACHE_PREFIX.$token, [
            'user_id' => $userId,
            'file_name' => $fileName,
            'file_hash' => $fileHash,
            'file_size' => $fileSize,
            'plan_hash' => $keHoach['plan_hash'],
            'inputs' => $keHoach['inputs'],
            'rows' => $keHoach['rows'],
            'counts' => $keHoach['counts'],
            'created_at' => time(),
        ], self::TTL_GIAY);

        return $token;
    }

    /** Lay preview (KHONG xoa — xoa o buoc xac nhan). */
    public function layPreview(string $token): ?array
    {
        $data = cache()->get(self::CACHE_PREFIX.$token);

        return is_array($data) ? $data : null;
    }

    /**
     * Xac nhan: kiem tra quyen so huu + du lieu chua doi + tieu thu token.
     * Tra ve ['ok'=>bool, 'loi'=>?string, 'ket_qua'=>?array]
     */
    public function xacNhan(string $token, int $userId): array
    {
        // Peek truoc de phan biet 403 (nguoi khac) vs het han — khong dot token cua chu hop le
        $preview = cache()->get(self::CACHE_PREFIX.$token);
        if (! is_array($preview)) {
            return ['ok' => false, 'loi' => 'Phiên xem trước đã hết hạn hoặc đã được dùng — vui lòng tải file lên lại.', 'ket_qua' => null];
        }
        if (($preview['user_id'] ?? null) !== $userId) {
            return ['ok' => false, 'loi' => 'Bạn không có quyền xác nhận phiên import này.', 'ket_qua' => null, 'cam' => 403];
        }

        // pull = doc + xoa nguyen tu -> chong bam 2 lan
        $preview = cache()->pull(self::CACHE_PREFIX.$token);
        if (! is_array($preview)) {
            return ['ok' => false, 'loi' => 'Phiên xem trước đã hết hạn hoặc đã được dùng — vui lòng tải file lên lại.', 'ket_qua' => null];
        }

        // Lap lai ke hoach tu inputs da luu, so sanh plan_hash voi DB hien tai
        $keHoachMoi = $this->lapKeHoach($preview['inputs']);
        if (! $keHoachMoi['ok']) {
            return ['ok' => false, 'loi' => 'Dữ liệu danh mục/hãng/sản phẩm đã thay đổi so với lúc xem trước — vui lòng tải file lên lại.', 'ket_qua' => null];
        }
        if (! hash_equals((string) $preview['plan_hash'], (string) $keHoachMoi['plan_hash'])) {
            return ['ok' => false, 'loi' => 'Dữ liệu đã thay đổi so với lúc xem trước (có sản phẩm/hãng/danh mục mới hoặc đã xóa) — vui lòng tải file lên lại.', 'ket_qua' => null];
        }

        try {
            $ketQua = $this->ghiKeHoach($keHoachMoi['plan']);
        } catch (\Throwable $e) {
            return ['ok' => false, 'loi' => 'Ghi dữ liệu thất bại: '.$e->getMessage(), 'ket_qua' => null];
        }

        return [
            'ok' => true,
            'loi' => null,
            'ket_qua' => $ketQua,
            'preview' => $preview,
        ];
    }
}
