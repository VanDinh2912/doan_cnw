<?php

namespace Tests\Feature;

use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\LoaiSanPham;
use App\Models\NhaCungCap;
use App\Models\PhieuKiemKe;
use App\Models\SanPham;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TongQuanSoLieuTest extends TestCase
{
    use RefreshDatabase;

    private const API_KEY = 'test_sepay_key_tongquan';

    private static int $dem = 0;

    private User $admin;
    private User $thungan;
    private User $thukho;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();

        Config::set('pos.sepay_webhook_api_key', self::API_KEY);
        Config::set('pos.cho_thanh_toan_phut', 15);
        Config::set('pos.han_tra_ngay', 7);
        Config::set('pos.nguong_tra_khong_duyet', 500000);
        Config::set('pos.telegram_bot_token', '');
        Config::set('pos.telegram_chat_id', '');

        $this->admin = $this->nguoiDung('admin');
        $this->thungan = $this->nguoiDung('thungan');
        $this->thukho = $this->nguoiDung('thukho');
    }

    private function nguoiDung(string $role): User
    {
        return User::create([
            'name' => 'Test '.$role,
            'email' => $role.'-tongquan@test.local',
            'password' => bcrypt('12345678'),
            'role' => $role,
        ]);
    }

    private function sanPham(array $them = []): SanPham
    {
        self::$dem++;

        return SanPham::create(array_merge([
            'category_id' => LoaiSanPham::firstOrCreate(['ten_loai' => 'Loại TQ'])->id,
            'sku' => 'SKU-TQ-'.self::$dem,
            'ten_sanpham' => 'Sản phẩm TQ '.self::$dem,
            'don_vi' => 'Cái',
            'gia_nhap' => 1000,
            'gia_ban' => 2000,
            'soluong' => 10,
            'soluong_toithieu' => 5,
            'trang_thai' => true,
        ], $them));
    }

    private function chiSo(): array
    {
        return $this->actingAs($this->admin)
            ->getJson(route('tongquan.solieu'))
            ->assertOk()
            ->json();
    }

    private function gioHang(SanPham $sanPham, int $soLuong): array
    {
        return [(string) $sanPham->id => [
            'id' => $sanPham->id,
            'sku' => $sanPham->sku,
            'ten_sanpham' => $sanPham->ten_sanpham,
            'gia_ban' => (float) $sanPham->gia_ban,
            'soluong' => $soLuong,
            'ton_kho' => $sanPham->soluong,
        ]];
    }

    private function taoDonChoThanhToan(SanPham $sanPham, int $soLuong): DonHang
    {
        $response = $this->actingAs($this->thungan)
            ->withSession(['gio_hang' => $this->gioHang($sanPham, $soLuong)])
            ->postJson(route('donhang.taoqr'), ['giam_gia_phantram' => 0]);

        $response->assertOk();

        return DonHang::findOrFail($response->json('id'));
    }

    public function test_ban_tien_mat_thi_ton_giam_ngay(): void
    {
        $sanPham = $this->sanPham();

        $this->actingAs($this->thungan)
            ->withSession(['gio_hang' => $this->gioHang($sanPham, 2)])
            ->post(route('donhang.thanhtoan'), [
                'giam_gia_phantram' => 0,
                'tien_khach_dua' => 10000,
                'phuong_thuc' => 'tienmat',
            ])->assertRedirect();

        $chiSo = $this->chiSo();
        $this->assertSame(8, $chiSo['tong_ton_tot']);
        $this->assertSame(1, $chiSo['so_mat_hang_dang_kinh_doanh']);
        $this->assertSame(1, $chiSo['so_con_hang']);
        $this->assertSame(0, $chiSo['so_het_hang']);
    }

    public function test_tao_qr_cho_thanh_toan_tru_kho_ngay(): void
    {
        $sanPham = $this->sanPham();

        $this->taoDonChoThanhToan($sanPham, 3);

        $this->assertSame(7, $sanPham->fresh()->soluong);
        $this->assertSame(7, $this->chiSo()['tong_ton_tot']);
    }

    public function test_webhook_hoan_tat_khong_tru_kho_lan_nua(): void
    {
        $sanPham = $this->sanPham();
        $donHang = $this->taoDonChoThanhToan($sanPham, 3);

        $this->postJson(route('webhook.thanhtoan'), [
            'id' => 900001,
            'transferAmount' => (int) $donHang->tong_thanh_toan,
            'transferType' => 'in',
            'content' => $donHang->ma_hoadon,
        ], ['Authorization' => 'Apikey '.self::API_KEY])->assertOk();

        $this->assertSame('da_thanh_toan', $donHang->fresh()->trang_thai);
        $this->assertSame(7, $sanPham->fresh()->soluong);
        $this->assertSame(7, $this->chiSo()['tong_ton_tot']);
    }

    public function test_huy_qua_han_hoan_kho(): void
    {
        $sanPham = $this->sanPham();
        $donHang = $this->taoDonChoThanhToan($sanPham, 3);

        $donHang->created_at = now()->subMinutes(25);
        $donHang->save();
        $this->artisan('donhang:huy-qua-han')->assertExitCode(0);

        $this->assertSame('da_huy', $donHang->fresh()->trang_thai);
        $this->assertSame(10, $sanPham->fresh()->soluong);
        $this->assertSame(10, $this->chiSo()['tong_ton_tot']);
    }

    private function hoaDonBan(SanPham $sanPham, int $soLuong): ChiTietDonHang
    {
        self::$dem++;
        $donHang = DonHang::create([
            'ma_hoadon' => 'HD-TQ-'.self::$dem,
            'user_id' => $this->thungan->id,
            'tong_tien' => 2000 * $soLuong,
            'tong_thanh_toan' => 2000 * $soLuong,
            'tien_khach_dua' => 2000 * $soLuong,
            'tien_thua' => 0,
            'trang_thai' => 'da_thanh_toan',
        ]);

        return ChiTietDonHang::create([
            'invoice_id' => $donHang->id,
            'product_id' => $sanPham->id,
            'soluong' => $soLuong,
            'don_gia' => 2000,
            'gia_von' => 1000,
            'thanh_tien' => 2000 * $soLuong,
        ]);
    }

    public function test_tra_hang_con_tot_tang_ton_tot(): void
    {
        $sanPham = $this->sanPham();
        $chiTiet = $this->hoaDonBan($sanPham, 2);

        $this->actingAs($this->thungan)->post(route('trahang.luu'), [
            'invoice_id' => $chiTiet->invoice_id,
            'hinh_thuc_hoan' => 'tienmat',
            'items' => [['invoice_detail_id' => $chiTiet->id, 'soluong' => 1, 'tinh_trang' => 'con_tot']],
        ])->assertRedirect();

        $chiSo = $this->chiSo();
        $this->assertSame(11, $chiSo['tong_ton_tot']);
        $this->assertSame(0, $chiSo['tong_ton_hong']);
    }

    public function test_tra_hang_hong_tang_soluong_hong_khong_tang_ton_tot(): void
    {
        $sanPham = $this->sanPham();
        $chiTiet = $this->hoaDonBan($sanPham, 2);

        $this->actingAs($this->thungan)->post(route('trahang.luu'), [
            'invoice_id' => $chiTiet->invoice_id,
            'hinh_thuc_hoan' => 'tienmat',
            'items' => [['invoice_detail_id' => $chiTiet->id, 'soluong' => 1, 'tinh_trang' => 'loi_hong']],
        ])->assertRedirect();

        $chiSo = $this->chiSo();
        $this->assertSame(10, $chiSo['tong_ton_tot']);
        $this->assertSame(1, $chiSo['tong_ton_hong']);
    }

    public function test_nhap_kho_truc_tiep_tang_ton(): void
    {
        $sanPham = $this->sanPham();

        $this->actingAs($this->thukho)->post(route('kho.postnhapkho'), [
            'product_id' => $sanPham->id,
            'soluong' => 5,
            'gia_nhap' => 1200,
        ])->assertRedirect();

        $chiSo = $this->chiSo();
        $this->assertSame(15, $chiSo['tong_ton_tot']);
        $this->assertSame(15 * 1200, $chiSo['gia_tri_ton']);
    }

    public function test_xac_nhan_hoa_don_nhap_tang_ton(): void
    {
        $sanPham = $this->sanPham();
        $nhaCungCap = NhaCungCap::create(['ten_nhacungcap' => 'NCC TQ', 'ma_so_thue' => 'TQ-MST-01']);

        $this->actingAs($this->thukho)->post(route('hoadonnhap.postthem'), [
            'supplier_id' => $nhaCungCap->id,
            'so_hoadon' => 'HD-NHAP-TQ-01',
            'ky_hieu' => 'AA/26E',
            'ngay_hoadon' => now()->toDateString(),
            'thue_suat' => 8,
            'hinh_thuc_thanh_toan' => 'chuyenkhoan',
            'items' => [[
                'product_id' => $sanPham->id,
                'ten_hang' => $sanPham->ten_sanpham,
                'don_vi' => 'Cái',
                'soluong' => 4,
                'don_gia' => 1000,
            ]],
        ])->assertRedirect();

        $hoaDon = \App\Models\HoaDonNhap::where('so_hoadon', 'HD-NHAP-TQ-01')->firstOrFail();
        $this->assertSame(10, $sanPham->fresh()->soluong);

        $this->actingAs($this->thukho)->post(route('hoadonnhap.xacnhan', $hoaDon))->assertRedirect();

        $this->assertSame(14, $sanPham->fresh()->soluong);
        $this->assertSame(14, $this->chiSo()['tong_ton_tot']);
    }

    public function test_kiem_ke_dieu_chinh_tang_ton(): void
    {
        $sanPham = $this->sanPham();

        $this->actingAs($this->thukho)->post(route('kiemke.store'), [])->assertRedirect();
        $phieu = PhieuKiemKe::firstOrFail();
        $dong = $phieu->chiTiets()->where('product_id', $sanPham->id)->firstOrFail();

        $this->actingAs($this->thukho)->post(route('kiemke.submit', $phieu), [
            'soluong_thuc' => [$dong->id => $dong->soluong_he_thong + 2],
        ])->assertRedirect();
        $this->actingAs($this->admin)->post(route('kiemke.approve', $phieu))->assertRedirect();

        $this->assertSame(12, $sanPham->fresh()->soluong);
        $this->assertSame(12, $this->chiSo()['tong_ton_tot']);
    }

    public function test_san_pham_an_khong_tinh_vao_so_mat_hang_nhung_ton_van_tinh(): void
    {
        $this->sanPham();
        $sanPhamAn = $this->sanPham(['trang_thai' => false, 'soluong' => 4]);

        $chiSo = $this->chiSo();
        $this->assertSame(1, $chiSo['so_mat_hang_dang_kinh_doanh']);
        $this->assertSame(1, $chiSo['so_con_hang']);
        $this->assertSame(14, $chiSo['tong_ton_tot']);

        $this->actingAs($this->admin)->get(route('tongquan'))
            ->assertOk()
            ->assertDontSee($sanPhamAn->ten_sanpham);
    }

    public function test_san_pham_xoa_khong_con_duoc_tinh(): void
    {
        $sanPham = $this->sanPham();
        $this->sanPham();

        $this->actingAs($this->admin)->delete(route('sanpham.xoa', $sanPham))->assertRedirect();

        $chiSo = $this->chiSo();
        $this->assertSame(1, $chiSo['so_mat_hang_dang_kinh_doanh']);
        $this->assertSame(10, $chiSo['tong_ton_tot']);
    }

    public function test_san_pham_het_hang_dem_dung(): void
    {
        $this->sanPham();
        $this->sanPham(['soluong' => 0]);

        $chiSo = $this->chiSo();
        $this->assertSame(2, $chiSo['so_mat_hang_dang_kinh_doanh']);
        $this->assertSame(1, $chiSo['so_con_hang']);
        $this->assertSame(1, $chiSo['so_het_hang']);
        $this->assertSame(10, $chiSo['tong_ton_tot']);
    }

    public function test_endpoint_chi_admin_duoc_truy_cap(): void
    {
        $this->actingAs($this->thungan)->getJson(route('tongquan.solieu'))->assertForbidden();
        $this->actingAs($this->thukho)->getJson(route('tongquan.solieu'))->assertForbidden();

        $khach = $this->nguoiDung('khachhang');
        $this->actingAs($khach)->getJson(route('tongquan.solieu'))
            ->assertRedirect(route('storefront.home'));
    }

    public function test_guest_bi_chuyen_ve_dang_nhap(): void
    {
        // Tách riêng vì actingAs() dính xuyên các request trong cùng một test.
        $this->get(route('tongquan.solieu'))->assertRedirect(route('auth.dangnhap'));
    }

    public function test_so_tren_trang_khop_endpoint(): void
    {
        $this->sanPham(['soluong' => 10]);
        $this->sanPham(['soluong' => 0, 'soluong_hong' => 3]);

        $chiSo = $this->chiSo();
        $trang = $this->actingAs($this->admin)->get(route('tongquan'))->assertOk();

        foreach (['so_mat_hang_dang_kinh_doanh', 'so_con_hang', 'so_het_hang', 'tong_ton_tot', 'tong_ton_hong'] as $key) {
            $trang->assertSee(number_format($chiSo[$key], 0, ',', '.'));
        }
        $trang->assertSee('Cập nhật lúc');
    }

    public function test_khong_con_so_cu_sau_thao_tac(): void
    {
        $sanPham = $this->sanPham();
        $truoc = $this->chiSo()['tong_ton_tot'];

        $this->actingAs($this->thungan)
            ->withSession(['gio_hang' => $this->gioHang($sanPham, 1)])
            ->post(route('donhang.thanhtoan'), [
                'giam_gia_phantram' => 0,
                'tien_khach_dua' => 10000,
                'phuong_thuc' => 'tienmat',
            ])->assertRedirect();

        $sau = $this->chiSo()['tong_ton_tot'];
        $this->assertSame($truoc - 1, $sau);
    }

    public function test_gia_tri_ton_theo_gia_nhap(): void
    {
        $this->sanPham(['soluong' => 10, 'gia_nhap' => 1000]);
        $this->sanPham(['soluong' => 5, 'gia_nhap' => 2000, 'soluong_hong' => 2]);

        $chiSo = $this->chiSo();
        $this->assertSame(10 * 1000 + 5 * 2000, $chiSo['gia_tri_ton']);
        $this->assertSame(2, $chiSo['tong_ton_hong']);
    }

    public function test_endpoint_co_throttle_va_dung_quyen(): void
    {
        $route = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'tongquan.solieu');

        $this->assertNotNull($route);
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $middleware = $route->gatherMiddleware();
        $this->assertContains('throttle:30,1', $middleware);
        $this->assertContains('role:admin', $middleware);
    }
}