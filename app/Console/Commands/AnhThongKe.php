<?php

namespace App\Console\Commands;

use App\Support\KiemTraSchema;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * GD1 — Thong ke anh san pham (read-only, an toan chay moi luc).
 * Bao cao: SP co anh / co nguon URL / anh noi bo; file trong storage
 * thu muc sanpham; file "mo coi" (khong SP nao tro toi).
 */
class AnhThongKe extends Command
{
    protected $signature = 'anh:thong-ke';

    protected $description = 'Thong ke anh san pham: anh tu URL, anh noi bo, file mo coi trong storage';

    public function handle(): int
    {
        if (! KiemTraSchema::coBang('products')) {
            $this->warn('Chua co bang products.');

            return self::SUCCESS;
        }

        $coCotAnh = KiemTraSchema::coCot('products', 'hinh_anh_nguon')
            && KiemTraSchema::coCot('products', 'hinh_anh_sha256')
            && KiemTraSchema::coCot('products', 'hinh_anh_tai_luc');

        $tong = (int) DB::table('products')->count();
        $coAnh = (int) DB::table('products')->whereNotNull('hinh_anh')->where('hinh_anh', '!=', '')->count();

        $this->info("Sản phẩm: {$tong} — có hình ảnh: {$coAnh}");

        if (! $coCotAnh) {
            $this->warn('products chưa có 3 cột metadata ảnh (hinh_anh_nguon/sha256/tai_luc) — chỉ thống kê tổng.');

            return self::SUCCESS;
        }

        $tuUrl = (int) DB::table('products')->whereNotNull('hinh_anh_nguon')->where('hinh_anh_nguon', '!=', '')->count();
        $coSha = (int) DB::table('products')->whereNotNull('hinh_anh_sha256')->where('hinh_anh_sha256', '!=', '')->count();
        $anhNgoai = (int) DB::table('products')
            ->whereNotNull('hinh_anh')->where('hinh_anh', '!=', '')
            ->where('hinh_anh', 'not like', 'http%')
            ->where('hinh_anh_nguon', null)->count();

        $this->line("  - ảnh có nguồn URL (hinh_anh_nguon): {$tuUrl}");
        $this->line("  - ảnh có sha256: {$coSha}");
        $this->line("  - ảnh cục bộ chưa ghi nguồn (upload tay cũ): {$anhNgoai}");

        // File trong storage/app/public/sanpham vs SP tro toi
        $files = [];
        $tongByte = 0;
        try {
            foreach (Storage::disk('public')->files('sanpham') as $f) {
                if (str_starts_with(basename($f), 'anh-')) {
                    $files[] = $f;
                    $tongByte += (int) Storage::disk('public')->size($f);
                }
            }
        } catch (\Throwable) {
            // disk loi -> bo qua phan file
        }
        $duocTro = DB::table('products')->whereIn('hinh_anh', $files)->count();
        $moCoiFiles = array_values(array_diff($files, DB::table('products')->whereIn('hinh_anh', $files)->pluck('hinh_anh')->all()));
        $moCoi = count($moCoiFiles);

        $this->line('  - file sanpham/anh-* trong storage: '.count($files).' ('.round($tongByte / 1048576, 2).' MB)'
            ." — được SP trỏ: {$duocTro}, mồ côi: {$moCoi}");
        foreach (array_slice($moCoiFiles, 0, 20) as $fMoCoi) {
            $size = 0;
            try {
                $size = (int) Storage::disk('public')->size($fMoCoi);
            } catch (\Throwable) {
            }
            $this->line('      · '.basename($fMoCoi).' ('.round($size / 1024, 1).' KB)');
        }
        if ($moCoi > 20) {
            $this->line("      … và ".($moCoi - 20).' file mồ côi khác');
        }
        $this->line('  (Gợi ý: có thể dọn file mồ côi bằng lệnh anh:don-file-mo-coi --dry-run khi được duyệt — hiện CHƯA triển khai.)');

        return self::SUCCESS;
    }
}
