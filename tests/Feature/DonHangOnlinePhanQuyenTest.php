<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\SanPham;
use App\Models\User;
use App\Services\ThanhToanChuyenKhoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CHỐT TRIỂN KHAI HM1 — ma trận vai trò × thao tác ở màn hình đơn online.
 * admin/thungan/thukho: xem danh sách/chi tiết/badge/phiếu giao + chuyển trạng thái giao.
 * thungan (+admin): xử lý pickup (xác nhận/hủy/gia hạn). thukho KHÔNG được.
 * admin: hủy đơn đã thanh toán + đánh dấu hoàn tiền.
 */
class DonHangOnlinePhanQuyenTest extends TestCase
{
    use RefreshDatabase;

    private function donGiaoDaTT(): DonHang
    {
        $sp = SanPham::orderBy('id')->firstOrFail();
        $sp->update(['soluong' => 10, 'trang_thai' => true]);
        $don = app(ThanhToanChuyenKhoanService::class)->taoHoaDonChoThanhToan(
            [['id' => $sp->id, 'soluong' => 1]], [], null,
            [
                'kenh' => 'online', 'phuong_thuc' => 'chuyenkhoan',
                'hinh_thuc_nhan' => 'giao_tan_noi',
                'ma_tra_cuu' => 'PQ'.random_int(100000, 999999),
                'ten_khach_dat' => 'Khách PQ', 'so_dienthoai_dat' => '0901111222',
                'ten_nguoi_nhan' => 'Người Nhận', 'sdt_nguoi_nhan' => '0901111222',
                'dia_chi_giao' => '5 Hai Bà Trưng, Q1',
                'han_giu' => now()->addMinutes(30),
            ]
        );
        $don->update(['trang_thai' => 'da_thanh_toan', 'thoi_gian_thanh_toan' => now(), 'trang_thai_giao' => 'cho_xac_nhan']);

        return $don->fresh();
    }

    private function donPickupCho(): DonHang
    {
        $sp = SanPham::orderBy('id')->firstOrFail();
        $sp->update(['soluong' => 10, 'trang_thai' => true]);
        $sdt = '09'.random_int(10000000, 99999999);

        return app(ThanhToanChuyenKhoanService::class)->taoHoaDonChoThanhToan(
            [['id' => $sp->id, 'soluong' => 1]], [], null,
            [
                'kenh' => 'online', 'phuong_thuc' => 'tienmat',
                'hinh_thuc_nhan' => 'tai_cua_hang',
                'ma_tra_cuu' => 'PQ'.random_int(100000, 999999),
                'ten_khach_dat' => 'Khách PQ', 'so_dienthoai_dat' => $sdt,
                'han_giu' => now()->addHours(4),
            ]
        );
    }

    private function donChoVeHuy(ThanhToanChuyenKhoanService $service, DonHang $don): void
    {
        $fresh = $don->fresh();
        if ($fresh && $fresh->trang_thai === 'cho_thanh_toan') {
            $service->huyChoThanhToan($fresh, 'Dọn test');
        }
    }

    private function user(string $role): ?User
    {
        return User::where('role', $role)->first();
    }

    /** GET endpoints nhân viên: cả 3 vai trò được, khách redirect, guest redirect login. */
    public function test_ma_tran_xem_don_online_theo_vai_tro(): void
    {
        $this->seed();
        config(['pos.online_pickup_gio' => 4, 'pos.online_gia_han_toi_da_gio' => 8]);
        $donGiao = $this->donGiaoDaTT();
        $donHuy = $this->donGiaoDaTT(); // cho thao tác hủy/đánh dấu

        $routesXem = [
            'danh sách' => fn () => route('donhangonline.danhsach'),
            'chi tiết' => fn () => route('donhangonline.chitiet', $donGiao),
            'badge' => fn () => route('donhangonline.soluong'),
            'phiếu giao' => fn () => route('donhangonline.phieugiaohang', $donGiao),
        ];

        foreach (['admin', 'thungan', 'thukho'] as $role) {
            $u = $this->user($role);
            $this->assertNotNull($u, "Thiếu user role $role trong seeder");
            foreach ($routesXem as $ten => $url) {
                $res = $this->actingAs($u)->get($url());
                $this->assertNotSame(403, $res->status(), "$role bị 403 ở $ten");
                $this->assertNotSame(302, $res->status(), "$role bị redirect ở $ten");
            }
        }

        // Khách hàng → staff.only redirect về storefront (không lộ dữ liệu)
        $khach = $this->user('khachhang');
        if ($khach) {
            foreach ($routesXem as $ten => $url) {
                $this->actingAs($khach)->get($url())->assertRedirect();
            }
        }
        // Chưa đăng nhập → redirect login
        foreach ($routesXem as $ten => $url) {
            $this->get($url())->assertRedirect();
        }
    }

    /** POST chuyển trạng thái giao: cả 3 vai trò được phép. */
    public function test_ma_tran_chuyen_trang_thai_giao(): void
    {
        $this->seed();
        foreach (['admin', 'thungan', 'thukho'] as $role) {
            $u = $this->user($role);
            $this->assertNotNull($u);
            $don = $this->donGiaoDaTT();
            $res = $this->actingAs($u)->post(route('donhangonline.trangthai', $don), [
                'trang_thai_giao_moi' => 'da_xac_nhan',
            ]);
            $this->assertNotSame(403, $res->status(), "$role bị 403 chuyển trạng thái giao");
            $this->assertSame('da_xac_nhan', $don->fresh()->trang_thai_giao);
        }
    }

    /** Pickup: xác nhận/hủy/gia hạn — admin+thungan OK, thukho 403, khách redirect, guest redirect. */
    public function test_ma_tran_xu_ly_pickup_theo_vai_tro(): void
    {
        $this->seed();
        config(['pos.online_pickup_gio' => 4, 'pos.online_gia_han_toi_da_gio' => 8]);
        $admin = $this->user('admin');
        $thuNgan = $this->user('thungan');
        $thuKho = $this->user('thukho');
        $this->assertNotNull($admin);
        $this->assertNotNull($thuNgan);
        $this->assertNotNull($thuKho);

        $service = app(ThanhToanChuyenKhoanService::class);
        $actions = ['xacnhan', 'huycho', 'giahan'];

        foreach ($actions as $ten) {
            // admin + thungan được phép (không 403)
            foreach (['admin', 'thungan'] as $role) {
                $u = $this->user($role);
                $don2 = $this->donPickupCho();
                $res = $this->actingAs($u)->postJson(route('donhang.'.$ten, $don2));
                $this->assertNotSame(403, $res->status(), "$role bị 403 ở pickup.$ten");
                $this->assertNotSame(401, $res->status());
                $this->donChoVeHuy($service, $don2);
            }
            // thủ kho → 403
            $don3 = $this->donPickupCho();
            $this->actingAs($thuKho)->postJson(route('donhang.'.$ten, $don3))->assertForbidden();
            $this->donChoVeHuy($service, $don3);
            // khách → redirect (staff.only), guest → 401/redirect
            $khach = $this->user('khachhang');
            if ($khach) {
                $don4 = $this->donPickupCho();
                $res = $this->actingAs($khach)->postJson(route('donhang.'.$ten, $don4));
                $this->assertContains($res->status(), [302, 401]);
                $this->donChoVeHuy($service, $don4);
            }
            $don5 = $this->donPickupCho();
            $res = $this->postJson(route('donhang.'.$ten, $don5));
            $this->assertContains($res->status(), [302, 401]);
            $this->donChoVeHuy($service, $don5);
        }
    }

    /** Hủy đơn đã thanh toán + hoàn tiền: chỉ admin; thungan/thukho 403. */
    public function test_ma_tran_huy_da_thanh_toan_va_hoan_tien(): void
    {
        $this->seed();
        $admin = $this->user('admin');
        $thuNgan = $this->user('thungan');
        $thuKho = $this->user('thukho');
        $this->assertNotNull($admin);
        $this->assertNotNull($thuNgan);
        $this->assertNotNull($thuKho);

        // GET danh sách cần hoàn tiền: admin OK, thungan/thukho 403
        $this->actingAs($admin)->get(route('donhangonline.canhoantien'))->assertOk();
        $this->actingAs($thuNgan)->get(route('donhangonline.canhoantien'))->assertForbidden();
        $this->actingAs($thuKho)->get(route('donhangonline.canhoantien'))->assertForbidden();

        foreach (['thungan', 'thukho'] as $role) {
            $u = $this->user($role);
            $don = $this->donGiaoDaTT();
            $this->actingAs($u)->post(route('donhangonline.huydathanhtoan', $don), [
                'ly_do' => 'Thử hủy trái phép',
            ])->assertForbidden();
            $this->assertSame('da_thanh_toan', $don->fresh()->trang_thai);

            $this->actingAs($u)->post(route('donhangonline.dahoantien', $don), [
                'ghi_chu' => 'Thử đánh dấu trái phép',
            ])->assertForbidden();
        }

        // Khách/guest không truy cập được
        $don = $this->donGiaoDaTT();
        $khach = $this->user('khachhang');
        if ($khach) {
            $this->actingAs($khach)->post(route('donhangonline.huydathanhtoan', $don), [
                'ly_do' => 'Khách tự hủy',
            ])->assertRedirect();
        }
        $this->post(route('donhangonline.huydathanhtoan', $don), [
            'ly_do' => 'Guest tự hủy',
        ])->assertRedirect();
        $this->assertSame('da_thanh_toan', $don->fresh()->trang_thai);
    }
}
