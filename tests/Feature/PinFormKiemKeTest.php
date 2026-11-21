<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Kiểm kê giao diện: mọi <form> trong blade có action trỏ tới route đã gắn
 * xac.nhan.pin phải có data-can-pin đúng mức — nếu thiếu, middleware sẽ
 * redirect-back và MẤT dữ liệu form đã nhập.
 */
class PinFormKiemKeTest extends TestCase
{
    public function test_moi_form_toi_route_pin_deu_co_data_can_pin(): void
    {
        config(['pos.pin_bat' => true]);
        // Resolve route name -> muc PIN qua RouteCollection (bat dung ca ten
        // co prefix group nhu taikhoan.update — cach doc text routes/web.php
        // khong bat duoc vi ->name('update') nam trong group ->name('taikhoan.')).
        $map = [];
        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $m) {
                if (preg_match('/^xac\.nhan\.pin:(\w+)$/', (string) $m, $mm)) {
                    $map[(string) $route->getName()] = $mm[1];
                }
            }
        }
        $this->assertNotEmpty($map, 'Không tìm thấy route PIN nào');

        $thieu = [];
        $tong = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($iterator as $f) {
            if ($f->isDir() || substr($f->getFilename(), -10) !== '.blade.php') {
                continue;
            }
            $c = file_get_contents($f->getPathname());
            // Ten route co the co prefix group -> lay phan sau dau . cuoi? Khong:
            // quet moi route('...') trong the <form> roi tra cuu map.
            if (! preg_match_all('/<form\b[^>]*>/s', $c, $formTags)) {
                continue;
            }
            foreach ($formTags[0] as $tag) {
                if (! preg_match_all("/route\\(\\s*[\x27\"]([a-zA-Z0-9._-]+)[\x27\"]/", $tag, $rm)) {
                    continue;
                }
                foreach ($rm[1] as $routeName) {
                    if (! isset($map[$routeName])) {
                        continue;
                    }
                    $muc = $map[$routeName];
                    $tong++;
                    if (! preg_match('/data-can-pin="(\w+)"/', $tag, $dm) || $dm[1] !== $muc) {
                        $thieu[] = $f->getFilename().' :: '.$routeName.' (can '.$muc.')';
                    }
                }
            }
        }
        $this->assertGreaterThan(0, $tong, 'Không tìm thấy form nào tới route PIN — kiểm tra lại regex');
        $this->assertSame([], $thieu, "Form thiếu/sai data-can-pin:\n".implode("\n", $thieu));
    }

    public function test_layout_da_nap_pin_js(): void
    {
        $master = file_get_contents(resource_path('views/layouts/master.blade.php'));
        $this->assertStringContainsString('js/pin.js', $master);
        $this->assertFileExists(public_path('js/pin.js'));
    }
}
