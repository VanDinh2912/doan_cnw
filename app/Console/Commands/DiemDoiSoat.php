<?php

namespace App\Console\Commands;

use App\Services\DiemService;
use App\Support\KiemTraSchema;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Đối soát sổ cái điểm (CHỈ ĐỌC): so SUM(diem) của sổ với
 * customers.diem_tichluy, in danh sách khách lệch. Không sửa gì.
 * An toàn chạy trên prod; khi bảng sổ chưa tồn tại thì báo rõ và thoát.
 */
class DiemDoiSoat extends Command
{
    protected $signature = 'diem:doi-soat';
    protected $description = 'Đối soát sổ cái điểm tích lũy với số dư customers (chỉ đọc)';

    public function handle(): int
    {
        if (! DiemService::sanSang() || ! KiemTraSchema::coBang('diem_bien_dong')) {
            $this->warn('Bảng diem_bien_dong chưa tồn tại — chưa thể đối soát.');
            return self::SUCCESS;
        }

        // Sổ theo khách: tổng biến động và số dư sau dòng mới nhất.
        $soTheoKhach = DB::table('diem_bien_dong')
            ->select('customer_id')
            ->selectRaw('SUM(diem) AS tong_so')
            ->selectRaw('MAX(id) AS dong_moi_nhat')
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->get()
            ->keyBy('customer_id');

        $soDuMoiNhat = DB::table('diem_bien_dong as d1')
            ->joinSub(
                DB::table('diem_bien_dong')
                    ->select('customer_id', DB::raw('MAX(id) AS max_id'))
                    ->whereNotNull('customer_id')
                    ->groupBy('customer_id'),
                'm',
                fn ($j) => $j->on('d1.id', '=', 'm.max_id')
            )
            ->whereNotNull('d1.customer_id')
            ->pluck('d1.so_du_sau', 'd1.customer_id');

        $khachLech = [];
        $tongKhop = 0;

        $khachHangs = DB::table('customers')->get(['id', 'diem_tichluy']);
        foreach ($khachHangs as $kh) {
            $so = $soTheoKhach->get($kh->id);
            $tongSo = (int) ($so->tong_so ?? 0);
            $soDuSo = (int) ($soDuMoiNhat[$kh->id] ?? 0);
            $soDuThat = (int) $kh->diem_tichluy;

            // Khách chưa có dòng sổ: tổng sổ = 0; chỉ lệch nếu số dư > 0
            // (chưa chạy diem:khoi-tao-so) — vẫn báo để nhìn thấy.
            if ($soDuSo === $soDuThat && $tongSo === $soDuThat) {
                $tongKhop++;
                continue;
            }
            if ($so === null && $soDuThat === 0) {
                $tongKhop++;
                continue;
            }
            $khachLech[] = [
                'id' => $kh->id,
                'so_du' => $soDuThat,
                'tong_so' => $tongSo,
                'chenh_lech' => $soDuThat - $tongSo,
            ];
        }

        $this->info('Khách khớp: '.$tongKhop.' | lệch: '.count($khachLech));

        if ($khachLech !== []) {
            $this->table(
                ['customer_id', 'so_du', 'tong_so', 'chenh_lech'],
                array_map(fn ($r) => [$r['id'], $r['so_du'], $r['tong_so'], $r['chenh_lech']], $khachLech)
            );
        }

        return self::SUCCESS;
    }
}
