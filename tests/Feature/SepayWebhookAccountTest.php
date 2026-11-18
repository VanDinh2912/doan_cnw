<?php

namespace Tests\Feature;

use App\Mail\DatHangOnlineDaThanhToanEmail;
use App\Models\BienDongKho;
use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\PaymentWebhook;
use App\Models\SanPham;
use App\Models\User;
use App\Services\ThanhToanChuyenKhoanService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Bổ sung cho ThanhToanChuyenKhoanTest: kiểm tra accountNumber,
 * đơn đã thanh toán thủ công rồi webhook đến sau, và hành vi
 * chuyển thiếu nhiều lần (ghi nhận: KHÔNG cộng dồn tự động).
 */
class SepayWebhookAccountTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected User $cashier;
    protected string $apiKey = 'test_sepay_key_123';

    private static int $hdCounter = 9100;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->cashier = User::where('role', 'thungan')->firstOrFail();

        Config::set('pos.sepay_webhook_api_key', $this->apiKey);
        Config::set('pos.bank_account', '0388888888');
        Config::set('pos.telegram_bot_token', '');
        Config::set('pos.telegram_chat_id', '');
        Mail::fake();
    }

    private function guiWebhook(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('webhook.thanhtoan'), $payload, [
            'Authorization' => 'Apikey '.$this->apiKey,
        ]);
    }

    private function payloadSePay(DonHang $don, int $id, int $soTien, ?string $accountNumber = '0388888888', string $transferType = 'in'): array
    {
        return [
            'id' => $id,
            'gateway' => 'VCB',
            'transactionDate' => now()->toDateTimeString(),
            'accountNumber' => $accountNumber,
            'transferType' => $transferType,
            'transferAmount' => $soTien,
            'content' => 'THANH TOAN '.str_replace('-', '', $don->ma_hoadon),
            'referenceCode' => 'REF-'.$id,
        ];
    }

    private function taoDonChoThanhToan(int $tong = 13000, ?KhachHang $kh = null): DonHang
    {
        self::$hdCounter++;
        $sp = SanPham::firstOrFail();
        $don = DonHang::create([
            'ma_hoadon' => sprintf('HD%s-%04d', now()->format('Ymd'), self::$hdCounter),
            'user_id' => $this->cashier->id,
            'customer_id' => $kh?->id,
            'tong_tien' => $tong,
            'tien_giam' => 0,
            'tong_thanh_toan' => $tong,
            'tien_khach_dua' => 0,
            'tien_thua' => 0,
            'phuong_thuc' => 'chuyenkhoan',
            'trang_thai' => 'cho_thanh_toan',
        ]);
        ChiTietDonHang::create([
            'invoice_id' => $don->id,
            'product_id' => $sp->id,
            'soluong' => 1,
            'don_gia' => $sp->gia_ban,
            'gia_von' => $sp->gia_nhap,
            'thanh_tien' => $tong,
        ]);
        $sp->decrement('soluong', 1);
        BienDongKho::create([
            'product_id' => $sp->id,
            'user_id' => $this->cashier->id,
            'invoice_id' => $don->id,
            'loai' => 'ban',
            'soluong' => -1,
            'ghi_chu' => 'Bán hàng '.$don->ma_hoadon,
        ]);

        return $don;
    }

    public function test_account_khop_thi_hoan_tat_don(): void
    {
        $don = $this->taoDonChoThanhToan();
        $this->guiWebhook($this->payloadSePay($don, 700001, 13000))->assertOk();

        $this->assertSame('da_thanh_toan', $don->fresh()->trang_thai);
    }

    public function test_account_khac_dinh_dang_nhung_cung_so_van_khop(): void
    {
        $don = $this->taoDonChoThanhToan();
        $this->guiWebhook($this->payloadSePay($don, 700002, 13000, '0388 888 888'))->assertOk();

        $this->assertSame('da_thanh_toan', $don->fresh()->trang_thai);
    }

    public function test_account_khong_khop_ghi_doi_soat_khong_doi_don(): void
    {
        $kh = KhachHang::create(['ho_ten' => 'KH Test', 'so_dienthoai' => '0900000001', 'diem_tichluy' => 0]);
        $don = $this->taoDonChoThanhToan(13000, $kh);

        $this->guiWebhook($this->payloadSePay($don, 700003, 13000, '9999999999'))->assertOk();

        $this->assertSame('cho_thanh_toan', $don->fresh()->trang_thai);
        $this->assertSame(0, $kh->fresh()->diem_tichluy);

        $ghi = PaymentWebhook::where('ma_giao_dich_ngoai', '700003')->firstOrFail();
        $this->assertSame('can_doi_soat', $ghi->ket_qua);
        $this->assertSame('tai_khoan_khong_khop', $ghi->ly_do);
        $this->assertSame('chua_xu_ly', $ghi->trang_thai_xu_ly);
    }

    public function test_thieu_account_number_van_xu_ly_binh_thuong(): void
    {
        $don = $this->taoDonChoThanhToan();
        $payload = $this->payloadSePay($don, 700004, 13000);
        unset($payload['accountNumber']);

        $this->guiWebhook($payload)->assertOk();

        $this->assertSame('da_thanh_toan', $don->fresh()->trang_thai);
    }

    public function test_chua_cau_hinh_bank_account_thi_khong_chan(): void
    {
        Config::set('pos.bank_account', '');
        $don = $this->taoDonChoThanhToan();

        $this->guiWebhook($this->payloadSePay($don, 700005, 13000, '9999999999'))->assertOk();

        $this->assertSame('da_thanh_toan', $don->fresh()->trang_thai);
    }

    public function test_xac_nhan_thu_cong_roi_webhook_den_ghi_doi_soat_khong_lap(): void
    {
        $kh = KhachHang::create(['ho_ten' => 'KH Thu Cong', 'so_dienthoai' => '0900000002', 'diem_tichluy' => 10]);
        $don = $this->taoDonChoThanhToan(13000, $kh);
        $don->update(['diem_tichluy_nhan' => 13]);

        // Thu ngân xác nhận thủ công (cùng code path với nút bấm POS)
        $res = app(ThanhToanChuyenKhoanService::class)->hoanTatThanhToan($don->fresh(), null, $this->cashier);
        $this->assertTrue($res['success']);
        $this->assertSame('da_thanh_toan', $don->fresh()->trang_thai);
        $this->assertSame(23, $kh->fresh()->diem_tichluy); // 10 + 13

        // Webhook thật của SePay đến sau đó
        $this->guiWebhook($this->payloadSePay($don->fresh(), 700006, 13000))->assertOk();

        $this->assertSame('da_thanh_toan', $don->fresh()->trang_thai);
        $this->assertSame(23, $kh->fresh()->diem_tichluy, 'không được cộng điểm lần 2');
        Mail::assertNothingSent();

        $ghi = PaymentWebhook::where('ma_giao_dich_ngoai', '700006')->firstOrFail();
        $this->assertSame('can_doi_soat', $ghi->ket_qua);
        $this->assertSame('hoadon_da_thanh_toan', $ghi->ly_do);
        $this->assertSame($don->id, (int) $ghi->invoice_id);
    }

    /**
     * GHI NHẬN hành vi hiện tại: nhiều giao dịch chuyển thiếu cho CÙNG một
     * đơn KHÔNG được cộng dồn — mỗi webhook chỉ xét số tiền của chính nó.
     * Đề xuất xử lý: nhân viên xác nhận thủ công từ /doisoat/POS.
     */
    public function test_chuyen_thieu_nhieu_lan_khong_cong_don_tu_dong(): void
    {
        $don = $this->taoDonChoThanhToan(13000);

        // Lần 1: thiếu 5000
        $this->guiWebhook($this->payloadSePay($don, 700010, 8000))->assertOk();
        // Lần 2: thiếu 8000 — dù tổng hai lần = 13000 vẫn không hoàn tất
        $this->guiWebhook($this->payloadSePay($don, 700011, 5000))->assertOk();

        $this->assertSame('cho_thanh_toan', $don->fresh()->trang_thai, 'hai lần thiếu cộng lại đủ vẫn không tự hoàn tất');

        $this->assertSame('thieu_tien', PaymentWebhook::where('ma_giao_dich_ngoai', '700010')->value('ly_do'));
        $this->assertSame('thieu_tien', PaymentWebhook::where('ma_giao_dich_ngoai', '700011')->value('ly_do'));
        $this->assertSame(2, PaymentWebhook::where('ket_qua', 'can_doi_soat')->count());

        // Lần 3: một giao dịch đủ tiền → hoàn tất bình thường
        $this->guiWebhook($this->payloadSePay($don, 700012, 13000))->assertOk();
        $this->assertSame('da_thanh_toan', $don->fresh()->trang_thai);
    }
}
