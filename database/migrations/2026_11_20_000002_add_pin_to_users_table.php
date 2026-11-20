<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pin_hash', 100)->nullable()->after('password');
            $table->unsignedTinyInteger('pin_sai_lien_tiep')->default(0)->after('pin_hash');
            $table->timestamp('pin_khoa_den')->nullable()->after('pin_sai_lien_tiep');
            $table->timestamp('pin_doi_luc')->nullable()->after('pin_khoa_den');
            $table->boolean('bat_buoc_doi_pin')->default(false)->after('pin_doi_luc');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pin_hash', 'pin_sai_lien_tiep', 'pin_khoa_den', 'pin_doi_luc', 'bat_buoc_doi_pin']);
        });
    }
};
