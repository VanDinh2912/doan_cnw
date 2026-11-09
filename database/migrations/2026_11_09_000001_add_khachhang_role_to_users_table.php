<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('admin','thungan','thukho','khachhang') NOT NULL DEFAULT 'khachhang'");
            return;
        }

        // SQLite: enum is a varchar + CHECK constraint. Rebuild the table.
        if ($driver === 'sqlite') {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('role')->default('khachhang')->change();
            });
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('admin','thungan','thukho') NOT NULL DEFAULT 'thungan'");
            return;
        }

        if ($driver === 'sqlite') {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('role')->default('thungan')->change();
            });
        }
    }
};