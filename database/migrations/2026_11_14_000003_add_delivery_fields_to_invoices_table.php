<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Hình thức nhận: tai_cua_hang (mặc định) hoặc giao_tan_noi
            $table->string('hinh_thuc_nhan', 20)->default('tai_cua_hang')->after('han_giu');

            // Thông tin người nhận (chỉ dùng khi giao_tan_noi)
            $table->string('ten_nguoi_nhan', 100)->nullable()->after('hinh_thuc_nhan');
            $table->string('sdt_nguoi_nhan', 15)->nullable()->after('ten_nguoi_nhan');
            $table->string('dia_chi_giao', 255)->nullable()->after('sdt_nguoi_nhan');

            // Phí giao hàng (tính từ config, mặc định 0)
            $table->decimal('phi_giao_hang', 10, 0)->default(0)->after('dia_chi_giao');

            // Trạng thái giao (tách biệt từ trạng thái thanh toán)
            // Giá trị: cho_xac_nhan, da_xac_nhan, dang_giao, da_giao, da_huy_giao
            // Chỉ dùng khi hinh_thuc_nhan = giao_tan_noi
            $table->string('trang_thai_giao', 50)->nullable()->index()->after('phi_giao_hang');
        });
    }

    public function down(): void
    {
        // Kiểm tra xem còn đơn giao_tan_noi nào không
        $soDonGiao = DB::table('invoices')
            ->where('hinh_thuc_nhan', 'giao_tan_noi')
            ->count();

        if ($soDonGiao > 0) {
            throw new RuntimeException(
                "Không thể rollback: còn {$soDonGiao} hóa đơn giao tận nơi (hinh_thuc_nhan=giao_tan_noi). " .
                "Hãy xử lý các đơn này trước khi rollback."
            );
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['trang_thai_giao']);
            $table->dropColumn([
                'hinh_thuc_nhan',
                'ten_nguoi_nhan',
                'sdt_nguoi_nhan',
                'dia_chi_giao',
                'phi_giao_hang',
                'trang_thai_giao'
            ]);
        });
    }
};
