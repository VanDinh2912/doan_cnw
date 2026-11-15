<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // khoa_luc: timestamp khi tài khoản bị khóa, NULL = hoạt động
            $table->timestamp('khoa_luc')->nullable()->after('remember_token');

            // bat_buoc_doi_mat_khau: cờ bắt buộc đổi mật khẩu ở lần đăng nhập đầu
            $table->boolean('bat_buoc_doi_mat_khau')->default(false)->after('khoa_luc');
        });
    }

    public function down(): void
    {
        // Chỉ drop cột nếu tồn tại (guard khi chưa áp migration lên DB thật)
        if (Schema::hasColumn('users', 'khoa_luc')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('khoa_luc');
            });
        }

        if (Schema::hasColumn('users', 'bat_buoc_doi_mat_khau')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('bat_buoc_doi_mat_khau');
            });
        }
    }
};
