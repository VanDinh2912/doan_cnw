<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M4 — Yeu cau xin cap/ dat lai PIN cua nhan vien co TRANG THAI.
 * Additive (chi CREATE). Thieu bang -> xin cap PIN chay nhu cu (log + Telegram).
 *
 * khoa_cho: chi co gia tri (= user_id) khi trang_thai='cho_xu_ly' -> moi NV
 * chi co 1 yeu cau dang cho; NULL sau khi xu ly -> nhan yeu cau moi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yeu_cau_pin', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('ly_do', 255);
            $table->string('trang_thai', 20)->default('cho_xu_ly'); // cho_xu_ly|da_cap|tu_choi|het_han
            $table->unsignedBigInteger('admin_xu_ly_id')->nullable();
            $table->timestamp('xu_ly_luc')->nullable();
            $table->string('ly_do_tu_choi', 255)->nullable();
            $table->unsignedBigInteger('khoa_cho')->nullable()->unique();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['trang_thai', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yeu_cau_pin');
    }
};
