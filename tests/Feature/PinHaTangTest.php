<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PinHaTangTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['pos.pin_bat' => true]);
    }

    private function nv(array $attr = []): User
    {
        return User::create(array_merge([
            'name' => 'NV Test', 'email' => 'nv'.uniqid().'@test.local',
            'password' => Hash::make('MatKhau@123'), 'role' => 'thungan',
        ], $attr));
    }


    private function req(string $method = 'POST', string $uri = '/x'): \Illuminate\Http\Request
    {
        $store = app('session')->driver();
        $store->start();
        $r = \Illuminate\Http\Request::create($uri, $method);
        $r->setLaravelSession($store);

        return $r;
    }

    public function test_migration_da_tao_cot_pin(): void
    {
        foreach (['pin_hash', 'pin_sai_lien_tiep', 'pin_khoa_den', 'pin_doi_luc', 'bat_buoc_doi_pin'] as $c) {
            $this->assertTrue(Schema::hasColumn('users', $c), "Thiếu cột {$c}");
        }
    }

    public function test_dat_pin_tu_choi_pin_yeu(): void
    {
        $u = $this->nv();
        foreach (['000000', '123456', '654321', '111111', '121212'] as $yeu) {
            try {
                PinService::datPin($u, $yeu);
                $this->fail("PIN yếu {$yeu} không bị chặn");
            } catch (\InvalidArgumentException) {
            }
        }
        $this->assertNull($u->fresh()->pin_hash);
    }

    public function test_dat_pin_tu_choi_trung_mat_khau(): void
    {
        $u = $this->nv(['password' => Hash::make('246810')]);
        $this->expectException(\InvalidArgumentException::class);
        PinService::datPin($u, '246810');
    }

    public function test_dat_pin_yeu_cau_mat_khau_khi_co_truyen(): void
    {
        $u = $this->nv();
        $this->expectException(\InvalidArgumentException::class);
        PinService::datPin($u, '483920', 'sai-mat-khau');
    }

    public function test_dat_pin_thanh_cong_luu_hash_khong_luu_ro(): void
    {
        $u = $this->nv();
        PinService::datPin($u, '483920', 'MatKhau@123');
        $f = $u->fresh();
        $this->assertNotNull($f->pin_hash);
        $this->assertNotSame('483920', $f->pin_hash);
        $this->assertTrue(Hash::check('483920', $f->pin_hash));
        $this->assertEmpty($f->bat_buoc_doi_pin);
    }

    public function test_kiem_tra_sai_5_lan_khoa_15_phut(): void
    {
        $u = $this->nv();
        PinService::datPin($u, '483920');
        $this->actingAs($u);
        for ($i = 0; $i < 4; $i++) {
            $kq = PinService::kiemTra($u->fresh(), '000001', 'thuong', 'POST /x', $this->req());
            $this->assertSame('SAI', $kq['ma']);
        }
        $kq = PinService::kiemTra($u->fresh(), '000001', 'thuong', 'POST /x', $this->req());
        $this->assertSame('KHOA', $kq['ma']);
        // Đúng PIN trong lúc khóa vẫn bị chặn
        $kq2 = PinService::kiemTra($u->fresh(), '483920', 'thuong', 'POST /x', $this->req());
        $this->assertSame('KHOA', $kq2['ma']);
        $this->assertNotNull($u->fresh()->pin_khoa_den);
    }

    public function test_kiem_tra_dung_reset_bo_dem_va_mo_cua_so(): void
    {
        $u = $this->nv();
        PinService::datPin($u, '483920');
        $this->actingAs($u);
        $kq = PinService::kiemTra($u->fresh(), '483920', 'thuong', 'POST /x', $this->req());
        $this->assertTrue($kq['ok']);
        $this->assertSame(0, $u->fresh()->pin_sai_lien_tiep);
        $this->assertTrue(PinService::daXacNhanThuong($u->fresh()));
    }

    public function test_audit_khong_chua_pin(): void
    {
        $u = $this->nv();
        PinService::datPin($u, '483920');
        $this->actingAs($u);
        PinService::kiemTra($u->fresh(), '000001', 'thuong', 'POST /x', $this->req());
        PinService::kiemTra($u->fresh(), '483920', 'nghiemtrong', 'POST /y', $this->req());
        $logs = DB::table('activity_logs')->where('doi_tuong_id', $u->id)->get();
        $this->assertGreaterThanOrEqual(2, $logs->count());
        foreach ($logs as $l) {
            $this->assertStringNotContainsString('483920', json_encode($l));
            $this->assertStringNotContainsString('000001', json_encode($l));
        }
    }

    public function test_co_cua_so_5_phut_het_han(): void
    {
        $u = $this->nv();
        PinService::datPin($u, '483920');
        $this->actingAs($u);
        PinService::kiemTra($u->fresh(), '483920', 'thuong', 'POST /x', $this->req());
        $this->assertTrue(PinService::daXacNhanThuong($u->fresh()));
        // Giả lập đã quá 5 phút: ghi đè session với thời điểm cũ
        session(['pin_ok_thuong' => ['user_id' => $u->id, 'luc' => time() - 400]]);
        $this->assertFalse(PinService::daXacNhanThuong($u->fresh()));
    }

    public function test_nghiem_trong_cap_ma_mot_lan_dung_mot_lan(): void
    {
        $u = $this->nv();
        PinService::datPin($u, '483920');
        $this->actingAs($u);
        $req = $this->req();
        $kq = PinService::kiemTra($u->fresh(), '483920', 'nghiemtrong', 'POST /donhang/xacnhan/1', $req);
        $this->assertTrue($kq['ok']);
        $this->assertNotEmpty($kq['token']);
        // Request giả lập mang _pin_token đúng hành động
        $req2 = \Illuminate\Http\Request::create('/donhang/xacnhan/1', 'POST', ['_pin_token' => $kq['token']]);
        $req2->setLaravelSession($req->session());
        $this->assertTrue(PinService::kiemTraTokenNt($u->fresh(), $req2));
        // Dùng lần 2 -> fail (đã đốt)
        $req3 = \Illuminate\Http\Request::create('/donhang/xacnhan/1', 'POST', ['_pin_token' => $kq['token']]);
        $req3->setLaravelSession($req->session());
        $this->assertFalse(PinService::kiemTraTokenNt($u->fresh(), $req3));
    }

    public function test_co_tat_thi_middleware_khong_cham_pin(): void
    {
        config(['pos.pin_bat' => false]);
        $u = $this->nv();
        $this->actingAs($u);
        // users chưa có cột pin_* vẫn phải chạy — giả lập bằng flag tắt
        $this->assertFalse(PinService::bat());
        $this->assertFalse(PinService::daXacNhanThuong($u->fresh()));
    }

    public function test_chua_co_pin_bi_chan(): void
    {
        $u = $this->nv(); // không đặt PIN
        $kq = PinService::kiemTra($u, '123456', 'thuong', 'POST /x', $this->req());
        $this->assertFalse($kq['ok']);
        $this->assertSame('CHUA_CO_PIN', $kq['ma']);
    }
}
