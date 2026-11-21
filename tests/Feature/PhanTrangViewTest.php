<?php

namespace Tests\Feature;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Request;
use Tests\TestCase;

class PhanTrangViewTest extends TestCase
{
    private function renderView(int $current, int $last, int $total = 0): string
    {
        $total = $total ?: $last * 10;
        $items = array_fill(0, 10, (object) ['id' => 1]);
        $paginator = new LengthAwarePaginator(
            $items,
            $total,
            10,
            $current,
            ['path' => '/admin/donhang']
        );

        return view('vendor.pagination.app', ['paginator' => $paginator])->render();
    }

    public function test_render_dung_mot_bo_nut_va_giu_query_string(): void
    {
        Request::swap(Request::create('/admin/donhang?trang_thai=cho', 'GET'));

        $html = $this->renderView(2, 5);

        $this->assertSame(1, substr_count($html, '<nav'));
        $this->assertSame(1, substr_count($html, 'Trước'));
        $this->assertSame(1, substr_count($html, 'Sau'));
        $this->assertStringContainsString('trang_thai=cho', $html);
        $this->assertStringContainsString('page=3', $html);
    }

    public function test_khong_render_gi_khi_chi_mot_trang(): void
    {
        Request::swap(Request::create('/admin/donhang', 'GET'));

        $html = $this->renderView(1, 1, 3);

        $this->assertSame('', trim($html));
    }
}
