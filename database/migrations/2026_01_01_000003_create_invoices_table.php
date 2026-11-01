<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('ma_hoadon', 30)->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('tong_tien', 12, 0);
            $table->decimal('giam_gia_phantram', 5, 2)->default(0);
            $table->decimal('tien_giam', 12, 0)->default(0);
            $table->decimal('tong_thanh_toan', 12, 0);
            $table->decimal('tien_khach_dua', 12, 0)->default(0);
            $table->decimal('tien_thua', 12, 0)->default(0);
            $table->enum('phuong_thuc', ['tienmat', 'chuyenkhoan'])->default('tienmat');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
