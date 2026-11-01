<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable()->after('user_id')->constrained('customers')->nullOnDelete();
            $table->unsignedInteger('diem_su_dung')->default(0);
            $table->decimal('tien_giam_diem', 12, 0)->default(0);
            $table->unsignedInteger('diem_tichluy_nhan')->default(0);
            $table->enum('trang_thai', ['cho_thanh_toan', 'da_thanh_toan', 'da_huy'])->default('da_thanh_toan');
            $table->string('ma_giaodich', 100)->nullable()->unique();
            $table->timestamp('thoi_gian_thanh_toan')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign(['customer_id']);
            $table->dropUnique(['ma_giaodich']);
            $table->dropColumn([
                'customer_id', 'diem_su_dung', 'tien_giam_diem', 'diem_tichluy_nhan',
                'trang_thai', 'ma_giaodich', 'thoi_gian_thanh_toan',
            ]);
        });
    }
};
