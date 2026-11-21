<?php

namespace Tests\Feature;

use App\Models\NhatKyHoatDong;
use App\Models\User;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Luồng NV tự xin cấp PIN: gửi lý do -> admin cấp qua /taikhoan ->
 * NV tự xem PIN tạm đúng 1 lần tại /pin/tam-cua-toi.
 */
class PinXinCapTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $nv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'A', 'email' => 'a@t.local', 'password' => Hash::make('Mk@12345'), 'role' => 'admin']);
        $this->nv = User::create(['name' => 'N', 'email' => 'n@t.local', 'password' => Hash::make('Mk@12345'), 'role' => 'thungan']);
    }

    public function test_get_doi_pin_khi_chua_co_pin_chuyen_sang_xin_cap(): void
    {
        $this->actingAs($this->nv);
        $this->get('/doi-pin')->assertRedirect(route('pin.xincap'));
    }

    public function test_xin_cap_bat_buoc_ly_do_va_ghi_nhat_ky(): void
    {
        $this->actingAs($this->nv);
        $this->post('/pin/xin-cap', [])->assertSessionHasErrors('ly_do');
        $this->post('/pin/xin-cap', ['ly_do' => 'ok'])->assertSessionHasErrors('ly_do'); // < 5 ký tự

        $this->post('/pin/xin-cap', ['ly_do' => 'Lần đầu sử dụng PIN'])
            ->assertRedirect(route('pin.xincap'));

        $this->assertDatabaseHas('activity_logs', [
            'hanh_dong' => 'pin_xin_cap',
            'doi_tuong' => 'user',
            'doi_tuong_id' => $this->nv->id,
        ]);
        $log = NhatKyHoatDong::where('hanh_dong', 'pin_xin_cap')->latest('id')->first();
        $this->assertStringContainsString('Lần đầu sử dụng PIN', (string) $log->mo_ta);
    }

    public function test_khach_hang_khong_xin_cap_duoc(): void
    {
        $kh = User::create(['name' => 'K', 'email' => 'k@t.local', 'password' => Hash::make('Mk@12345'), 'role' => 'khachhang']);
        $this->actingAs($kh);
        // Route nằm trong nhóm staff.only -> khách bị redirect, không vào được
        $this->get('/pin/xin-cap')->assertRedirect();
        $this->post('/pin/xin-cap', ['ly_do' => 'muốn có PIN'])->assertRedirect();
        $this->get('/pin/tam-cua-toi')->assertRedirect();
    }

    public function test_admin_cap_pin_tam_nv_tu_xem_mot_lan(): void
    {
        // Cờ PIN tắt để gọi thẳng luồng cấp (middleware nghiêmtrong đã có test riêng)
        config(['pos.pin_bat' => false]);
        PinService::datPin($this->admin, '135790');

        $this->actingAs($this->admin)
            ->post("/taikhoan/dat-lai-pin/{$this->nv->id}", ['ly_do' => 'NV mới vào ca'])
            ->assertOk();

        // PIN tạm được giữ mã hóa trong cache cho đúng user
        $key = \App\Http\Controllers\PinController::KHOA_PIN_TAM().$this->nv->id;
        $maHoa = Cache::get($key);
        $this->assertNotNull($maHoa);
        $pinTam = decrypt($maHoa);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $pinTam);

        // NV đăng nhập xem -> thấy đúng PIN đó
        $this->actingAs($this->nv)
            ->get('/pin/tam-cua-toi')
            ->assertOk()
            ->assertSee($pinTam);

        // Xem lần 2 -> cache đã xóa, redirect
        $this->actingAs($this->nv)
            ->get('/pin/tam-cua-toi')
            ->assertRedirect(route('pin.xincap'));
    }

    public function test_admin_thay_nhan_xin_pin_va_form_duyet(): void
    {
        $this->actingAs($this->nv)->post('/pin/xin-cap', ['ly_do' => 'Quên PIN ca sáng']);

        // Danh sách tài khoản: badge "Đã xin PIN"
        $this->actingAs($this->admin)
            ->get('/taikhoan')
            ->assertOk()
            ->assertSee('Đã xin PIN');

        // Trang sửa: card PIN có form dat-lai-pin + ô lý do
        $this->actingAs($this->admin)
            ->get("/taikhoan/sua/{$this->nv->id}")
            ->assertOk()
            ->assertSee('/taikhoan/dat-lai-pin/'.$this->nv->id)
            ->assertSee('name="ly_do"', false)
            ->assertSee('data-can-pin="nghiemtrong"', false);
    }

    public function test_trang_sua_tai_khoan_pick_dung_vai_tro_hien_tai(): void
    {
        // nv la thungan -> option "thungan" phai co thuoc tinh selected
        $this->actingAs($this->admin)
            ->get("/taikhoan/sua/{$this->nv->id}")
            ->assertOk()
            ->assertSee('<option value="thungan" selected>', false);
    }

    public function test_pin_tam_bi_ep_doi_mot_lan_roi_vo_hieu(): void
    {
        // pin_bat=false de goi thang route cap (middleware xac.nhan.pin da co test rieng)
        config(['pos.pin_bat' => false]);
        PinService::datPin($this->admin, '135790');

        // Admin cap PIN tam cho nv
        $this->actingAs($this->admin)
            ->post("/taikhoan/dat-lai-pin/{$this->nv->id}", ['ly_do' => 'test tam'])
            ->assertOk();
        $pinTam = decrypt(Cache::get(\App\Http\Controllers\PinController::KHOA_PIN_TAM().$this->nv->id));
        $this->assertTrue((bool) $this->nv->fresh()->bat_buoc_doi_pin);

        // NV dang nhap -> moi trang bi ep ve /doi-pin (ke ca tong quan)
        $this->actingAs($this->nv->fresh())->get('/tongquan')->assertRedirect(route('pin.doi'));
        $this->actingAs($this->nv->fresh())->get('/doi-pin')->assertOk();

        // Dung PIN tam 1 lan de dat PIN moi
        $this->actingAs($this->nv->fresh())->post('/doi-pin', [
            'pin_cu' => $pinTam,
            'pin_moi' => '579135',
            'pin_moi_confirmation' => '579135',
        ])->assertRedirect(route('pin.doi'));

        $nv = $this->nv->fresh();
        $this->assertFalse((bool) $nv->bat_buoc_doi_pin);
        // PIN tam da chet: kiem tra truc tiep phai SAI
        $kq = PinService::kiemTra($nv, $pinTam, PinService::MUC_THUONG, 'POST /x', request());
        $this->assertFalse($kq['ok']);
        // Vao lai trang binh thuong
        $this->actingAs($this->nv->fresh())->get('/tongquan')->assertOk();
    }

    public function test_nv_khac_khong_xem_duoc_pin_tam(): void
    {
        config(['pos.pin_bat' => false]);
        PinService::datPin($this->admin, '135790');
        $nv2 = User::create(['name' => 'N2', 'email' => 'n2@t.local', 'password' => Hash::make('Mk@12345'), 'role' => 'thungan']);

        $this->actingAs($this->admin)
            ->post("/taikhoan/dat-lai-pin/{$this->nv->id}", ['ly_do' => 'test']);

        $this->actingAs($nv2)->get('/pin/tam-cua-toi')->assertRedirect(route('pin.xincap'));
    }
}
