<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yeu_cau_duyet', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('nhan_vien_id')->index();
            $table->string('ten_route', 120);
            $table->string('method', 10)->default('POST');
            $table->string('doi_tuong_loai', 60)->nullable();
            $table->unsignedBigInteger('doi_tuong_id')->nullable();
            $table->string('mo_ta_thao_tac', 255);
            // Tham số server ràng buộc vào mã (vd gia_ban_moi, giam_gia_phantram, tong_gio)
            $table->json('tham_so_duyet')->nullable();
            $table->string('trang_thai', 20)->default('cho_cap')->index();
            $table->char('ma_hash', 64)->nullable();
            // Chống trùng: chỉ có giá trị khi cho_cap/da_cap; NULL sau khi xử lý
            $table->char('khoa_trung', 64)->nullable()->unique();
            $table->unsignedBigInteger('admin_cap_id')->nullable();
            $table->timestamp('cap_luc')->nullable();
            $table->timestamp('het_han_luc')->nullable();
            $table->timestamp('dung_luc')->nullable();
            $table->timestamp('tu_choi_luc')->nullable();
            $table->string('ly_do_tu_choi', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('nhan_vien_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['trang_thai', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yeu_cau_duyet');
    }
};
