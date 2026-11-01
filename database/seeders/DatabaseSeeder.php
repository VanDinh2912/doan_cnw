<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $matKhau = Hash::make('123456');

        User::create(['name' => 'Quản lý', 'email' => 'admin@example.com', 'password' => $matKhau, 'role' => 'admin']);
        User::create(['name' => 'Thu ngân', 'email' => 'thungan@example.com', 'password' => $matKhau, 'role' => 'thungan']);
        User::create(['name' => 'Thủ kho', 'email' => 'thukho@example.com', 'password' => $matKhau, 'role' => 'thukho']);

        $this->call(DuLieuMau300Seeder::class);
    }
}
