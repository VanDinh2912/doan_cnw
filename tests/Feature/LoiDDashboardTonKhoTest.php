<?php

namespace Tests\Feature;

use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\LoaiSanPham;
use App\Models\SanPham;
use App\Models\User;
use App\Services\ThanhToanChuyenKhoanService;
use App\Services\TongQuanKhoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tái hiện và kiểm thử Lỗi D — Dashboard không phản ánh tồn kho sau hóa đơn:
 * Kiểm tra 4 bước với số cụ thể:
 * (1) Hoàn tất hóa đơn POS tiền mặt;
 * (2) Tạo đơn QR chờ thanh toán (giữ hàng);
 * (3) Webhook hoàn tất;
 * (4) Hủy đơn quá hạn (hoàn hàng).
 * Đảm bảo 3 chỉ số hiển thị rõ:
 * - "Tồn khả dụng" (SUM soluong, đã trừ phần đang giữ)
 * - "Đang giữ chờ thanh toán" (từ chi tiết đơn cho_thanh_toan)
 * - "Tổng hàng thực tế trong kho" = khả dụng + đang giữ
 */
class LoiDDashboardTonKhoTest extends TestCase
{
    use RefreshDatabase;

    private const API_KEY = 'test_key_dashboard';

    private User $admin;
    private User $cashier;
    private SanPham $product;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();

        Config::set('pos.sepay_webhook_api_key', self::API_KEY);
        Config::set('pos.cho_thanh_toan_phut', 15);
        Config::set('pos.bank_code', 'MB');
        Config::set('pos.bank_account', '0987654321');

        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin-dashboard@test.local',
            'password' => bcrypt('12345678'),
            'role' => 'admin',
        ]);

        $this->cashier = User::create([
            'name' => 'Cashier Test',
            'email' => 'cashier-dashboard@test.local',
            'password' => bcrypt('12345678'),
            'role' => 'thungan',
        ]);

        $category = LoaiSanPham::create(['ten_loai' => 'Bánh kẹo']);

        $this->product = SanPham::create([
            'category_id' => $category->id,
            'sku' => 'DASH-0001',
            'ten_sanpham' => 'Bánh quy bơ',
            'don_vi' => 'Hộp',
            'gia_nhap' => 10000,
            'gia_ban' => 20000,
            'soluong' => 100, // Ban đầu 100 hộp
            'soluong_toithieu' => 10,
            'trang_thai' => true,
        ]);
    }

    private function layChiSo(): array
    {
        return $this->actingAs($this->admin)
            ->getJson(route('tongquan.solieu'))
            ->assertOk()
            ->json();
    }

    public function test_chuoi_4_buoc_phan_anh_chinh_xac_ton_kho_tren_dashboard(): void
    {
        // Trạng thái ban đầu
        $soBanDau = $this->layChiSo();
        $this->assertSame(1, $soBanDau['so_mat_hang_dang_kinh_doanh']);
        $this->assertSame(100, $soBanDau['ton_kha_dung']);
        $this->assertSame(0, $soBanDau['dang_giu']);
        $this->assertSame(100, $soBanDau['tong_thuc_te']);

        // (1) Hoàn tất hóa đơn POS tiền mặt bán 10 cái
        $this->actingAs($this->cashier)
            ->withSession([
                'gio_hang' => [
                    (string) $this->product->id => [
                        'id' => $this->product->id,
                        'sku' => $this->product->sku,
                        'ten_sanpham' => $this->product->ten_sanpham,
                        'gia_ban' => (float) $this->product->gia_ban,
                        'soluong' => 10,
                        'ton_kho' => 100,
                    ],
                ],
            ])
            ->post(route('donhang.thanhtoan'), [
                'giam_gia_phantram' => 0,
                'tien_khach_dua' => 200000,
                'phuong_thuc' => 'tienmat',
            ])
            ->assertRedirect();

        $soBuoc1 = $this->layChiSo();
        $this->assertSame(1, $soBuoc1['so_mat_hang_dang_kinh_doanh'], 'Số mặt hàng vẫn là 1');
        $this->assertSame(90, $soBuoc1['ton_kha_dung'], 'Tồn khả dụng giảm còn 90');
        $this->assertSame(0, $soBuoc1['dang_giu'], 'Không có hàng đang giữ');
        $this->assertSame(90, $soBuoc1['tong_thuc_te'], 'Tổng thực tế trong kho là 90');

        // (2) Tạo đơn QR chờ thanh toán bán 20 cái (giữ hàng)
        $resQr = $this->actingAs($this->cashier)
            ->withSession([
                'gio_hang' => [
                    (string) $this->product->id => [
                        'id' => $this->product->id,
                        'sku' => $this->product->sku,
                        'ten_sanpham' => $this->product->ten_sanpham,
                        'gia_ban' => (float) $this->product->gia_ban,
                        'soluong' => 20,
                        'ton_kho' => 90,
                    ],
                ],
            ])
            ->postJson(route('donhang.taoqr'), ['giam_gia_phantram' => 0]);

        $resQr->assertOk();
        $donQrId = $resQr->json('id');
        $donQr = DonHang::findOrFail($donQrId);

        $soBuoc2 = $this->layChiSo();
        $this->assertSame(1, $soBuoc2['so_mat_hang_dang_kinh_doanh'], 'Số mặt hàng vẫn là 1');
        $this->assertSame(70, $soBuoc2['ton_kha_dung'], 'Tồn khả dụng giảm còn 70');
        $this->assertSame(20, $soBuoc2['dang_giu'], 'Đang giữ chờ thanh toán là 20');
        $this->assertSame(90, $soBuoc2['tong_thuc_te'], 'Tổng thực tế trong kho vẫn là 90 (hàng chưa xuất khỏi cửa hàng)');

        // (3) Webhook SePay hoàn tất đơn QR
        $this->postJson(route('webhook.thanhtoan'), [
            'id' => 'GD-SEPAY-01',
            'transferType' => 'in',
            'transferAmount' => 400000,
            'content' => 'THANH TOAN '.$donQr->ma_hoadon,
        ], ['Authorization' => 'Apikey '.self::API_KEY])->assertOk();

        $this->assertSame('da_thanh_toan', $donQr->fresh()->trang_thai);

        $soBuoc3 = $this->layChiSo();
        $this->assertSame(1, $soBuoc3['so_mat_hang_dang_kinh_doanh']);
        $this->assertSame(70, $soBuoc3['ton_kha_dung'], 'Tồn khả dụng là 70');
        $this->assertSame(0, $soBuoc3['dang_giu'], 'Đang giữ giảm về 0 vì đã hoàn tất giao');
        $this->assertSame(70, $soBuoc3['tong_thuc_te'], 'Tổng thực tế trong kho giảm về 70');

        // (4) Tạo đơn QR thứ hai bán 15 cái rồi hủy quá hạn
        $resQr2 = $this->actingAs($this->cashier)
            ->withSession([
                'gio_hang' => [
                    (string) $this->product->id => [
                        'id' => $this->product->id,
                        'sku' => $this->product->sku,
                        'ten_sanpham' => $this->product->ten_sanpham,
                        'gia_ban' => (float) $this->product->gia_ban,
                        'soluong' => 15,
                        'ton_kho' => 70,
                    ],
                ],
            ])
            ->postJson(route('donhang.taoqr'), ['giam_gia_phantram' => 0]);

        $resQr2->assertOk();
        $donQr2 = DonHang::findOrFail($resQr2->json('id'));

        $soTam = $this->layChiSo();
        $this->assertSame(55, $soTam['ton_kha_dung']);
        $this->assertSame(15, $soTam['dang_giu']);
        $this->assertSame(70, $soTam['tong_thuc_te']);

        // Hủy đơn do quá hạn / thu ngân hủy
        $service = app(ThanhToanChuyenKhoanService::class);
        $service->huyChoThanhToan($donQr2, 'Quá hạn chờ thanh toán');
        $this->assertSame('da_huy', $donQr2->fresh()->trang_thai);

        $soBuoc4 = $this->layChiSo();
        $this->assertSame(1, $soBuoc4['so_mat_hang_dang_kinh_doanh']);
        $this->assertSame(70, $soBuoc4['ton_kha_dung'], 'Tồn khả dụng phục hồi về 70');
        $this->assertSame(0, $soBuoc4['dang_giu'], 'Đang giữ về 0');
        $this->assertSame(70, $soBuoc4['tong_thuc_te'], 'Tổng thực tế là 70');
    }
}
