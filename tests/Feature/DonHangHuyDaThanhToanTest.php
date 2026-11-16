<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\NhatKyHoatDong;
use App\Models\SanPham;
use App\Models\User;
use App\Services\ThanhToanChuyenKhoanService;
use App\Support\KiemTraSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** HM4 — hủy đơn đã thanh toán (admin) + đánh dấu hoàn tiền thủ công. */
class DonHangHuyDaThanhToanTest extends TestCase
{
    use RefreshDatabase;

    private function donDaThanhToan(array $overrides = []): DonHang
    {
        $sp = SanPham::orderBy('id')->firstOrFail();
        $sp->update(['soluong' => 10, 'trang_thai' => true]);
        $don = app(ThanhToanChuyenKhoanService::class)->taoHoaDonChoThanhToan(
            [['id' => $sp->id, 'soluong' => 2]], [], null,
            array_merge([
                'kenh' => 'online', 'phuong_thuc' => 'chuyenkhoan',
                'hinh_thuc_nhan' => 'giao_tan_noi',
                'ma_tra_cuu' => 'HT'.random_int(100000, 999999),
                'ten_khach_dat' => 'Khách Hủy', 'so_dienthoai_dat' => '0907777888',
                'ten_nguoi_nhan' => 'Người Nhận', 'sdt_nguoi_nhan' => '0907777888',
                'dia_chi_giao' => '9 Trần Hưng Đạo, Q1',
                'han_giu' => now()->addMinutes(30),
            ], $overrides)
        );
        DonHang::whereKey($don->id)->update(array_intersect_key($overrides, array_flip([
            'customer_id', 'diem_tichluy_nhan', 'trang_thai_giao',
        ])));
        $don->update(['trang_thai' => 'da_thanh_toan', 'thoi_gian_thanh_toan' => now()]);

        return $don->fresh();
    }

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    public function test_huy_don_da_thanh_toan_hoan_kho_thu_hoi_diem_dung_mot_lan(): void
    {
        $this->seed();
        Http::fake();
        config(['pos.telegram_bot_token' => 'tok', 'pos.telegram_chat_id' => 'chat']);

        $kh = KhachHang::create(['ho_ten' => 'KH', 'so_dienthoai' => '0908888999', 'diem_tichluy' => 30]);
        $don = $this->donDaThanhToan([
            'customer_id' => $kh->id, 'diem_tichluy_nhan' => 10, 'trang_thai_giao' => 'dang_giao',
        ]);
        $sp = SanPham::orderBy('id')->firstOrFail();
        $this->assertSame(8, (int) $sp->fresh()->soluong);

        $this->actingAs($this->admin())->post(route('donhangonline.huydathanhtoan', $don), [
            'ly_do' => 'Khách yêu cầu hủy do đổi ý',
        ])->assertSessionHas('success');

        $don = $don->fresh();
        $this->assertSame('da_huy', $don->trang_thai);
        $this->assertSame('da_huy_giao', $don->trang_thai_giao);
        $this->assertTrue((bool) $don->can_hoan_tien);
        $this->assertSame(10, (int) $sp->fresh()->soluong); // hoàn kho đúng 1 lần
        $this->assertSame(20, (int) $kh->fresh()->diem_tichluy); // 30 - 10, không âm
        $this->assertSame(1, \App\Models\BienDongKho::where('invoice_id', $don->id)->where('loai', 'dieuchinh')->count());

        // Telegram: chỉ mã đơn + tổng + lý do, không SĐT/địa chỉ
        Http::assertSent(function ($req) {
            $text = $req['text'] ?? '';
            return str_contains($text, 'CẦN HOÀN TIỀN')
                && ! str_contains($text, '0907777888')
                && ! str_contains($text, 'Trần Hưng Đạo');
        });

        // Bấm đúp / hủy lại → từ chối, không hoàn kho lần hai
        $this->actingAs($this->admin())->post(route('donhangonline.huydathanhtoan', $don), [
            'ly_do' => 'Hủy lần hai',
        ])->assertSessionHas('error');
        $this->assertSame(10, (int) $sp->fresh()->soluong);
        $this->assertSame(1, \App\Models\BienDongKho::where('invoice_id', $don->id)->where('loai', 'dieuchinh')->count());
        $this->assertSame(20, (int) $kh->fresh()->diem_tichluy);
    }

    public function test_diem_khong_bao_gio_am(): void
    {
        $this->seed();
        Http::fake();
        $kh = KhachHang::create(['ho_ten' => 'KH2', 'so_dienthoai' => '0906666555', 'diem_tichluy' => 3]);
        $don = $this->donDaThanhToan(['customer_id' => $kh->id, 'diem_tichluy_nhan' => 10]);

        $this->actingAs($this->admin())->post(route('donhangonline.huydathanhtoan', $don), [
            'ly_do' => 'Thu hồi điểm vượt số dư',
        ])->assertSessionHas('success');
        $this->assertSame(0, (int) $kh->fresh()->diem_tichluy); // kẹp sàn 0
    }

    public function test_quyen_va_bat_buoc_ly_do(): void
    {
        $this->seed();
        $don = $this->donDaThanhToan();
        $thuNgan = User::where('role', 'thungan')->firstOrFail();
        $thuKho = User::where('role', 'thukho')->firstOrFail();
        $sp = SanPham::orderBy('id')->firstOrFail();

        // thungan/thukho không được hủy đơn đã thanh toán
        foreach ([$thuNgan, $thuKho] as $u) {
            $this->actingAs($u)->post(route('donhangonline.huydathanhtoan', $don), [
                'ly_do' => 'Thử hủy trái phép',
            ])->assertForbidden();
        }
        $this->assertSame('da_thanh_toan', $don->fresh()->trang_thai);
        $this->assertSame(8, (int) $sp->fresh()->soluong);

        // admin thiếu lý do → 302 validation, không đổi gì
        $this->actingAs($this->admin())->post(route('donhangonline.huydathanhtoan', $don), [])
            ->assertSessionHasErrors('ly_do');
        $this->assertSame('da_thanh_toan', $don->fresh()->trang_thai);

        // khách không truy cập được (staff.only)
        $khach = User::where('role', 'khachhang')->first();
        if ($khach) {
            $this->actingAs($khach)->post(route('donhangonline.huydathanhtoan', $don), [
                'ly_do' => 'Khách tự hủy',
            ])->assertRedirect();
        }
        $this->assertSame('da_thanh_toan', $don->fresh()->trang_thai);
    }

    public function test_danh_dau_da_hoan_tien_va_nhat_ky(): void
    {
        $this->seed();
        Http::fake();
        $don = $this->donDaThanhToan();
        $admin = $this->admin();

        // Hủy trước để có can_hoan_tien
        $this->actingAs($admin)->post(route('donhangonline.huydathanhtoan', $don), [
            'ly_do' => 'Hủy để test hoàn tiền',
        ])->assertSessionHas('success');

        // Đơn xuất hiện trong danh sách cần hoàn tiền
        $this->actingAs($admin)->get(route('donhangonline.canhoantien'))
            ->assertOk()->assertSee($don->ma_hoadon);
        // thungan không vào được danh sách
        $this->actingAs(User::where('role', 'thungan')->firstOrFail())
            ->get(route('donhangonline.canhoantien'))->assertForbidden();

        // Đánh dấu đã hoàn tiền — bắt buộc ghi chú
        $this->actingAs($admin)->post(route('donhangonline.dahoantien', $don), [])
            ->assertSessionHasErrors('ghi_chu');
        $this->actingAs($admin)->post(route('donhangonline.dahoantien', $don), [
            'ghi_chu' => 'Đã chuyển khoản hoàn lại ngày 16/11',
        ])->assertSessionHas('success');

        $this->assertFalse((bool) $don->fresh()->can_hoan_tien);
        $this->assertStringContainsString('Đã hoàn tiền', (string) $don->fresh()->ghi_chu_noi_bo);

        // Nhật ký đủ 2 thao tác, không PII
        $logs = NhatKyHoatDong::where('doi_tuong', 'don_hang')->where('doi_tuong_id', $don->id)
            ->whereIn('hanh_dong', ['huy_don_da_thanh_toan', 'danh_dau_da_hoan_tien'])->get();
        $this->assertGreaterThanOrEqual(2, $logs->count());
        $this->assertStringNotContainsString('0907777888', json_encode($logs->toArray()));

        // Đánh dấu lần nữa → từ chối
        $this->actingAs($admin)->post(route('donhangonline.dahoantien', $don), [
            'ghi_chu' => 'Hoàn tiền lần hai',
        ])->assertSessionHas('error');
    }

    public function test_thieu_cot_can_hoan_tien_van_huy_duoc(): void
    {
        $this->seed();
        Http::fake();
        $don = $this->donDaThanhToan();
        $sp = SanPham::orderBy('id')->firstOrFail();

        // Giả lập DB chưa áp migration HM4
        Schema::table('invoices', function ($t): void {
            $t->dropColumn(['can_hoan_tien', 'ghi_chu_noi_bo']);
        });
        KiemTraSchema::datLai();

        $res = $this->actingAs($this->admin())->post(route('donhangonline.huydathanhtoan', $don), [
            'ly_do' => 'Hủy khi chưa migrate',
        ]);
        $res->assertSessionHas('success');
        $this->assertStringContainsString('can_hoan_tien', session('success'));

        $don = $don->fresh();
        $this->assertSame('da_huy', $don->trang_thai);
        $this->assertSame('da_huy_giao', $don->trang_thai_giao); // cột M3 vẫn còn
        $this->assertSame(10, (int) $sp->fresh()->soluong);

        // Danh sách hoàn tiền báo thiếu migration, không 500
        $this->actingAs($this->admin())->get(route('donhangonline.canhoantien'))
            ->assertOk()->assertSee('Cần áp migration');
        // Đánh dấu hoàn tiền báo lỗi rõ
        $this->actingAs($this->admin())->post(route('donhangonline.dahoantien', $don), [
            'ghi_chu' => 'Thử đánh dấu',
        ])->assertSessionHas('error');
    }
}
