<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->string('ma_loai', 10)->nullable()->unique()->after('ten_loai');
            $table->text('tu_khoa')->nullable()->after('mo_ta');
            $table->decimal('he_so_lai', 4, 2)->default(1.30)->after('tu_khoa');
        });
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->string('ma_so_thue', 20)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropUnique(['ma_so_thue']);
            $table->dropColumn('ma_so_thue');
        });
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropUnique(['ma_loai']);
            $table->dropColumn(['ma_loai', 'tu_khoa', 'he_so_lai']);
        });
    }
};
