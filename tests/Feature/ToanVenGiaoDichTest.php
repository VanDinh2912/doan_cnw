<?php

namespace Tests\Feature;

use App\Models\BienDongKho;
use App\Models\ChiTietDonHang;
use App\Models\ChiTietHoaDonNhap;
use App\Models\DonHang;
use App\Models\HoaDonNhap;
use App\Models\KhachHang;
use App\Models\NhaCungCap;
use App\Models\SanPham;
use App\Models\User;
use App\Services\HoaDonNhapService;
use App\Services\ThanhToanChuyenKhoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use RuntimeException;
use Tests\TestCase;

/**
 * Kiểm chứng idempotency và "kiểm tra lại trạng thái sau khi khóa".
 *
 * GIỚI HẠN: SQLite :memory: chạy một kết nối nên không tạo được race thật giữa
 * hai tiến trình. Các test dưới đây mô phỏng TUẦN TỰ đúng thứ tự mà một race
 * sẽ tạo ra (thao tác A hoàn tất rồi thao tác B mới chạy), để chứng minh lớp
 * bảo vệ thứ hai nằm TRONG transaction sau lockForUpdate vẫn chặn được B.
 */
class ToanVenGiaoDichTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->cashier = User::where('role', 'thungan')->firstOrFail();
        $this->admin = User::where('role', 'admin')->firstOrFail();

        Config::set('pos.sepay_webhook_api_key', 'test_sepay_key_123');
        Config::set('pos.cho_thanh_toan_phut', 15);
        Config::set('pos.telegram_bot_token', '');
        Config::set('pos.telegram_chat_id', '');
    }

    private static int $soThuTu = 9100;

    private function taoDonChoThanhToan(SanPham $sanPham, int $soLuong, int $donGia, ?KhachHang $khach = null, int $diemDung = 0): DonHang
    {
        self::$soThuTu++;
        $tongTien = $soLuong * $donGia;

        $donHang = DonHang::create([
            'ma_hoadon' => sprintf('HD%s-%04d', now()->format('Ymd'), self::$soThuTu),
            'user_id' => $this->cashier->id,
            'customer_id' => $khach?->id,
            'tong_tien' => $tongTien,
            'diem_su_dung' => $diemDung,
            'tien_giam_diem' => $diemDung * config('pos.gia_tri_diem'),
            'diem_tichluy_nhan' => 5,
            'tong_thanh_toan' => $tongTien - $diemDung * config('pos.gia_tri_diem'),
            'tien_khach_dua' => 0,
            'tien_thua' => 0,
            'phuong_thuc' => 'chuyenkhoan',
            'trang_thai' => 'cho_thanh_toan',
        ]);

        ChiTietDonHang::create([
            'invoice_id' => $donHang->id, 'product_id' => $sanPham->id,
            'soluong' => $soLuong, 'don_gia' => $donGia,
            'gia_von' => $sanPham->gia_nhap, 'thanh_tien' => $tongTien,
        ]);

        $sanPham->decrement('soluong', $soLuong);
        BienDongKho::create([
            'product_id' => $sanPham->id, 'user_id' => $this->cashier->id,
            'invoice_id' => $donHang->id, 'loai' => 'ban', 'soluong' => -$soLuong,
            'ghi_chu' => 'Bán hàng '.$donHang->ma_hoadon,
        ]);

        if ($khach && $diemDung > 0) {
            $khach->decrement('diem_tichluy', $diemDung);
        }

        return $donHang;
    }

    private function guiWebhook(string $maHoaDon, int $soTien, int $maGiaoDich): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('webhook.thanhtoan'), [
            'id' => $maGiaoDich,
            'transferType' => 'in',
            'transferAmount' => $soTien,
            'content' => 'THANH TOAN DON '.str_replace('-', '', $maHoaDon),
        ], ['Authorization' => 'Apikey test_sepay_key_123']);
    }

    private function taoHoaDonNhapNhapTam(SanPham $sanPham, int $soLuong): HoaDonNhap
    {
        $donGia = (int) $sanPham->gia_nhap;
        $tienHang = $soLuong * $donGia;

        $hoaDon = HoaDonNhap::create([
            'ma_phieu' => 'PN-IDEM-'.self::$soThuTu,
            'so_hoadon' => '0009911',
            'ky_hieu' => '1C26TAA',
            'ngay_hoadon' => now()->toDateString(),
            'supplier_id' => NhaCungCap::firstOrFail()->id,
            'user_id' => $this->admin->id,
            'trang_thai' => 'nhap_tam',
            'hinh_thuc_thanh_toan' => 'tienmat',
            'tong_tien_hang' => $tienHang,
            'thue_suat' => 0,
            'tien_thue' => 0,
            'tong_thanh_toan' => $tienHang,
        ]);

        ChiTietHoaDonNhap::create([
            'purchase_invoice_id' => $hoaDon->id, 'stt' => 1,
            'product_id' => $sanPham->id, 'ten_hang' => $sanPham->ten_sanpham,
            'don_vi' => $sanPham->don_vi, 'soluong' => $soLuong,
            'don_gia' => $donGia, 'thanh_tien' => $tienHang,
        ]);

        return $hoaDon;
    }

    // ------------------------------------------------------------------
    // 1.4 — donhang:huy-qua-han idempotent, không hủy đơn đã thanh toán
    // ------------------------------------------------------------------

    // T-ID01: Chạy lệnh hủy hai lần liên tiếp chỉ hoàn kho/điểm đúng một lần.
    public function test_T_ID01_lenh_huy_qua_han_chay_hai_lan_chi_hoan_kho_mot_lan(): void
    {
        $sanPham = SanPham::where('sku', 'CS-0001')->firstOrFail();
        $khach = KhachHang::create([
            'ho_ten' => 'Khach Idempotent', 'so_dienthoai' => '0944100200', 'diem_tichluy' => 50,
        ]);

        $donHang = $this->taoDonChoThanhToan($sanPham, 3, 100000, $khach, 10);
        $donHang->created_at = now()->subMinutes(30);
        $donHang->save();

        $tonSauKhiGiu = (int) $sanPham->fresh()->soluong;
        $diemSauKhiTru = (int) $khach->fresh()->diem_tichluy; // 50 - 10 = 40

        $this->artisan('donhang:huy-qua-han')->assertExitCode(0);
        $this->assertSame('da_huy', $donHang->fresh()->trang_thai);
        $this->assertSame($tonSauKhiGiu + 3, (int) $sanPham->fresh()->soluong);
        $this->assertSame($diemSauKhiTru + 10, (int) $khach->fresh()->diem_tichluy);
        $soMovementSauLan1 = BienDongKho::where('invoice_id', $donHang->id)->count();

        // Lần hai: đơn đã da_huy nên không được hoàn kho/điểm thêm lần nữa.
        $this->artisan('donhang:huy-qua-han')
            ->expectsOutput('Không có hóa đơn nào quá hạn chờ thanh toán.')
            ->assertExitCode(0);

        $this->assertSame($tonSauKhiGiu + 3, (int) $sanPham->fresh()->soluong);
        $this->assertSame($diemSauKhiTru + 10, (int) $khach->fresh()->diem_tichluy);
        $this->assertSame($soMovementSauLan1, BienDongKho::where('invoice_id', $donHang->id)->count());
    }

    // T-ID02: Đơn đã thanh toán dù tạo từ lâu vẫn không bị lệnh hủy đụng tới.
    public function test_T_ID02_lenh_huy_khong_dung_don_da_thanh_toan(): void
    {
        $sanPham = SanPham::where('sku', 'CS-0001')->firstOrFail();
        $donHang = $this->taoDonChoThanhToan($sanPham, 2, 100000);
        $donHang->created_at = now()->subDays(2);
        $donHang->save();

        app(ThanhToanChuyenKhoanService::class)->hoanTatThanhToan($donHang, 'GD-DA-TRA');
        $tonSauThanhToan = (int) $sanPham->fresh()->soluong;

        $this->artisan('donhang:huy-qua-han')->assertExitCode(0);

        $donHang->refresh();
        $this->assertSame('da_thanh_toan', $donHang->trang_thai);
        $this->assertSame('GD-DA-TRA', $donHang->ma_giaodich);
        $this->assertSame($tonSauThanhToan, (int) $sanPham->fresh()->soluong);
        $this->assertDatabaseMissing('stock_movements', ['invoice_id' => $donHang->id, 'loai' => 'dieuchinh']);
    }

    // T-ID03: Race mô phỏng — webhook hoàn tất đơn quá hạn, NGAY SAU ĐÓ lệnh hủy
    // chạy. Lớp kiểm tra lại trạng thái sau lock phải giữ đơn da_thanh_toan và
    // không hoàn kho nhầm.
    public function test_T_ID03_webhook_hoan_tat_roi_lenh_huy_chay_khong_hoan_kho_nham(): void
    {
        $sanPham = SanPham::where('sku', 'CS-0001')->firstOrFail();
        $khach = KhachHang::create([
            'ho_ten' => 'Khach Race', 'so_dienthoai' => '0944100201', 'diem_tichluy' => 20,
        ]);

        $donHang = $this->taoDonChoThanhToan($sanPham, 4, 100000, $khach, 0);
        $donHang->created_at = now()->subMinutes(40);
        $donHang->save();

        $tonDangGiu = (int) $sanPham->fresh()->soluong;

        // Tiền về trước khi lệnh hủy kịp chạy.
        $this->guiWebhook($donHang->ma_hoadon, 400000, 555001)->assertOk();
        $this->assertSame('da_thanh_toan', $donHang->fresh()->trang_thai);

        // Lệnh hủy chạy ngay sau: phải bỏ qua đơn này.
        $this->artisan('donhang:huy-qua-han')->assertExitCode(0);

        $donHang->refresh();
        $this->assertSame('da_thanh_toan', $donHang->trang_thai);
        $this->assertSame('555001', $donHang->ma_giaodich);
        $this->assertSame($tonDangGiu, (int) $sanPham->fresh()->soluong);
        $this->assertSame(25, (int) $khach->fresh()->diem_tichluy); // 20 + 5, không bị trừ/hoàn nhầm
        $this->assertDatabaseMissing('stock_movements', ['invoice_id' => $donHang->id, 'loai' => 'dieuchinh']);
    }

    // ------------------------------------------------------------------
    // 1.5 — Kiểm tra lại trạng thái sau khi khóa (mô phỏng tuần tự)
    // ------------------------------------------------------------------

    // T-ID04: Xác nhận hóa đơn nhập hai lần — lần hai bị chặn, kho chỉ nhập một lần.
    public function test_T_ID04_xac_nhan_nhap_kho_hai_lan_chi_nhap_mot_lan(): void
    {
        $sanPham = SanPham::where('sku', 'CS-0001')->firstOrFail();
        $tonBanDau = (int) $sanPham->soluong;
        $hoaDon = $this->taoHoaDonNhapNhapTam($sanPham, 7);

        $service = app(HoaDonNhapService::class);
        $service->xacNhanNhapKho($hoaDon, $this->admin);

        $this->assertSame('da_nhap_kho', $hoaDon->fresh()->trang_thai);
        $this->assertSame($tonBanDau + 7, (int) $sanPham->fresh()->soluong);
        $this->assertSame(1, BienDongKho::where('purchase_invoice_id', $hoaDon->id)->count());

        // Lần xác nhận thứ hai phải ném lỗi và không nhập thêm kho.
        try {
            $service->xacNhanNhapKho($hoaDon->fresh(), $this->admin);
            $this->fail('Lần xác nhận thứ hai phải bị từ chối.');
        } catch (RuntimeException $e) {
            $this->assertSame('Hóa đơn chỉ được xác nhận một lần.', $e->getMessage());
        }

        $this->assertSame($tonBanDau + 7, (int) $sanPham->fresh()->soluong);
        $this->assertSame(1, BienDongKho::where('purchase_invoice_id', $hoaDon->id)->count());
    }

    // T-ID05: Hai webhook cùng mã giao dịch — chỉ hoàn tất một lần, cộng điểm một lần.
    public function test_T_ID05_hai_webhook_cung_ma_giao_dich_chi_xu_ly_mot_lan(): void
    {
        $sanPham = SanPham::where('sku', 'CS-0001')->firstOrFail();
        $khach = KhachHang::create([
            'ho_ten' => 'Khach Trung Webhook', 'so_dienthoai' => '0944100202', 'diem_tichluy' => 30,
        ]);
        $donHang = $this->taoDonChoThanhToan($sanPham, 1, 200000, $khach, 0);
        $tonDangGiu = (int) $sanPham->fresh()->soluong;

        $this->guiWebhook($donHang->ma_hoadon, 200000, 666001)->assertOk();
        $response2 = $this->guiWebhook($donHang->ma_hoadon, 200000, 666001);
        $response2->assertOk()->assertJson(['message' => 'Giao dịch đã được xử lý trước đó.']);

        $this->assertSame('da_thanh_toan', $donHang->fresh()->trang_thai);
        $this->assertSame(35, (int) $khach->fresh()->diem_tichluy); // 30 + 5, cộng đúng một lần
        $this->assertSame($tonDangGiu, (int) $sanPham->fresh()->soluong);
        $this->assertDatabaseCount('payment_webhooks', 1);
        $this->assertSame(1, BienDongKho::where('invoice_id', $donHang->id)->count());
    }

    // T-ID06: Tồn chỉ còn 1 — hai yêu cầu bán cùng sản phẩm, chỉ một thành công.
    public function test_T_ID06_ban_hang_khi_ton_chi_con_1_chi_mot_don_thanh_cong(): void
    {
        $sanPham = SanPham::where('sku', 'CS-0001')->firstOrFail();
        $sanPham->update(['soluong' => 1]);

        $service = app(ThanhToanChuyenKhoanService::class);
        $gioHang = [['id' => $sanPham->id, 'soluong' => 1]];
        $thongTin = ['giam_gia_phantram' => 0, 'diem_su_dung' => 0];

        $don1 = $service->taoHoaDonChoThanhToan($gioHang, $thongTin, $this->cashier);
        $this->assertSame('cho_thanh_toan', $don1->trang_thai);
        $this->assertSame(0, (int) $sanPham->fresh()->soluong);

        // Yêu cầu thứ hai đọc lại tồn TRONG transaction sau khi khóa => phải từ chối.
        try {
            $service->taoHoaDonChoThanhToan($gioHang, $thongTin, $this->cashier);
            $this->fail('Đơn thứ hai phải bị từ chối vì hết tồn kho.');
        } catch (RuntimeException $e) {
            $this->assertSame('Tồn kho đã thay đổi, vui lòng kiểm tra lại giỏ hàng.', $e->getMessage());
        }

        $this->assertSame(0, (int) $sanPham->fresh()->soluong);
        $this->assertSame(-1, (int) BienDongKho::where('product_id', $sanPham->id)->where('loai', 'ban')->sum('soluong'));
    }
}
