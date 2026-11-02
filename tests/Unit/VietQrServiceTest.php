<?php

namespace Tests\Unit;

use App\Services\VietQrService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class VietQrServiceTest extends TestCase
{
    protected VietQrService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VietQrService();
    }

    /**
     * T61: Tạo URL đúng định dạng
     */
    public function test_t61_generates_correct_vietqr_url_format(): void
    {
        Config::set('pos.bank_code', 'VCB');
        Config::set('pos.bank_account', '0123456789');
        Config::set('pos.bank_name', 'TEN CHU TAI KHOAN');
        Config::set('pos.qr_template', 'compact2');

        $url = $this->service->url(197000, 'HD2026-0001');

        $this->assertNotNull($url);
        $this->assertStringStartsWith('https://img.vietqr.io/image/VCB-0123456789-compact2.png', $url);
        $this->assertStringContainsString('amount=197000', $url);
        $this->assertStringContainsString('addInfo=HD20260001', $url);
        $this->assertStringContainsString('accountName=TEN%20CHU%20TAI%20KHOAN', $url);
    }

    /**
     * T62: Chưa cấu hình thì url() trả null
     */
    public function test_t62_returns_null_when_unconfigured(): void
    {
        Config::set('pos.bank_code', '');
        Config::set('pos.bank_account', '');

        $this->assertFalse($this->service->enabled());
        $this->assertNull($this->service->url(100000, 'HD001'));

        Config::set('pos.bank_code', 'VCB');
        Config::set('pos.bank_account', '');
        $this->assertFalse($this->service->enabled());
        $this->assertNull($this->service->url(100000, 'HD001'));
    }

    /**
     * T63: Số tiền <= 0 thì url() trả null
     */
    public function test_t63_returns_null_when_amount_is_zero_or_negative(): void
    {
        Config::set('pos.bank_code', 'VCB');
        Config::set('pos.bank_account', '0123456789');

        $this->assertNull($this->service->url(0, 'HD001'));
        $this->assertNull($this->service->url(-50000, 'HD001'));
    }

    /**
     * T64: Làm sạch nội dung tiếng Việt có dấu, ký tự lạ và cắt tối đa 25 ký tự
     */
    public function test_t64_cleans_transfer_content_properly(): void
    {
        $rawText = 'Thanh toán hóa đơn phụ kiện điện thoại Hoco cực xịn xò 100%!! @#$';
        $cleaned = VietQrService::cleanNoiDung($rawText);

        // Bỏ dấu: Thanh toan hoa don...
        $this->assertDoesNotMatchRegularExpression('/[àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ]/iu', $cleaned);
        // Không chứa ký tự đặc biệt như %, !, @, #, $
        $this->assertDoesNotMatchRegularExpression('/[%!@#$]/', $cleaned);
        // Độ dài tối đa 25 ký tự
        $this->assertLessThanOrEqual(25, mb_strlen($cleaned));
    }

    /**
     * T65: Template không hợp lệ tự động fallback về compact2
     */
    public function test_t65_invalid_template_falls_back_to_compact2(): void
    {
        Config::set('pos.bank_code', 'MB');
        Config::set('pos.bank_account', '9876543210');
        Config::set('pos.qr_template', 'invalid_template_name');

        $url = $this->service->url(250000, 'HD999');

        $this->assertNotNull($url);
        $this->assertStringStartsWith('https://img.vietqr.io/image/MB-9876543210-compact2.png', $url);
    }
}
