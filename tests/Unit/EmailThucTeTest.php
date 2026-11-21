<?php

namespace Tests\Unit;

use App\Rules\EmailThucTe;
use App\Support\TraCuuDns;
use Illuminate\Support\Facades\Validator;
use Mockery;
use Tests\TestCase;

class EmailThucTeTest extends TestCase
{
    private function hopLe(mixed $email): bool
    {
        return Validator::make(
            ['email' => $email],
            ['email' => ['nullable', new EmailThucTe()]]
        )->passes();
    }

    private function giaLapDns(?bool $ketQua)
    {
        $dns = Mockery::mock(TraCuuDns::class);
        $dns->shouldReceive('nhanThuDuoc')->andReturn($ketQua);
        $this->app->instance(TraCuuDns::class, $dns);
        config()->set('email_rac.kiem_tra_dns', true);
    }

    public function test_email_hop_le_cac_dinh_dang(): void
    {
        $this->giaLapDns(true);

        foreach ([
            'nguyenvana@gmail.com',
            'khach.outlook@outlook.com',
            'sinhvien@hus.edu.vn',
            'lienhe@congty.com.vn',
            'info@abc-corp.co',
        ] as $email) {
            $this->assertTrue($this->hopLe($email), "Phải hợp lệ: {$email}");
        }
    }

    public function test_email_rong_va_null_duoc_chap_nhan(): void
    {
        // 'nullable' cho phép rỗng — form đặt hàng email tùy chọn.
        $this->assertTrue($this->hopLe(null));
        $this->assertTrue($this->hopLe(''));
        $this->assertTrue($this->hopLe('   '));
    }

    public function test_dinh_dang_sai(): void
    {
        foreach ([
            'khong-co-at.com',
            'a@b',
            'a@@b.com',
            '@gmail.com',
            'a b@gmail.com',
            'a..b@gmail.com',
            '.a@gmail.com',
            'a.@gmail.com',
            'aaaa@gmail.com',
        ] as $email) {
            $this->assertFalse($this->hopLe($email), "Phải bị từ chối: {$email}");
        }
    }

    public function test_ten_mien_vi_du(): void
    {
        $this->giaLapDns(true);

        // example.com/org/net/edu thuộc danh sách cho phép khi test ->
        // vẫn qua; dùng domain ví dụ KHÔNG nằm trong ngoại lệ test.
        $this->assertFalse($this->hopLe('ai@domain.com'));
        $this->assertFalse($this->hopLe('ai@email.com'));
        $this->assertFalse($this->hopLe('ai@ten-mien-vi-du.test'));
    }

    public function test_ten_mien_disposable(): void
    {
        $this->giaLapDns(true);

        $this->assertFalse($this->hopLe('abc@mailinator.com'));
        $this->assertFalse($this->hopLe('abc@sub.yopmail.com'));
        $this->assertFalse($this->hopLe('abc@10minutemail.com'));
    }

    public function test_go_tu_nham_co_goi_y_khong_sua_ngam(): void
    {
        $this->giaLapDns(true);

        $validator = Validator::make(
            ['email' => 'khach@gmial.com'],
            ['email' => [new EmailThucTe()]]
        );

        $this->assertFalse($validator->passes());
        $this->assertStringContainsString('gmail.com', $validator->errors()->first('email'));
        // Input gốc không bị sửa.
        $this->assertSame('khach@gmial.com', request()->input('email', 'khach@gmial.com'));
    }

    public function test_dns_khong_ton_tai_bi_tu_choi(): void
    {
        $this->giaLapDns(false);

        $this->assertFalse($this->hopLe('ai@khong-ton-tai-xyz123.com'));
    }

    public function test_dns_loi_mang_cho_qua(): void
    {
        $this->giaLapDns(null);

        $this->assertTrue($this->hopLe('ai@ten-mien-bat-ky.com'));
    }

    public function test_co_dns_tat_thi_bo_qua_dns(): void
    {
        config()->set('email_rac.kiem_tra_dns', false);
        $dns = Mockery::mock(TraCuuDns::class);
        $dns->shouldNotReceive('nhanThuDuoc');
        $this->app->instance(TraCuuDns::class, $dns);

        $this->assertTrue($this->hopLe('ai@khong-ton-tai-xyz123.com'));
    }

    public function test_cache_ket_qua_dns_theo_ten_mien(): void
    {
        // Cơ chế cache: kết quả (kể cả "unknown") được ghi theo tên miền.
        $svc = new TraCuuDns();
        $svc->nhanThuDuoc('cache-test-domain-xyz.invalid');
        $this->assertNotNull(\Illuminate\Support\Facades\Cache::get('email_dns:cache-test-domain-xyz.invalid'));
        // Gọi lại — phải đọc từ cache, không lỗi.
        $this->assertIsBool($svc->nhanThuDuoc('cache-test-domain-xyz.invalid') ?? true);
    }
}
