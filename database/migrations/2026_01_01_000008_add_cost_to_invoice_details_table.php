<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_details', function (Blueprint $table): void {
            $table->decimal('gia_von', 12, 0)->default(0)->after('don_gia');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_details', fn (Blueprint $table) => $table->dropColumn('gia_von'));
    }
};
