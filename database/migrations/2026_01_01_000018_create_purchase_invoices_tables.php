<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('ma_phieu', 30)->unique();
            $table->string('so_hoadon', 30);
            $table->string('ky_hieu', 20);
            $table->string('mau_so', 10)->nullable();
            $table->date('ngay_hoadon');
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('trang_thai', ['nhap_tam', 'da_nhap_kho', 'da_huy'])->default('nhap_tam');
            $table->foreignId('nguoi_nhap_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('ngay_nhap_kho')->nullable();
            $table->enum('hinh_thuc_thanh_toan', ['tienmat', 'chuyenkhoan', 'congno'])->default('chuyenkhoan');
            $table->decimal('tong_tien_hang', 12, 0)->default(0);
            $table->decimal('thue_suat', 5, 2)->default(8);
            $table->decimal('tien_thue', 12, 0)->default(0);
            $table->decimal('tong_thanh_toan', 12, 0)->default(0);
            $table->string('file_goc')->nullable();
            $table->string('ghi_chu')->nullable();
            $table->timestamps();
            $table->unique(['supplier_id', 'ky_hieu', 'so_hoadon']);
        });
        Schema::create('purchase_invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('stt');
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->string('ten_hang', 255);
            $table->string('don_vi', 20);
            $table->unsignedInteger('soluong');
            $table->decimal('don_gia', 12, 0);
            $table->decimal('thanh_tien', 12, 0);
            $table->foreignId('category_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->decimal('gia_ban_de_xuat', 12, 0)->nullable();
            $table->timestamps();
            $table->unique(['purchase_invoice_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_items');
        Schema::dropIfExists('purchase_invoices');
    }
};
