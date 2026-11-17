<?php

namespace Tests\Feature;

use App\Exports\HoaDonExport;
use App\Exports\XuatNhapTonExport;
use App\Excel\FormulaSafeBinder;
use App\Models\NhatKyHoatDong;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Tests\TestCase;

/**
 * Chong CSV/Excel formula injection o cac export co san
 * (HoaDonExport, XuatNhapTonExport, NhatKyController::export).
 */
class FormulaInjectionExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_binder_ep_chuoi_cho_gia_tri_nguy_hiểm(): void
    {
        $binder = new FormulaSafeBinder();
        $sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet();
        $cell = $sheet->getCell('A1');

        foreach (['=1+1', '+cmd', '-2+3', '@SUM(1)', "\t=1", "\r=1"] as $nguyHiem) {
            $binder->bindValue($cell, $nguyHiem);
            $this->assertSame(DataType::TYPE_STRING, $cell->getDataType(), "Gia tri '{$nguyHiem}' phai la string");
        }

        // Gia tri thuong van di qua binder mac dinh
        $binder->bindValue($cell, 'san pham thuong');
        $this->assertSame('san pham thuong', $cell->getValue());
        $binder->bindValue($cell, 150000);
        $this->assertSame(150000, $cell->getValue());
    }

    public function test_export_cu_dung_binder_an_toan(): void
    {
        $this->assertContains(
            \Maatwebsite\Excel\Concerns\WithCustomValueBinder::class,
            class_implements(HoaDonExport::class)
        );
        $this->assertContains(
            \Maatwebsite\Excel\Concerns\WithCustomValueBinder::class,
            class_implements(XuatNhapTonExport::class)
        );
    }

    public function test_csv_nhat_ky_escape_cong_thuc(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        NhatKyHoatDong::create([
            'user_id' => $admin->id, 'hanh_dong' => 'test', 'doi_tuong' => 'SanPham',
            'doi_tuong_id' => 1, 'mo_ta' => '=HYPERLINK("http://evil")', 'ip' => '127.0.0.1',
        ]);
        $this->actingAs($admin);
        $res = $this->get(route('nhatky.export'));
        $res->assertOk();
        $content = $res->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertStringNotContainsString('"=HYPERLINK', $content);
    }
}
