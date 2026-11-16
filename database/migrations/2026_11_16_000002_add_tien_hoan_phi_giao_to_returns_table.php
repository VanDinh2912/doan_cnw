<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            // Quy tắc hoàn phí giao: chỉ admin tick "Hoàn cả phí giao", một lần/đơn.
            // > 0 = phiếu này đã hoàn phí giao; tổng tiền hoàn vẫn nằm ở tong_hoan.
            if (! Schema::hasColumn('returns', 'tien_hoan_phi_giao')) {
                $table->unsignedInteger('tien_hoan_phi_giao')->default(0)->after('tong_hoan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            if (Schema::hasColumn('returns', 'tien_hoan_phi_giao')) {
                $table->dropColumn('tien_hoan_phi_giao');
            }
        });
    }
};
