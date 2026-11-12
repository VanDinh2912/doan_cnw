<?php

namespace Tests\Unit;

use App\Rules\SoDienThoaiHopLe;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SoDienThoaiHopLeTest extends TestCase
{
    public static function chuanHoaProvider(): array
    {
        return [
            ['0912345678', '0912345678'],
            ['+84912345678', '0912345678'],
            ['84912345678', '0912345678'],
            ['091 234 5678', '0912345678'],
            ['0912-345-678', '0912345678'],
            ['(0912) 345.678', '0912345678'],
            ['  0912345678  ', '0912345678'],
        ];
    }

    #[DataProvider('chuanHoaProvider')]
    public function test_chuan_hoa(string $input, string $expected): void
    {
        $this->assertSame($expected, SoDienThoaiHopLe::chuanHoa($input));
    }

    public function test_chuan_hoa_giu_nguyen_khong_phai_chuoi(): void
    {
        $this->assertNull(SoDienThoaiHopLe::chuanHoa(null));
        $this->assertSame(['abc'], SoDienThoaiHopLe::chuanHoa(['abc']));
    }

    public function test_rule_chap_nhan_sdt_hop_le(): void
    {
        $validator = Validator::make(
            ['so_dienthoai' => '0912345678'],
            ['so_dienthoai' => [new SoDienThoaiHopLe()]]
        );

        $this->assertFalse($validator->fails());
    }

    public static function sdtLoiProvider(): array
    {
        return [
            ['abc'],
            ['09123abc78'],
            ['0912345'],
            ['09123456789'],
            ['1912345678'],
            ['0212345678'],
            ['0912345678!'],
        ];
    }

    #[DataProvider('sdtLoiProvider')]
    public function test_rule_tu_choi_sdt_loi(string $input): void
    {
        $validator = Validator::make(
            ['so_dienthoai' => $input],
            ['so_dienthoai' => [new SoDienThoaiHopLe()]]
        );

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'Số điện thoại không hợp lệ. Nhập 10 chữ số, bắt đầu bằng 0.',
            $validator->errors()->first('so_dienthoai')
        );
    }
}
