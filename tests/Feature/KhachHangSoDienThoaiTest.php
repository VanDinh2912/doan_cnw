<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class KhachHangSoDienThoaiTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role = 'admin'): User
    {
        return User::create([
            'name' => 'Staff '.$role,
            'email' => $role.'@test.local',
            'password' => bcrypt('12345678'),
            'role' => $role,
        ]);
    }

    private function khach(string $phone = '0912345678', array $extra = []): KhachHang
    {
        return KhachHang::create(array_merge([
            'ho_ten' => 'Khach Mau',
            'so_dienthoai' => $phone,
            'dia_chi' => null,
            'diem_tichluy' => 0,
        ], $extra));
    }

    public static function sdtHopLeProvider(): array
    {
        return [
            'so thuan' => ['0912345678'],
            'cong 84' => ['+84912345678'],
            '84 khong dau cong' => ['84912345678'],
            'khoang trang' => ['091 234 5678'],
            'gach ngang' => ['0912-345-678'],
            'ngoac va cham' => ['(0912) 345.678'],
        ];
    }

    #[DataProvider('sdtHopLeProvider')]
    public function test_them_khach_chuan_hoa_sdt_hop_le(string $input): void
    {
        $this->actingAs($this->staff('thungan'))
            ->post(route('khachhang.postthem'), [
                'ho_ten' => 'Nguyen Van A',
                'so_dienthoai' => $input,
                'dia_chi' => 'Ha Noi',
            ])
            ->assertRedirect(route('khachhang.danhsach'));

        $this->assertDatabaseHas('customers', [
            'ho_ten' => 'Nguyen Van A',
            'so_dienthoai' => '0912345678',
        ]);
    }

    public static function sdtKhongHopLeProvider(): array
    {
        return [
            'chu cai' => ['abc'],
            'lan chu' => ['09123abc78'],
            'qua ngan' => ['0912345'],
            'qua dai 11 so' => ['09123456789'],
            'bat dau bang 1' => ['1912345678'],
            'rong' => [''],
            'ky tu dac biet' => ['0912345678!'],
            'chuoi qua dai' => [str_repeat('0', 200)],
            'mang thay vi chuoi' => [['abc']],
        ];
    }

    #[DataProvider('sdtKhongHopLeProvider')]
    public function test_them_khach_tu_choi_sdt_khong_hop_le(mixed $input): void
    {
        $this->actingAs($this->staff('admin'))
            ->from(route('khachhang.them'))
            ->post(route('khachhang.postthem'), [
                'ho_ten' => 'Khach Loi',
                'so_dienthoai' => $input,
            ])
            ->assertRedirect(route('khachhang.them'))
            ->assertSessionHasErrors('so_dienthoai');

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_them_trung_sdt_bi_tu_choi(): void
    {
        $this->khach('0912345678');

        $this->actingAs($this->staff('admin'))
            ->post(route('khachhang.postthem'), [
                'ho_ten' => 'Khach Trung',
                'so_dienthoai' => '0912345678',
            ])
            ->assertSessionHasErrors('so_dienthoai');

        $this->assertDatabaseCount('customers', 1);
    }

    public function test_them_trung_sdt_sau_chuan_hoa_bi_tu_choi(): void
    {
        $this->khach('0912345678');

        $this->actingAs($this->staff('admin'))
            ->post(route('khachhang.postthem'), [
                'ho_ten' => 'Khach Trung',
                'so_dienthoai' => '+84912345678',
            ])
            ->assertSessionHasErrors('so_dienthoai');

        $this->assertDatabaseCount('customers', 1);
    }

    public function test_sua_giu_nguyen_sdt_hop_le(): void
    {
        $khach = $this->khach('0912345678');

        $this->actingAs($this->staff('admin'))
            ->put(route('khachhang.postsua', $khach), [
                'ho_ten' => 'Ten Moi',
                'so_dienthoai' => '0912345678',
                'dia_chi' => 'Dia chi moi',
            ])
            ->assertRedirect(route('khachhang.danhsach'));

        $this->assertDatabaseHas('customers', [
            'id' => $khach->id,
            'ho_ten' => 'Ten Moi',
            'so_dienthoai' => '0912345678',
        ]);
    }

    public function test_sua_sdt_trung_khach_khac_bi_tu_choi(): void
    {
        $this->khach('0912345678');
        $khach2 = $this->khach('0987654321');

        $this->actingAs($this->staff('admin'))
            ->put(route('khachhang.postsua', $khach2), [
                'ho_ten' => 'Khach 2',
                'so_dienthoai' => '0912345678',
            ])
            ->assertSessionHasErrors('so_dienthoai');

        $this->assertDatabaseHas('customers', ['id' => $khach2->id, 'so_dienthoai' => '0987654321']);
    }

    public function test_form_giu_old_va_hien_loi_tieng_viet(): void
    {
        $this->actingAs($this->staff('admin'))
            ->from(route('khachhang.them'))
            ->post(route('khachhang.postthem'), [
                'ho_ten' => 'Khach Old',
                'so_dienthoai' => 'abc',
            ])
            ->assertRedirect(route('khachhang.them'));

        $this->get(route('khachhang.them'))
            ->assertSee('Khach Old')
            ->assertSee('Số điện thoại không hợp lệ. Nhập 10 chữ số, bắt đầu bằng 0.');
    }

    public function test_pos_tao_nhanh_chuan_hoa_sdt_hop_le(): void
    {
        $this->actingAs($this->staff('thungan'))
            ->postJson(route('khachhang.nhanh'), [
                'ho_ten' => 'Khach Nhanh',
                'so_dienthoai' => '+84912345678',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('customers', [
            'ho_ten' => 'Khach Nhanh',
            'so_dienthoai' => '0912345678',
        ]);
    }

    public function test_pos_tao_nhanh_tu_choi_sdt_chu(): void
    {
        $this->actingAs($this->staff('thungan'))
            ->postJson(route('khachhang.nhanh'), [
                'ho_ten' => 'Khach Nhanh',
                'so_dienthoai' => '09123abc78',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('so_dienthoai');

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_pos_tao_nhanh_trung_sdt(): void
    {
        $this->khach('0912345678');

        $this->actingAs($this->staff('thungan'))
            ->postJson(route('khachhang.nhanh'), [
                'ho_ten' => 'Khach Nhanh',
                'so_dienthoai' => '0912345678',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('so_dienthoai');
    }

    public function test_pos_tim_kiem_chuan_hoa_sdt(): void
    {
        $this->khach('0912345678');

        $this->actingAs($this->staff('thungan'))
            ->getJson(route('khachhang.timkiem', ['sdt' => '+84912345678']))
            ->assertOk()
            ->assertJsonFragment(['so_dienthoai' => '0912345678']);
    }

    public function test_pos_tim_kiem_escape_ky_tu_like(): void
    {
        $this->khach('0912345678');

        $this->actingAs($this->staff('thungan'))
            ->getJson(route('khachhang.timkiem', ['sdt' => '%']))
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_dang_ky_chuan_hoa_sdt(): void
    {
        $this->post('/dang-ky', [
            'name' => 'Khach Dang Ky',
            'email' => 'dk.chuanhoa@test.local',
            'so_dienthoai' => '+84912345678',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('storefront.home'));

        $this->assertDatabaseHas('customers', ['so_dienthoai' => '0912345678']);
    }

    public function test_dang_ky_tu_choi_sdt_chu(): void
    {
        $this->from('/dang-ky')
            ->post('/dang-ky', [
                'name' => 'Khach Dang Ky',
                'email' => 'dk.loi@test.local',
                'so_dienthoai' => 'abc',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertRedirect('/dang-ky')
            ->assertSessionHasErrors('so_dienthoai');

        $this->assertDatabaseMissing('users', ['email' => 'dk.loi@test.local']);
    }

    public function test_dang_ky_link_khach_vang_lai_sau_chuan_hoa(): void
    {
        $existing = $this->khach('0912345678', ['diem_tichluy' => 50]);

        $this->post('/dang-ky', [
            'name' => 'Khach Dang Ky',
            'email' => 'dk.link@test.local',
            'so_dienthoai' => '+84912345678',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('storefront.home'));

        $existing->refresh();
        $this->assertNotNull($existing->user_id);
        $this->assertSame(50, $existing->diem_tichluy);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_thu_kho_khong_duoc_them_khach(): void
    {
        $this->actingAs($this->staff('thukho'))
            ->post(route('khachhang.postthem'), [
                'ho_ten' => 'Khach',
                'so_dienthoai' => '0912345678',
            ])
            ->assertForbidden();
    }

    public function test_thu_ngan_duoc_them_khach(): void
    {
        $this->actingAs($this->staff('thungan'))
            ->post(route('khachhang.postthem'), [
                'ho_ten' => 'Khach',
                'so_dienthoai' => '0912345678',
            ])
            ->assertRedirect(route('khachhang.danhsach'));
    }

    public function test_khach_hang_khong_vao_duoc_khu_khach_hang(): void
    {
        $user = User::create([
            'name' => 'Khach',
            'email' => 'khach.role@test.local',
            'password' => bcrypt('password123'),
            'role' => 'khachhang',
        ]);

        $this->actingAs($user)
            ->get(route('khachhang.danhsach'))
            ->assertRedirect(route('storefront.home'));
    }

    public function test_xoa_khach_co_hoa_don_khong_gay_500(): void
    {
        $admin = $this->staff('admin');
        $khach = $this->khach('0912345678');
        DonHang::create([
            'ma_hoadon' => 'HD20261111-0001',
            'user_id' => $admin->id,
            'customer_id' => $khach->id,
            'tong_tien' => 100000,
            'tong_thanh_toan' => 100000,
            'tien_khach_dua' => 100000,
            'phuong_thuc' => 'tienmat',
            'trang_thai' => 'da_thanh_toan',
            'thoi_gian_thanh_toan' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('khachhang.xoa', $khach))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('customers', ['id' => $khach->id]);
    }

    public function test_xoa_khach_chua_co_hoa_don_thanh_cong(): void
    {
        $khach = $this->khach('0912345678');

        $this->actingAs($this->staff('admin'))
            ->delete(route('khachhang.xoa', $khach))
            ->assertRedirect(route('khachhang.danhsach'));

        $this->assertDatabaseMissing('customers', ['id' => $khach->id]);
    }
}
