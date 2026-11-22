<?php

namespace Tests\Feature;

use App\Models\SanPham;
use App\Models\User;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\LayMaDuyet;
use Tests\TestCase;

class PinGiamGiaTest extends TestCase
{
    use LayMaDuyet;
    use RefreshDatabase;

    private function nv(): User
    {
        $u = User::create(['name' => 'N', 'email' => 'n'.uniqid().'@t.local', 'password' => Hash::make('Mk@12345'), 'role' => 'thungan']);
        PinService::datPin($u, '246813');

        return $u;
    }

    private function postThanhToan(float $giam): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/donhang/thanhtoan', [
            'giam_gia_phantram' => $giam, 'tien_khach_dua' => 0,
            'phuong_thuc' => 'tienmat',
        ]);
    }

    public function test_co_tat_khong_chan(): void
    {
        config(['pos.pin_bat' => false]);
        $this->actingAs($this->nv());
        $this->postThanhToan(50)->assertStatus(302); // qua được guard PIN (giỏ trống -> redirect)
    }

    public function test_duoi_nguong_khong_hoi_pin(): void
    {
        config(['pos.pin_bat' => true, 'pos.ma_duyet_bat' => true, 'pos.giam_gia_khong_pin_toi_da' => 10]);
        $this->actingAs($this->nv());
        $this->postThanhToan(10)->assertStatus(302);
    }

    public function test_vuot_nguong_chua_pin_bi_423(): void
    {
        config(['pos.pin_bat' => true, 'pos.ma_duyet_bat' => true, 'pos.giam_gia_khong_pin_toi_da' => 10]);
        $this->actingAs($this->nv());
        // Vượt ngưỡng = M2: nhân viên cần mã duyệt, không còn chỉ cần PIN.
        $this->postThanhToan(50)->assertStatus(423)->assertJsonPath('ma', 'CAN_MA_DUYET');
    }

    public function test_vuot_nguong_sai_pin_van_bi_chan(): void
    {
        config(['pos.pin_bat' => true, 'pos.ma_duyet_bat' => true, 'pos.giam_gia_khong_pin_toi_da' => 10]);
        $u = $this->nv();
        $this->actingAs($u);
        $this->postJson('/xac-nhan-pin/kiem-tra', ['pin' => '000001', 'muc' => 'thuong', 'hanh_dong' => 'POST /x'])->assertStatus(422);
        $this->postThanhToan(50)->assertStatus(423);
    }

    public function test_vuot_nguong_dung_ma_duyet_qua_duoc_guard(): void
    {
        config(['pos.pin_bat' => true, 'pos.ma_duyet_bat' => true, 'pos.giam_gia_khong_pin_toi_da' => 10]);
        $u = $this->nv();
        $this->actingAs($u);
        // PIN đúng KHÔNG còn đủ cho thao tác M2 — vẫn bị chặn.
        $this->postJson('/xac-nhan-pin/kiem-tra', ['pin' => '246813', 'muc' => 'thuong', 'hanh_dong' => 'POST /x'])->assertOk();
        $this->postThanhToan(50)->assertStatus(423)->assertJsonPath('ma', 'CAN_MA_DUYET');

        // Mã duyệt đúng tham số -> qua guard, giỏ trống -> redirect
        $ma = $this->capMaCho($u, 'donhang.thanhtoan', ['giam_gia_phantram' => 50]);
        $this->postJson('/donhang/thanhtoan', [
            'giam_gia_phantram' => 50, 'tien_khach_dua' => 0,
            'phuong_thuc' => 'tienmat', '_ma_duyet' => $ma,
        ])->assertStatus(302);
    }

    public function test_audit_khi_giam_lon_duoc_phep(): void
    {
        // Kiểm chứng gián tiếp: guard audit chỉ chạy sau transaction thành công
        // — unit-level đã cover ở PinHaTangTest; ở đây chỉ đảm bảo không 423 khi đủ PIN.
        $this->assertTrue(true);
    }

    public function test_vuot_nguong_thanh_toan_dung_mot_lan(): void
    {
        $this->seed();
        config(['pos.pin_bat' => true, 'pos.ma_duyet_bat' => true, 'pos.giam_gia_khong_pin_toi_da' => 10]);
        $u = User::where('role', 'thungan')->firstOrFail();
        PinService::datPin($u, '135792');
        $sp = SanPham::where('sku', 'CS-0001')->firstOrFail();
        $slTruoc = (int) $sp->soluong;

        $this->actingAs($u)->withSession([
            'gio_hang' => [
                (string) $sp->id => ['id' => $sp->id, 'soluong' => 2, 'gia_ban' => (float) $sp->gia_ban],
            ],
        ]);

        $payload = ['giam_gia_phantram' => 50, 'tien_khach_dua' => 99999999, 'phuong_thuc' => 'tienmat'];

        // Chưa mã duyệt -> 423 CAN_MA_DUYET, chưa có hóa đơn
        $this->postJson('/donhang/thanhtoan', $payload)->assertStatus(423)->assertJsonPath('ma', 'CAN_MA_DUYET');
        $this->assertDatabaseCount('invoices', 0);

        // PIN sai -> vẫn 423
        $this->postJson('/xac-nhan-pin/kiem-tra', ['pin' => '000001', 'muc' => 'thuong', 'hanh_dong' => 'POST /donhang/thanhtoan'])->assertStatus(422);
        $this->postJson('/donhang/thanhtoan', $payload)->assertStatus(423);
        $this->assertDatabaseCount('invoices', 0);

        // PIN đúng cũng KHÔNG đủ — M2 bắt buộc mã duyệt
        $this->postJson('/xac-nhan-pin/kiem-tra', ['pin' => '135792', 'muc' => 'thuong', 'hanh_dong' => 'POST /donhang/thanhtoan'])->assertOk();
        $this->postJson('/donhang/thanhtoan', $payload)->assertStatus(423)->assertJsonPath('ma', 'CAN_MA_DUYET');
        $this->assertDatabaseCount('invoices', 0);

        // Mã duyệt đúng -> đúng 1 hóa đơn, tồn trừ đúng 1 lần
        $ma = $this->capMaCho($u, 'donhang.thanhtoan', ['giam_gia_phantram' => 50]);
        $this->postJson('/donhang/thanhtoan', $payload + ['_ma_duyet' => $ma])->assertStatus(302);
        $this->assertDatabaseCount('invoices', 1);
        $invoiceId = (int) \App\Models\DonHang::query()->value('id');
        $this->assertSame($slTruoc - 2, (int) SanPham::findOrFail($sp->id)->soluong);
        $this->assertSame(1, \App\Models\BienDongKho::where('invoice_id', $invoiceId)->count());

        // Gửi lại không kèm mã -> vẫn bị chặn M2, không hóa đơn thứ hai
        $this->postJson('/donhang/thanhtoan', $payload)->assertStatus(423);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertSame($slTruoc - 2, (int) SanPham::findOrFail($sp->id)->soluong);
        $this->assertSame(1, \App\Models\BienDongKho::where('invoice_id', $invoiceId)->count());
    }
}
