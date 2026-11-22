<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\YeuCauDuyet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * UNIQUE(khoa_trung) của yeu_cau_duyet trên MySQL bản sao — sqlite test chỉ
 * kiểm ở mức ứng dụng, ràng buộc thật phải kiểm trên InnoDB.
 * Chạy:
 *   DB_CONNECTION=mysql DB_DATABASE=doan_cnw_copy DB_USERNAME=root DB_PASSWORD=''
 *   php artisan test --filter=MaDuyetMysqlCopyTest
 * Yêu cầu bản sao đã migrate (bảng yeu_cau_duyet tồn tại); tự dọn dữ liệu test.
 */
class MaDuyetMysqlCopyTest extends TestCase
{
    private function chiChayTrenBanSaoMysql(): void
    {
        $ketNoi = DB::connection();
        $database = (string) $ketNoi->getDatabaseName();

        if ($ketNoi->getDriverName() !== 'mysql' || ! str_ends_with($database, '_copy')) {
            $this->markTestSkipped(
                'Cần MySQL bản sao: DB_CONNECTION=mysql DB_DATABASE=doan_cnw_copy php artisan test --filter=MaDuyetMysqlCopyTest'
            );
        }
        if (! Schema::hasTable('yeu_cau_duyet')) {
            $this->markTestSkipped('Bản sao chưa có bảng yeu_cau_duyet — chạy migrate trên bản sao trước.');
        }
    }

    public function test_khoa_trung_unique_tren_mysql(): void
    {
        $this->chiChayTrenBanSaoMysql();

        $nv = User::where('role', '!=', 'khachhang')->first()
            ?? User::create(['name' => 'T', 'email' => 'md-'.uniqid().'@t.local',
                'password' => Hash::make('Mk@12345'), 'role' => 'thungan']);

        $khoa = 'mdcopy-'.uniqid();
        $base = [
            'nhan_vien_id' => $nv->id, 'ten_route' => 'khachhang.xoa', 'method' => 'DELETE',
            'mo_ta_thao_tac' => 'mysql copy test', 'trang_thai' => YeuCauDuyet::TT_CHO_CAP,
            'khoa_trung' => $khoa, 'created_at' => now(),
        ];

        try {
            YeuCauDuyet::create($base);
            $this->expectException(\Illuminate\Database\QueryException::class);
            YeuCauDuyet::create($base);
        } finally {
            YeuCauDuyet::where('khoa_trung', $khoa)->delete();
        }
    }
}
