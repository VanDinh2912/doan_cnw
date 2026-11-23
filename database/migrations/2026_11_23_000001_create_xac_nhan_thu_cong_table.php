<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * So tay cac lan XAC NHAN THANH TOAN THU CONG (thu ngan bam nut xac nhan
 * thay vi webhook SePay tu dong). Additive — khong sua invoices.
 * Moi hoa don toi da 1 dong (invoice_id unique, chong bam dup/webhook lap).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xac_nhan_thu_cong', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->unique()->constrained('invoices')->cascadeOnDelete();
            $table->unsignedBigInteger('so_tien');
            $table->string('bang_chung', 255);
            $table->unsignedBigInteger('nguoi_xac_nhan_id')->nullable();
            $table->unsignedBigInteger('yeu_cau_duyet_id')->nullable();
            $table->unsignedBigInteger('admin_duyet_id')->nullable();
            $table->timestamp('xac_nhan_luc');
            $table->unsignedBigInteger('soat_boi_id')->nullable();
            $table->timestamp('soat_luc')->nullable();
            $table->string('ghi_chu_soat', 500)->nullable();
            $table->timestamps();

            $table->index(['soat_luc', 'xac_nhan_luc']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xac_nhan_thu_cong');
    }
};
