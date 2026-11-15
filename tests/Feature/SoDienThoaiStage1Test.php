<?php

namespace Tests\Feature;

use App\Models\KhachHang;
use App\Models\User;
use App\Rules\SoDienThoaiHopLe;
use App\Support\SoDienThoai;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SoDienThoaiStage1Test extends TestCase
{
    use RefreshDatabase;

    private const TRUNG_CHUNG = 'Số điện thoại này không thể sử dụng.';

    private function staff(string $role = 'admin'): User
    {
        return User::create([
            'name' => 'Staff '.$role,
            'email' => $role.'.stage1@test.local',
            'password' => bcrypt('12345678'),
            'role' => $role,
        ]);
    }

    private function khachHangUser(string $phone = '0987654321'): User
    {
        $user = User::create([
            'name' => 'Khach Ho So',
            'email' => 'khachhosotest@test.local',
            'password' => bcrypt('12345678'),
            'role' => 'khachhang',
        ]);

        KhachHang::create([
            'user_id' => $user->id,
            'ho_ten' => 'Khach Ho So',
            'so_dienthoai' => $phone,
            'dia_chi' => null,
            'diem_tichluy' => 0,
        ]);

        return $user;
    }

    private function legacyRaw(string $phone, array $extra = []): int
    {
        return DB::table('customers')->insertGetId(array_merge([
            'ho_ten' => 'Khach Cu',
            'so_dienthoai' => $phone,
            'dia_chi' => null,
            'diem_tichluy' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $extra));
    }

    public static function sdtSaiDinhDangProvider(): array
    {
        return [
            'chu cai' => ['abcdef'],
            'lan chu' => ['09123abc78'],
            'cong 84 lan chu' => ['+84 abc'],
            'qua ngan' => ['0912345'],
            'qua dai 11 so' => ['09123456789'],
            'bat dau bang 1' => ['1912345678'],
            'mang thay vi chuoi' => [['abc']],
            'ky tu dac biet' => ['0912345678!'],
        ];
    }

    #[DataProvider('sdtSaiDinhDangProvider')]
    public function test_ho_so_tu_choi_sdt_sai_dinh_dang(mixed $input): void
    {
        $user = $this->khachHangUser();

        $this->actingAs($user)
            ->from(route('profile.show'))
            ->post(route('profile.update'), [
                'ho_ten' => 'Khach Ho So',
                'so_dienthoai' => $input,
            ])
            ->assertRedirect(route('profile.show'))
            ->assertSessionHasErrors(['so_dienthoai' => SoDienThoaiHopLe::THONG_BAO]);

        $this->assertDatabaseHas('customers', ['user_id' => $user->id, 'so_dienthoai' => '0987654321']);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_ho_so_tu_choi_sdt_toan_khoang_trang(): void
    {
        $user = $this->khachHangUser();

        $this->actingAs($user)
            ->from(route('profile.show'))
            ->post(route('profile.update'), [
                'ho_ten' => 'Khach Ho So',
                'so_dienthoai' => '   ',
            ])
            ->assertSessionHasErrors(['so_dienthoai' => 'Vui lòng nhập số điện thoại.']);

        $this->assertDatabaseHas('customers', ['user_id' => $user->id, 'so_dienthoai' => '0987654321']);
    }

    public static function sdtChuanHoaProvider(): array
    {
        return [
            'khoang trang' => ['091 234 5678'],
            'cong 84' => ['+84912345678'],
            '84 khong dau cong' => ['84912345678'],
            'gach ngang' => ['0912-345-678'],
            'ngoac va cham' => ['(0912) 345.678'],
        ];
    }

    #[DataProvider('sdtChuanHoaProvider')]
    public function test_ho_so_chuan_hoa_sdt_hop_le(string $input): void
    {
        $user = $this->khachHangUser();

        $this->actingAs($user)
            ->from(route('profile.show'))
            ->post(route('profile.update'), [
                'ho_ten' => 'Khach Ho So',
                'so_dienthoai' => $input,
            ])
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('customers', ['user_id' => $user->id, 'so_dienthoai' => '0912345678']);
    }

    public function test_ho_so_trung_sdt_khach_khac_bao_loi_chung_khong_500(): void
    {
        $this->legacyRaw('0912345678');
        $user = $this->khachHangUser();

        $this->actingAs($user)
            ->from(route('profile.show'))
            ->post(route('profile.update'), [
                'ho_ten' => 'Khach Ho So',
                'so_dienthoai' => '0912345678',
            ])
            ->assertRedirect(route('profile.show'))
            ->assertSessionHasErrors(['so_dienthoai' => self::TRUNG_CHUNG]);

        $this->assertDatabaseHas('customers', ['user_id' => $user->id, 'so_dienthoai' => '0987654321']);
    }

    public function test_ho_so_trung_sdt_sau_chuan_hoa_bao_loi_chung(): void
    {
        $this->legacyRaw('0912345678');
        $user = $this->khachHangUser();

        $this->actingAs($user)
            ->post(route('profile.update'), [
                'ho_ten' => 'Khach Ho So',
                'so_dienthoai' => '+84912345678',
            ])
            ->assertSessionHasErrors(['so_dienthoai' => self::TRUNG_CHUNG]);

        $this->assertDatabaseHas('customers', ['user_id' => $user->id, 'so_dienthoai' => '0987654321']);
    }

    public function test_ho_so_giu_nguyen_sdt_cua_chinh_minh(): void
    {
        $user = $this->khachHangUser('0912345678');

        $this->actingAs($user)
            ->from(route('profile.show'))
            ->post(route('profile.update'), [
                'ho_ten' => 'Ten Moi',
                'so_dienthoai' => '0912345678',
            ])
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('customers', ['user_id' => $user->id, 'ho_ten' => 'Ten Moi', 'so_dienthoai' => '0912345678']);
    }

    public function test_ho_so_tao_moi_khach_chua_co_ho_so(): void
    {
        $user = User::create([
            'name' => 'Khach Chua Ho So',
            'email' => 'chuahoso@test.local',
            'password' => bcrypt('12345678'),
            'role' => 'khachhang',
        ]);

        $this->actingAs($user)
            ->from(route('profile.show'))
            ->post(route('profile.update'), [
                'ho_ten' => 'Khach Chua Ho So',
                'so_dienthoai' => '+84912345678',
            ])
            ->assertRedirect(route('profile.show'));

        $this->assertDatabaseHas('customers', [
            'user_id' => $user->id,
            'so_dienthoai' => '0912345678',
        ]);
    }

    public function test_ho_so_form_co_thuoc_tinh_tel_va_giu_old(): void
    {
        $user = $this->khachHangUser();

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('type="tel"', false)
            ->assertSee('inputmode="numeric"', false)
            ->assertSee('maxlength="20"', false)
            ->assertSee('pattern=', false);

        $this->actingAs($user)
            ->from(route('profile.show'))
            ->post(route('profile.update'), [
                'ho_ten' => 'Khach Ho So',
                'so_dienthoai' => 'abcdef',
            ]);

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertSee('abcdef')
            ->assertSee(SoDienThoaiHopLe::THONG_BAO);
    }

    public function test_bail_sua_khach_khong_bao_trung_khi_sai_dinh_dang(): void
    {
        $this->legacyRaw('abcdef');
        $khach = KhachHang::create([
            'ho_ten' => 'Khach Sua',
            'so_dienthoai' => '0987654321',
            'dia_chi' => null,
            'diem_tichluy' => 0,
        ]);

        $response = $this->actingAs($this->staff())
            ->from(route('khachhang.sua', $khach))
            ->put(route('khachhang.postsua', $khach), [
                'ho_ten' => 'Khach Sua',
                'so_dienthoai' => 'abcdef',
            ]);

        $response->assertSessionHasErrors('so_dienthoai');
        $this->assertSame([SoDienThoaiHopLe::THONG_BAO], session('errors')->get('so_dienthoai'));
        $this->assertDatabaseHas('customers', ['id' => $khach->id, 'so_dienthoai' => '0987654321']);
    }

    public function test_bail_them_khach_khong_bao_trung_khi_sai_dinh_dang(): void
    {
        $this->legacyRaw('abcdef');

        $response = $this->actingAs($this->staff())
            ->from(route('khachhang.them'))
            ->post(route('khachhang.postthem'), [
                'ho_ten' => 'Khach Moi',
                'so_dienthoai' => 'abcdef',
            ]);

        $response->assertSessionHasErrors('so_dienthoai');
        $this->assertSame([SoDienThoaiHopLe::THONG_BAO], session('errors')->get('so_dienthoai'));
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_bail_pos_tao_nhanh_khong_bao_trung_khi_sai_dinh_dang(): void
    {
        $this->legacyRaw('abcdef');

        $response = $this->actingAs($this->staff('thungan'))
            ->postJson(route('khachhang.nhanh'), [
                'ho_ten' => 'Khach Nhanh',
                'so_dienthoai' => 'abcdef',
            ]);

        $response->assertStatus(422);
        $this->assertSame([SoDienThoaiHopLe::THONG_BAO], $response->json('errors.so_dienthoai'));
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_bail_dang_ky_khong_bao_trung_khi_sai_dinh_dang(): void
    {
        $this->legacyRaw('abcdef');

        $response = $this->from('/dang-ky')
            ->post('/dang-ky', [
                'name' => 'Khach Dang Ky',
                'email' => 'dk.bail@test.local',
                'so_dienthoai' => 'abcdef',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

        $response->assertSessionHasErrors('so_dienthoai');
        $this->assertSame([SoDienThoaiHopLe::THONG_BAO], session('errors')->get('so_dienthoai'));
        $this->assertDatabaseMissing('users', ['email' => 'dk.bail@test.local']);
    }

    #[DataProvider('sdtSaiDinhDangProvider')]
    public function test_dang_ky_tu_choi_sdt_sai_dinh_dang(mixed $input): void
    {
        $this->from('/dang-ky')
            ->post('/dang-ky', [
                'name' => 'Khach Dang Ky',
                'email' => 'dk.sai@test.local',
                'so_dienthoai' => $input,
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertSessionHasErrors(['so_dienthoai' => SoDienThoaiHopLe::THONG_BAO]);

        $this->assertDatabaseMissing('users', ['email' => 'dk.sai@test.local']);
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_sua_khach_chuan_hoa_sdt_hop_le(): void
    {
        $khach = KhachHang::create([
            'ho_ten' => 'Khach Sua',
            'so_dienthoai' => '0987654321',
            'dia_chi' => null,
            'diem_tichluy' => 0,
        ]);

        $this->actingAs($this->staff())
            ->put(route('khachhang.postsua', $khach), [
                'ho_ten' => 'Khach Sua',
                'so_dienthoai' => '091 234 5678',
            ])
            ->assertRedirect(route('khachhang.danhsach'));

        $this->assertDatabaseHas('customers', ['id' => $khach->id, 'so_dienthoai' => '0912345678']);
    }

    public function test_khach_sdt_rong_khong_chan_dang_ky_hop_le(): void
    {
        $this->legacyRaw('');

        $this->post('/dang-ky', [
            'name' => 'Khach Dang Ky',
            'email' => 'dk.rong@test.local',
            'so_dienthoai' => '0912345678',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('storefront.home'));

        $this->assertDatabaseHas('customers', ['so_dienthoai' => '0912345678']);
        $this->assertDatabaseHas('customers', ['so_dienthoai' => '']);
        $this->assertDatabaseCount('customers', 2);
    }

    public function test_sua_khach_sdt_rong_thanh_so_hop_le(): void
    {
        $id = $this->legacyRaw('');

        $this->actingAs($this->staff())
            ->put(route('khachhang.postsua', $id), [
                'ho_ten' => 'Khach Cu',
                'so_dienthoai' => '0912345678',
            ])
            ->assertRedirect(route('khachhang.danhsach'));

        $this->assertDatabaseHas('customers', ['id' => $id, 'so_dienthoai' => '0912345678']);
    }

    public function test_them_khach_sdt_rong_bao_required_khong_bao_trung(): void
    {
        $this->legacyRaw('');

        $response = $this->actingAs($this->staff())
            ->from(route('khachhang.them'))
            ->post(route('khachhang.postthem'), [
                'ho_ten' => 'Khach Moi',
                'so_dienthoai' => '',
            ]);

        $response->assertSessionHasErrors(['so_dienthoai' => 'Vui lòng nhập số điện thoại.']);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_pos_tao_nhanh_sdt_rong_bao_required(): void
    {
        $this->legacyRaw('');

        $response = $this->actingAs($this->staff('thungan'))
            ->postJson(route('khachhang.nhanh'), [
                'ho_ten' => 'Khach Nhanh',
                'so_dienthoai' => '',
            ]);

        $response->assertStatus(422);
        $this->assertSame(['Vui lòng nhập số điện thoại.'], $response->json('errors.so_dienthoai'));
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_model_mutator_chuan_hoa_khi_create_va_update(): void
    {
        $khach = KhachHang::create([
            'ho_ten' => 'Khach Model',
            'so_dienthoai' => '+84912345678',
            'dia_chi' => null,
            'diem_tichluy' => 0,
        ]);

        $this->assertDatabaseHas('customers', ['id' => $khach->id, 'so_dienthoai' => '0912345678']);

        $khach->update(['so_dienthoai' => '091 234 5678']);
        $this->assertDatabaseHas('customers', ['id' => $khach->id, 'so_dienthoai' => '0912345678']);
    }

    public static function chuanHoaThongNhatProvider(): array
    {
        return [
            ['0912345678'],
            ['+84912345678'],
            ['84912345678'],
            ['091 234 5678'],
            ['0912-345-678'],
            ['(0912) 345.678'],
            ['  0912345678  '],
            ['abcdef'],
            [''],
            ['+84 abc'],
        ];
    }

    #[DataProvider('chuanHoaThongNhatProvider')]
    public function test_rule_va_support_chuan_hoa_giong_nhau(string $input): void
    {
        $this->assertSame(SoDienThoai::chuanHoa($input), SoDienThoaiHopLe::chuanHoa($input));
    }

    public function test_lenh_dry_run_liet_ke_khong_sua_va_che_sdt(): void
    {
        $idCanChuanHoa = $this->legacyRaw('+84912345678');
        $idSai = $this->legacyRaw('abcdef');
        $khachHopLe = KhachHang::create([
            'ho_ten' => 'Khach Hop Le',
            'so_dienthoai' => '0987654321',
            'dia_chi' => null,
            'diem_tichluy' => 0,
        ]);

        $exit = Artisan::call('khachhang:chuan-hoa-sdt');
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('DRY-RUN', $output);
        $this->assertStringContainsString('Cần chuẩn hóa: 1', $output);
        $this->assertStringContainsString('Sai định dạng: 1', $output);
        $this->assertStringNotContainsString('+84912345678', $output);
        $this->assertStringNotContainsString('0912345678', $output);

        $this->assertDatabaseHas('customers', ['id' => $idCanChuanHoa, 'so_dienthoai' => '+84912345678']);
        $this->assertDatabaseHas('customers', ['id' => $idSai, 'so_dienthoai' => 'abcdef']);
        $this->assertDatabaseHas('customers', ['id' => $khachHopLe->id, 'so_dienthoai' => '0987654321']);
    }

    public function test_lenh_ap_dung_chuan_hoa_so_hop_le_giu_nguyen_so_sai(): void
    {
        $idCanChuanHoa = $this->legacyRaw('+84912345678');
        $idSai = $this->legacyRaw('abcdef');
        $idRong = $this->legacyRaw('');

        $exit = Artisan::call('khachhang:chuan-hoa-sdt', ['--ap-dung' => true]);

        $this->assertSame(0, $exit);
        $this->assertDatabaseHas('customers', ['id' => $idCanChuanHoa, 'so_dienthoai' => '0912345678']);
        $this->assertDatabaseHas('customers', ['id' => $idSai, 'so_dienthoai' => 'abcdef']);
        $this->assertDatabaseHas('customers', ['id' => $idRong, 'so_dienthoai' => '']);
    }

    public function test_lenh_ap_dung_bo_qua_khi_trung_unique(): void
    {
        $idXungDot = $this->legacyRaw('+84912345678');
        $idGiu = $this->legacyRaw('0912345678');

        $exit = Artisan::call('khachhang:chuan-hoa-sdt', ['--ap-dung' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertDatabaseHas('customers', ['id' => $idXungDot, 'so_dienthoai' => '+84912345678']);
        $this->assertDatabaseHas('customers', ['id' => $idGiu, 'so_dienthoai' => '0912345678']);
        $this->assertStringContainsString('xung đột', $output);
    }

    public function test_lenh_khong_co_gi_thay_doi(): void
    {
        KhachHang::create([
            'ho_ten' => 'Khach Hop Le',
            'so_dienthoai' => '0987654321',
            'dia_chi' => null,
            'diem_tichluy' => 0,
        ]);

        $exit = Artisan::call('khachhang:chuan-hoa-sdt', ['--ap-dung' => true]);

        $this->assertSame(0, $exit);
        $this->assertDatabaseHas('customers', ['so_dienthoai' => '0987654321']);
        $this->assertDatabaseCount('customers', 1);
    }
}