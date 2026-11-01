<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('sku', 50)->unique();
            $table->string('barcode', 50)->nullable()->unique();
            $table->string('ten_sanpham', 255);
            $table->string('don_vi', 20)->default('Cái');
            $table->decimal('gia_nhap', 12, 0)->default(0);
            $table->decimal('gia_ban', 12, 0)->default(0);
            $table->unsignedInteger('soluong')->default(0);
            $table->unsignedInteger('soluong_toithieu')->default(5);
            $table->string('hinh_anh')->nullable();
            $table->boolean('trang_thai')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
