<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yeu_cau_duyet', function (Blueprint $table) {
            $table->string('ly_do_yeu_cau', 255)->nullable()->after('mo_ta_thao_tac');
        });
    }

    public function down(): void
    {
        Schema::table('yeu_cau_duyet', function (Blueprint $table) {
            $table->dropColumn('ly_do_yeu_cau');
        });
    }
};
