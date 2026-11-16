<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('topic_id')->constrained('news_topics')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tieu_de', 200);
            $table->string('slug', 220)->unique();
            $table->string('tom_tat', 500)->nullable();
            $table->longText('noi_dung');
            $table->string('hinh_anh', 500)->nullable();
            $table->unsignedInteger('luot_xem')->default(0);
            $table->boolean('kiem_duyet')->default(true);
            $table->boolean('kich_hoat')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_posts');
    }
};