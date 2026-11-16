<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // P3 HM4: đánh dấu đơn đã thanh toán bị hủy cần hoàn tiền thủ công.
            // null = không liên quan; 1 = CẦN HOÀN TIỀN THỦ CÔNG; 0 = đã đánh dấu hoàn.
            if (! Schema::hasColumn('invoices', 'can_hoan_tien')) {
                $table->boolean('can_hoan_tien')->nullable()->after('trang_thai_giao');
            }
            // Ghi chú nội bộ (lý do hủy, ghi chú khi đánh dấu đã hoàn tiền).
            if (! Schema::hasColumn('invoices', 'ghi_chu_noi_bo')) {
                $table->string('ghi_chu_noi_bo', 255)->nullable()->after('can_hoan_tien');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'ghi_chu_noi_bo')) {
                $table->dropColumn('ghi_chu_noi_bo');
            }
            if (Schema::hasColumn('invoices', 'can_hoan_tien')) {
                $table->dropColumn('can_hoan_tien');
            }
        });
    }
};
