<?php

namespace App\Console\Commands;

use App\Models\KhachHang;
use App\Rules\SoDienThoaiHopLe;
use App\Support\CheThongTin;
use App\Support\SoDienThoai;
use Illuminate\Console\Command;

class ChuanHoaSdtKhachHang extends Command
{
    protected $signature = 'khachhang:chuan-hoa-sdt {--ap-dung : Áp dụng chuẩn hóa (mặc định chỉ liệt kê, không ghi)}';

    protected $description = 'Liệt kê SĐT khách cần chuẩn hóa hoặc sai định dạng; mặc định DRY-RUN, chỉ ghi khi có --ap-dung';

    public function handle(): int
    {
        $apDung = (bool) $this->option('ap-dung');
        $canChuanHoa = 0;
        $saiDinhDang = 0;
        $hopLe = 0;
        $daSua = 0;
        $xungDot = 0;

        if (! $apDung) {
            $this->line('DRY-RUN — không ghi thay đổi. Thêm --ap-dung để áp dụng.');
        }

        KhachHang::query()->chunkById(200, function ($khachs) use ($apDung, &$canChuanHoa, &$saiDinhDang, &$hopLe, &$daSua, &$xungDot): void {
            foreach ($khachs as $khach) {
                $hienTai = (string) $khach->so_dienthoai;
                $chuan = SoDienThoai::chuanHoa($hienTai);

                if (! preg_match(SoDienThoaiHopLe::REGEX, $chuan)) {
                    $saiDinhDang++;
                    $this->line(sprintf('[%d] %s | %s | sai định dạng (giữ nguyên, cần người thật sửa)', $khach->id, $khach->ho_ten, CheThongTin::soDienThoai($hienTai)));

                    continue;
                }

                if ($chuan === $hienTai) {
                    $hopLe++;

                    continue;
                }

                $canChuanHoa++;
                $this->line(sprintf('[%d] %s | %s → %s | cần chuẩn hóa', $khach->id, $khach->ho_ten, CheThongTin::soDienThoai($hienTai), CheThongTin::soDienThoai($chuan)));

                if (! $apDung) {
                    continue;
                }

                if (KhachHang::where('so_dienthoai', $chuan)->where('id', '!=', $khach->id)->exists()) {
                    $xungDot++;
                    $this->warn(sprintf('[%d] bỏ qua: xung đột unique với bản ghi khác.', $khach->id));

                    continue;
                }

                $khach->so_dienthoai = $chuan;
                $khach->save();
                $daSua++;
            }
        });

        $this->line(sprintf('Tổng: %d khách | Cần chuẩn hóa: %d | Sai định dạng: %d | Hợp lệ: %d', $canChuanHoa + $saiDinhDang + $hopLe, $canChuanHoa, $saiDinhDang, $hopLe));

        if ($apDung) {
            $this->line(sprintf('Đã chuẩn hóa: %d bản ghi. Bỏ qua xung đột unique: %d bản ghi.', $daSua, $xungDot));
        }

        return self::SUCCESS;
    }
}