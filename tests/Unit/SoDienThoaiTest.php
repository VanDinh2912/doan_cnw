<?php

namespace Tests\Unit;

use App\Support\SoDienThoai;
use PHPUnit\Framework\TestCase;

class SoDienThoaiTest extends TestCase
{
    public function test_chuan_hoa_giu_nguyen_dang_0(): void
    {
        $this->assertSame('0901234567', SoDienThoai::chuanHoa('0901234567'));
    }

    public function test_chuan_hoa_dau_cong_84(): void
    {
        $this->assertSame('0901234567', SoDienThoai::chuanHoa('+84901234567'));
    }

    public function test_chuan_hoa_dau_84_khong_dau_cong(): void
    {
        $this->assertSame('0901234567', SoDienThoai::chuanHoa('84901234567'));
    }

    public function test_chuan_hoa_bo_khoang_trang_dau_gach_ngoac(): void
    {
        $this->assertSame('0901234567', SoDienThoai::chuanHoa(' 090.123-4567 '));
        $this->assertSame('0901234567', SoDienThoai::chuanHoa('(090) 123 4567'));
    }

    public function test_chuan_hoa_null_thanh_chuoi_rong(): void
    {
        $this->assertSame('', SoDienThoai::chuanHoa(null));
    }

    public function test_chuan_hoa_khong_doi_chuoi_ngan_khong_phai_84(): void
    {
        $this->assertSame('84123', SoDienThoai::chuanHoa('84123'));
    }
}