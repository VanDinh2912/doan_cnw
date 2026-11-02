<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PdfInvoiceParserTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_manager_can_parse_sample_pdf_invoice(): void
    {
        $this->seed();
        $stockManager = User::where('role', 'thukho')->firstOrFail();

        $samplePdfPath = base_path('TaiLieu/ThongTinHangHoa/hoa-don-mau-300-mat-hang-phu-kien-dien-thoai.pdf');
        $this->assertFileExists($samplePdfPath);

        $file = new UploadedFile(
            $samplePdfPath,
            'hoa-don-mau.pdf',
            'application/pdf',
            null,
            true
        );

        $response = $this->actingAs($stockManager)
            ->post(route('hoadonnhap.boctachpdf'), [
                'file_pdf' => $file,
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'header' => [
                'so_hoadon' => '0000123',
                'ky_hieu' => '1C26TMM',
                'ngay_hoadon' => '2026-11-01',
                'supplier_id' => 81,
            ],
            'summary' => [
                'total_items' => 300,
                'total_quantity' => 2405,
            ],
        ]);
    }

    public function test_cashier_cannot_access_pdf_parsing_endpoint(): void
    {
        $this->seed();
        $cashier = User::where('role', 'thungan')->firstOrFail();

        $file = UploadedFile::fake()->create('fake_invoice.pdf', 100, 'application/pdf');

        $response = $this->actingAs($cashier)
            ->post(route('hoadonnhap.boctachpdf'), [
                'file_pdf' => $file,
            ]);

        $response->assertForbidden();
    }

    public function test_parsing_invalid_pdf_returns_error_response(): void
    {
        $this->seed();
        $stockManager = User::where('role', 'thukho')->firstOrFail();

        $file = UploadedFile::fake()->createWithContent('corrupted.pdf', 'Not a real PDF file content');

        $response = $this->actingAs($stockManager)
            ->post(route('hoadonnhap.boctachpdf'), [
                'file_pdf' => $file,
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => false,
            'parsing_status' => 'failed',
        ]);
    }
}
