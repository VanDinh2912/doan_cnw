<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('kenh', 10)->default('pos')->index()->after('id');
            $table->string('ma_tra_cuu', 20)->nullable()->unique()->after('kenh');
            $table->string('ten_khach_dat', 100)->nullable()->after('ma_tra_cuu');
            $table->string('so_dienthoai_dat', 15)->nullable()->index()->after('ten_khach_dat');
            $table->string('ghi_chu_khach', 500)->nullable()->after('so_dienthoai_dat');
            $table->timestamp('han_giu')->nullable()->after('ghi_chu_khach');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();

            // SQLite dựng lại bảng khi ->change() và đánh mất CHECK của enum;
            // khai báo lại enum y nguyên để giữ ràng buộc. MySQL không đụng enum.
            if (DB::getDriverName() === 'sqlite') {
                $table->enum('trang_thai', ['cho_thanh_toan', 'da_thanh_toan', 'da_huy'])->default('da_thanh_toan')->change();
                $table->enum('phuong_thuc', ['tienmat', 'chuyenkhoan'])->default('tienmat')->change();
            }
        });
    }

    public function down(): void
    {
        $soDonNull = DB::table('invoices')->whereNull('user_id')->count();
        if ($soDonNull > 0) {
            throw new RuntimeException("Không thể rollback: còn {$soDonNull} hóa đơn có user_id NULL (đơn online). Hãy xử lý các đơn này trước khi rollback.");
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['kenh']);
            $table->dropUnique(['ma_tra_cuu']);
            $table->dropIndex(['so_dienthoai_dat']);
            $table->dropColumn(['kenh', 'ma_tra_cuu', 'ten_khach_dat', 'so_dienthoai_dat', 'ghi_chu_khach', 'han_giu']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();

            if (DB::getDriverName() === 'sqlite') {
                $table->enum('trang_thai', ['cho_thanh_toan', 'da_thanh_toan', 'da_huy'])->default('da_thanh_toan')->change();
                $table->enum('phuong_thuc', ['tienmat', 'chuyenkhoan'])->default('tienmat')->change();
            }
        });
    }
};