<?php

namespace Tests\Feature;

use App\Http\Middleware\ValidateUtf8Encoding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Middleware ValidateUtf8Encoding: byte sai UTF-8 trong input web
 * -> ValidationException (form: loi validate; JSON: 422). Khong anh huong
 * file upload, NFC/NFD tieng Viet hop le, webhook (withoutMiddleware web).
 */
class Utf8EncodingTest extends TestCase
{
    use RefreshDatabase;

    private function middlewarexuLy(Request $request): void
    {
        $middleware = new ValidateUtf8Encoding();
        $middleware->handle($request, fn () => new \Symfony\Component\HttpFoundation\Response('ok'));
    }

    public function test_tieng_viet_nfc_va_nfd_hop_le_khong_bi_chan(): void
    {
        // NFC: "ệ" don ky tu; NFD: "ệ" = e + dau rieng
        $nfc = 'Nguyễn Văn Ệ';
        $nfd = \Normalizer::normalize('Nguyễn Văn Ệ', \Normalizer::FORM_D);
        $this->assertNotSame($nfc, $nfd); // dam bao dang thu 2 khac biet

        $req = Request::create('/lien-he', 'POST', ['ho_ten' => $nfc]);
        $this->middlewarexuLy($req); // khong nem loi

        $req2 = Request::create('/lien-he', 'POST', ['ho_ten' => $nfd]);
        $this->middlewarexuLy($req2);

        $this->assertTrue(true);
    }

    public function test_byte_sai_trong_value_nem_validation_exception(): void
    {
        // \xC3\x28 la chuoi byte khong hop le UTF-8
        $req = Request::create('/lien-he', 'POST', ['ly_do_khoa' => "ly do \xC3\x28 sai"]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('ký tự không hợp lệ');
        $this->middlewarexuLy($req);
    }

    public function test_byte_sai_trong_key_va_mang_long_nhau(): void
    {
        $req = Request::create('/lien-he', 'POST', [
            'binh_thuong' => 'ok',
            'mang' => ['cap1' => ['cap2' => "gia tri \xFF\xFE loi"]],
        ]);
        try {
            $this->middlewarexuLy($req);
            $this->fail('phai nem ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('utf8', $e->errors());
        }

        // Key chua byte loi (key hop le trong PHP van co the la byte tho)
        $badKey = "khoa\xC3\x28";
        $req2 = Request::create('/lien-he', 'POST', [$badKey => 'v']);
        $this->expectException(ValidationException::class);
        $this->middlewarexuLy($req2);
    }

    public function test_file_upload_khong_bi_chan_nham(): void
    {
        $file = UploadedFile::fake()->create('test.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $req = Request::create('/sanpham/import-excel', 'POST', ['ghi_chu' => 'ok'], [], ['file' => $file]);
        $this->middlewarexuLy($req); // khong nem loi
        $this->assertTrue(true);
    }

    public function test_post_ajax_byte_sai_tra_422_json(): void
    {
        // Form-encoded + Accept JSON: ValidationException -> 422 JSON co key 'utf8'.
        // (Body JSON tho chua byte loi khong parse duoc -> Laravel da tra 422
        //  o tang parse request, khong can middleware.)
        $this->post('/lien-he', ['ho_ten' => "ten \xC3\x28"], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('utf8');
    }

    public function test_post_form_byte_sai_quay_lai_kem_loi_khong_500(): void
    {
        $this->post('/lien-he', ['ho_ten' => "ten \xC3\x28"])
            ->assertSessionHasErrors('utf8');
    }

    public function test_webhook_khong_bi_anh_huong(): void
    {
        // /api/webhook/thanhtoan dung withoutMiddleware('web') -> middleware
        // khong chay; response phai la loi nghiep vu (401 thieu key), khong phai 422 utf8.
        $res = $this->call('POST', '/api/webhook/thanhtoan', [], [], [], ['CONTENT_TYPE' => 'application/json'], "{\"a\":\"\\xC3\\x28\"}");
        $this->assertNotSame(422, $res->getStatusCode());
    }
}
