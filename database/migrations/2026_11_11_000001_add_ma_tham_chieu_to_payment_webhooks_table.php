<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mã tham chiếu ngân hàng (API v2: reference_number, webhook: referenceCode)
     * dùng để đối chiếu hai nguồn mà không trộn id số của webhook với id UUID của API.
     * Guard hasColumn: chạy lại/DB đã có cột không lỗi.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('payment_webhooks', 'ma_tham_chieu')) {
            Schema::table('payment_webhooks', function (Blueprint $table): void {
                $table->string('ma_tham_chieu', 100)->nullable()->after('ma_giao_dich_ngoai');
                $table->index('ma_tham_chieu');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payment_webhooks', 'ma_tham_chieu')) {
            Schema::table('payment_webhooks', function (Blueprint $table): void {
                $table->dropIndex(['ma_tham_chieu']);
                $table->dropColumn('ma_tham_chieu');
            });
        }
    }
};
