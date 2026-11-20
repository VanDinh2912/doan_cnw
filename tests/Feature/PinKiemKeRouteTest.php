<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Kiểm kê route: mọi route GHI (POST/PUT/PATCH/DELETE) và GET xuất dữ liệu
 * phải có middleware xac.nhan.pin HOẶC nằm trong danh sách ngoại lệ KHÔNG
 * tường minh. Route mới thêm mà chưa phân loại -> test FAIL.
 */
class PinKiemKeRouteTest extends TestCase
{
    /** Route name được phép KHÔNG có PIN (kèm lý do trong comment). */
    private const NGOAI_LE = [
        // Auth / khách / storefront
        'auth.postdangky', 'auth.postdangnhap', 'doi-mat-khau.post',
        'dat-hang.dat', 'don-hang.posttracuu', 'don-hang.huy', 'don-hang.poll',
        'storefront.cart.update', 'storefront.cart.clear',
        'giohang.capnhat', 'giohang.lammoi',
        'lien-he.gui', 'tintuc.binhluan', 'profile.update',
        // POS bán hàng hằng ngày (không làm chậm thu ngân)
        'donhang.giahan', 'donhang.huycho', 'donhang.taoqr', 'donhang.thanhtoan',
        'hold.giu', 'hold.xoa', 'khachhang.nhanh',
        // Import: bước upload/preview không ghi DB
        'sanpham.import.upload',
        // Hạ tầng PIN — chính cơ chế xác thực
        'pin.kiemtra', 'pin.xacnhan', 'pin.doi.post',
        // Webhook + framework
        'webhook.thanhtoan', 'storage.local.upload',
    ];

    /** GET xuất dữ liệu bắt buộc phải phân loại (phải có middleware PIN). */
    private const GET_XUAT_DU_LIEU = [
        'hangsxuat.xuatexcel', 'sanpham.xuatexcel', 'baocao.xuatnhapton',
        'baocao.hoadon', 'nhatky.export',
    ];

    public function test_moi_route_ghi_deu_co_pin_hoac_ngoai_le(): void
    {
        config(['pos.pin_bat' => true]);
        $thieu = [];
        foreach (Route::getRoutes() as $route) {
            $methods = array_diff($route->methods(), ['GET', 'HEAD', 'OPTIONS']);
            if ($methods === []) {
                continue;
            }
            $name = (string) $route->getName();
            if (in_array($name, self::NGOAI_LE, true)) {
                continue;
            }
            $coPin = collect($route->gatherMiddleware())->contains(
                fn ($m) => str_contains((string) $m, 'xac.nhan.pin')
            );
            if (! $coPin) {
                $thieu[] = implode('|', $methods).' '.$route->uri().' ('.$name.')';
            }
        }
        $this->assertSame([], $thieu, "Route chưa phân loại PIN:\n".implode("\n", $thieu));
    }

    public function test_get_xuat_du_lieu_deu_co_middleware(): void
    {
        config(['pos.pin_bat' => true]);
        foreach (self::GET_XUAT_DU_LIEU as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Thiếu route {$name}");
            $coPin = collect($route->gatherMiddleware())->contains(
                fn ($m) => str_contains((string) $m, 'xac.nhan.pin')
            );
            $this->assertTrue($coPin, "GET xuất dữ liệu {$name} thiếu xac.nhan.pin");
        }
    }

    public function test_route_moi_khong_co_trong_bang_van_an_toan(): void
    {
        // Sanity: số route ghi ngoài lệ + có PIN phải > 0
        $co = 0;
        foreach (Route::getRoutes() as $route) {
            if (array_diff($route->methods(), ['GET', 'HEAD', 'OPTIONS']) !== []) {
                $co++;
            }
        }
        $this->assertGreaterThan(60, $co);
    }
}
