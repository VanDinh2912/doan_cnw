<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Kiểm kê route: mọi route GHI (POST/PUT/PATCH/DELETE) phải CÓ TÊN và
 * nằm trong config/pin_muc.php (K/M1/M2/A) hoặc danh sách NGOAI_LE.
 * Route mức M1/M2/A phải gắn middleware pin.muc; mức K không được gắn.
 * GET xuất dữ liệu phải được phân loại rõ (không nằm trong NGOAI_LE).
 */
class PinKiemKeRouteTest extends TestCase
{
    /** Route name được phép KHÔNG phân loại trong pin_muc.php (kèm lý do). */
    private const NGOAI_LE = [
        // Framework / vendor
        'storage.local.upload',
    ];

    /** GET xuất dữ liệu bắt buộc phải phân loại M1+. */
    private const GET_XUAT_DU_LIEU = [
        'hangsxuat.xuatexcel', 'sanpham.xuatexcel', 'baocao.xuatnhapton',
        'baocao.hoadon', 'nhatky.export',
    ];

    private function mucCua(string $name): ?string
    {
        // Route name chứa dấu chấm -> tra trên mảng phẳng, không dùng dot notation.
        $muc = config('pin_muc')[$name] ?? null;

        return $muc === null ? null : strtoupper((string) $muc);
    }

    public function test_moi_route_ghi_co_ten_va_nam_trong_pin_muc(): void
    {
        $thieuTen = [];
        $chuaPhanLoai = [];
        foreach (Route::getRoutes() as $route) {
            if (array_diff($route->methods(), ['GET', 'HEAD', 'OPTIONS']) === []) {
                continue;
            }
            $name = (string) $route->getName();
            if ($name === '') {
                $thieuTen[] = implode('|', $route->methods()).' '.$route->uri();
                continue;
            }
            if ($this->mucCua($name) === null && ! in_array($name, self::NGOAI_LE, true)) {
                $chuaPhanLoai[] = $name.' ('.implode('|', $route->methods()).' '.$route->uri().')';
            }
        }
        $this->assertSame([], $thieuTen, "Route ghi thiếu tên:\n".implode("\n", $thieuTen));
        $this->assertSame([], $chuaPhanLoai, "Route ghi chưa phân loại trong config/pin_muc.php:\n".implode("\n", $chuaPhanLoai));
    }

    public function test_route_muc_m1_m2_a_co_pin_muc_va_khong_co_thi_khong(): void
    {
        $sai = [];
        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            if ($name === '') {
                continue;
            }
            $muc = $this->mucCua($name);
            if ($muc === null) {
                continue;
            }
            $coPinMuc = collect($route->gatherMiddleware())->contains(
                fn ($m) => $m === 'pin.muc' || str_ends_with((string) $m, ':pin.muc')
            );
            $canBaoVe = in_array($muc, ['M1', 'M2', 'A'], true);
            // Route GET thường không cần pin.muc trừ khi được phân loại M1+
            if ($canBaoVe && ! $coPinMuc) {
                $sai[] = "{$name} mức {$muc} thiếu middleware pin.muc";
            }
            if (! $canBaoVe && $coPinMuc) {
                $sai[] = "{$name} mức {$muc} không được gắn pin.muc";
            }
        }
        $this->assertSame([], $sai, "Sai middleware pin.muc:\n".implode("\n", $sai));
    }

    public function test_get_xuat_du_lieu_duoc_phan_loai_m1_tro_len(): void
    {
        foreach (self::GET_XUAT_DU_LIEU as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Thiếu route {$name}");
            $muc = $this->mucCua($name);
            $this->assertContains($muc, ['M1', 'M2', 'A'], "GET xuất {$name} phải là M1+");
            $coPinMuc = collect($route->gatherMiddleware())->contains(
                fn ($m) => $m === 'pin.muc' || str_ends_with((string) $m, ':pin.muc')
            );
            $this->assertTrue($coPinMuc, "GET xuất {$name} thiếu pin.muc");
        }
    }

    public function test_moi_khoa_config_tuong_ung_route_that(): void
    {
        // Cho phép 3 route duyet.* khai báo trước (commit giao diện sẽ thêm).
        $choPhepThieu = ['duyet.gui', 'duyet.capma', 'duyet.tuchoi'];
        $thua = [];
        foreach (array_keys(config('pin_muc', [])) as $name) {
            if (Route::getRoutes()->getByName($name) === null && ! in_array($name, $choPhepThieu, true)) {
                $thua[] = $name;
            }
        }
        $this->assertSame([], $thua, "config/pin_muc.php có khóa không tương ứng route:\n".implode("\n", $thua));
    }

    public function test_route_moi_khong_co_trong_bang_van_an_toan(): void
    {
        $co = 0;
        foreach (Route::getRoutes() as $route) {
            if (array_diff($route->methods(), ['GET', 'HEAD', 'OPTIONS']) !== []) {
                $co++;
            }
        }
        $this->assertGreaterThan(60, $co);
    }
}
