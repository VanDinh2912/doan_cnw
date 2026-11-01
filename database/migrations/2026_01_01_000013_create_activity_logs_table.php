<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('hanh_dong', 50);
            $table->string('doi_tuong', 100);
            $table->unsignedBigInteger('doi_tuong_id')->nullable();
            $table->text('mo_ta');
            $table->ipAddress('ip')->nullable();
            $table->timestamps();
            $table->index(['doi_tuong', 'doi_tuong_id']);
            $table->index('hanh_dong');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
