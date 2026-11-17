<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Khóa hồ sơ khách hàng (customers): khoa_den = thời điểm khóa (null = đang
 * hoạt động), ly_do_khoa = lý do bắt buộc khi khóa. Additive, nullable —
 * không ảnh hưởng đơn hàng, công nợ hay dữ liệu hiện có.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->timestamp('khoa_den')->nullable()->after('diem_tichluy');
            $table->string('ly_do_khoa', 255)->nullable()->after('khoa_den');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn(['khoa_den', 'ly_do_khoa']);
        });
    }
};
