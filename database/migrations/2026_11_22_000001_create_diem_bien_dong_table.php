<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diem_bien_dong', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('loai', 30);
            $table->integer('diem');
            $table->integer('so_du_sau');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('return_id')->nullable()->constrained('returns')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ly_do', 255)->nullable();
            // Khóa sự kiện chống ghi sổ trùng (webhook lặp, bấm đúp). NULL = không ràng buộc.
            $table->string('su_kien', 80)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['customer_id', 'created_at']);
            $table->index('invoice_id');
            $table->index('return_id');
            $table->unique('su_kien');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diem_bien_dong');
    }
};
