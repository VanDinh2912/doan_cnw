<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\YeuCauPin;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** M4 — yeu cau xin cap PIN co trang thai + hop duyet 2 tab. */
class YeuCauPinTest extends TestCase
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

    public function test_xin_cap_tao_ban_ghi_cho_xu_ly_va_chan_trung(): void
    {
        $this->actingAs($this->nv)->post('/pin/xin-cap', ['ly_do' => 'Quên PIN ca sáng'])->assertRedirect();

        $yc = YeuCauPin::sole();
        $this->assertSame($this->nv->id, $yc->user_id);
        $this->assertSame(YeuCauPin::TT_CHO_XU_LY, $yc->trang_thai);
        $this->assertSame($this->nv->id, $yc->khoa_cho);

        // Gui trung -> bao cho, khong tao them
        $this->actingAs($this->nv)->post('/pin/xin-cap', ['ly_do' => 'Xin lại lần nữa'])->assertRedirect();
        $this->assertSame(1, YeuCauPin::count());
    }

    public function test_admin_thay_tab_va_cap_pin(): void
    {
        config(['pos.pin_bat' => false]); // PIN tat -> POST thang, middleware da co test rieng
        $this->actingAs($this->nv)->post('/pin/xin-cap', ['ly_do' => 'Mất điện thoại'])->assertRedirect();
        $yc = YeuCauPin::sole();

        // Badge endpoint gop 2 loai
        $this->actingAs($this->admin)->getJson('/yeu-cau-duyet/so-luong')->assertJson(['so_luong' => 1]);

        // Tab pin hien thi
        $this->actingAs($this->admin)->get('/yeu-cau-duyet?tab=pin')
            ->assertOk()->assertSee('Xin cấp PIN')->assertSee('Mất điện thoại');

        // Cap PIN tam
        $this->actingAs($this->admin)->post("/yeu-cau-duyet/pin/{$yc->id}/cap")->assertRedirect();
        $yc->refresh();
        $this->assertSame(YeuCauPin::TT_DA_CAP, $yc->trang_thai);
        $this->assertSame($this->admin->id, $yc->admin_xu_ly_id);
        $this->assertNotNull($yc->xu_ly_luc);
        $this->assertNull($yc->khoa_cho);
        $this->assertNotNull($this->nv->fresh()->pin_hash);

        // NV tu xem PIN tam 1 lan
        $maHoa = Cache::get('pin_tam_user_'.$this->nv->id);
        $this->assertNotNull($maHoa);
        $this->actingAs($this->nv)->get('/pin/tam-cua-toi')->assertOk();

        // NV gui yeu cau moi duoc (khoa_cho da nha)
        $this->actingAs($this->nv)->post('/pin/xin-cap', ['ly_do' => 'Quên tiếp'])->assertRedirect();
        $this->assertSame(2, YeuCauPin::count());
    }

    public function test_tu_choi_can_ly_do(): void
    {
        config(['pos.pin_bat' => false]);
        $this->actingAs($this->nv)->post('/pin/xin-cap', ['ly_do' => 'Xin PIN'])->assertRedirect();
        $yc = YeuCauPin::sole();

        $this->actingAs($this->admin)->post("/yeu-cau-duyet/pin/{$yc->id}/tu-choi", [])
            ->assertSessionHasErrors('ly_do');
        $this->actingAs($this->admin)->post("/yeu-cau-duyet/pin/{$yc->id}/tu-choi", ['ly_do' => 'ab'])
            ->assertSessionHasErrors('ly_do');

        $this->actingAs($this->admin)->post("/yeu-cau-duyet/pin/{$yc->id}/tu-choi", ['ly_do' => 'Nghi ngờ lạm dụng'])
            ->assertRedirect();
        $yc->refresh();
        $this->assertSame(YeuCauPin::TT_TU_CHOI, $yc->trang_thai);
        $this->assertSame('Nghi ngờ lạm dụng', $yc->ly_do_tu_choi);
        $this->assertNull($yc->khoa_cho);
    }

    public function test_yeu_cau_qua_7_ngay_tu_het_han(): void
    {
        $yc = YeuCauPin::create([
            'user_id' => $this->nv->id, 'ly_do' => 'Xin PIN cũ',
            'trang_thai' => YeuCauPin::TT_CHO_XU_LY, 'khoa_cho' => $this->nv->id,
        ]);
        DB::table('yeu_cau_pin')->where('id', $yc->id)
            ->update(['created_at' => now()->subDays(8)]);

        // NV vao trang -> donHetHan chay -> thay het_han, gui lai duoc
        $this->actingAs($this->nv)->get('/pin/xin-cap')->assertOk();
        $this->assertSame(YeuCauPin::TT_HET_HAN, $yc->fresh()->trang_thai);
        $this->assertNull($yc->fresh()->khoa_cho);

        $this->actingAs($this->nv)->post('/pin/xin-cap', ['ly_do' => 'Xin lại'])->assertRedirect();
        $this->assertSame(1, YeuCauPin::where('trang_thai', YeuCauPin::TT_CHO_XU_LY)->count());
    }

    public function test_nv_thay_trang_thai_yeu_cau(): void
    {
        $this->actingAs($this->nv)->post('/pin/xin-cap', ['ly_do' => 'Xin PIN'])->assertRedirect();
        $this->actingAs($this->nv)->get('/pin/xin-cap')
            ->assertOk()->assertSee('Yêu cầu của tôi')->assertSee('Đang chờ');
    }

    public function test_thieu_bang_thi_chay_nhu_cu(): void
    {
        Schema::drop('yeu_cau_pin');
        \App\Support\KiemTraSchema::datLai();

        // Xin cap van ghi log + Telegram nhu cu, khong tao ban ghi, khong loi
        $this->actingAs($this->nv)->post('/pin/xin-cap', ['ly_do' => 'Xin PIN luồng cũ'])->assertRedirect();
        $this->assertDatabaseHas('activity_logs', ['hanh_dong' => 'pin_xin_cap']);

        // Trang xin cap + hop duyet khong loi
        $this->actingAs($this->nv)->get('/pin/xin-cap')->assertOk();
        $this->actingAs($this->admin)->get('/yeu-cau-duyet?tab=pin')->assertOk();
        $this->actingAs($this->admin)->getJson('/yeu-cau-duyet/so-luong')->assertJson(['so_luong' => 0]);
    }

    public function test_nv_khong_goi_duoc_action_admin(): void
    {
        $this->actingAs($this->nv)->post('/pin/xin-cap', ['ly_do' => 'Xin PIN'])->assertRedirect();
        $yc = YeuCauPin::sole();

        config(['pos.pin_bat' => false]);
        $this->actingAs($this->nv)->post("/yeu-cau-duyet/pin/{$yc->id}/cap")->assertForbidden();
        $this->actingAs($this->nv)->post("/yeu-cau-duyet/pin/{$yc->id}/tu-choi", ['ly_do' => 'tu choi'])->assertForbidden();
        $this->assertSame(YeuCauPin::TT_CHO_XU_LY, $yc->fresh()->trang_thai);
    }

    public function test_thu_kho_di_tron_luong_xin_cap(): void
    {
        // Mo phong thu kho id=3: chua co PIN -> bi chan M1 -> xin cap -> admin cap -> xem PIN
        $thuKho = User::create(['name' => 'K', 'email' => 'k@t.local', 'password' => Hash::make('Mk@12345'), 'role' => 'thukho']);
        config(['pos.pin_bat' => false]);

        $this->actingAs($thuKho)->post('/pin/xin-cap', ['ly_do' => 'Chưa có PIN lần đầu'])->assertRedirect();
        $yc = YeuCauPin::where('user_id', $thuKho->id)->sole();

        $this->actingAs($this->admin)->post("/yeu-cau-duyet/pin/{$yc->id}/cap")->assertRedirect();
        $this->assertSame(YeuCauPin::TT_DA_CAP, $yc->fresh()->trang_thai);

        $maHoa = Cache::get('pin_tam_user_'.$thuKho->id);
        $this->assertNotNull($maHoa);
        $this->actingAs($thuKho)->get('/pin/tam-cua-toi')->assertOk();
        $kq = PinService::kiemTra($thuKho->fresh(), decrypt($maHoa), PinService::MUC_THUONG, 'POST /x', request());
        $this->assertTrue($kq['ok']);
    }
}
