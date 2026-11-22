<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\SanPham;
use App\Models\User;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\LayMaDuyet;
use Tests\TestCase;

class PinDiemTest extends TestCase
{
    use LayMaDuyet;
    use RefreshDatabase;

    private function nv(): User
    {
        $u = User::create(['name' => 'N', 'email' => 'n'.uniqid().'@t.local', 'password' => Hash::make('Mk@12345'), 'role' => 'thungan']);
        PinService::datPin($u, '246813');

        return $u;
    }

    private function khach(int $diem): KhachHang
    {
        $k = KhachHang::create(['ho_ten' => 'Khach Pin', 'so_dienthoai' => '09'.random_int(10000000, 99999999)]);
        $k->diem_tichluy = $diem;
        $k->save();

        return $k;
    }

    private function sessionGioHang(SanPham $sp): array
    {
        return ['gio_hang' => [
            (string) $sp->id => ['id' => $sp->id, 'soluong' => 1, 'gia_ban' => (float) $sp->gia_ban],
        ]];
    }

    private function postThanhToan(KhachHang $kh, int $diem): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/donhang/thanhtoan', [
            'giam_gia_phantram' => 0, 'tien_khach_dua' => 99999999,
            'phuong_thuc' => 'tienmat', 'customer_id' => $kh->id, 'diem_su_dung' => $diem,
        ]);
    }

    public function test_co_tat_khong_chan(): void
    {
        $this->seed();
        config(['pos.pin_bat' => false, 'pos.diem_dung_khong_pin_toi_da' => 5, 'pos.gia_tri_diem' => 100]);
        $u = User::where('role', 'thungan')->firstOrFail();
        $kh = $this->khach(100);
        $sp = SanPham::where('sku', 'CS-0001')->firstOrFail();

        $this->actingAs($u)->withSession($this->sessionGioHang($sp));
        $this->postThanhToan($kh, 60)->assertStatus(302);
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_duoi_nguong_khong_hoi_pin(): void
    {
        $this->seed();
        config(['pos.pin_bat' => true, 'pos.ma_duyet_bat' => true, 'pos.diem_dung_khong_pin_toi_da' => 50, 'pos.gia_tri_diem' => 100]);
        $u = $this->nv();
        $kh = $this->khach(100);
        $sp = SanPham::where('sku', 'CS-0001')->firstOrFail();

        $this->actingAs($u)->withSession($this->sessionGioHang($sp));
        $this->postThanhToan($kh, 50)->assertStatus(302);
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_vuot_nguong_chua_pin_bi_423(): void
    {
        $this->seed();
        config(['pos.pin_bat' => true, 'pos.ma_duyet_bat' => true, 'pos.diem_dung_khong_pin_toi_da' => 50, 'pos.gia_tri_diem' => 100]);
        $u = $this->nv();
        $kh = $this->khach(100);
        $sp = SanPham::where('sku', 'CS-0001')->firstOrFail();

        $this->actingAs($u)->withSession($this->sessionGioHang($sp));
        // Vượt ngưỡng điểm = M2: cần mã duyệt, không còn chỉ cần PIN.
        $this->postThanhToan($kh, 51)->assertStatus(423)->assertJsonPath('ma', 'CAN_MA_DUYET');
        $this->assertDatabaseCount('invoices', 0);
        $this->assertSame(100, (int) $kh->fresh()->diem_tichluy);
    }

    public function test_vuot_nguong_dung_pin_qua_va_khong_tai_dung(): void
    {
        $this->seed();
        config(['pos.pin_bat' => true, 'pos.ma_duyet_bat' => true, 'pos.diem_dung_khong_pin_toi_da' => 50, 'pos.gia_tri_diem' => 100]);
        $u = $this->nv();
        $kh = $this->khach(100);
        $sp = SanPham::where('sku', 'CS-0001')->firstOrFail();

        $this->actingAs($u)->withSession($this->sessionGioHang($sp));

        // PIN sai -> vẫn 423
        $this->postJson('/xac-nhan-pin/kiem-tra', ['pin' => '000001', 'muc' => 'thuong', 'hanh_dong' => 'POST /donhang/thanhtoan'])->assertStatus(422);
        $this->postThanhToan($kh, 60)->assertStatus(423);
        $this->assertDatabaseCount('invoices', 0);

        // PIN đúng cũng KHÔNG đủ — M2 bắt buộc mã duyệt của quản lý
        $this->postJson('/xac-nhan-pin/kiem-tra', ['pin' => '246813', 'muc' => 'thuong', 'hanh_dong' => 'POST /donhang/thanhtoan'])->assertOk();
        $this->postThanhToan($kh, 60)->assertStatus(423)->assertJsonPath('ma', 'CAN_MA_DUYET');
        $this->assertDatabaseCount('invoices', 0);

        // Mã duyệt đúng tham số (giảm 0%, 60 điểm, tổng giỏ 34000) -> qua guard
        $ma = $this->capMaCho($u, 'donhang.thanhtoan', [
            'giam_gia_phantram' => 0, 'diem_su_dung' => 60, 'tong_gio' => 34000,
        ]);
        $this->postJson('/donhang/thanhtoan', [
            'giam_gia_phantram' => 0, 'tien_khach_dua' => 99999999,
            'phuong_thuc' => 'tienmat', 'customer_id' => $kh->id, 'diem_su_dung' => 60,
            '_ma_duyet' => $ma,
        ])->assertStatus(302);
        $this->assertDatabaseCount('invoices', 1);
        $invoiceId = (int) DonHang::query()->value('id');
        $this->assertSame(60, (int) DonHang::findOrFail($invoiceId)->diem_su_dung);

        // Gửi lại không kèm mã -> vẫn bị chặn M2, không hóa đơn thứ hai
        $this->postThanhToan($kh, 60)->assertStatus(423);
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_qr_vuot_nguong_chua_pin_bi_423(): void
    {
        $this->seed();
        config(['pos.pin_bat' => true, 'pos.ma_duyet_bat' => true, 'pos.diem_dung_khong_pin_toi_da' => 50, 'pos.gia_tri_diem' => 100]);
        $u = $this->nv();
        $kh = $this->khach(100);
        $sp = SanPham::where('sku', 'CS-0001')->firstOrFail();

        $this->actingAs($u)->withSession($this->sessionGioHang($sp));
        $this->postJson('/donhang/taoqr', [
            'giam_gia_phantram' => 0, 'customer_id' => $kh->id, 'diem_su_dung' => 51,
        ])->assertStatus(423)->assertJsonPath('ma', 'CAN_MA_DUYET');
        $this->assertDatabaseCount('invoices', 0);
    }
}
