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
        // Bản đồ route name -> muc tu routes/web.php
        $src = file_get_contents(base_path('routes/web.php'));
        preg_match_all("/->name\(\x27([a-z0-9._]+)\x27\)->middleware\(\x27xac\.nhan\.pin:(\w+)\x27\)/", $src, $m, PREG_SET_ORDER);
        $map = [];
        foreach ($m as $r) {
            $map[$r[1]] = $r[2];
        }
        // route dat middleware truoc name (vd taikhoan.datlaipin)
        preg_match_all("/middleware\(\[?'xac\.nhan\.pin:(\w+)'?\]?\)?[^\n]*->name\(\x27([a-z0-9._]+)\x27\)/", $src, $m2, PREG_SET_ORDER);
        foreach ($m2 as $r) {
            $map[$r[2]] = $r[1];
        }
        $this->assertNotEmpty($map, 'Không đọc được route PIN từ web.php');

        $thieu = [];
        $tong = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($iterator as $f) {
            if ($f->isDir() || substr($f->getFilename(), -10) !== '.blade.php') {
                continue;
            }
            $c = file_get_contents($f->getPathname());
            foreach ($map as $name => $muc) {
                if (! preg_match_all("/<form\\b[^>]*route\\(\\s*[\x27\"]".preg_quote($name, '/')."[\x27\"][^>]*>/s", $c, $ms)) {
                    continue;
                }
                foreach ($ms[0] as $tag) {
                    $tong++;
                    if (! preg_match('/data-can-pin="(\w+)"/', $tag, $dm) || $dm[1] !== $muc) {
                        $thieu[] = $f->getFilename().' :: '.$name.' (can '.$muc.')';
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
