<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('returns', function (Blueprint $table): void {
            $table->unsignedInteger('diem_hoan')->default(0)->after('diem_thu_hoi');
        });
    }

    public function down(): void
    {
        Schema::table('returns', function (Blueprint $table): void {
            $table->dropColumn('diem_hoan');
        });
    }
};
