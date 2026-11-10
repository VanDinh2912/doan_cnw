<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DB thật của cửa hàng đang dùng MyISAM: mọi DB::transaction, lockForUpdate
 * và rollback đều không có tác dụng, nên các luồng tiền/tồn kho không còn
 * được bảo vệ nguyên tử. Migration này chuyển toàn bộ bảng sang InnoDB.
 *
 * Chỉ tác động MySQL/MariaDB; SQLite (test) bỏ qua. Hạ cấp không được hỗ trợ
 * vì quay lại MyISAM sẽ làm mất tính nguyên tử lần nữa.
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection();

        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach (Schema::getTables() as $thongTin) {
            $table = (string) ($thongTin['name'] ?? '');

            if ($table === '' || ! preg_match('/^[A-Za-z0-9_]+$/', $table)) {
                continue;
            }

            if (strcasecmp((string) ($thongTin['engine'] ?? ''), 'InnoDB') === 0) {
                continue;
            }

            $connection->statement("ALTER TABLE `{$table}` ENGINE=InnoDB");
        }
    }

    public function down(): void
    {
        // Cố ý không hạ cấp: MyISAM sẽ vô hiệu hóa transaction và khóa dòng.
    }
};
