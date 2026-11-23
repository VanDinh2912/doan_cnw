<?php

namespace App\Console\Commands;

use App\Models\DonHang;
use App\Models\XacNhanThuCong;
use App\Support\KiemTraSchema;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * M5 — Nhập lại các lần "Thu ngân xác nhận thủ công" đã ghi vào invoices.ghi_chu
 * trước khi có bảng xac_nhan_thu_cong.
 *
 * Mặc định --dry-run (chỉ đếm, không ghi). --apply ghi trong transaction,
 * idempotent (invoice_id unique), audit 1 dòng cho cả lượt chạy.
 *
 *   --id=2,18     : chỉ nhập các hóa đơn được chọn (bắt buộc khi muốn lọc)
 *   --thu         : đánh dấu "đơn thử" — bang_chung 'Đơn thử, không cần soát',
 *                   soat_luc=now() (không phải soát lại)
 */
class ThuCongKhoiTaoTuGhiChu extends Command
{
    protected $signature = 'thu-cong:khoi-tao-tu-ghi-chu
                            {--apply : Ghi thật (mặc định chỉ dry-run)}
                            {--id= : Danh sách id hóa đơn, phân cách bởi dấu phẩy}
                            {--thu : Đánh dấu đơn thử, đánh dấu đã soát luôn}';

    protected $description = 'Tạo dòng xac_nhan_thu_cong cho hóa đơn cũ có ghi_chu "Thu ngân xác nhận thủ công"';

    private const CAU_GHI_CHU = 'Thu ngân xác nhận thủ công';

    public function handle(): int
    {
        if (! KiemTraSchema::coBang('xac_nhan_thu_cong')) {
            $this->warn('Bảng xac_nhan_thu_cong chưa tồn tại — không có gì để nhập lại.');

            return self::SUCCESS;
        }

        $ids = $this->docIds();
        if ($ids === false) {
            return self::FAILURE;
        }

        $candidates = DonHang::query()
            ->where('trang_thai', 'da_thanh_toan')
            ->where('ghi_chu', 'like', '%'.self::CAU_GHI_CHU.'%')
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('xac_nhan_thu_cong')
                    ->whereColumn('xac_nhan_thu_cong.invoice_id', 'invoices.id');
            })
            ->orderBy('id')
            ->get(['id', 'ma_hoadon', 'tong_thanh_toan', 'thoi_gian_thanh_toan', 'created_at']);

        if ($candidates->isEmpty()) {
            $this->info('Không có hóa đơn nào cần nhập lại.'.($ids !== null ? ' (trong phạm vi --id đã chọn)' : ''));

            return self::SUCCESS;
        }

        $laThu = (bool) $this->option('thu');
        $this->info(($this->option('apply') ? 'APPLY' : 'DRY-RUN').($laThu ? ' [ĐƠN THỬ]' : '').': '.$candidates->count().' hóa đơn cần tạo dòng xac_nhan_thu_cong:');
        foreach ($candidates as $hd) {
            $this->line(sprintf(
                '  #%d %s — %s đ — thanh toán %s',
                $hd->id, $hd->ma_hoadon,
                number_format((int) $hd->tong_thanh_toan, 0, ',', '.'),
                ($hd->thoi_gian_thanh_toan ?? $hd->created_at)?->format('d/m/Y H:i') ?? '—'
            ));
        }

        if (! $this->option('apply')) {
            $this->warn('Chạy lại với --apply để ghi thật.');

            return self::SUCCESS;
        }

        $daTao = 0;
        DB::transaction(function () use ($candidates, $laThu, &$daTao): void {
            foreach ($candidates as $hd) {
                try {
                    XacNhanThuCong::create([
                        'invoice_id' => $hd->id,
                        'so_tien' => (int) $hd->tong_thanh_toan,
                        'bang_chung' => $laThu ? 'Đơn thử, không cần soát' : 'Nhập lại từ ghi chú cũ',
                        'nguoi_xac_nhan_id' => null,
                        'xac_nhan_luc' => $hd->thoi_gian_thanh_toan ?? $hd->created_at ?? now(),
                        'soat_luc' => $laThu ? now() : null,
                        'ghi_chu_soat' => $laThu ? 'Đơn thử — không cần đối chiếu sao kê' : null,
                    ]);
                    $daTao++;
                } catch (\Illuminate\Database\QueryException $e) {
                    if (! str_contains($e->getMessage(), 'Duplicate')) {
                        throw $e;
                    }
                }
            }
        });

        if (KiemTraSchema::coBang('activity_logs')) {
            \App\Models\NhatKyHoatDong::create([
                'user_id' => null,
                'hanh_dong' => 'xac_nhan_thu_cong_khoi_tao',
                'doi_tuong' => 'xac_nhan_thu_cong',
                'doi_tuong_loai' => 'xac_nhan_thu_cong',
                'doi_tuong_id' => null,
                'mo_ta' => 'Nhập lại '.$daTao.' xác nhận thủ công từ ghi chú hóa đơn cũ'
                    .($laThu ? ' [đơn thử]' : '').($ids !== null ? ' --id='.implode(',', $ids) : ''),
                'nguon' => 'cli',
            ]);
        }

        $this->info('Đã tạo '.$daTao.' dòng.');

        return self::SUCCESS;
    }

    /** @return array<int,int>|null|false null = không lọc; false = input sai */
    private function docIds(): array|null|false
    {
        $raw = trim((string) $this->option('id'));
        if ($raw === '') {
            return null;
        }
        $ids = array_filter(array_map('intval', explode(',', $raw)), fn ($i) => $i > 0);
        if ($ids === [] || count($ids) !== count(array_unique($ids))) {
            $this->error('--id phải là danh sách id dương, phân cách bởi dấu phẩy.');

            return false;
        }

        return array_values($ids);
    }
}
