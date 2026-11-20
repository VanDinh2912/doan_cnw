<?php

namespace Tests\Feature;

use App\Models\KhachHang;
use App\Models\User;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PinRouteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $nv;

    protected function setUp(): void
    {
        parent::setUp();
        config(['pos.pin_bat' => true]);
        $this->admin = User::create(['name' => 'A', 'email' => 'a@t.local', 'password' => Hash::make('Mk@12345'), 'role' => 'admin']);
        $this->nv = User::create(['name' => 'N', 'email' => 'n@t.local', 'password' => Hash::make('Mk@12345'), 'role' => 'thungan']);
        PinService::datPin($this->admin, '135790');
        PinService::datPin($this->nv, '246813');
    }

    private function kiemTra(User $u, string $pin, string $muc = 'thuong', string $hd = 'POST /x'): array
    {
        $store = app('session')->driver();
        $store->start();
        $r = \Illuminate\Http\Request::create('/x', 'POST', ['pin' => $pin, 'muc' => $muc, 'hanh_dong' => $hd]);
        $r->setLaravelSession($store);

        return PinService::kiemTra($u->fresh(), $pin, $muc, PinService::MUC_NGHIEMTRONG === $muc ? 'POST /x' : $hd, $r);
    }

    public function test_co_tat_route_nhay_cam_chay_binh_thuong(): void
    {
        config(['pos.pin_bat' => false]);
        // Route nghiêm trọng vẫn vào được controller khi cờ tắt (không PIN)
        $this->actingAs($this->admin);
        $this->get('/khachhang')->assertOk();
        $res = $this->post('/khachhang/khoa/999999');
        $this->assertNotEquals(423, $res->status()); // middleware bỏ qua, route chạy tới controller
    }

    public function test_thuong_chua_nhap_pin_bi_chan_ajax_423(): void
    {
        $this->actingAs($this->nv);
        $this->postJson('/trahang', [])->assertStatus(423)->assertJsonPath('ma', 'CAN_PIN');
    }

    public function test_thuong_sai_pin_khong_mo_quyen(): void
    {
        $this->actingAs($this->nv);
        $this->postJson('/xac-nhan-pin/kiem-tra', ['pin' => '999999', 'muc' => 'thuong', 'hanh_dong' => 'POST /trahang'])
            ->assertStatus(422)->assertJsonPath('ma', 'SAI');
        $this->postJson('/trahang', [])->assertStatus(423);
    }

    public function test_thuong_dung_pin_mo_cua_so_5_phut(): void
    {
        $this->actingAs($this->nv);
        $this->postJson('/xac-nhan-pin/kiem-tra', ['pin' => '246813', 'muc' => 'thuong', 'hanh_dong' => 'POST /trahang'])
            ->assertOk()->assertJsonPath('ok', true);
        // Cửa sổ mở -> route thường đi qua middleware (validation fail 302/422 chứ không 423)
        $res = $this->post('/trahang', []);
        $this->assertNotEquals(423, $res->status());
    }

    public function test_nghiem_trong_can_ma_mot_lan(): void
    {
        $this->actingAs($this->admin);
        // Chưa có token -> 423
        $this->postJson('/taikhoan', [])->assertStatus(423);
        // Lấy token qua kiem-tra
        $kq = $this->postJson('/xac-nhan-pin/kiem-tra', ['pin' => '135790', 'muc' => 'nghiemtrong', 'hanh_dong' => 'POST /taikhoan'])->json();
        $this->assertTrue($kq['ok']);
        // Token đúng hành động -> POST mang _pin_token đi qua middleware
        $res = $this->postJson('/taikhoan', ['_pin_token' => $kq['token']]);
        $this->assertNotEquals(423, $res->status());
        // Token đã bị đốt -> request tiếp theo lại 423
        $this->postJson('/taikhoan', [])->assertStatus(423);
    }

    public function test_doi_pin_dung_bo_dem_chung_khoa_sau_5_sai(): void
    {
        $this->actingAs($this->nv);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/doi-pin', ['pin_cu' => '000000', 'pin_moi' => '112233', 'pin_moi_confirmation' => '112233']);
        }
        $this->assertNotNull($this->nv->fresh()->pin_khoa_den);
        // Đúng PIN cũ trong lúc khóa vẫn bị chặn
        $this->post('/doi-pin', ['pin_cu' => '246813', 'pin_moi' => '112233', 'pin_moi_confirmation' => '112233']);
        $this->assertNotNull($this->nv->fresh()->pin_khoa_den);
    }

    public function test_admin_khong_co_web_quen_pin(): void
    {
        // Không tồn tại route đặt lại PIN của admin trên web (chỉ artisan)
        $routes = collect(\Illuminate\Support\Facades\Route::getRoutes())->map(fn ($r) => $r->uri());
        $this->assertFalse($routes->contains(fn ($u) => str_contains($u, 'quen-pin') || str_contains($u, 'pin/quen')));
    }

    public function test_khach_hang_khong_bi_anh_huong(): void
    {
        $kh = KhachHang::create(['ho_ten' => 'K', 'so_dienthoai' => '0900000001']);
        $cust = User::create(['name' => 'K', 'email' => 'k@t.local', 'password' => Hash::make('Mk@12345'), 'role' => 'khachhang']);
        $this->actingAs($cust);
        $this->get('/thong-tin-ca-nhan')->assertOk();
        // Route nhân viên bị chặn bởi staff.only (không phải PIN)
        $this->get('/khachhang')->assertStatus(302); // staff.only redirect, khong phai PIN
    }

    public function test_chua_co_pin_bi_chan_thong_bao_ro(): void
    {
        $nv2 = User::create(['name' => 'N2', 'email' => 'n2@t.local', 'password' => Hash::make('Mk@12345'), 'role' => 'thungan']);
        $this->actingAs($nv2);
        $res = $this->postJson('/trahang', []);
        $res->assertStatus(423)->assertJsonPath('ma', 'CAN_THIET_LAP_PIN');
    }
}
