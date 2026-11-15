<?php

namespace Tests\Feature;

use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\LoaiSanPham;
use App\Models\NhatKyHoatDong;
use App\Models\SanPham;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KhachHangLichSuDonTest extends TestCase
{
    use RefreshDatabase;

    private function khachUser(string $email = 'khach.donhang@test.local', string $soDienThoai = '0912345678'): User
    {
        $user = User::create([
            'name' => 'Khach Don Hang',
            'email' => $email,
            'password' => bcrypt('12345678'),
            'role' => 'khachhang',
        ]);

        KhachHang::create([
            'user_id' => $user->id,
            'ho_ten' => 'Khach Don Hang',
            'so_dienthoai' => $soDienThoai,
            'dia_chi' => null,
            'diem_tichluy' => 0,
        ]);

        return $user;
    }

    private function donHangCho(User $user, ?string $maTraCuu = null): DonHang
    {
        $khachHang = $user->khachHang;
        $staff = User::create([
            'name' => 'Thu ngan',
            'email' => 'staff.'.$user->id.'@test.local',
            'password' => bcrypt('12345678'),
            'role' => 'thungan',
        ]);

        return DonHang::create([
            'ma_hoadon' => 'HD-TEST-'.$user->id.'-'.uniqid(),
            'user_id' => $staff->id,
            'customer_id' => $khachHang->id,
            'tong_tien' => 100000,
            'tong_thanh_toan' => 100000,
            'tien_khach_dua' => 100000,
            'tien_thua' => 0,
            'trang_thai' => 'da_thanh_toan',
            'kenh' => 'pos',
            'ma_tra_cuu' => $maTraCuu,
        ]);
    }

    public function test_lich_su_don_hang_hien_thi_don_cua_khach_ke_ca_don_pos_khong_ma_tra_cuu(): void
    {
        $user = $this->khachUser();
        $don = $this->donHangCho($user); // POS: ma_tra_cuu = null

        $this->actingAs($user)
            ->get(route('profile.orders'))
            ->assertOk()
            ->assertSee($don->ma_hoadon);
    }

    public function test_chi_tiet_don_hang_cua_chinh_minh(): void
    {
        $user = $this->khachUser();
        $don = $this->donHangCho($user);

        $loai = LoaiSanPham::create(['ten_loai' => 'Sap '.uniqid()]);
        $sanPham = SanPham::create([
            'category_id' => $loai->id,
            'sku' => 'SKU-'.uniqid(),
            'ten_sanpham' => 'Cap sac nhanh test',
            'don_vi' => 'Cái',
            'gia_nhap' => 50000,
            'gia_ban' => 100000,
            'soluong' => 10,
            'soluong_toithieu' => 1,
            'trang_thai' => true,
        ]);
        ChiTietDonHang::create([
            'invoice_id' => $don->id,
            'product_id' => $sanPham->id,
            'soluong' => 1,
            'don_gia' => 100000,
            'thanh_tien' => 100000,
        ]);

        $this->actingAs($user)
            ->get(route('profile.orders.show', $don))
            ->assertOk()
            ->assertSee($don->ma_hoadon)
            ->assertSee('Cap sac nhanh test');
    }

    public function test_khong_xem_duoc_don_cua_khach_khac(): void
    {
        $userA = $this->khachUser('a@test.local', '0911111111');
        $userB = $this->khachUser('b@test.local', '0922222222');
        $donCuaA = $this->donHangCho($userA);

        $this->actingAs($userB)
            ->get(route('profile.orders.show', $donCuaA))
            ->assertForbidden();
    }

    public function test_khach_chua_co_ho_so_khong_xem_duoc_chi_tiet(): void
    {
        $user = User::create([
            'name' => 'Khach Chua Ho So',
            'email' => 'chua.hoso.donhang@test.local',
            'password' => bcrypt('12345678'),
            'role' => 'khachhang',
        ]);
        $owner = $this->khachUser('owner@test.local');
        $don = $this->donHangCho($owner);

        $this->actingAs($user)
            ->get(route('profile.orders.show', $don))
            ->assertForbidden();
    }

    public function test_chu_don_da_dang_nhap_xem_duoc_trang_thai_online_khong_can_bang_chung(): void
    {
        $user = $this->khachUser('chu.don.online@test.local');
        $don = $this->donHangCho($user, 'MA-TRA-CUU-TEST-01');

        $this->actingAs($user)
            ->get(route('don-hang.trangthai', $don->ma_tra_cuu))
            ->assertOk()
            ->assertSee($don->ma_hoadon);
    }

    public function test_khach_khac_khong_xem_duoc_trang_thai_online(): void
    {
        $userA = $this->khachUser('chu.a@test.local', '0933333333');
        $userB = $this->khachUser('chu.b@test.local', '0944444444');
        $don = $this->donHangCho($userA, 'MA-TRA-CUU-TEST-02');

        $this->actingAs($userB)
            ->get(route('don-hang.trangthai', $don->ma_tra_cuu))
            ->assertRedirect(route('don-hang.tracuu'));
    }
}
