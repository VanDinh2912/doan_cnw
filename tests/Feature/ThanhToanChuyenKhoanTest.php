<?php

namespace Tests\Feature;

use App\Http\Controllers\WebhookController;
use App\Models\BienDongKho;
use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\PaymentWebhook;
use App\Models\SanPham;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Routing\RouteCollection;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ThanhToanChuyenKhoanTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;
    protected User $admin;
    protected string $apiKey = 'test_sepay_key_123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->cashier = User::where('role', 'thungan')->firstOrFail();
        $this->admin   = User::where('role', 'admin')->firstOrFail();

        Config::set('pos.sepay_webhook_api_key', $this->apiKey);
        Config::set('pos.cho_thanh_toan_phut', 15);
        Config::set('pos.telegram_bot_token', '');
        Config::set('pos.telegram_chat_id', '');
    }

    public function test_webhook_chi_dang_ky_mot_post_dung_action_name_va_throttle(): void
    {
        $this->app->make(Kernel::class);
        $routesGoc = Route::getRoutes();
        // RouteCollection ghi đè URI trùng nhau nên phải đếm ngay lúc đăng ký.
        $routes = new class extends RouteCollection
        {
            public int $soPostWebhook = 0;

            public function add(RoutingRoute $route)
            {
                if ($route->uri() === 'api/webhook/thanhtoan' && in_array('POST', $route->methods(), true)) {
                    $this->soPostWebhook++;
                }

                return parent::add($route);
            }
        };

        try {
            Route::setRoutes($routes);
            Route::prefix('api')->middleware('api')->group(base_path('routes/api.php'));
            Route::middleware('web')->group(base_path('routes/web.php'));
            $routes->refreshNameLookups();

            $webhooks = collect($routes->getRoutes())->filter(fn (RoutingRoute $route) =>
                $route->uri() === 'api/webhook/thanhtoan' && in_array('POST', $route->methods(), true)
            )->values();

            $this->assertSame(1, $routes->soPostWebhook);
            $this->assertCount(1, $webhooks);
            $this->assertSame(['POST'], $webhooks[0]->methods());
            $this->assertSame('webhook.thanhtoan', $webhooks[0]->getName());
            $this->assertSame(WebhookController::class.'@postThanhToan', $webhooks[0]->getActionName());
            $this->assertContains('throttle:60,1', $webhooks[0]->gatherMiddleware());
            $this->assertSame([ThrottleRequests::class.':60,1'], $this->app['router']->gatherRouteMiddleware($webhooks[0]));
            $this->assertFalse(Route::has('api.webhook.thanhtoan'));
        } finally {
            Route::setRoutes($routesGoc);
        }
    }

    public function test_csrf_dang_bat_chi_mien_dung_path_webhook(): void
    {
        $middleware = $this->middlewareCsrfDangBat();
        $this->assertSame(['api/webhook/thanhtoan'], $middleware->getExcludedPaths());
        $session = $this->app['session']->driver();
        $session->start();
        $this->assertNotEmpty($session->token());

        foreach ([
            '/api/webhook/thanhtoan' => 204,
            '/api/webhook/khac' => 419,
            '/api/webhook/thanhtoan/khac' => 419,
            '/dangnhap' => 419,
        ] as $path => $statusMongDoi) {
            $request = Request::create($path, 'POST');
            $request->setLaravelSession($session);

            try {
                $status = $middleware->handle($request, fn () => response('', 204))->getStatusCode();
            } catch (TokenMismatchException) {
                $status = 419;
            }

            $this->assertSame($statusMongDoi, $status, $path);
        }
    }

    public function test_webhook_khong_can_session_cookie_khi_csrf_dang_bat(): void
    {
        $this->app->instance(ValidateCsrfToken::class, $this->middlewareCsrfDangBat());

        $response = $this->postJson(route('webhook.thanhtoan'), []);
        $response->assertUnauthorized()->assertJson(['error' => 'Unauthorized']);
        $this->assertFalse($this->app['request']->hasSession());
        $this->assertSame([], $this->app['request']->cookies->all());
        $this->assertSame([], $response->headers->getCookies());

        $response = $this->guiSepayWebhook([]);
        $response->assertStatus(400)->assertJson(['error' => 'Invalid payload']);
        $this->assertFalse($this->app['request']->hasSession());
        $this->assertSame([], $response->headers->getCookies());

        $donHang = $this->taoHoaDonChoThanhToan();
        $response = $this->guiSepayWebhook([
            'id' => 987654321,
            'transferAmount' => 200000,
            'transferType' => 'in',
            'content' => $donHang->ma_hoadon,
        ]);
        $response->assertOk()->assertJson(['success' => true]);
        $this->assertSame('da_thanh_toan', $donHang->fresh()->trang_thai);
        $this->assertFalse($this->app['request']->hasSession());
        $this->assertSame([], $response->headers->getCookies());

        $this->postJson(route('auth.postdangnhap'), [])->assertStatus(419);
    }

    private function middlewareCsrfDangBat(): ValidateCsrfToken
    {
        $this->app->make(Kernel::class);

        return new class($this->app, $this->app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        };
    }

    /**
     * Helper gửi SePay webhook.
     */
    private function guiSepayWebhook(array $payload, ?string $key = 'test_sepay_key_123'): \Illuminate\Testing\TestResponse
    {
        $headers = ['Content-Type' => 'application/json'];
        if ($key !== null) {
            $headers['Authorization'] = 'Apikey ' . $key;
        }

        return $this->postJson(route('webhook.thanhtoan'), $payload, $headers);
    }

    private static int $hdCounter = 8000;

    /**
     * Helper tạo nhanh hóa đơn cho_thanh_toan có chi tiết và trừ kho/điểm ban đầu.
     */
    private function taoHoaDonChoThanhToan(int $tongThanhToan = 200000, ?KhachHang $khachHang = null, int $diemDung = 0, int $diemNhan = 20): DonHang
    {
        self::$hdCounter++;
        $sp = SanPham::firstOrFail();
        $maHoaDon = sprintf('HD%s-%04d', now()->format('Ymd'), self::$hdCounter);

        $donHang = DonHang::create([
            'ma_hoadon'          => $maHoaDon,
            'user_id'            => $this->cashier->id,
            'customer_id'        => $khachHang?->id,
            'tong_tien'          => $tongThanhToan + ($diemDung * 1000),
            'giam_gia_phantram'  => 0,
            'tien_giam'          => 0,
            'diem_su_dung'       => $diemDung,
            'tien_giam_diem'     => $diemDung * 1000,
            'diem_tichluy_nhan'  => $diemNhan,
            'tong_thanh_toan'    => $tongThanhToan,
            'tien_khach_dua'     => $tongThanhToan,
            'tien_thua'          => 0,
            'phuong_thuc'        => 'chuyenkhoan',
            'trang_thai'         => 'cho_thanh_toan',
        ]);

        ChiTietDonHang::create([
            'invoice_id' => $donHang->id,
            'product_id' => $sp->id,
            'soluong'    => 1,
            'don_gia'    => $sp->gia_ban,
            'gia_von'    => $sp->gia_nhap,
            'thanh_tien' => $tongThanhToan,
        ]);

        $sp->decrement('soluong', 1);

        BienDongKho::create([
            'product_id' => $sp->id,
            'user_id'    => $this->cashier->id,
            'invoice_id' => $donHang->id,
            'loai'       => 'ban',
            'soluong'    => -1,
            'ghi_chu'    => 'Bán hàng ' . $donHang->ma_hoadon,
        ]);

        if ($khachHang && $diemDung > 0) {
            $khachHang->decrement('diem_tichluy', $diemDung);
        }

        return $donHang;
    }

    // ----------------------------------------------------------------
    // T-CK01: Thu ngân gọi donhang.taoqr tạo hóa đơn cho_thanh_toan, trừ kho, trừ điểm dùng
    // ----------------------------------------------------------------
    public function test_T_CK01_tao_qr_tao_hoa_don_cho_thanh_toan_va_tru_ton_kho(): void
    {
        $sp = SanPham::firstOrFail();
        $tonKhoGoc = $sp->soluong;

        $kh = KhachHang::create([
            'ho_ten'        => 'Nguyen Van Test',
            'so_dienthoai'  => '0988776655',
            'diem_tichluy'  => 100,
        ]);

        $gioHang = [
            $sp->id => [
                'id'          => $sp->id,
                'ten_sanpham' => $sp->ten_sanpham,
                'sku'         => $sp->sku,
                'gia_ban'     => (float) $sp->gia_ban,
                'soluong'     => 2,
                'ton_kho'     => $tonKhoGoc,
            ],
        ];

        $response = $this->actingAs($this->cashier)
            ->withSession(['gio_hang' => $gioHang])
            ->postJson(route('donhang.taoqr'), [
                'giam_gia_phantram' => 10,
                'customer_id'       => $kh->id,
                'diem_su_dung'      => 20,
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['id', 'ma_hoadon', 'tong_thanh_toan', 'url_qr', 'het_han_luc']);

        $donHangId = $response->json('id');
        $donHang = DonHang::find($donHangId);
        $this->assertNotNull($donHang);
        $this->assertSame('cho_thanh_toan', $donHang->trang_thai);

        // Kiểm tra trừ tồn kho
        $this->assertSame($tonKhoGoc - 2, $sp->fresh()->soluong);

        // Kiểm tra biến động kho loại ban
        $this->assertDatabaseHas('stock_movements', [
            'invoice_id' => $donHang->id,
            'product_id' => $sp->id,
            'loai'       => 'ban',
            'soluong'    => -2,
        ]);

        // Kiểm tra trừ điểm dùng
        $this->assertSame(80, $kh->fresh()->diem_tichluy);

        // Kiểm tra giỏ hàng session đã được xóa
        $this->assertNull(session('gio_hang'));
    }

    // ----------------------------------------------------------------
    // T-CK02: Thu ngân gọi donhang.taoqr khi giỏ hàng rỗng ➜ 422
    // ----------------------------------------------------------------
    public function test_T_CK02_tao_qr_gio_hang_rong_tra_ve_422(): void
    {
        $response = $this->actingAs($this->cashier)
            ->withSession(['gio_hang' => []])
            ->postJson(route('donhang.taoqr'), []);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Giỏ hàng đang trống.']);
    }

    // ----------------------------------------------------------------
    // T-CK03: Webhook SePay hợp lệ ➜ chuyển da_thanh_toan, ghi ma_giaodich, cộng điểm
    // ----------------------------------------------------------------
    public function test_T_CK03_webhook_sepay_hop_le_chuyen_da_thanh_toan_va_cong_diem(): void
    {
        $kh = KhachHang::create([
            'ho_ten'       => 'Test Webhook Valid',
            'so_dienthoai' => '0911223344',
            'diem_tichluy' => 50,
        ]);

        $donHang = $this->taoHoaDonChoThanhToan(300000, $kh, 0, 30);
        $cleanMa = str_replace('-', '', $donHang->ma_hoadon);

        $payload = [
            'id'              => 998877,
            'gateway'         => 'MBBank',
            'transactionDate' => now()->toDateTimeString(),
            'accountNumber'   => '0388888888',
            'transferType'    => 'in',
            'transferAmount'  => 300000,
            'content'         => 'THANH TOAN DON ' . $cleanMa,
            'referenceCode'   => 'FT998877',
        ];

        $response = $this->guiSepayWebhook($payload);
        $response->assertOk();
        $response->assertJson(['success' => true]);

        $donHang->refresh();
        $this->assertSame('da_thanh_toan', $donHang->trang_thai);
        $this->assertSame('998877', $donHang->ma_giaodich);
        $this->assertNotNull($donHang->thoi_gian_thanh_toan);

        // Cộng điểm thưởng
        $this->assertSame(80, $kh->fresh()->diem_tichluy); // 50 + 30
    }

    // ----------------------------------------------------------------
    // T-CK03b: Webhook thành công lưu mã tham chiếu để đối soát API biết đã nhận
    // ----------------------------------------------------------------
    public function test_T_CK03b_webhook_thanh_cong_luu_ma_tham_chieu(): void
    {
        $donHang = $this->taoHoaDonChoThanhToan(120000);
        $cleanMa = str_replace('-', '', $donHang->ma_hoadon);

        $this->guiSepayWebhook([
            'id'              => 771122,
            'gateway'         => 'VCB',
            'transactionDate' => now()->toDateTimeString(),
            'accountNumber'   => '1017588888',
            'transferType'    => 'in',
            'transferAmount'  => 120000,
            'content'         => 'THANH TOAN DON ' . $cleanMa,
            'referenceCode'   => 'FT-API-771122',
        ])->assertOk()->assertJson(['success' => true]);

        $banGhi = PaymentWebhook::where('ma_giao_dich_ngoai', '771122')->firstOrFail();
        $this->assertSame('FT-API-771122', $banGhi->ma_tham_chieu);
        $this->assertSame('success', $banGhi->ket_qua);
        $this->assertSame($donHang->id, (int) $banGhi->invoice_id);
        $this->assertSame('da_xu_ly', $banGhi->trang_thai_xu_ly);
    }

    // ----------------------------------------------------------------
    // T-CK04: Webhook SePay sai API Key ➜ 401 Unauthorized
    // ----------------------------------------------------------------
    public function test_T_CK04_webhook_sepay_sai_auth_tra_ve_401(): void
    {
        $donHang = $this->taoHoaDonChoThanhToan(100000);

        $payload = [
            'id'             => 111,
            'transferType'   => 'in',
            'transferAmount' => 100000,
            'content'        => $donHang->ma_hoadon,
        ];

        // Gửi với key sai
        $response = $this->guiSepayWebhook($payload, 'wrong_api_key');
        $response->assertStatus(401);

        // Hóa đơn vẫn nguyên
        $this->assertSame('cho_thanh_toan', $donHang->fresh()->trang_thai);
    }

    // ----------------------------------------------------------------
    // T-CK05: Webhook SePay gửi trùng lặp (idempotent) ➜ không cộng điểm 2 lần
    // ----------------------------------------------------------------
    public function test_T_CK05_webhook_sepay_gui_trung_lap_idempotent(): void
    {
        $kh = KhachHang::create([
            'ho_ten'       => 'Test Duplicate',
            'so_dienthoai' => '0922334455',
            'diem_tichluy' => 10,
        ]);

        $donHang = $this->taoHoaDonChoThanhToan(200000, $kh, 0, 20);
        $cleanMa = str_replace('-', '', $donHang->ma_hoadon);

        $payload = [
            'id'             => 555666,
            'transferType'   => 'in',
            'transferAmount' => 200000,
            'content'        => 'CHUYEN KHOAN ' . $cleanMa,
        ];

        // Gửi lần 1
        $this->guiSepayWebhook($payload)->assertOk();
        $this->assertSame(30, $kh->fresh()->diem_tichluy); // 10 + 20

        // Gửi lần 2 (trùng lặp)
        $res2 = $this->guiSepayWebhook($payload);
        $res2->assertOk();
        $res2->assertJson(['success' => true]);

        // Điểm không được cộng thêm
        $this->assertSame(30, $kh->fresh()->diem_tichluy);
    }

    // ----------------------------------------------------------------
    // T-CK06: Webhook SePay số tiền lớn hơn tổng thanh toán ➜ hoàn tất + ghi chú dư
    // ----------------------------------------------------------------
    public function test_T_CK06_webhook_sepay_so_tien_lon_hon_tong_thanh_toan(): void
    {
        $donHang = $this->taoHoaDonChoThanhToan(200000);
        $cleanMa = str_replace('-', '', $donHang->ma_hoadon);

        $payload = [
            'id'             => 777111,
            'transferType'   => 'in',
            'transferAmount' => 250000, // chuyển dư 50k
            'content'        => $cleanMa,
        ];

        $response = $this->guiSepayWebhook($payload);
        $response->assertOk();

        $donHang->refresh();
        $this->assertSame('da_thanh_toan', $donHang->trang_thai);
        $this->assertStringContainsString('khách chuyển dư', $donHang->ghi_chu ?? '');
    }

    // ----------------------------------------------------------------
    // T-CK07: Webhook SePay số tiền nhỏ hơn tổng thanh toán ➜ giữ cho_thanh_toan
    // ----------------------------------------------------------------
    public function test_T_CK07_webhook_sepay_so_tien_nho_hon_tong_thanh_toan(): void
    {
        $donHang = $this->taoHoaDonChoThanhToan(200000);
        $cleanMa = str_replace('-', '', $donHang->ma_hoadon);

        $payload = [
            'id'             => 777222,
            'transferType'   => 'in',
            'transferAmount' => 150000, // thiếu 50k
            'content'        => $cleanMa,
        ];

        $response = $this->guiSepayWebhook($payload);
        // Theo quy ước trả success true để cổng thanh toán không spam retry
        $response->assertOk();
        $response->assertJson(['success' => true]);

        $donHang->refresh();
        $this->assertSame('cho_thanh_toan', $donHang->trang_thai);
        $this->assertNull($donHang->ma_giaodich);
    }

    // ----------------------------------------------------------------
    // T-CK08: Webhook SePay transferType = out ➜ bỏ qua không xử lý
    // ----------------------------------------------------------------
    public function test_T_CK08_webhook_sepay_transfer_type_out_bo_qua(): void
    {
        $donHang = $this->taoHoaDonChoThanhToan(200000);
        $cleanMa = str_replace('-', '', $donHang->ma_hoadon);

        $payload = [
            'id'             => 777333,
            'transferType'   => 'out', // tiền ra
            'transferAmount' => 200000,
            'content'        => $cleanMa,
        ];

        $response = $this->guiSepayWebhook($payload);
        $response->assertOk();

        $donHang->refresh();
        $this->assertSame('cho_thanh_toan', $donHang->trang_thai);
    }

    // ----------------------------------------------------------------
    // T-CK09: Webhook SePay nội dung không chứa mã hóa đơn ➜ bỏ qua
    // ----------------------------------------------------------------
    public function test_T_CK09_webhook_sepay_noi_dung_khong_chua_ma_hoadon(): void
    {
        $donHang = $this->taoHoaDonChoThanhToan(200000);

        $payload = [
            'id'             => 777444,
            'transferType'   => 'in',
            'transferAmount' => 200000,
            'content'        => 'Chuyen tien an trua khong co ma',
        ];

        $response = $this->guiSepayWebhook($payload);
        $response->assertOk();

        $donHang->refresh();
        $this->assertSame('cho_thanh_toan', $donHang->trang_thai);
    }

    // ----------------------------------------------------------------
    // T-CK10: Thu ngân bấm "Xác nhận đã nhận tiền" ➜ da_thanh_toan, cộng điểm
    // ----------------------------------------------------------------
    public function test_T_CK10_thu_ngan_xac_nhan_thu_cong_hoan_tat_thanh_toan(): void
    {
        $kh = KhachHang::create([
            'ho_ten'       => 'Test Manual Confirm',
            'so_dienthoai' => '0933445566',
            'diem_tichluy' => 20,
        ]);

        $donHang = $this->taoHoaDonChoThanhToan(150000, $kh, 0, 15);

        $response = $this->actingAs($this->cashier)
            ->postJson(route('donhang.xacnhan', $donHang));

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $donHang->refresh();
        $this->assertSame('da_thanh_toan', $donHang->trang_thai);
        $this->assertSame(35, $kh->fresh()->diem_tichluy); // 20 + 15
    }

    // ----------------------------------------------------------------
    // T-CK11: Thu ngân bấm xác nhận khi hóa đơn đã được xử lý trước đó ➜ an toàn
    // ----------------------------------------------------------------
    public function test_T_CK11_thu_ngan_xac_nhan_khi_hoa_don_da_thanh_toan_truoc_do(): void
    {
        $kh = KhachHang::create([
            'ho_ten'       => 'Test Already Confirmed',
            'so_dienthoai' => '0944556677',
            'diem_tichluy' => 20,
        ]);

        $donHang = $this->taoHoaDonChoThanhToan(150000, $kh, 0, 15);

        // Webhook hoàn tất trước
        $this->guiSepayWebhook([
            'id'             => 888999,
            'transferType'   => 'in',
            'transferAmount' => 150000,
            'content'        => str_replace('-', '', $donHang->ma_hoadon),
        ]);

        $this->assertSame(35, $kh->fresh()->diem_tichluy);

        // Thu ngân bấm xác nhận sau đó
        $response = $this->actingAs($this->cashier)
            ->postJson(route('donhang.xacnhan', $donHang));

        $response->assertStatus(422);
        // Điểm không bị cộng thêm
        $this->assertSame(35, $kh->fresh()->diem_tichluy);
    }

    // ----------------------------------------------------------------
    // T-CK12: Thu ngân hủy chờ ➜ da_huy, hoàn kho, hoàn điểm
    // ----------------------------------------------------------------
    public function test_T_CK12_thu_ngan_huy_cho_hoan_kho_va_hoan_diem(): void
    {
        $sp = SanPham::firstOrFail();
        $tonKhoGoc = $sp->soluong;

        $kh = KhachHang::create([
            'ho_ten'       => 'Test Cancel',
            'so_dienthoai' => '0955667788',
            'diem_tichluy' => 50,
        ]);

        // Tạo hóa đơn chờ, trừ 1 tồn kho, dùng 30 điểm
        $donHang = $this->taoHoaDonChoThanhToan(100000, $kh, 30, 10);
        $this->assertSame(20, $kh->fresh()->diem_tichluy); // 50 - 30

        $response = $this->actingAs($this->cashier)
            ->postJson(route('donhang.huycho', $donHang));

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $donHang->refresh();
        $this->assertSame('da_huy', $donHang->trang_thai);

        // Hoàn lại kho
        $this->assertSame($tonKhoGoc, $sp->fresh()->soluong);

        // Biến động kho điều chỉnh dương
        $this->assertDatabaseHas('stock_movements', [
            'invoice_id' => $donHang->id,
            'product_id' => $sp->id,
            'loai'       => 'dieuchinh',
            'soluong'    => 1,
        ]);

        // Hoàn lại điểm đã dùng
        $this->assertSame(50, $kh->fresh()->diem_tichluy);
    }

    // ----------------------------------------------------------------
    // T-CK13: POS polling trangthai khi chưa quá hạn ➜ trả cho_thanh_toan
    // ----------------------------------------------------------------
    public function test_T_CK13_pos_polling_trang_thai_chua_qua_han(): void
    {
        $donHang = $this->taoHoaDonChoThanhToan(100000);

        $response = $this->actingAs($this->cashier)
            ->getJson(route('donhang.trangthai', $donHang));

        $response->assertOk();
        $response->assertJson([
            'trang_thai' => 'cho_thanh_toan',
            'url_in'     => null,
        ]);
    }

    // ----------------------------------------------------------------
    // T-CK14: POS polling trangthai khi đã quá hạn ➜ hủy lười, trả da_huy
    // ----------------------------------------------------------------
    public function test_T_CK14_pos_polling_trang_thai_da_qua_han_kich_hoat_huy_luoi(): void
    {
        $sp = SanPham::firstOrFail();
        $tonKhoGoc = $sp->soluong;

        $kh = KhachHang::create([
            'ho_ten'       => 'Test Lazy Expiry',
            'so_dienthoai' => '0966778899',
            'diem_tichluy' => 40,
        ]);

        $donHang = $this->taoHoaDonChoThanhToan(100000, $kh, 20, 10);
        // Đặt thời gian tạo quá hạn (lùi 20 phút trước)
        $donHang->created_at = now()->subMinutes(20);
        $donHang->save();

        $response = $this->actingAs($this->cashier)
            ->getJson(route('donhang.trangthai', $donHang));

        $response->assertOk();
        $response->assertJson([
            'trang_thai' => 'da_huy',
            'url_in'     => null,
        ]);

        $donHang->refresh();
        $this->assertSame('da_huy', $donHang->trang_thai);
        $this->assertSame($tonKhoGoc, $sp->fresh()->soluong);
        $this->assertSame(40, $kh->fresh()->diem_tichluy);
    }

    // ----------------------------------------------------------------
    // T-CK15: Artisan command donhang:huy-qua-han quét và hủy đơn quá hạn
    // ----------------------------------------------------------------
    public function test_T_CK15_artisan_command_huy_qua_han(): void
    {
        $donHangQuaHan = $this->taoHoaDonChoThanhToan(100000);
        $donHangQuaHan->created_at = now()->subMinutes(25);
        $donHangQuaHan->save();

        $donHangMoi = $this->taoHoaDonChoThanhToan(150000);

        $this->artisan('donhang:huy-qua-han')->assertExitCode(0);

        $this->assertSame('da_huy', $donHangQuaHan->fresh()->trang_thai);
        $this->assertSame('cho_thanh_toan', $donHangMoi->fresh()->trang_thai);
    }

    // ----------------------------------------------------------------
    // T-CK16: Hóa đơn cho_thanh_toan không in được, không tính vào doanh thu; da_thanh_toan thì được
    // ----------------------------------------------------------------
    public function test_T_CK16_hoa_don_cho_thanh_toan_khong_the_in_va_khong_tinh_doanh_thu(): void
    {
        $donHang = $this->taoHoaDonChoThanhToan(100000);

        // 1. Không thể in hóa đơn khi chưa thanh toán (403)
        $this->actingAs($this->cashier)
            ->get(route('donhang.inhoadon', $donHang))
            ->assertStatus(403);

        // 2. Không được tính vào doanh thu thống kê
        $resDoanhThu = $this->actingAs($this->admin)->get(route('thongke.doanhthu'));
        $resDoanhThu->assertOk();
        // Hóa đơn chưa thanh toán không được xuất hiện trong doanh thu.
        $this->assertSame([], $resDoanhThu->json());

        // 3. Sau khi thanh toán thành công
        $this->actingAs($this->cashier)
            ->postJson(route('donhang.xacnhan', $donHang))
            ->assertOk();

        // 4. Doanh thu ghi nhận đúng số tiền của hóa đơn vừa thanh toán.
        $doanhThu = $this->actingAs($this->admin)
            ->get(route('thongke.doanhthu'))
            ->assertOk()
            ->json();
        $this->assertCount(1, $doanhThu);
        $this->assertSame(now()->format('Y-m-d'), $doanhThu[0]['ngay']);
        $this->assertSame(100000, (int) $doanhThu[0]['doanh_thu']);

        // 5. In được hóa đơn
        $this->actingAs($this->cashier)
            ->get(route('donhang.inhoadon', $donHang))
            ->assertOk();
    }

    // ----------------------------------------------------------------
    // T-CK17: Webhook tới đơn đã hủy ➜ bỏ qua, không đổi trạng thái/kho/điểm
    // ----------------------------------------------------------------
    public function test_T_CK17_webhook_toi_don_da_huy_bi_bo_qua(): void
    {
        $sp = SanPham::firstOrFail();

        $kh = KhachHang::create([
            'ho_ten'       => 'Test Webhook Da Huy',
            'so_dienthoai' => '0977112233',
            'diem_tichluy' => 25,
        ]);

        $donHang = $this->taoHoaDonChoThanhToan(200000, $kh, 0, 20);
        // Cho đơn quá hạn rồi chạy lệnh hủy để có trạng thái da_huy thật (kho đã hoàn).
        $donHang->created_at = now()->subMinutes(20);
        $donHang->save();
        $this->artisan('donhang:huy-qua-han')->assertExitCode(0);

        $donHang->refresh();
        $this->assertSame('da_huy', $donHang->trang_thai);
        $tonKhoSauHuy = $sp->fresh()->soluong;
        $diemSauHuy   = $kh->fresh()->diem_tichluy;

        $cleanMa = str_replace('-', '', $donHang->ma_hoadon);
        $response = $this->guiSepayWebhook([
            'id'             => 778899,
            'transferType'   => 'in',
            'transferAmount' => 200000,
            'content'        => 'THANH TOAN DON ' . $cleanMa,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'message' => 'Hóa đơn đã bị hủy trước đó.']);

        // Đơn vẫn da_huy: không ghi mã giao dịch, không cộng điểm, không hoàn kho lần 2.
        $donHang->refresh();
        $this->assertSame('da_huy', $donHang->trang_thai);
        $this->assertNull($donHang->ma_giaodich);
        $this->assertNull($donHang->ma_giao_dich);
        $this->assertNull($donHang->thoi_gian_thanh_toan);
        $this->assertSame($tonKhoSauHuy, $sp->fresh()->soluong);
        $this->assertSame($diemSauHuy, $kh->fresh()->diem_tichluy);

        // Tiền đã về mà không hoàn tất được đơn => phải nằm trong hàng chờ đối soát.
        $this->assertDatabaseHas('payment_webhooks', [
            'ma_giao_dich_ngoai' => '778899',
            'invoice_id' => $donHang->id,
            'ket_qua' => 'can_doi_soat',
            'ly_do' => 'don_da_huy',
            'trang_thai_xu_ly' => 'chua_xu_ly',
            'so_tien' => 200000,
        ]);
        $this->assertDatabaseCount('payment_webhooks', 1);

        // SePay gửi lại cùng mã giao dịch: không nhân đôi bản ghi đối soát.
        $this->guiSepayWebhook([
            'id' => 778899, 'transferType' => 'in',
            'transferAmount' => 200000, 'content' => 'THANH TOAN DON ' . $cleanMa,
        ])->assertOk();
        $this->assertDatabaseCount('payment_webhooks', 1);
    }

    // ----------------------------------------------------------------
    // T-CK18: Webhook tới đơn quá hạn nhưng CHƯA bị hủy ➜ vẫn hoàn tất,
    // kèm ghi chú "Thanh toán trễ hạn" trên đơn và cảnh báo Telegram cho thu ngân.
    // Hành vi hoàn tất được giữ nguyên; chỉ bổ sung dấu vết để đối soát.
    // ----------------------------------------------------------------
    public function test_T_CK18_webhook_toi_don_qua_han_chua_huy_van_hoan_tat(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        Config::set('pos.telegram_bot_token', 'test_telegram_token');
        Config::set('pos.telegram_chat_id', '123456');

        $sp = SanPham::firstOrFail();

        $kh = KhachHang::create([
            'ho_ten'       => 'Test Webhook Qua Han',
            'so_dienthoai' => '0966445577',
            'diem_tichluy' => 10,
        ]);

        $donHang = $this->taoHoaDonChoThanhToan(250000, $kh, 0, 25);
        // Quá hạn chờ 15 phút nhưng không ai poll/chạy lệnh hủy nên vẫn cho_thanh_toan.
        $donHang->created_at = now()->subMinutes(30);
        $donHang->save();

        $tonKhoSauKhiCho = $sp->fresh()->soluong;
        $this->assertSame('cho_thanh_toan', $donHang->fresh()->trang_thai);

        $cleanMa = str_replace('-', '', $donHang->ma_hoadon);
        $response = $this->guiSepayWebhook([
            'id'             => 334455,
            'transferType'   => 'in',
            'transferAmount' => 250000,
            'content'        => 'THANH TOAN DON ' . $cleanMa,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'message' => 'Xác nhận thanh toán thành công.']);

        $donHang->refresh();
        $this->assertSame('da_thanh_toan', $donHang->trang_thai);
        $this->assertSame('334455', $donHang->ma_giaodich);
        $this->assertSame('334455', $donHang->ma_giao_dich);
        $this->assertNotNull($donHang->thoi_gian_thanh_toan);

        // Điểm thưởng vẫn được cộng dù đơn đã quá hạn chờ.
        $this->assertSame(35, $kh->fresh()->diem_tichluy); // 10 + 25

        // Tồn kho giữ nguyên mức đã trừ lúc tạo đơn chờ: không trừ lần 2, không hoàn kho.
        $this->assertSame($tonKhoSauKhiCho, $sp->fresh()->soluong);

        // Dấu vết trễ hạn để thu ngân biết mà đối soát.
        $this->assertNotNull($donHang->ghi_chu);
        $this->assertStringContainsString('Thanh toán trễ hạn', $donHang->ghi_chu);
        $this->assertStringContainsString('15 phút', $donHang->ghi_chu);

        // Đơn hoàn tất thành công nên KHÔNG nằm trong hàng chờ đối soát.
        $this->assertDatabaseHas('payment_webhooks', [
            'ma_giao_dich_ngoai' => '334455',
            'ket_qua' => 'success',
        ]);
        $this->assertDatabaseCount('payment_webhooks', 1);

        // Telegram phải nhận cả thông báo thành công lẫn cảnh báo trễ hạn.
        Http::assertSent(fn ($request) => str_contains($request['text'] ?? '', 'THANH TOÁN TRỄ HẠN')
            && str_contains($request['text'] ?? '', $donHang->ma_hoadon));
        Http::assertSent(fn ($request) => str_contains($request['text'] ?? '', 'Đã nhận'));
    }
}
