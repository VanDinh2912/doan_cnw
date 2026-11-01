<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('returns', function (Blueprint $table): void {
            $table->id();
            $table->string('ma_phieu', 30)->unique();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->decimal('tong_hoan', 12, 0);
            $table->unsignedInteger('diem_thu_hoi')->default(0);
            $table->enum('hinh_thuc_hoan', ['tienmat', 'chuyenkhoan', 'doi_hang'])->default('tienmat');
            $table->enum('trang_thai', ['cho_duyet', 'hoan_thanh', 'tu_choi'])->default('hoan_thanh');
            $table->string('ly_do')->nullable();
            $table->timestamps();
        });

        Schema::create('return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $table->foreignId('invoice_detail_id')->constrained('invoice_details')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedInteger('soluong');
            $table->decimal('don_gia', 12, 0);
            $table->decimal('gia_von', 12, 0)->default(0);
            $table->decimal('thanh_tien', 12, 0);
            $table->enum('tinh_trang', ['con_tot', 'loi_hong'])->default('con_tot');
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('soluong_hong')->default(0)->after('soluong');
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->foreignId('return_id')->nullable()->after('purchase_order_id')
                ->constrained('returns')->nullOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE stock_movements MODIFY loai ENUM('nhap','ban','dieuchinh','trahang','trahang_hong') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE stock_movements MODIFY loai ENUM('nhap','ban','dieuchinh') NOT NULL");
        }
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropForeign(['return_id']);
            $table->dropColumn('return_id');
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('soluong_hong');
        });
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('returns');
    }
};
