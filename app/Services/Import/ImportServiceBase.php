<?php

namespace App\Services\Import;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * M2 — Khung import Excel 2 buoc dung chung cho moi doi tuong:
 *   1. docFile() — doc .xlsx, khop cot theo ten tieu de (chuan hoa + aliases
 *      config), tra ve inputs + thong tin cot (nhan/bo_qua/goi_y/he_thong).
 *   2. lapKeHoach() — (tung service tu implement) validate + lap plan.
 *   3. ghiKeHoach() — (tung service tu implement) ghi trong 1 transaction.
 *   4. luuPreview/layPreview/xacNhan — cache token, plan_hash chong replay,
 *      so huu preview, all-or-nothing.
 *
 * Lop con dinh nghia: cauHinhImport() (doc config), truongImport() (map
 * field => nhan chuan), cotHeThong(), cotChiDoc(), lapKeHoach(), ghiKeHoach().
 */
abstract class ImportServiceBase
{
    public const MAX_DONG = 1000;
    public const TTL_GIAY = 900; // 15 phut

    private string $cachePrefix = '';

    /** Cau hinh import: config/<file>.php — key 'truong','cot_he_thong','cot_chi_doc'. */
    abstract protected function fileCauHinh(): string;

    /**
     * Map field => ['nhan'=>..., 'aliases'=>..., 'bat_buoc'=>bool] cho cac
     * truong DUOC NHAP. mac dinh doc tu config.
     */
    protected function truongImport(): array
    {
        return (array) config($this->fileCauHinh().'.truong', []);
    }

    protected function cotHeThong(): array
    {
        return (array) config($this->fileCauHinh().'.cot_he_thong', []);
    }

    protected function cotChiDoc(): array
    {
        return (array) config($this->fileCauHinh().'.cot_chi_doc', []);
    }

    protected function cacheKey(): string
    {
        return $this->cachePrefix !== '' ? $this->cachePrefix : 'import:'.static::class.':';
    }

    protected function datCachePrefix(string $prefix): void
    {
        $this->cachePrefix = $prefix;
    }

    /**
     * Doc file xlsx -> inputs theo khung chung.
     * inputs[i] = ['line'=>int, 'fields'=>[field=>raw string|'']]
     *   - field vang mat trong file -> key NULL (cot khong co trong file)
     *   - field co cot nhung o trong -> ''
     * 'cot' = ['nhan'=>[field=>ten goc], 'bo_qua'=>[...], 'goi_y'=>[...],
     *          'he_thong'=>[...], 'chi_doc'=>[...]]
     */
    public function docFile(UploadedFile $file): array
    {
        $hash = hash_file('sha256', $file->getRealPath()) ?: '';
        $base = ['file_hash' => $hash, 'errors' => [], 'inputs' => [], 'cot' => $this->cotRong()];

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getSheet(0);
            $rows = $sheet->toArray(null, true, false, false);
            $spreadsheet->disconnectWorksheets();
        } catch (\Throwable) {
            return $base + ['header_loi' => 'Không đọc được file — chỉ chấp nhận .xlsx hợp lệ (tải file mẫu để biết định dạng).'];
        }

        if (count($rows) > self::MAX_DONG + 50) {
            return $base + ['header_loi' => 'File vượt quá '.self::MAX_DONG.' dòng dữ liệu — hãy chia nhỏ file.'];
        }
        if (count($rows) < 2) {
            return $base + ['header_loi' => 'File trống hoặc thiếu dòng dữ liệu (sau dòng tiêu đề).'];
        }

        // --- Dong 1: khop cot ---
        $headerTho = array_values($rows[0] ?? []);
        $bangTruong = CotMapper::bangKhacPhuc($this->truongImport());
        $bangPhu = $this->bangPhu();

        // Ung vien goi y: ten chuan (nhan) cua truong + aliases
        $ungVien = [];
        foreach ($this->truongImport() as $field => $cfg) {
            foreach (array_merge([$cfg['nhan'] ?? $field], $cfg['aliases'] ?? []) as $name) {
                $chuan = CotMapper::chuanHoa((string) $name);
                if ($chuan !== '') {
                    $ungVien[$chuan] = (string) ($cfg['nhan'] ?? $name);
                }
            }
        }

        $viTri = [];   // field => vi tri cot
        $nhan = [];    // field => ten cot goc trong file
        $boQua = [];
        $goiY = [];
        $heThong = [];
        $chiDoc = [];
        $daCo = [];

        foreach ($headerTho as $i => $tenCot) {
            $ten = trim((string) $tenCot);
            if ($ten === '') {
                continue;
            }
            $khop = CotMapper::khop($ten, $bangTruong, $bangPhu);
            if ($khop === null) {
                $goi = CotMapper::goiY($ten, $ungVien);
                $boQua[] = $ten.($goi !== null ? " (ý bạn là cột '{$goi}'?)" : '');
                continue;
            }
            if (str_starts_with($khop, 'he_thong:')) {
                $heThong[$khop] = $ten;
                continue;
            }
            if (str_starts_with($khop, 'chi_doc:')) {
                $chiDoc[$khop] = $ten;
                continue;
            }
            // trung field (2 cot cung nghia) -> cot sau bi bo qua
            if (isset($viTri[$khop])) {
                $boQua[] = $ten." (trùng nghĩa cột '{$nhan[$khop]}', bỏ qua)";
                continue;
            }
            $viTri[$khop] = $i;
            $nhan[$khop] = $ten;
            $daCo[$khop] = true;
        }

        // Cot bat buoc phai co mat
        $thieu = [];
        foreach ($this->truongImport() as $field => $cfg) {
            if (($cfg['bat_buoc'] ?? false) && ! isset($viTri[$field])) {
                $thieu[] = $cfg['nhan'] ?? $field;
            }
        }
        if ($thieu !== []) {
            return $base + [
                'header_loi' => 'File thiếu cột bắt buộc: '.implode(', ', $thieu)
                    .'. Cột bắt buộc: '.implode(', ', array_map(
                        fn ($f, $c) => ($c['bat_buoc'] ?? false) ? ($c['nhan'] ?? $f) : null,
                        array_keys($this->truongImport()), $this->truongImport()
                    )) .'.',
                'cot' => $this->dongGoiCot($nhan, $boQua, $goiY, $heThong, $chiDoc),
            ];
        }

        // --- Cac dong du lieu ---
        $inputs = [];
        $trong = []; // field => so o trong (chi cot co mat trong file)
        $fields = array_keys($this->truongImport());
        foreach ($rows as $idx => $row) {
            if ($idx === 0) {
                continue;
            }
            $line = $idx + 1;
            $cells = array_values($row);
            $raw = array_map(fn ($c) => trim((string) $c), $cells);
            if (implode('', $raw) === '') {
                continue;
            }

            $f = [];
            foreach ($fields as $field) {
                if (! isset($viTri[$field])) {
                    $f[$field] = null; // cot khong co trong file
                    continue;
                }
                $cell = $cells[$viTri[$field]] ?? '';
                $f[$field] = $this->oSangChuoi($cell);
                if ($f[$field] === '') {
                    $trong[$field] = ($trong[$field] ?? 0) + 1;
                }
            }
            $inputs[] = ['line' => $line, 'fields' => $f];
        }

        if ($inputs === []) {
            return $base + ['header_loi' => 'Không có dòng dữ liệu nào (chỉ có dòng tiêu đề hoặc toàn dòng trống).'];
        }
        if (count($inputs) > self::MAX_DONG) {
            return $base + ['header_loi' => 'File vượt quá '.self::MAX_DONG.' dòng dữ liệu — hãy chia nhỏ file.'];
        }

        return [
            'file_hash' => $hash,
            'errors' => [],
            'inputs' => $inputs,
            'cot' => $this->dongGoiCot($nhan, $boQua, $goiY, $heThong, $chiDoc, $trong, count($inputs)),
        ];
    }

    /** bang phu: chuanHoa(alias) => 'he_thong:key' | 'chi_doc:key' */
    private function bangPhu(): array
    {
        $bang = [];
        foreach ($this->cotHeThong() as $key => $aliases) {
            foreach ($aliases as $a) {
                $k = CotMapper::chuanHoa((string) $a);
                if ($k !== '') {
                    $bang[$k] = 'he_thong:'.$key;
                }
            }
        }
        foreach ($this->cotChiDoc() as $key => $aliases) {
            foreach ($aliases as $a) {
                $k = CotMapper::chuanHoa((string) $a);
                if ($k !== '' && ! isset($bang[$k])) {
                    $bang[$k] = 'chi_doc:'.$key;
                }
            }
        }

        return $bang;
    }

    private function cotRong(): array
    {
        return ['nhan' => [], 'bo_qua' => [], 'goi_y' => [], 'he_thong' => [], 'chi_doc' => [],
            'trong' => [], 'tong_dong' => 0, 'bat_buoc' => []];
    }

    private function dongGoiCot(array $nhan, array $boQua, array $goiY, array $heThong, array $chiDoc,
        array $trong = [], int $tongDong = 0): array
    {
        // goi_y da nhung trong chuoi bo_qua; tach rieng de view hien ro
        $dsGoiY = [];
        foreach ($boQua as $b) {
            if (preg_match('/^(.+?) \(ý bạn là cột \'(.+?)\'\?\)$/u', $b, $m)) {
                $dsGoiY[$m[1]] = $m[2];
            }
        }

        $batBuoc = [];
        foreach ($this->truongImport() as $f => $c) {
            if ($c['bat_buoc'] ?? false) {
                $batBuoc[] = $f;
            }
        }

        return [
            'nhan' => $nhan,
            'bo_qua' => array_values(array_filter($boQua, fn ($b) => ! str_contains($b, 'ý bạn là'))),
            'goi_y' => $dsGoiY,
            'he_thong' => array_values($heThong),
            'chi_doc' => array_values($chiDoc),
            'trong' => $trong,
            'tong_dong' => $tongDong,
            'bat_buoc' => $batBuoc,
        ];
    }

    /** O Excel -> chuoi an toan: so nguyen giu nguyen, float khong dang khoa hoc. */
    protected function oSangChuoi(mixed $cell): string
    {
        if ($cell === null) {
            return '';
        }
        if (is_int($cell)) {
            return (string) $cell;
        }
        if (is_float($cell)) {
            // barcode/sku dai co the thanh float — tranh "8.9E+12"
            $s = rtrim(rtrim(sprintf('%.10F', $cell), '0'), '.');

            return str_contains($s, '.') ? rtrim($s, '0') : $s;
        }

        return trim((string) $cell);
    }

    // ---------- Ke hoach: lop con implement ----------

    /**
     * @param  array{inputs:array<int,array{line:int,fields:array<string,?string>}>,cot?:array}  $doc
     */
    abstract public function lapKeHoach(array $doc): array;

    abstract public function ghiKeHoach(array $plan): array;

    // ---------- Preview 2 buoc (dung chung) ----------

    public function luuPreview(int $userId, string $fileName, string $fileHash, int $fileSize, array $keHoach, array $doc): string
    {
        $token = Str::random(48);
        cache()->put($this->cacheKey().$token, [
            'user_id' => $userId,
            'file_name' => $fileName,
            'file_hash' => $fileHash,
            'file_size' => $fileSize,
            'plan_hash' => $keHoach['plan_hash'],
            'inputs' => $doc['inputs'],
            'cot' => $doc['cot'] ?? $this->cotRong(),
            'rows' => $keHoach['rows'],
            'counts' => $keHoach['counts'],
            'created_at' => time(),
        ], self::TTL_GIAY);

        return $token;
    }

    public function layPreview(string $token): ?array
    {
        $data = cache()->get($this->cacheKey().$token);

        return is_array($data) ? $data : null;
    }

    /**
     * Xac nhan: kiem tra quyen so huu + du lieu chua doi + tieu thu token.
     */
    public function xacNhan(string $token, int $userId): array
    {
        $preview = cache()->get($this->cacheKey().$token);
        if (! is_array($preview)) {
            return ['ok' => false, 'loi' => 'Phiên xem trước đã hết hạn hoặc đã được dùng — vui lòng tải file lên lại.', 'ket_qua' => null];
        }
        if (($preview['user_id'] ?? null) !== $userId) {
            return ['ok' => false, 'loi' => 'Bạn không có quyền xác nhận phiên import này.', 'ket_qua' => null, 'cam' => 403];
        }

        // pull = doc + xoa nguyen tu -> chong bam 2 lan
        $preview = cache()->pull($this->cacheKey().$token);
        if (! is_array($preview)) {
            return ['ok' => false, 'loi' => 'Phiên xem trước đã hết hạn hoặc đã được dùng — vui lòng tải file lên lại.', 'ket_qua' => null];
        }

        // Lap lai ke hoach, so sanh plan_hash voi DB hien tai
        $keHoachMoi = $this->lapKeHoach(['inputs' => $preview['inputs'], 'cot' => $preview['cot'] ?? []]);
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

        return ['ok' => true, 'loi' => null, 'ket_qua' => $ketQua, 'preview' => $preview];
    }
}
