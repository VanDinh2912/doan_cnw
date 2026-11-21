<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Bằng chứng "khách đã đến nhận hàng" cho đơn pickup online.
            // Additive + nullable: code mới phải guard bằng KiemTraSchema::coCot
            // vì DB thật có thể chưa áp migration này.
            if (! Schema::hasColumn('invoices', 'nhan_hang_luc')) {
                $table->timestamp('nhan_hang_luc')->nullable()->after('trang_thai_giao');
            }
            if (! Schema::hasColumn('invoices', 'nhan_hang_boi')) {
                // Chỉ index, KHÔNG FK: trên sqlite, ->constrained() buộc Laravel
                // dựng lại bảng và làm mất CHECK constraint của enum trang_thai.
                $table->foreignId('nhan_hang_boi')->nullable()->index()->after('nhan_hang_luc');
            }
            if (! Schema::hasColumn('invoices', 'ghi_chu_nhan')) {
                $table->string('ghi_chu_nhan', 255)->nullable()->after('nhan_hang_boi');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'nhan_hang_boi')) {
                $table->dropIndex(['nhan_hang_boi']);
                $table->dropColumn('nhan_hang_boi');
            }
            foreach (['nhan_hang_luc', 'ghi_chu_nhan'] as $cot) {
                if (Schema::hasColumn('invoices', $cot)) {
                    $table->dropColumn($cot);
                }
            }
        });
    }
};
