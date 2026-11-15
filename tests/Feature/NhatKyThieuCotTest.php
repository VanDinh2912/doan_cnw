<?php

namespace Tests\Feature;

use App\Models\SanPham;
use App\Models\User;
use App\Support\KiemTraSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Bước 1B-b — DB thiếu các cột MỚI của activity_logs (migration kiểm toán
 * Stage 2 chưa áp). Guard trong GhiNhatKy + NhatKyService phải bỏ qua cột
 * thiếu (ghi mức tối thiểu) và tuyệt đối không làm hỏng giao dịch nghiệp vụ:
 * bán hàng POS, đăng nhập, scheduler hủy đơn quá hạn vẫn chạy.
 * TODO(gỡ guard): xóa test này sau khi migration nhật ký áp lên DB thật.
 */
class NhatKyThieuCotTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;
    private SanPham $sanPham;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->cashier = User::where('email', 'thungan@example.com')->firstOrFail();
        $this->sanPham = SanPham::where('sku', 'CS-0001')->firstOrFail();
        $this->sanPham->update(['soluong' => 10, 'trang_thai' => true]);
    }

    /**
     * Bỏ các cột mở rộng của activity_logs (migration 2026_11_15_000001).
     * SQLite không cho DROP COLUMN cột đang có index → gỡ index trước.
     */
    private function goBoCotNhatKy(): void
    {
        $indexes = DB::select(
            "SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='activity_logs' AND name NOT LIKE 'sqlite_%'"
        );
        foreach ($indexes as $idx) {
            DB::statement('DROP INDEX IF EXISTS "'.$idx->name.'"');
        }

        foreach (['doi_tuong_loai', 'truoc', 'sau', 'user_agent', 'nguon', 'nhom_id'] as $cot) {
            DB::statement('ALTER TABLE activity_logs DROP COLUMN '.$cot);
        }
        KiemTraSchema::datLai();

        $this->assertFalse(Schema::hasColumn('activity_logs', 'truoc'));
        $this->assertFalse(Schema::hasColumn('activity_logs', 'nguon'));
    }

    private function sessionGioHang(): array
    {
        return [
            'gio_hang' => [
                (string) $this->sanPham->id => [
                    'id' => $this->sanPham->id,
                    'sku' => $this->sanPham->sku,
                    'ten_sanpham' => $this->sanPham->ten_sanpham,
                    'gia_ban' => (float) $this->sanPham->gia_ban,
                    'soluong' => 1,
                    'ton_kho' => $this->sanPham->soluong,
                ],
            ],
        ];
    }

    public function test_pos_tien_mat_va_dang_nhap_van_chay_khi_thieu_cot_nhat_ky(): void
    {
        $this->goBoCotNhatKy();

        // Đăng nhập: NhatKyService::ghi phải bỏ qua cột thiếu, không lỗi.
        $this->post('/dangnhap', [
            'email' => 'thungan@example.com',
            'password' => '123456',
        ])->assertRedirect();
        $this->assertAuthenticated();

        // POS tiền mặt vẫn tạo hóa đơn + trừ kho.
        $res = $this->actingAs($this->cashier)
            ->withSession($this->sessionGioHang())
            ->post(route('donhang.thanhtoan'), [
                'giam_gia_phantram' => 0,
                'tien_khach_dua' => 10000000,
                'phuong_thuc' => 'tienmat',
            ]);

        $res->assertRedirect();
        $this->assertSame(9, (int) $this->sanPham->fresh()->soluong);
        $this->assertDatabaseCount('invoices', 1);

        // Nhật ký vẫn ghi mức tối thiểu (cột lõi còn tồn tại).
        $this->assertDatabaseHas('activity_logs', [
            'hanh_dong' => 'tao',
            'doi_tuong' => 'invoices',
        ]);
    }

    public function test_scheduler_huy_qua_han_van_chay_khi_thieu_cot_nhat_ky(): void
    {
        $this->goBoCotNhatKy();

        $this->artisan('donhang:huy-qua-han')->assertExitCode(0);
    }

    public function test_mat_han_bang_nhat_ky_van_khong_hong_giao_dich(): void
    {
        Schema::drop('activity_logs');
        KiemTraSchema::datLai();

        $this->assertFalse(KiemTraSchema::coBang('activity_logs'));

        // Cả model event lẫn NhatKyService đều phải bỏ qua êm.
        $res = $this->actingAs($this->cashier)
            ->withSession($this->sessionGioHang())
            ->post(route('donhang.thanhtoan'), [
                'giam_gia_phantram' => 0,
                'tien_khach_dua' => 10000000,
                'phuong_thuc' => 'tienmat',
            ]);

        $res->assertRedirect();
        $this->assertSame(9, (int) $this->sanPham->fresh()->soluong);
        $this->assertDatabaseCount('invoices', 1);
    }
}
