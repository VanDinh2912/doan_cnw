<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\LoaiSanPham;
use App\Models\SanPham;
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

        $loai = [
            'sac' => LoaiSanPham::create(['ten_loai' => 'Củ sạc & cáp sạc']),
            'pin' => LoaiSanPham::create(['ten_loai' => 'Pin sạc dự phòng']),
            'tai' => LoaiSanPham::create(['ten_loai' => 'Tai nghe']),
        ];

        $sanPhams = [
            ['sac', 'HC-C109-CL', 'Củ sạc nhanh Type-C sang Lightning 20W Hoco C109', 'Bộ', 54040, 69000, 5],
            ['sac', 'HC-C109-CC', 'Củ sạc Type-C sang Type-C PD20W Hoco C109', 'Bộ', 46127, 59000, 5],
            ['pin', 'HC-J159', 'Pin dự phòng 10000mAh 22.5W Hoco J159', 'Cái', 155365, 199000, 3],
            ['pin', 'HC-Q34', 'Pin dự phòng 10000mAh PD20W Hoco Q34', 'Cái', 315000, 399000, 2],
            ['pin', 'HC-J160A', 'Pin dự phòng 10000mAh PD20W Hoco J160A', 'Cái', 258000, 329000, 2],
            ['pin', 'HC-J151A', 'Pin dự phòng 20000mAh Hoco J151A', 'Cái', 220000, 289000, 4],
            ['tai', 'HC-W35', 'Tai nghe Bluetooth Hoco W35', 'Cái', 125000, 169000, 5],
            ['tai', 'HC-E63', 'Tai nghe Bluetooth Hoco E63', 'Cái', 76000, 99000, 3],
            ['tai', 'HC-DMW3', 'Tai nghe có dây Hoco DMW3', 'Cái', 35000, 49000, 1],
            ['sac', 'HC-UCN3', 'Cáp sạc nhanh Type-C Hoco UCN3', 'Sợi', 28000, 39000, 5],
        ];

        foreach ($sanPhams as [$nhom, $sku, $ten, $donVi, $giaNhap, $giaBan, $soLuong]) {
            SanPham::create([
                'category_id' => $loai[$nhom]->id,
                'sku' => $sku,
                'ten_sanpham' => $ten,
                'don_vi' => $donVi,
                'gia_nhap' => $giaNhap,
                'gia_ban' => $giaBan,
                'soluong' => $soLuong,
            ]);
        }
    }
}
