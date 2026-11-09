<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_webhooks', function (Blueprint $table): void {
            $table->string('ly_do', 50)->nullable()->after('ket_qua');
            $table->string('ma_hoadon', 30)->nullable()->after('ly_do');
            $table->string('noi_dung', 255)->nullable()->after('ma_hoadon');
            $table->json('payload')->nullable()->after('noi_dung');
            $table->string('trang_thai_xu_ly', 20)->default('chua_xu_ly')->after('payload');
            $table->text('ghi_chu_xu_ly')->nullable()->after('trang_thai_xu_ly');
            $table->foreignId('nguoi_xu_ly_id')->nullable()->after('ghi_chu_xu_ly')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('thoi_gian_xu_ly')->nullable()->after('nguoi_xu_ly_id');

            $table->index(['trang_thai_xu_ly', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_webhooks', function (Blueprint $table): void {
            $table->dropIndex(['trang_thai_xu_ly', 'created_at']);
            $table->dropConstrainedForeignId('nguoi_xu_ly_id');
            $table->dropColumn([
                'ly_do', 'ma_hoadon', 'noi_dung', 'payload',
                'trang_thai_xu_ly', 'ghi_chu_xu_ly', 'thoi_gian_xu_ly',
            ]);
        });
    }
};
