<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\PaymentWebhook;
use App\Models\SanPham;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SepayDoiSoatTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'TOKEN-SEPAY-DOI-SOAT-BI-MAT';
    private const KHOA_WEBHOOK = 'khoa-webhook-test';

    protected User $admin;
    protected User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('role', 'admin')->firstOrFail();
        $this->cashier = User::where('role', 'thungan')->firstOrFail();

        config([
            'pos.sepay_api_token' => self::TOKEN,
            'pos.sepay_api_base_url' => 'https://userapi.sepay.vn/v2',
            'pos.sepay_api_version' => 'v2',
            'pos.sepay_api_timeout' => 5,
            'pos.sepay_api_retries' => 1,
            'pos.sepay_api_per_page' => 100,
            'pos.sepay_api_min_interval_ms' => 0,
            'pos.sepay_api_max_retry_after' => 1,
            'pos.sepay_webhook_api_key' => self::KHOA_WEBHOOK,
            'pos.cho_thanh_toan_phut' => 15,
            'pos.telegram_bot_token' => 'telegram-token-test',
            'pos.telegram_chat_id' => 'telegram-chat-test',
        ]);

        Http::preventStrayRequests();
    }

    private function giaoDichApi(
        string $id,
        string $noiDung,
        int $soTien = 150000,
        string $thamChieu = 'FT-001',
        string $soTaiKhoan = '1017588888'
    ): array {
        return [
            'id' => $id,
            'transaction_date' => '2026-11-11 09:00:00',
            'account_number' => $soTaiKhoan,
            'transfer_type' => 'in',
            'amount_in' => $soTien,
            'amount_out' => 0,
            'transaction_content' => $noiDung,
            'reference_number' => $thamChieu,
            'code' => 'SEVN1',
            'bank_brand_name' => 'VCB',
        ];
    }

    private function phanHoiApi(array $data): array
    {
        return [
            'status' => 'success',
            'data' => $data,
            'meta' => [
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 100,
                    'total' => count($data),
                ],
            ],
        ];
    }

    private function fakeApi(array $data): void
    {
        Http::fake([
            'https://userapi.sepay.vn/v2/*' => Http::response($this->phanHoiApi($data), 200),
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);
    }

    private function soLanGuiTelegram(): int
    {
        return collect(Http::recorded())
            ->filter(fn (array $cap): bool => str_contains($cap[0]->url(), 'api.telegram.org'))
            ->count();
    }

    /**
     * @return list<string>
     */
    private function noiDungTelegram(): array
    {
        return collect(Http::recorded())
            ->filter(fn (array $cap): bool => str_contains($cap[0]->url(), 'api.telegram.org'))
            ->map(fn (array $cap): string => (string) ($cap[0]->data()['text'] ?? ''))
            ->values()
            ->all();
    }

    private function taoHoaDon(
        string $ma,
        string $trangThai = 'cho_thanh_toan',
        int $tong = 150000,
        ?KhachHang $khachHang = null,
        int $diemNhan = 0
    ): DonHang {
        return DonHang::create([
            'ma_hoadon' => $ma,
            'user_id' => $this->cashier->id,
            'customer_id' => $khachHang?->id,
            'tong_tien' => $tong,
            'giam_gia_phantram' => 0,
            'tien_giam' => 0,
            'diem_su_dung' => 0,
            'tien_giam_diem' => 0,
            'diem_tichluy_nhan' => $diemNhan,
            'tong_thanh_toan' => $tong,
            'tien_khach_dua' => 0,
            'tien_thua' => 0,
            'phuong_thuc' => 'chuyenkhoan',
            'trang_thai' => $trangThai,
        ]);
    }

    private function guiWebhook(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('webhook.thanhtoan'), $payload, [
            'Content-Type' => 'application/json',
            'Authorization' => 'Apikey '.self::KHOA_WEBHOOK,
        ]);
    }

    // T-SDS01: Chế độ báo cáo tạo bản ghi can_doi_soat nguồn sepay_api, không đổi đơn/kho/điểm.
    public function test_T_SDS01_bao_cao_tao_ban_ghi_va_khong_doi_hoa_don_kho_diem(): void
    {
        $sanPham = SanPham::firstOrFail();
        $tonKho = (int) $sanPham->soluong;
        $khachHang = KhachHang::create([
            'ho_ten' => 'Khach Doi Soat',
            'so_dienthoai' => '0987000111',
            'diem_tichluy' => 100,
        ]);
        $donHang = $this->taoHoaDon('HD20261111-0001', 'cho_thanh_toan', 150000, $khachHang, 20);

        $this->fakeApi([
            $this->giaoDichApi('uuid-ds-001', 'THANH TOAN HD20261111-0001', 150000, 'FT-DS-001'),
        ]);

        $this->artisan('sepay:doi-soat', ['--gio' => 2])
            ->expectsOutputToContain('1 giao dịch tiền vào, 0 đã có, 1 thiếu mới')
            ->assertExitCode(0);

        $banGhi = PaymentWebhook::where('ma_giao_dich_ngoai', 'api:uuid-ds-001')->firstOrFail();
        $this->assertSame('sepay_api', $banGhi->nha_cung_cap);
        $this->assertSame('FT-DS-001', $banGhi->ma_tham_chieu);
        $this->assertSame($donHang->id, (int) $banGhi->invoice_id);
        $this->assertSame(150000, (int) $banGhi->so_tien);
        $this->assertSame('can_doi_soat', $banGhi->ket_qua);
        $this->assertSame('api_doi_soat', $banGhi->ly_do);
        $this->assertSame($donHang->ma_hoadon, $banGhi->ma_hoadon);
        $this->assertSame('chua_xu_ly', $banGhi->trang_thai_xu_ly);

        $payload = $banGhi->payload;
        $this->assertSame('api_doi_soat', $payload['nguon']);
        $this->assertSame(str_repeat('*', 6).'8888', $payload['account_number_masked']);
        $this->assertArrayNotHasKey('account_number', $payload);
        $this->assertStringNotContainsString('1017588888', json_encode($payload));

        // Báo cáo-only: tuyệt đối không đụng nghiệp vụ.
        $this->assertSame('cho_thanh_toan', $donHang->fresh()->trang_thai);
        $this->assertSame($tonKho, (int) $sanPham->fresh()->soluong);
        $this->assertSame(100, (int) $khachHang->fresh()->diem_tichluy);
        $this->assertDatabaseMissing('stock_movements', ['invoice_id' => $donHang->id]);
    }

    // T-SDS02: Chạy lại không tạo trùng và chỉ gửi Telegram một lần cho mỗi giao dịch.
    public function test_T_SDS02_chay_lai_khong_tao_trung_va_telegram_chi_mot_lan(): void
    {
        $this->taoHoaDon('HD20261111-0002', 'cho_thanh_toan', 150000);
        $this->fakeApi([
            $this->giaoDichApi('uuid-ds-002', 'THANH TOAN HD20261111-0002', 150000, 'FT-DS-002'),
        ]);

        $this->artisan('sepay:doi-soat', ['--gio' => 2])->assertExitCode(0);
        $this->artisan('sepay:doi-soat', ['--gio' => 2])
            ->expectsOutputToContain('1 giao dịch tiền vào, 1 đã có, 0 thiếu mới')
            ->assertExitCode(0);

        $this->assertSame(1, PaymentWebhook::where('ma_tham_chieu', 'FT-DS-002')->count());
        $this->assertSame(1, $this->soLanGuiTelegram());
    }

    // T-SDS03: Giao dịch đã nhận qua webhook không bị coi là thiếu, không xử lý lần hai,
    // không cộng điểm lần hai (cùng giao dịch ở hai nguồn, mã tham chiếu trùng).
    public function test_T_SDS03_webhook_truoc_khong_bi_coi_la_thieu_va_khong_xu_ly_lan_hai(): void
    {
        config(['pos.telegram_bot_token' => '', 'pos.telegram_chat_id' => '']);

        $khachHang = KhachHang::create([
            'ho_ten' => 'Khach Webhook Truoc',
            'so_dienthoai' => '0987000222',
            'diem_tichluy' => 0,
        ]);
        $donHang = $this->taoHoaDon('HD20261111-0003', 'cho_thanh_toan', 150000, $khachHang, 20);

        $this->guiWebhook([
            'id' => 555000111,
            'gateway' => 'VCB',
            'transactionDate' => '2026-11-11 09:00:00',
            'accountNumber' => '1017588888',
            'content' => 'THANH TOAN HD20261111-0003',
            'transferType' => 'in',
            'transferAmount' => 150000,
            'referenceCode' => 'FT-DS-003',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertSame('da_thanh_toan', $donHang->fresh()->trang_thai);
        $this->assertSame(20, (int) $khachHang->fresh()->diem_tichluy);
        $webhook = PaymentWebhook::where('ma_giao_dich_ngoai', '555000111')->firstOrFail();
        $this->assertSame('FT-DS-003', $webhook->ma_tham_chieu);
        $this->assertSame('success', $webhook->ket_qua);

        $this->fakeApi([
            $this->giaoDichApi('uuid-ds-003', 'THANH TOAN HD20261111-0003', 150000, 'FT-DS-003'),
        ]);

        $this->artisan('sepay:doi-soat', ['--gio' => 2])
            ->expectsOutputToContain('1 giao dịch tiền vào, 1 đã có, 0 thiếu mới')
            ->assertExitCode(0);

        $this->assertSame(1, PaymentWebhook::where('ma_tham_chieu', 'FT-DS-003')->count());
        $this->assertSame('da_thanh_toan', $donHang->fresh()->trang_thai);
        $this->assertSame(20, (int) $khachHang->fresh()->diem_tichluy);
        $this->assertSame(0, $this->soLanGuiTelegram());
    }

    // T-SDS04: Sai mã, hóa đơn không tồn tại, đơn hủy, đã thanh toán, thiếu tiền chỉ báo.
    public function test_T_SDS04_cac_truong_hop_khong_khop_chi_bao_cao(): void
    {
        $this->taoHoaDon('HD20261111-0101', 'da_huy', 200000);
        $this->taoHoaDon('HD20261111-0102', 'da_thanh_toan', 200000);
        $this->taoHoaDon('HD20261111-0103', 'cho_thanh_toan', 200000);

        $this->fakeApi([
            $this->giaoDichApi('uuid-ds-101', 'CHUYEN TIEN KHONG CO MA', 200000, 'FT-DS-101'),
            $this->giaoDichApi('uuid-ds-102', 'THANH TOAN HD20261111-9999', 200000, 'FT-DS-102'),
            $this->giaoDichApi('uuid-ds-103', 'THANH TOAN HD20261111-0101', 200000, 'FT-DS-103'),
            $this->giaoDichApi('uuid-ds-104', 'THANH TOAN HD20261111-0102', 200000, 'FT-DS-104'),
            $this->giaoDichApi('uuid-ds-105', 'THANH TOAN HD20261111-0103', 100000, 'FT-DS-105'),
        ]);

        $this->artisan('sepay:doi-soat', ['--gio' => 2])
            ->expectsOutputToContain('5 giao dịch tiền vào, 0 đã có, 5 thiếu mới')
            ->assertExitCode(0);

        $this->assertDatabaseHas('payment_webhooks', [
            'ma_giao_dich_ngoai' => 'api:uuid-ds-101',
            'ly_do' => 'khong_tim_thay_ma_hoadon',
            'invoice_id' => null,
            'ma_hoadon' => null,
        ]);
        $this->assertDatabaseHas('payment_webhooks', [
            'ma_giao_dich_ngoai' => 'api:uuid-ds-102',
            'ly_do' => 'hoadon_khong_ton_tai',
            'ma_hoadon' => 'HD20261111-9999',
        ]);
        $this->assertDatabaseHas('payment_webhooks', [
            'ma_giao_dich_ngoai' => 'api:uuid-ds-103',
            'ly_do' => 'don_da_huy',
        ]);
        $this->assertDatabaseHas('payment_webhooks', [
            'ma_giao_dich_ngoai' => 'api:uuid-ds-104',
            'ly_do' => 'hoadon_da_thanh_toan',
        ]);
        $this->assertDatabaseHas('payment_webhooks', [
            'ma_giao_dich_ngoai' => 'api:uuid-ds-105',
            'ly_do' => 'thieu_tien',
        ]);

        $this->assertSame('da_huy', DonHang::where('ma_hoadon', 'HD20261111-0101')->firstOrFail()->trang_thai);
        $this->assertSame('da_thanh_toan', DonHang::where('ma_hoadon', 'HD20261111-0102')->firstOrFail()->trang_thai);
        $this->assertSame('cho_thanh_toan', DonHang::where('ma_hoadon', 'HD20261111-0103')->firstOrFail()->trang_thai);
        $this->assertDatabaseCount('payment_webhooks', 5);
    }

    // T-SDS04b: Bản ghi báo cáo API không chặn webhook đến sau; webhook gộp cùng
    // bản ghi theo mã tham chiếu, hoàn tất đơn đúng một lần và không tạo trùng.
    public function test_T_SDS04b_webhook_den_sau_gop_cung_ban_ghi_va_hoan_tat_mot_lan(): void
    {
        config(['pos.telegram_bot_token' => '', 'pos.telegram_chat_id' => '']);

        $khachHang = KhachHang::create([
            'ho_ten' => 'Khach Webhook Sau',
            'so_dienthoai' => '0987000333',
            'diem_tichluy' => 0,
        ]);
        $donHang = $this->taoHoaDon('HD20261111-0004', 'cho_thanh_toan', 150000, $khachHang, 20);

        $this->fakeApi([
            $this->giaoDichApi('uuid-ds-004', 'THANH TOAN HD20261111-0004', 150000, 'FT-DS-004'),
        ]);
        $this->artisan('sepay:doi-soat', ['--gio' => 2])->assertExitCode(0);
        $this->assertDatabaseHas('payment_webhooks', [
            'ma_tham_chieu' => 'FT-DS-004',
            'ket_qua' => 'can_doi_soat',
        ]);

        $this->guiWebhook([
            'id' => 555000444,
            'gateway' => 'VCB',
            'transactionDate' => '2026-11-11 09:00:00',
            'accountNumber' => '1017588888',
            'content' => 'THANH TOAN HD20261111-0004',
            'transferType' => 'in',
            'transferAmount' => 150000,
            'referenceCode' => 'FT-DS-004',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertSame('da_thanh_toan', $donHang->fresh()->trang_thai);
        $this->assertSame(20, (int) $khachHang->fresh()->diem_tichluy);

        $banGhi = PaymentWebhook::where('ma_tham_chieu', 'FT-DS-004')->firstOrFail();
        $this->assertSame('sepay', $banGhi->nha_cung_cap);
        $this->assertSame('success', $banGhi->ket_qua);
        $this->assertSame($donHang->id, (int) $banGhi->invoice_id);
        $this->assertSame(1, PaymentWebhook::where('ma_tham_chieu', 'FT-DS-004')->count());

        $this->artisan('sepay:doi-soat', ['--gio' => 2])
            ->expectsOutputToContain('1 giao dịch tiền vào, 1 đã có, 0 thiếu mới')
            ->assertExitCode(0);
        $this->assertSame(20, (int) $khachHang->fresh()->diem_tichluy);
        $this->assertSame(1, PaymentWebhook::where('ma_tham_chieu', 'FT-DS-004')->count());
    }

    // T-SDS11: Giao dịch không tìm thấy hóa đơn chỉ ghi /doisoat, không gửi Telegram.
    public function test_T_SDS11_khong_tim_thay_hoa_don_khong_gui_telegram(): void
    {
        $this->fakeApi([
            $this->giaoDichApi('uuid-ds-201', 'CHUYEN TIEN CA NHAN', 50000, 'FT-DS-201'),
            $this->giaoDichApi('uuid-ds-202', 'THANH TOAN HD20261111-9999', 70000, 'FT-DS-202'),
        ]);

        $this->artisan('sepay:doi-soat', ['--gio' => 2])
            ->expectsOutputToContain('2 giao dịch tiền vào, 0 đã có, 2 thiếu mới')
            ->assertExitCode(0);

        $this->assertDatabaseHas('payment_webhooks', [
            'ma_giao_dich_ngoai' => 'api:uuid-ds-201',
            'ly_do' => 'khong_tim_thay_ma_hoadon',
        ]);
        $this->assertDatabaseHas('payment_webhooks', [
            'ma_giao_dich_ngoai' => 'api:uuid-ds-202',
            'ly_do' => 'hoadon_khong_ton_tai',
        ]);
        $this->assertSame(0, $this->soLanGuiTelegram());
    }

    // T-SDS12: Giới hạn tin Telegram mỗi lần chạy; phần vượt gộp thành một tin tóm tắt.
    public function test_T_SDS12_gioi_han_telegram_va_gop_tin_con_lai(): void
    {
        config(['pos.sepay_doi_soat_telegram_toi_da' => 2]);

        $ds = [];
        foreach ([1, 2, 3, 4] as $i) {
            $ma = sprintf('HD20261111-02%02d', $i);
            $this->taoHoaDon($ma, 'cho_thanh_toan', 100000);
            $ds[] = $this->giaoDichApi('uuid-ds-30'.$i, 'THANH TOAN '.$ma, 100000, 'FT-DS-30'.$i);
        }
        $ds[] = $this->giaoDichApi('uuid-ds-305', 'CHUYEN TIEN CA NHAN', 80000, 'FT-DS-305');
        $this->fakeApi($ds);

        $this->artisan('sepay:doi-soat', ['--gio' => 2])
            ->expectsOutputToContain('5 giao dịch tiền vào, 0 đã có, 5 thiếu mới')
            ->assertExitCode(0);

        $tin = $this->noiDungTelegram();
        $this->assertCount(3, $tin, 'Hai tin chi tiết + một tin tóm tắt phần vượt giới hạn.');
        $this->assertStringContainsString('Còn 2 giao dịch thiếu webhook', implode("\n", $tin));

        // Giao dịch không có hóa đơn không được lọt vào tin chi tiết.
        foreach ($tin as $noiDung) {
            $this->assertStringNotContainsString('Hóa đơn: không rõ', $noiDung);
        }
    }

    // T-SDS05: --ap-dung bị từ chối trước khi gọi mạng hay ghi dữ liệu.
    public function test_T_SDS05_ap_dung_bi_tu_choi_khong_goi_mang_khong_ghi_du_lieu(): void
    {
        Http::fake();

        $this->artisan('sepay:doi-soat', ['--ap-dung' => true])
            ->expectsOutputToContain('Không thể chạy --ap-dung: chưa chứng minh được khóa chống trùng chung')
            ->assertExitCode(1);

        Http::assertNothingSent();
        $this->assertDatabaseCount('payment_webhooks', 0);
    }

    // T-SDS06: Thiếu token thì tắt tính năng, không gọi mạng.
    public function test_T_SDS06_thieu_token_khong_goi_mang(): void
    {
        config(['pos.sepay_api_token' => '']);
        Http::fake();

        $this->artisan('sepay:doi-soat')
            ->expectsOutputToContain('Chưa cấu hình SEPAY_API_TOKEN')
            ->assertExitCode(1);

        Http::assertNothingSent();
        $this->assertDatabaseCount('payment_webhooks', 0);
    }

    // T-SDS07: Lỗi xác thực API chỉ in thông điệp an toàn, không lộ token.
    public function test_T_SDS07_loi_api_khong_lo_token(): void
    {
        Http::fake([
            'https://userapi.sepay.vn/v2/*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $maThoat = Artisan::call('sepay:doi-soat');
        $output = Artisan::output();

        $this->assertSame(1, $maThoat);
        $this->assertStringContainsString('401', $output);
        $this->assertStringContainsString('SEPAY_API_TOKEN', $output);
        $this->assertStringNotContainsString(self::TOKEN, $output);
        $this->assertDatabaseCount('payment_webhooks', 0);
    }

    // T-SDS08: Lịch chạy 10 phút đã đăng ký.
    public function test_T_SDS08_lich_doi_soat_da_dang_ky(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('sepay:doi-soat')
            ->assertExitCode(0);
    }

    // T-SDS09: /doisoat gắn nhãn nguồn api_doi_soat và vẫn chỉ admin.
    public function test_T_SDS09_man_doi_soat_gan_nhan_nguon_va_chi_admin(): void
    {
        $banGhi = PaymentWebhook::create([
            'nha_cung_cap' => 'sepay_api',
            'ma_giao_dich_ngoai' => 'api:uuid-ds-901',
            'ma_tham_chieu' => 'FT-DS-901',
            'so_tien' => 100000,
            'ket_qua' => 'can_doi_soat',
            'ly_do' => 'api_doi_soat',
            'ma_hoadon' => 'HD20261111-0901',
            'noi_dung' => 'THANH TOAN HD20261111-0901',
            'payload' => ['nguon' => 'api_doi_soat'],
            'trang_thai_xu_ly' => 'chua_xu_ly',
        ]);

        $this->actingAs($this->admin)->get(route('doisoat.index'))
            ->assertOk()
            ->assertSee('api_doi_soat')
            ->assertSee($banGhi->ma_giao_dich_ngoai);

        $this->actingAs($this->cashier)->get(route('doisoat.index'))->assertForbidden();
    }
}
