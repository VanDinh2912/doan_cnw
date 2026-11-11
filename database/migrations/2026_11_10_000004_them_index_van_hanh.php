<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index cho các truy vấn chạy thường xuyên:
 * - invoices: lọc theo trạng thái + thời gian (hủy đơn quá hạn, thống kê doanh thu),
 *   sắp xếp theo created_at khi liệt kê hóa đơn.
 * - stock_movements, activity_logs: liệt kê mới nhất (->latest()->paginate()).
 * Không đổi cấu trúc bảng, chỉ thêm index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->index(['trang_thai', 'created_at'], 'invoices_trang_thai_created_at_index');
            $table->index('created_at', 'invoices_created_at_index');
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->index('created_at', 'stock_movements_created_at_index');
        });

        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->index('created_at', 'activity_logs_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex('invoices_trang_thai_created_at_index');
            $table->dropIndex('invoices_created_at_index');
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropIndex('stock_movements_created_at_index');
        });

        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->dropIndex('activity_logs_created_at_index');
        });
    }
};
