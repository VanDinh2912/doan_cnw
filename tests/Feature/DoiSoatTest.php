<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\PaymentWebhook;
use App\Models\SanPham;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoiSoatTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $cashier;
    protected User $stocker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('role', 'admin')->firstOrFail();
        $this->cashier = User::where('role', 'thungan')->firstOrFail();
        $this->stocker = User::where('role', 'thukho')->firstOrFail();
    }

    private function taoWebhookCanDoiSoat(array $overrides = []): PaymentWebhook
    {
        static $ma = 900000;
        $ma++;

        return PaymentWebhook::create(array_merge([
            'nha_cung_cap' => 'sepay',
            'ma_giao_dich_ngoai' => (string) $ma,
            'invoice_id' => null,
            'so_tien' => 250000,
            'ket_qua' => 'can_doi_soat',
            'ly_do' => 'don_da_huy',
            'ma_hoadon' => 'HD20261110-0001',
            'noi_dung' => 'THANH TOAN DON HD202611100001',
            'payload' => ['id' => $ma, 'transferAmount' => 250000, 'transferType' => 'in', 'content' => 'THANH TOAN DON HD202611100001'],
            'trang_thai_xu_ly' => 'chua_xu_ly',
        ], $overrides));
    }

    // T-DS01: Chỉ admin vào được danh sách đối soát.
    public function test_T_DS01_chi_admin_xem_duoc_danh_sach_doi_soat(): void
    {
        $this->taoWebhookCanDoiSoat();

        $this->actingAs($this->admin)->get(route('doisoat.index'))
            ->assertOk()->assertSee('Đối soát thanh toán');

        $this->actingAs($this->cashier)->get(route('doisoat.index'))->assertForbidden();
        $this->actingAs($this->stocker)->get(route('doisoat.index'))->assertForbidden();
    }

    // T-DS01b: Khách chưa đăng nhập bị đưa về trang đăng nhập.
    public function test_T_DS01b_guest_bi_chuyen_ve_dang_nhap(): void
    {
        $this->get(route('doisoat.index'))->assertRedirect(route('auth.dangnhap'));
    }

    // T-DS02: Lọc theo trạng thái xử lý và khoảng ngày.
    public function test_T_DS02_loc_theo_trang_thai_va_ngay(): void
    {
        $chuaXuLy = $this->taoWebhookCanDoiSoat(['ly_do' => 'thieu_tien']);
        $daXuLy = $this->taoWebhookCanDoiSoat([
            'ly_do' => 'don_da_huy',
            'trang_thai_xu_ly' => 'da_xu_ly',
            'ghi_chu_xu_ly' => 'Đã hoàn tiền mặt cho khách',
        ]);

        // Mặc định chỉ hiện chưa xử lý.
        $this->actingAs($this->admin)->get(route('doisoat.index'))
            ->assertOk()
            ->assertSee($chuaXuLy->ma_giao_dich_ngoai)
            ->assertDontSee($daXuLy->ma_giao_dich_ngoai);

        $this->actingAs($this->admin)->get(route('doisoat.index', ['trang_thai_xu_ly' => 'da_xu_ly']))
            ->assertOk()
            ->assertSee($daXuLy->ma_giao_dich_ngoai)
            ->assertDontSee($chuaXuLy->ma_giao_dich_ngoai);

        $this->actingAs($this->admin)->get(route('doisoat.index', ['trang_thai_xu_ly' => 'tat_ca']))
            ->assertOk()
            ->assertSee($chuaXuLy->ma_giao_dich_ngoai)
            ->assertSee($daXuLy->ma_giao_dich_ngoai);

        // Khoảng ngày không chứa bản ghi nào => danh sách rỗng.
        $this->actingAs($this->admin)->get(route('doisoat.index', [
            'trang_thai_xu_ly' => 'tat_ca',
            'tu_ngay' => now()->subDays(10)->toDateString(),
            'den_ngay' => now()->subDays(9)->toDateString(),
        ]))->assertOk()->assertSee('Không có giao dịch nào cần đối soát.');

        // Khoảng ngày chứa hôm nay => có bản ghi.
        $this->actingAs($this->admin)->get(route('doisoat.index', [
            'trang_thai_xu_ly' => 'tat_ca',
            'tu_ngay' => now()->toDateString(),
            'den_ngay' => now()->toDateString(),
        ]))->assertOk()->assertSee($chuaXuLy->ma_giao_dich_ngoai);
    }

    // T-DS03: Xem chi tiết payload, và webhook thành công không nằm trong màn này.
    public function test_T_DS03_xem_chi_tiet_payload(): void
    {
        $webhook = $this->taoWebhookCanDoiSoat();

        $this->actingAs($this->admin)->get(route('doisoat.show', $webhook))
            ->assertOk()
            ->assertSee($webhook->ma_giao_dich_ngoai)
            ->assertSee('Payload giao dịch')
            ->assertSee('THANH TOAN DON HD202611100001');

        $this->actingAs($this->cashier)->get(route('doisoat.show', $webhook))->assertForbidden();

        $success = PaymentWebhook::create([
            'nha_cung_cap' => 'sepay',
            'ma_giao_dich_ngoai' => '999111',
            'so_tien' => 100000,
            'ket_qua' => 'success',
        ]);
        $this->actingAs($this->admin)->get(route('doisoat.show', $success))->assertNotFound();
    }

    // T-DS04: Ghi chú là bắt buộc khi đánh dấu đã xử lý.
    public function test_T_DS04_danh_dau_xu_ly_bat_buoc_ghi_chu(): void
    {
        $webhook = $this->taoWebhookCanDoiSoat();

        $this->actingAs($this->admin)->post(route('doisoat.xuly', $webhook), [])
            ->assertSessionHasErrors('ghi_chu');

        $this->actingAs($this->admin)->post(route('doisoat.xuly', $webhook), ['ghi_chu' => 'ab'])
            ->assertSessionHasErrors('ghi_chu');

        $this->assertSame('chua_xu_ly', $webhook->fresh()->trang_thai_xu_ly);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    // T-DS05: Đánh dấu đã xử lý cập nhật bản ghi, ghi nhật ký, KHÔNG đụng đơn/kho.
    public function test_T_DS05_danh_dau_xu_ly_ghi_nhat_ky_va_khong_doi_don_kho(): void
    {
        $product = SanPham::firstOrFail();
        $tonKho = (int) $product->soluong;

        $invoice = DonHang::create([
            'ma_hoadon' => 'HD20261110-0001',
            'user_id' => $this->cashier->id,
            'tong_tien' => 250000,
            'tong_thanh_toan' => 250000,
            'tien_khach_dua' => 0,
            'tien_thua' => 0,
            'phuong_thuc' => 'chuyenkhoan',
            'trang_thai' => 'da_huy',
        ]);
        $webhook = $this->taoWebhookCanDoiSoat(['invoice_id' => $invoice->id]);

        $this->actingAs($this->admin)->post(route('doisoat.xuly', $webhook), [
            'ghi_chu' => 'Đã hoàn 250.000đ tiền mặt cho khách lúc 10h, có chữ ký.',
        ])->assertRedirect(route('doisoat.index'));

        $webhook->refresh();
        $this->assertSame('da_xu_ly', $webhook->trang_thai_xu_ly);
        $this->assertSame('Đã hoàn 250.000đ tiền mặt cho khách lúc 10h, có chữ ký.', $webhook->ghi_chu_xu_ly);
        $this->assertSame($this->admin->id, (int) $webhook->nguoi_xu_ly_id);
        $this->assertNotNull($webhook->thoi_gian_xu_ly);

        $this->assertDatabaseHas('activity_logs', [
            'hanh_dong' => 'doi_soat_danh_dau',
            'doi_tuong' => 'payment_webhook',
            'doi_tuong_id' => $webhook->id,
            'user_id' => $this->admin->id,
        ]);

        // Màn hình đối soát không được tự hoàn tiền hay tự đổi đơn.
        $this->assertSame('da_huy', $invoice->fresh()->trang_thai);
        $this->assertSame($tonKho, (int) $product->fresh()->soluong);
        $this->assertDatabaseMissing('stock_movements', ['invoice_id' => $invoice->id]);

        // Đánh dấu lại lần nữa bị từ chối (idempotent, không ghi đè ghi chú cũ).
        $this->actingAs($this->admin)->post(route('doisoat.xuly', $webhook), [
            'ghi_chu' => 'Ghi chú lần hai không được ghi đè',
        ])->assertStatus(422);
        $this->assertSame('Đã hoàn 250.000đ tiền mặt cho khách lúc 10h, có chữ ký.', $webhook->fresh()->ghi_chu_xu_ly);
    }

    // T-DS06: Thu ngân / thủ kho không được đánh dấu xử lý.
    public function test_T_DS06_nhan_vien_khong_duoc_danh_dau_xu_ly(): void
    {
        $webhook = $this->taoWebhookCanDoiSoat();

        $this->actingAs($this->cashier)->post(route('doisoat.xuly', $webhook), ['ghi_chu' => 'Đã xử lý xong'])
            ->assertForbidden();
        $this->actingAs($this->stocker)->post(route('doisoat.xuly', $webhook), ['ghi_chu' => 'Đã xử lý xong'])
            ->assertForbidden();

        $this->assertSame('chua_xu_ly', $webhook->fresh()->trang_thai_xu_ly);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    // T-DS07: Giao dịch thành công không bị đưa vào hàng chờ đối soát.
    public function test_T_DS07_webhook_thanh_cong_khong_nam_trong_hang_cho(): void
    {
        PaymentWebhook::create([
            'nha_cung_cap' => 'sepay',
            'ma_giao_dich_ngoai' => '999222',
            'so_tien' => 100000,
            'ket_qua' => 'success',
        ]);

        $this->actingAs($this->admin)->get(route('doisoat.index', ['trang_thai_xu_ly' => 'tat_ca']))
            ->assertOk()
            ->assertDontSee('999222')
            ->assertSee('Không có giao dịch nào cần đối soát.');
    }
}
