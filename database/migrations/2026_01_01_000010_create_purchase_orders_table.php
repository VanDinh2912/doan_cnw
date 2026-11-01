<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('ma_don', 30)->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('nguoi_nhan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('trang_thai', ['cho_duyet', 'cho_giao', 'dang_giao', 'da_nhan', 'da_huy'])->default('cho_duyet');
            $table->decimal('tong_tien', 15, 2)->default(0);
            $table->date('ngay_dukien')->nullable();
            $table->dateTime('ngay_nhan')->nullable();
            $table->text('ghi_chu')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
