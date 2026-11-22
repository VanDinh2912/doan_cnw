<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Kiểm kê giao diện: mọi <form> trong blade có action trỏ tới route được
 * bảo vệ (pin.muc, mức M1/M2/A trong config/pin_muc.php) phải có
 * data-can-pin="m1|m2|a" tương ứng — nếu thiếu, middleware sẽ
 * redirect-back và MẤT dữ liệu form đã nhập.
 */
class PinFormKiemKeTest extends TestCase
{
    public function test_moi_form_toi_route_bao_ve_deu_co_data_can_pin(): void
    {
        config(['pos.pin_bat' => true]);
        // Route name -> mức pin.muc (chỉ route thật sự gắn pin.muc).
        $map = [];
        foreach (Route::getRoutes() as $route) {
            $coPinMuc = collect($route->gatherMiddleware())->contains(
                fn ($m) => $m === 'pin.muc' || str_ends_with((string) $m, ':pin.muc')
            );
            if (! $coPinMuc) {
                continue;
            }
            $muc = config('pin_muc')[(string) $route->getName()] ?? null;
            if ($muc && in_array(strtoupper((string) $muc), ['M1', 'M2', 'A'], true)) {
                $map[(string) $route->getName()] = strtolower((string) $muc);
            }
        }
        $this->assertNotEmpty($map, 'Không tìm thấy route pin.muc nào');

        $thieu = [];
        $tong = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($iterator as $f) {
            if ($f->isDir() || substr($f->getFilename(), -10) !== '.blade.php') {
                continue;
            }
            $c = file_get_contents($f->getPathname());
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
                    // data-can-pin tĩnh "m1" hoặc biểu thức blade chứa 'm1'
                    $ok = preg_match('/data-can-pin="(m1|m2|a)"/', $tag, $dm) && $dm[1] === $muc;
                    if (! $ok && str_contains($tag, "data-can-pin=\"{{") && str_contains($tag, "'{$muc}'")) {
                        $ok = true;
                    }
                    if (! $ok) {
                        $thieu[] = $f->getFilename().' :: '.$routeName.' (can '.$muc.')';
                    }
                }
            }
        }
        $this->assertGreaterThan(0, $tong, 'Không tìm thấy form nào tới route bảo vệ — kiểm tra lại regex');
        $this->assertSame([], $thieu, "Form thiếu/sai data-can-pin:\n".implode("\n", $thieu));
    }

    public function test_layout_da_nap_pin_js(): void
    {
        $master = file_get_contents(resource_path('views/layouts/master.blade.php'));
        $this->assertStringContainsString('js/pin.js', $master);
        $this->assertFileExists(public_path('js/pin.js'));
    }
}
