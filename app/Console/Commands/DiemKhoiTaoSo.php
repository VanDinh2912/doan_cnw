<?php

namespace App\Console\Commands;

use App\Models\DiemBienDong;
use App\Models\KhachHang;
use App\Services\DiemService;
use App\Services\NhatKyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Khởi tạo số dư đầu kỳ trong sổ cái điểm.
 * Với mỗi khách có diem_tichluy > 0 mà chưa có dòng nào trong sổ,
 * tạo 1 dòng loai=so_du_dau_ky. Idempotent — chạy lại không nhân đôi.
 * Mặc định --dry-run; --apply mới ghi.
 */
class DiemKhoiTaoSo extends Command
{
    protected $signature = 'diem:khoi-tao-so {--apply : Ghi số dư đầu kỳ vào sổ (mặc định chỉ in)} {--dry-run : Chỉ in, không ghi (mặc định)}';
    protected $description = 'Tạo dòng số dư đầu kỳ cho khách đã có điểm trước khi bật sổ cái';

    public function handle(): int
    {
        if (! DiemService::sanSang()) {
            $this->warn('Bảng diem_bien_dong chưa tồn tại — chạy migration trước.');
            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');

        $daCoSo = DiemBienDong::where('loai', DiemService::LOAI_SO_DAU_KY)
            ->pluck('customer_id')
            ->flip();

        $khach = KhachHang::where('diem_tichluy', '>', 0)->get(['id', 'diem_tichluy']);
        $canTao = $khach->reject(fn ($k) => isset($daCoSo[$k->id]));

        $this->info('Khách có điểm: '.$khach->count().' | đã có số dư đầu kỳ: '.$daCoSo->count()
            .' | cần tạo: '.$canTao->count());

        if (! $apply) {
            $this->line('Chế độ dry-run — thêm --apply để ghi.');
            foreach ($canTao as $k) {
                $this->line("  customer#{$k->id}: {$k->diem_tichluy} điểm");
            }

            return self::SUCCESS;
        }

        $daGhi = 0;
        DB::transaction(function () use ($canTao, &$daGhi): void {
            foreach ($canTao as $k) {
                DiemService::ghi($k->id, DiemService::LOAI_SO_DAU_KY, (int) $k->diem_tichluy,
                    (int) $k->diem_tichluy, [
                        'ly_do' => 'Số dư đầu kỳ khi bắt đầu ghi sổ',
                        'su_kien' => 'so-du-dau-ky:'.$k->id,
                    ]);
                $daGhi++;
            }
        });

        // 1 dòng audit tổng kết (không ghi từng khách).
        NhatKyService::ghi(
            \Illuminate\Http\Request::create('/', 'GET'),
            'khoi_tao_so_diem', 'diem_bien_dong', null,
            'Khởi tạo số dư đầu kỳ sổ điểm: '.$daGhi.' khách',
            nguon: 'console'
        );

        $this->info('Đã ghi '.$daGhi.' dòng số dư đầu kỳ.');

        return self::SUCCESS;
    }
}
