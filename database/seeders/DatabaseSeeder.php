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

        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Quản lý', 'password' => $matKhau, 'role' => 'admin']
        );
        User::firstOrCreate(
            ['email' => 'thungan@example.com'],
            ['name' => 'Thu ngân', 'password' => $matKhau, 'role' => 'thungan']
        );
        User::firstOrCreate(
            ['email' => 'thukho@example.com'],
            ['name' => 'Thủ kho', 'password' => $matKhau, 'role' => 'thukho']
        );

        // System user that owns guest storefront orders (user_id on invoices
        // is non-nullable). Not a real login account — password is random.
        User::firstOrCreate(
            ['email' => 'guest-storefront@internal'],
            [
                'name' => 'Khách mua Online (Storefront)',
                'password' => Hash::make(\Illuminate\Support\Str::random(64)),
                'role' => 'khachhang',
            ]
        );

        $this->call(DuLieuMau300Seeder::class);
    }
}
