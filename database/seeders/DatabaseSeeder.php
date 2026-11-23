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
        // CANH BAO: mat khau demo co dinh '123456' — CHI cho moi truong dev/thu.
        // KHONG duoc seed len production (mat khau da public trong repo).
        // De xuat (chua lam): doc tu bien moi truong SEED_DEMO_PASSWORD.
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
