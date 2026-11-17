<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email khách để lại khi đặt hàng online (tùy chọn). Nullable: đơn cũ,
 * đơn POS và guest không nhập email đều không bị ảnh hưởng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('email_dat', 150)->nullable()->after('ghi_chu_khach');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('email_dat');
        });
    }
};
