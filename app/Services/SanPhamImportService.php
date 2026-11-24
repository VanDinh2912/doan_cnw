<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\LoaiSanPham;
use App\Models\SanPham;
use App\Services\Import\ImportServiceBase;
use App\Support\KiemTraSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Import san pham tu file .xlsx theo quy trinh 2 buoc (M2 — linh hoat):
 *   1. docFile() — khop cot theo ten tieu de chuan hoa + aliases
 *      (config/import_sp.php); cot la bo qua + goi y; cot he thong
 *      (soluong, gia_nhap, trang_thai...) bo qua ro rang.
 *   2. lapKeHoach() — chi truong CO COT trong file moi duoc xet;
 *      o TRONG = khong doi (cap nhat) / thieu truong bat buoc (tao moi).
 *   3. ghiKeHoach() — ghi trong 1 transaction, all-or-nothing.
 *
 * KHONG bao gio nhap: soluong, gia_nhap, soluong_hong, soluong_toithieu,
 * trang_thai. San pham tao moi: trang_thai=0, soluong=0, gia_nhap=0.
 */
class SanPhamImportService extends ImportServiceBase
{
    /** Truong bat buoc khi TAO MOI (cap nhat chi can sku). */
    private const BAT_BUOC_TAO_MOI = ['ten_sanpham', 'danh_muc', 'don_vi', 'gia_ban'];

    public function __construct(private readonly AnhTuUrlService $anh)
    {
        $this->datCachePrefix('import_sp:');
    }

    /** products da co 3 cot metadata anh? Thieu -> bo qua cot hinh_anh_url em. */
    public function coCotAnh(): bool
    {
        return KiemTraSchema::coCot('products', 'hinh_anh_nguon')
            && KiemTraSchema::coCot('products', 'hinh_anh_sha256')
            && KiemTraSchema::coCot('products', 'hinh_anh_tai_luc');
    }

    protected function fileCauHinh(): string
    {
        return 'import_sp';
    }

    /**
     * Kiem tra + lap ke hoach GHI (khong ghi DB).
     * $doc = ket qua docFile(): inputs + cot.
     */
    public function lapKeHoach(array $doc): array
    {
        $inputs = $doc['inputs'] ?? [];
        $truongCo = $doc['cot']['nhan'] ?? []; // field => ten cot goc

        $coCotAnh = $this->coCotAnh();

        $loaiTheoMa = LoaiSanPham::query()->get()->keyBy(fn ($l) => mb_strtolower((string) $l->ma_loai));
        $hangTheoTen = Brand::query()->get()->keyBy(fn ($b) => mb_strtolower((string) $b->ten));

        $cotSp = ['id', 'sku', 'barcode', 'ten_sanpham', 'category_id', 'brand_id', 'don_vi', 'gia_ban', 'gia_nhap', 'soluong', 'trang_thai', 'hinh_anh'];
        if ($coCotAnh) {
            $cotSp[] = 'hinh_anh_nguon';
        }
        $sanPham = SanPham::query()->get($cotSp);
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
            $f = $input['fields'];
            $skuKey = mb_strtolower(trim((string) ($f['sku'] ?? '')));
            if ($skuKey !== '') {
                $skuTrongFile[$skuKey][] = $input['line'];
            }
            $bc = trim((string) ($f['barcode'] ?? ''));
            if ($bc !== '') {
                $barcodeTrongFile[mb_strtolower($bc)][] = $input['line'];
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
            $f = $input['fields'];
            $lay = fn (string $field): ?string => $f[$field] ?? null; // null = cot khong co

            $loi = [];
            $canhBao = [];

            $sku = trim((string) ($lay('sku') ?? ''));
            $spHienCo = $sku !== '' ? $theoSku->get(mb_strtolower($sku)) : null;
            $laTaoMoi = $spHienCo === null;

            // Nguon anh hien tai: cot metadata, fallback URL https cu trong hinh_anh.
            // So sanh theo URL DA CHUAN HOA (bo query) de khop quy tac luu moi.
            $nguonAnhCu = null;
            if ($spHienCo !== null) {
                $nguonAnhCu = $coCotAnh ? trim((string) ($spHienCo->hinh_anh_nguon ?? '')) : '';
                if ($nguonAnhCu === '' && is_string($spHienCo->hinh_anh) && str_starts_with($spHienCo->hinh_anh, 'http')) {
                    $nguonAnhCu = $spHienCo->hinh_anh;
                }
                $nguonAnhCu = $nguonAnhCu === '' ? null : $this->anh->chuanHoaUrlLuu($nguonAnhCu);
            }

            // --- sku (luon co cot, da kiem o docFile) ---
            if ($sku === '') {
                $loi[] = 'Thiếu SKU.';
            } elseif (mb_strlen($sku) > 50) {
                $loi[] = 'SKU quá 50 ký tự.';
            } elseif (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/-]{0,49}$/', $sku)) {
                $loi[] = "SKU '{$sku}' chứa ký tự không hợp lệ hoặc bắt đầu bằng = + - @.";
            }

            // --- Du lieu import theo cot co mat; o trong = khong doi ---
            // Gia tri '' hoac null = khong lay (update: giu cu; create: thieu neu bat buoc)
            $giaTri = [];
            foreach (['barcode', 'ten_sanpham', 'danh_muc', 'hang', 'don_vi', 'gia_ban'] as $field) {
                $raw = $lay($field);
                $giaTri[$field] = ($raw === null || trim($raw) === '') ? null : trim($raw);
            }

            // Thieu truong bat buoc khi TAO MOI
            if ($laTaoMoi) {
                $thieu = [];
                foreach (self::BAT_BUOC_TAO_MOI as $req) {
                    if (($giaTri[$req] ?? null) === null) {
                        $tenCot = $truongCo[$req] ?? $req;
                        $thieu[] = "{$tenCot} (cột '{$req}')";
                    }
                }
                if ($thieu !== []) {
                    $loi[] = 'Tạo mới thiếu trường bắt buộc: '.implode(', ', $thieu).'.';
                }
            }

            // Validate chi cac truong CO GIA TRI (cot co + o khong trong)
            $barcode = $giaTri['barcode'];
            $ten = $giaTri['ten_sanpham'];
            $maLoai = $giaTri['danh_muc'];
            $hangTen = $giaTri['hang'];
            $donVi = $giaTri['don_vi'];
            $giaBanRaw = $giaTri['gia_ban'];

            if ($barcode !== null && mb_strlen($barcode) > 50) {
                $loi[] = 'Barcode quá 50 ký tự.';
            } elseif ($barcode !== null && ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/-]{0,49}$/', $barcode)) {
                $loi[] = "Barcode '{$barcode}' chứa ký tự không hợp lệ hoặc bắt đầu bằng = + - @.";
            }
            if ($ten !== null) {
                if (mb_strlen($ten) > 255) {
                    $loi[] = 'Tên sản phẩm quá 255 ký tự.';
                }
            }
            if ($donVi !== null) {
                if (mb_strlen($donVi) > 20) {
                    $loi[] = 'Đơn vị tính quá 20 ký tự.';
                }
            }

            $giaBan = null;
            if ($giaBanRaw !== null) {
                if (! is_numeric($giaBanRaw) || (float) $giaBanRaw != floor((float) $giaBanRaw) || (float) $giaBanRaw <= 0) {
                    $loi[] = "Giá bán '{$giaBanRaw}' không hợp lệ — phải là số nguyên dương.";
                } else {
                    $giaBan = (int) $giaBanRaw;
                }
            }

            $loai = null;
            if ($maLoai !== null && $maLoai !== '') {
                $loai = $loaiTheoMa->get(mb_strtolower($maLoai));
                if (! $loai) {
                    $loi[] = "Mã loại '{$maLoai}' không tồn tại.";
                }
            }

            // --- Anh qua URL (GD1): chi kiem DINH DANG o preview; tai that khi xac nhan ---
            // URL tro ve APP_URL/storage/... = anh cuc bo -> khong tai mang.
            $anhUrl = null;      // URL ngoai can tai
            $anhNoiBo = null;    // duong dan disk public khi URL noi bo
            $anhUrlDayDu = null; // URL goc trong file (de luu nguon da chuan hoa)
            $canhBaoAnhNoiBo = null;
            if ($coCotAnh) {
                $rawAnh = $f['hinh_anh_url'] ?? null;
                if ($rawAnh !== null && trim((string) $rawAnh) !== '') {
                    $urlAnh = trim((string) $rawAnh);
                    $loiUrl = $this->anh->kiemTraUrl($urlAnh);
                    if ($loiUrl !== null) {
                        $loi[] = 'Ảnh: '.$loiUrl;
                    } else {
                        $anhUrlDayDu = $urlAnh;
                        $pathNoiBo = $this->anh->laUrlNoiBo($urlAnh);
                        if ($pathNoiBo !== null) {
                            // URL noi bo CHI duoc dung khi trung anh hien tai
                            // cua chinh san pham do (vong xuat -> nhap = khong doi).
                            // File khac -> tu choi kem canh bao, khong cho tro anh lung.
                            $anhHienTai = $spHienCo?->hinh_anh;
                            if ($spHienCo !== null && is_string($anhHienTai) && $anhHienTai === $pathNoiBo) {
                                // trung anh hien tai -> khong can lam gi (khong doi)
                            } else {
                                $canhBaoAnhNoiBo = 'URL ảnh nội bộ trỏ file khác ảnh hiện tại — bỏ qua ảnh (chỉ chấp nhận đúng ảnh đang dùng).';
                            }
                        } else {
                            $anhUrl = $urlAnh;
                        }
                    }
                }
            }

            $hang = null;
            $hangHopLe = true;
            if ($hangTen !== null && $hangTen !== '') {
                $hang = $hangTheoTen->get(mb_strtolower($hangTen));
                if (! $hang) {
                    $loi[] = "Hãng '{$hangTen}' không tồn tại.";
                    $hangHopLe = false;
                } elseif (! $hang->kich_hoat) {
                    $loi[] = "Hãng '{$hangTen}' đang ở trạng thái ngừng — không gán được.";
                    $hangHopLe = false;
                }
            }

            // Barcode trung SP khac (chi khi co gia tri import)
            if ($barcode !== null && $barcode !== '' && isset($barcodeDaDung[mb_strtolower($barcode)])) {
                $chu = $barcodeDaDung[mb_strtolower($barcode)];
                if ($spHienCo === null || $spHienCo->id !== $chu) {
                    $loi[] = "Barcode '{$barcode}' đã thuộc sản phẩm khác.";
                }
            }

            // Gia tri mac dinh cho TAO MOI (da validate o tren)
            if ($laTaoMoi) {
                $duLieuMoi = [
                    'ten_sanpham' => $ten,
                    'barcode' => $barcode,
                    'category_id' => $loai?->id,
                    'brand_id' => $hangHopLe ? $hang?->id : null,
                    'don_vi' => $donVi,
                    'gia_ban' => $giaBan,
                ];
            } else {
                // CAP NHAT: chi truong co gia tri moi duoc dua vao plan;
                // trong/vang = giu nguyen gia tri hien tai.
                $duLieuMoi = [];
                if ($ten !== null) {
                    $duLieuMoi['ten_sanpham'] = $ten;
                }
                if ($barcode !== null) {
                    // o trong = giu barcode cu (tranh xoa nham)
                    $duLieuMoi['barcode'] = $barcode !== '' ? $barcode : $spHienCo->barcode;
                }
                if ($loai !== null) {
                    $duLieuMoi['category_id'] = $loai->id;
                }
                if ($hangTen !== null) {
                    // hang trong -> giu hang cu; co gia tri -> hang moi (da validate)
                    if ($hangTen !== '') {
                        $duLieuMoi['brand_id'] = $hangHopLe ? $hang?->id : $spHienCo->brand_id;
                    }
                }
                if ($donVi !== null) {
                    $duLieuMoi['don_vi'] = $donVi;
                }
                if ($giaBan !== null) {
                    $duLieuMoi['gia_ban'] = $giaBan;
                }
            }

            if ($canhBaoAnhNoiBo !== null) {
                $canhBao[] = $canhBaoAnhNoiBo;
            }

            if ($loi !== []) {
                foreach ($loi as $l) {
                    $errors[] = "Dòng {$line}: {$l}";
                }
                $rows[] = [
                    'line' => $line, 'action' => 'error', 'sku' => $sku,
                    'ten_sanpham' => $ten ?? '', 'changes' => [], 'warnings' => [],
                    'note' => implode(' ', $loi),
                ];
                continue;
            }

            $tenHienThi = $ten ?? (string) $spHienCo?->ten_sanpham;

            if ($laTaoMoi) {
                $changes = [];
                foreach ($duLieuMoi as $field => $new) {
                    $changes[] = ['field' => $field, 'old' => null, 'new' => $new];
                }
                if ($anhUrl !== null || $anhNoiBo !== null) {
                    $changes[] = ['field' => 'hinh_anh_url', 'old' => null, 'new' => $anhUrlDayDu];
                }
                $canhBao[] = 'Sẽ tạo ở trạng thái NGỪNG BÁN, tồn 0, giá nhập 0 — cần nhập kho rồi bật \'Đang bán\'.';
                if (($duLieuMoi['brand_id'] ?? null) === null) {
                    $canhBao[] = 'Không gán hãng.';
                }
                $rows[] = [
                    'line' => $line, 'action' => 'create', 'product_id' => null,
                    'sku' => $sku, 'ten_sanpham' => $tenHienThi,
                    'changes' => $changes, 'warnings' => $canhBao, 'note' => '',
                ];
                $plan[] = ['type' => 'create', 'line' => $line, 'input' => $f, 'data' => $duLieuMoi,
                    'product_id' => null, 'anh_url' => $anhUrl, 'anh_noi_bo' => $anhNoiBo,
                    'anh_nguon' => $anhUrlDayDu !== null ? $this->anh->chuanHoaUrlLuu($anhUrlDayDu) : null];
                continue;
            }

            // --- Cap nhat: diff chi tren truong duoc import ---
            $changes = [];
            $mapField = [
                'ten_sanpham' => 'ten_sanpham',
                'barcode' => 'barcode',
                'category_id' => 'category_id',
                'brand_id' => 'brand_id',
                'don_vi' => 'don_vi',
                'gia_ban' => 'gia_ban',
            ];
            foreach ($duLieuMoi as $field => $new) {
                $cot = $mapField[$field] ?? $field;
                $old = $spHienCo->{$cot};
                $giong = ($old === null && $new === null)
                    || ($old !== null && $new !== null && (string) $old === (string) $new);
                if (! $giong) {
                    $changes[] = ['field' => $field, 'old' => $old, 'new' => $new];
                }
            }

            // Anh URL/noi bo: chi tinh la thay doi khi khac nguon/path hien tai
            $khoaAnhMoi = $anhUrl !== null ? 'url:'.$this->anh->chuanHoaUrlLuu($anhUrl)
                : ($anhNoiBo !== null ? 'path:'.$anhNoiBo : null);
            $khoaAnhCu = $nguonAnhCu !== null ? 'url:'.$nguonAnhCu
                : (is_string($spHienCo->hinh_anh) && $spHienCo->hinh_anh !== '' && ! str_starts_with($spHienCo->hinh_anh, 'http')
                    ? 'path:'.$spHienCo->hinh_anh : null);
            $anhThayDoi = $khoaAnhMoi !== null && $khoaAnhMoi !== $khoaAnhCu;
            if ($anhThayDoi) {
                $changes[] = ['field' => 'hinh_anh_url', 'old' => $nguonAnhCu ?? $spHienCo->hinh_anh, 'new' => $anhUrlDayDu];
            }

            if ($sku !== (string) $spHienCo->sku) {
                $canhBao[] = "SKU trong file '{$sku}' khác hoa/thường với '{$spHienCo->sku}' trong hệ thống — giữ nguyên SKU hiện có.";
            }

            if (isset($duLieuMoi['gia_ban'])) {
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
            }

            if ($changes === []) {
                $action = 'skip';
                $note = 'Không có thay đổi.';
            } else {
                $action = 'update';
                $note = '';
            }

            $rows[] = [
                'line' => $line, 'action' => $action, 'product_id' => $spHienCo->id,
                'sku' => $sku, 'ten_sanpham' => $tenHienThi,
                'changes' => $changes, 'warnings' => $canhBao, 'note' => $note,
            ];
            if ($action === 'update') {
                $plan[] = ['type' => 'update', 'line' => $line, 'input' => $f, 'data' => $duLieuMoi,
                    'product_id' => $spHienCo->id, 'anh_url' => $anhThayDoi ? $anhUrl : null,
                    'anh_noi_bo' => $anhThayDoi ? $anhNoiBo : null,
                    'anh_nguon' => $anhThayDoi && $anhUrlDayDu !== null ? $this->anh->chuanHoaUrlLuu($anhUrlDayDu) : null];
            }
        }

        // Gioi han anh moi luot xac nhan (chi URL ngoai — anh noi bo khong tai mang)
        $soAnh = count(array_filter($plan, fn ($p) => ($p['anh_url'] ?? null) !== null));
        if ($soAnh > (int) config('anh_url.gioi_han.anh_moi_luot', 20)) {
            $errors[] = "File yêu cầu tải {$soAnh} ảnh — tối đa ".config('anh_url.gioi_han.anh_moi_luot', 20).' ảnh mỗi lần nhập.';
        }

        $counts = [
            'create' => count(array_filter($rows, fn ($r) => $r['action'] === 'create')),
            'update' => count(array_filter($rows, fn ($r) => $r['action'] === 'update')),
            'skip' => count(array_filter($rows, fn ($r) => $r['action'] === 'skip')),
            'error' => count(array_filter($rows, fn ($r) => $r['action'] === 'error')),
        ];

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

    /** Ghi ke hoach trong 1 transaction. Chi goi sau khi da xac nhan plan_hash. */
    public function ghiKeHoach(array $plan): array
    {
        $daTao = 0;
        $daCapNhat = 0;
        $now = now();
        $coCotAnh = $this->coCotAnh();

        // --- Anh noi bo (APP_URL/storage/...) khong tai mang: kiem file ton tai ---
        $anhTheoDong = [];   // line => ket qua anh (ok/duong_dan/sha256/loi/url/nguon_luu)
        foreach ($plan as $item) {
            $path = $item['anh_noi_bo'] ?? null;
            if (! is_string($path) || $path === '') {
                continue;
            }
            if (Storage::disk('public')->exists($path)) {
                $diaThat = Storage::disk('public')->path($path);
                $anhTheoDong[$item['line']] = [
                    'ok' => true,
                    'duong_dan' => $path,
                    'sha256' => is_file($diaThat) ? hash_file('sha256', $diaThat) ?: null : null,
                    'loi' => null,
                    'url' => $item['anh_nguon'] ?? '',
                    'nguon_luu' => $item['anh_nguon'] ?? null,
                ];
            } else {
                $anhTheoDong[$item['line']] = [
                    'ok' => false, 'duong_dan' => null, 'sha256' => null,
                    'loi' => 'Ảnh nội bộ không tồn tại trên máy chủ (storage).',
                    'url' => '', 'nguon_luu' => null,
                ];
            }
        }

        // --- Tai anh ngoai TRUOC transaction (IO mang, khong giu lock DB) ---
        // Loi mot anh KHONG huy dong: san pham van duoc ghi, anh loi ghi vao anh_loi.
        // Ngan sach thoi gian CHUNG cho ca luot tai anh (mac dinh 60s,
        // config anh_url.gioi_han.ngan_sach_luot) — 20 anh x 15s co the vuot
        // max_execution_time web; het ngan sach -> anh con lai bo qua + canh bao.
        $nganSachLuot = (int) config('anh_url.gioi_han.ngan_sach_luot', 60);
        $mocNganSach = microtime(true);
        $cacheUrl = [];      // url => ket qua (tranh tai trung trong file)
        foreach ($plan as $item) {
            $url = $item['anh_url'] ?? null;
            if (! is_string($url) || trim($url) === '') {
                continue;
            }
            if (isset($anhTheoDong[$item['line']])) {
                continue; // anh noi bo da xu ly
            }
            $url = trim($url);
            if (! isset($cacheUrl[$url])) {
                $conLai = $nganSachLuot - (int) round(microtime(true) - $mocNganSach);
                if ($conLai <= 0) {
                    $cacheUrl[$url] = [
                        'ok' => false, 'duong_dan' => null, 'sha256' => null,
                        'loi' => 'Hết ngân sách thời gian tải ảnh của lượt nhập — ảnh bị bỏ qua.',
                    ];
                } else {
                    $cacheUrl[$url] = $this->anh->taiVe($url, $conLai);
                }
            }
            $anhTheoDong[$item['line']] = $cacheUrl[$url] + [
                'url' => $url,
                'nguon_luu' => $item['anh_nguon'] ?? $this->anh->chuanHoaUrlLuu($url),
            ];
        }

        $anhThanhCong = 0;
        $anhLoi = [];
        $tepCuCanXoa = []; // duong dan file cuc bo se xoa SAU commit

        DB::transaction(function () use ($plan, $anhTheoDong, $coCotAnh, $now, &$daTao, &$daCapNhat, &$anhThanhCong, &$anhLoi, &$tepCuCanXoa): void {
            foreach ($plan as $item) {
                $anh = $anhTheoDong[$item['line']] ?? null;
                $anhOk = $anh !== null && ($anh['ok'] ?? false);
                if ($anh !== null) {
                    if ($anhOk) {
                        $anhThanhCong++;
                    } else {
                        $anhLoi[$item['line']] = $anh['loi'] ?? 'Tải ảnh thất bại.';
                    }
                }

                if ($item['type'] === 'create') {
                    $row = [
                        'category_id' => $item['data']['category_id'],
                        'sku' => $item['input']['sku'] ?? '',
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
                    ];
                    if ($coCotAnh) {
                        $row['hinh_anh_nguon'] = null;
                        $row['hinh_anh_sha256'] = null;
                        $row['hinh_anh_tai_luc'] = null;
                        if ($anhOk) {
                            $row['hinh_anh'] = $anh['duong_dan'];
                            $row['hinh_anh_nguon'] = $anh['nguon_luu'] ?? $anh['url'];
                            $row['hinh_anh_sha256'] = $anh['sha256'];
                            $row['hinh_anh_tai_luc'] = $now;
                        }
                    }
                    DB::table('products')->insert($row);
                    $daTao++;
                } else {
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
                    if ($anhOk && $coCotAnh) {
                        // Anh moi khac file cu -> danh dau xoa file cu sau commit
                        if (is_string($sp->hinh_anh) && $sp->hinh_anh !== '' && $sp->hinh_anh !== $anh['duong_dan']) {
                            $tepCuCanXoa[] = $sp->hinh_anh;
                        }
                        $capNhat['hinh_anh'] = $anh['duong_dan'];
                        $capNhat['hinh_anh_nguon'] = $anh['nguon_luu'] ?? $anh['url'];
                        $capNhat['hinh_anh_sha256'] = $anh['sha256'];
                        $capNhat['hinh_anh_tai_luc'] = $now;
                    }
                    if ($capNhat !== []) {
                        $capNhat['updated_at'] = $now;
                        DB::table('products')->where('id', $item['product_id'])->update($capNhat);
                        $daCapNhat++;
                    }
                }
            }
        });

        // Xoa file anh cu SAU commit (chi file cuc bo do he thong tao — helper tu loc)
        foreach (array_unique($tepCuCanXoa) as $tepCu) {
            TepAnhSanPham::xoaNeuLaTepCucBo($tepCu);
        }

        return ['tao_moi' => $daTao, 'cap_nhat' => $daCapNhat, 'anh_thanh_cong' => $anhThanhCong, 'anh_loi' => $anhLoi];
    }
}
