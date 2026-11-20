<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // last_login_at: thời điểm đăng nhập thành công gần nhất.
            // Nullable — tài khoản chưa từng đăng nhập giữ NULL.
            // Cập nhật bằng query builder (không đụng updated_at).
            $table->timestamp('last_login_at')->nullable()->after('bat_buoc_doi_mat_khau');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'last_login_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('last_login_at');
            });
        }
    }
};
