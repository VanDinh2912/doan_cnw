<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anh qua URL giai doan 1 — metadata anh tai tu URL trong import Excel.
 * Additive (chi them cot nullable vao products, khong sua/xoa gi).
 * Code doc qua KiemTraSchema::coCot: thieu cot -> cot hinh_anh_url trong
 * file import bi bo qua em, luong cu chay y nguyen.
 *
 * hinh_anh      : duong dan tep cuc bo 'sanpham/anh-<sha>.<ext>' (hoac URL/anh cu).
 * hinh_anh_nguon: URL goc da dung de tai anh (nullable).
 * hinh_anh_sha256: bam noi dung anh DA XU LY (GD re-encode), chong luu trung.
 * hinh_anh_tai_luc: thoi diem tai thanh cong gan nhat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('hinh_anh_nguon', 500)->nullable()->after('hinh_anh');
            $table->string('hinh_anh_sha256', 64)->nullable()->after('hinh_anh_nguon');
            $table->timestamp('hinh_anh_tai_luc')->nullable()->after('hinh_anh_sha256');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['hinh_anh_nguon', 'hinh_anh_sha256', 'hinh_anh_tai_luc']);
        });
    }
};
