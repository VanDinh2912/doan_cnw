<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_topics', function (Blueprint $table): void {
            $table->id();
            $table->string('ten_chude', 100)->unique();
            $table->string('slug', 120)->unique();
            $table->string('mo_ta', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_topics');
    }
};