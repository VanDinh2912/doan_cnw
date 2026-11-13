<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->change();

            // SQLite dựng lại bảng khi ->change() và đánh mất CHECK của enum;
            // khai báo lại enum loai ĐẦY ĐỦ (kể cả trahang/trahang_hong do migration
            // returns thêm trên MySQL) để giữ ràng buộc khớp MySQL. MySQL không đụng enum.
            if (DB::getDriverName() === 'sqlite') {
                $table->enum('loai', ['nhap', 'ban', 'dieuchinh', 'trahang', 'trahang_hong'])->change();
            }
        });
    }

    public function down(): void
    {
        $soNull = DB::table('stock_movements')->whereNull('user_id')->count();
        if ($soNull > 0) {
            throw new RuntimeException("Không thể rollback: còn {$soNull} biến động kho có user_id NULL (đơn online). Hãy xử lý các bản ghi này trước khi rollback.");
        }

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();

            if (DB::getDriverName() === 'sqlite') {
                $table->enum('loai', ['nhap', 'ban', 'dieuchinh', 'trahang', 'trahang_hong'])->change();
            }
        });
    }
};