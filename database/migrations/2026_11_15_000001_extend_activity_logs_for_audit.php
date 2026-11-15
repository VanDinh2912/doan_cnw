<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_logs')) {
            return;
        }

        Schema::table('activity_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('activity_logs', 'doi_tuong_loai')) {
                $table->string('doi_tuong_loai', 100)->nullable()->after('doi_tuong');
            }
            if (! Schema::hasColumn('activity_logs', 'truoc')) {
                $table->json('truoc')->nullable()->after('mo_ta');
            }
            if (! Schema::hasColumn('activity_logs', 'sau')) {
                $table->json('sau')->nullable()->after('truoc');
            }
            if (! Schema::hasColumn('activity_logs', 'user_agent')) {
                $table->string('user_agent', 500)->nullable()->after('ip');
            }
            if (! Schema::hasColumn('activity_logs', 'nguon')) {
                $table->string('nguon', 30)->default('web')->after('user_agent');
            }
            if (! Schema::hasColumn('activity_logs', 'nhom_id')) {
                $table->string('nhom_id', 80)->nullable()->after('nguon');
            }
        });

        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->index(['nguon', 'created_at'], 'activity_logs_nguon_created_index');
            $table->index(['user_id', 'created_at'], 'activity_logs_user_created_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('activity_logs')) {
            return;
        }

        Schema::table('activity_logs', function (Blueprint $table): void {
            foreach (['activity_logs_nguon_created_index', 'activity_logs_user_created_index'] as $index) {
                try { $table->dropIndex($index); } catch (Throwable) { /* already absent */ }
            }
            foreach (['doi_tuong_loai', 'truoc', 'sau', 'user_agent', 'nguon', 'nhom_id'] as $column) {
                if (Schema::hasColumn('activity_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
