<?php

namespace Tests\Unit;

use App\Models\SanPham;
use App\Rules\AnhSanPhamUrl;
use App\Services\TepAnhSanPham;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AnhSanPhamTest extends TestCase
{
    public static function urlHopLeProvider(): array
    {
        return [
            'cdn không đuôi' => ['https://cdn.example.com/image/abc123'],
            'đuôi jpg' => ['https://cdn.example.com/a.jpg'],
            'query string' => ['https://cdn.example.com/a.png?w=300&h=300'],
            'cổng' => ['https://cdn.example.com:8443/a.png'],
            'đúng 255 ký tự' => ['https://cdn.example.com/'.str_repeat('a', 231)],
            'có khoảng trắng hai đầu (trim)' => ['  https://cdn.example.com/a.png  '],
        ];
    }

    #[DataProvider('urlHopLeProvider')]
    public function test_rule_chap_nhan_url_hop_le(string $url): void
    {
        $validator = Validator::make(
            ['hinh_anh_url' => trim($url)],
            ['hinh_anh_url' => ['nullable', new AnhSanPhamUrl()]]
        );

        $this->assertFalse($validator->fails());
    }

    public static function urlLoiProvider(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'data base64' => ['data:image/png;base64,AAAA'],
            'http' => ['http://cdn.example.com/a.png'],
            'ftp' => ['ftp://host/a.png'],
            'file' => ['file:///etc/passwd'],
            'protocol-relative' => ['//evil.com/a.png'],
            'userinfo' => ['https://user:pass@host/a.png'],
            'khoảng trắng giữa' => ['https://host/a b.png'],
            'host rỗng' => ['https://'],
            'quá 255 ký tự' => ['https://cdn.example.com/'.str_repeat('a', 232)],
            'mảng' => [['https://cdn.example.com/a.png']],
            'tương đối' => ['/img/a.png'],
            'chrome-extension' => ['chrome-extension://abc/a.png'],
        ];
    }

    #[DataProvider('urlLoiProvider')]
    public function test_rule_tu_choi_url_loi(mixed $url): void
    {
        $validator = Validator::make(
            ['hinh_anh_url' => $url],
            ['hinh_anh_url' => ['nullable', new AnhSanPhamUrl()]]
        );

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'URL ảnh không hợp lệ. Chỉ chấp nhận https, tối đa 255 ký tự, không chứa khoảng trắng hay thông tin đăng nhập.',
            $validator->errors()->first('hinh_anh_url')
        );
    }

    public function test_rule_bo_qua_null(): void
    {
        $validator = Validator::make(
            ['hinh_anh_url' => null],
            ['hinh_anh_url' => ['nullable', new AnhSanPhamUrl()]]
        );

        $this->assertFalse($validator->fails());
    }

    public function test_accessor_tra_url_https_nguyen_ven(): void
    {
        $sanPham = new SanPham(['hinh_anh' => 'https://cdn.example.com/a.png?x=1&y=2']);

        $this->assertSame('https://cdn.example.com/a.png?x=1&y=2', $sanPham->anh_url);
    }

    public function test_accessor_rong_tra_anh_mac_dinh(): void
    {
        $this->assertSame(asset('img/no-image.svg'), (new SanPham(['hinh_anh' => null]))->anh_url);
        $this->assertSame(asset('img/no-image.svg'), (new SanPham(['hinh_anh' => '']))->anh_url);
        $this->assertSame(asset('img/no-image.svg'), (new SanPham([]))->anh_url);
    }

    public function test_accessor_tep_tai_len_dung_storage_url(): void
    {
        $sanPham = new SanPham(['hinh_anh' => 'sanpham/abc.jpg']);

        $this->assertSame(Storage::disk('public')->url('sanpham/abc.jpg'), $sanPham->anh_url);
    }

    public function test_accessor_duong_dan_cu_upload_va_storage(): void
    {
        $this->assertSame(
            asset('upload/legacy.jpg'),
            (new SanPham(['hinh_anh' => 'upload/legacy.jpg']))->anh_url
        );
        $this->assertSame(
            asset('storage/products/cu.svg'),
            (new SanPham(['hinh_anh' => 'storage/products/cu.svg']))->anh_url
        );
    }

    public function test_xoa_tep_chi_xoa_duong_dan_trong_thu_muc_anh(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('sanpham/a.jpg', 'x');
        Storage::disk('public')->put('products/b.svg', 'x');

        TepAnhSanPham::xoaNeuLaTepCucBo('sanpham/a.jpg');
        TepAnhSanPham::xoaNeuLaTepCucBo('storage/products/b.svg');

        Storage::disk('public')->assertMissing('sanpham/a.jpg');
        Storage::disk('public')->assertMissing('products/b.svg');
    }

    public static function giaTriKhongDuocXoaProvider(): array
    {
        return [
            'url https' => ['https://cdn.example.com/a.png'],
            'url http' => ['http://cdn.example.com/a.png'],
            'null' => [null],
            'rỗng' => [''],
            'traversal' => ['sanpham/../../secret.jpg'],
            'thư mục khác' => ['khac/tep.jpg'],
            'backslash' => ['sanpham\\..\\secret.jpg'],
        ];
    }

    #[DataProvider('giaTriKhongDuocXoaProvider')]
    public function test_xoa_tep_khong_dong_vao_url_hay_duong_dan_la(?string $giaTri): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('sanpham/giu-lai.jpg', 'x');

        TepAnhSanPham::xoaNeuLaTepCucBo($giaTri);

        Storage::disk('public')->assertExists('sanpham/giu-lai.jpg');
    }

    public function test_xoa_tep_legacy_upload_chi_trong_public_upload(): void
    {
        $thuMuc = public_path('upload');
        if (! is_dir($thuMuc)) {
            mkdir($thuMuc, 0755, true);
        }
        $ten = '.test-anh-'.uniqid().'.jpg';
        $duongDan = $thuMuc.'/'.$ten;
        file_put_contents($duongDan, 'x');

        try {
            TepAnhSanPham::xoaNeuLaTepCucBo('upload/'.$ten);
            $this->assertFileDoesNotExist($duongDan);

            file_put_contents($duongDan, 'x');
            TepAnhSanPham::xoaNeuLaTepCucBo('upload/../'.$ten);
            $this->assertFileExists($duongDan);
        } finally {
            @unlink($duongDan);
        }
    }
}