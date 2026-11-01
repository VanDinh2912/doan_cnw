<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocktakes', function (Blueprint $table): void {
            $table->id();
            $table->string('ma_phieu', 30)->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('trang_thai', ['ban_nhap', 'dang_kiem', 'cho_duyet', 'da_duyet', 'da_huy'])->default('ban_nhap');
            $table->string('ghi_chu')->nullable();
            $table->timestamps();
        });
        Schema::create('stocktake_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stocktake_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedInteger('soluong_he_thong');
            $table->unsignedInteger('soluong_thuc')->nullable();
            $table->integer('chenh_lech')->nullable();
            $table->dateTime('thoi_diem_dem')->nullable();
            $table->timestamps();
            $table->unique(['stocktake_id', 'product_id']);
        });
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->foreignId('stocktake_id')->nullable()->after('return_id')
                ->constrained('stocktakes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropForeign(['stocktake_id']);
            $table->dropColumn('stocktake_id');
        });
        Schema::dropIfExists('stocktake_items');
        Schema::dropIfExists('stocktakes');
    }
};
