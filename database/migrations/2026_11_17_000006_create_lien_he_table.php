<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lien_he', function (Blueprint $table): void {
            $table->id();
            $table->string('ho_ten', 100);
            $table->string('so_dien_thoai', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->enum('loai', ['lien_he', 'gop_y', 'khieu_nai', 'ung_tuyen']);
            $table->string('vi_tri', 100)->nullable();
            $table->string('chu_de', 150);
            $table->text('noi_dung');
            $table->enum('trang_thai', ['moi', 'dang_xu_ly', 'da_xu_ly'])->default('moi');
            $table->text('ghi_chu_noibo')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('trang_thai');
            $table->index('loai');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lien_he');
    }
};
