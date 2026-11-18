<?php

namespace Tests\Unit;

use App\Services\Payment\SepayWebhook;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SepayWebhookTest extends TestCase
{
    private SepayWebhook $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SepayWebhook();
    }

    /**
     * Mã đơn phải được nhận diện qua mọi biến thể nội dung chuyển khoản
     * mà ngân hàng/SePay có thể trả về.
     */
    #[DataProvider('maDonHopLeProvider')]
    public function test_tim_ma_hoa_don_cac_bien_the(string $noiDung, string $mongDoi): void
    {
        $this->assertSame($mongDoi, $this->service->timMaHoaDon($noiDung));
    }

    public static function maDonHopLeProvider(): array
    {
        return [
            'gach ngang' => ['HD20261118-0001', 'HD20261118-0001'],
            'khong gach ngang' => ['HD202611180001', 'HD20261118-0001'],
            'chu thuong' => ['hd20261118-0001', 'HD20261118-0001'],
            'co khoang trang' => ['HD20261118 0001', 'HD20261118-0001'],
            'ngan hang chen ky tu' => ['MBVCB.1234.HD202611180001.CT tu NGUYEN VAN A', 'HD20261118-0001'],
            'chu xen ke' => ['CK HD20261118 0001 CAM ON', 'HD20261118-0001'],
            'duoi chuoi dai' => ['HD20261118-0001 extra text', 'HD20261118-0001'],
        ];
    }

    /**
     * 12 chữ số ngẫu nhiên không có tiền tố HD tuyệt đối không được khớp,
     * tránh nhận nhầm mã tham chiếu ngân hàng thành mã hóa đơn.
     */
    #[DataProvider('khongPhaiMaDonProvider')]
    public function test_khong_khop_ma_hoa_don_gia(string $noiDung): void
    {
        $this->assertNull($this->service->timMaHoaDon($noiDung));
    }

    public static function khongPhaiMaDonProvider(): array
    {
        return [
            '12 so ngau nhien' => ['123456789012'],
            'so trong cau' => ['KH chuyen 123456789012 cam on'],
            'khong co ma' => ['KHONG CO MA DON'],
            'ma ngan' => ['HD2026111-8000'],
            'tien to khac' => ['ORD202611180001'],
            'ref ngan hang' => ['FT24012345678'],
        ];
    }
}
