<?php

namespace Tests\Feature;

use App\Exports\HoaDonExport;
use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\SanPham;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * T-DH01 (P0 — docs/THIET_KE_DAT_HANG_ONLINE.md mục 9): migration M1 trên SQLite
 * (cột mới đúng kiểu/nullable/index, user_id nhận null, enum giữ nguyên, down có
 * điều kiện) + fillable DonHang + config online + fallback "Đơn online" ở view
 * in, danh sách hóa đơn và export.
 */
class DatHangP0Test extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_11_12_000001_add_online_order_fields_to_invoices_table.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function donHangMau(array $them = []): DonHang
    {
        return DonHang::create(array_merge([
            'ma_hoadon' => 'P0-'.uniqid(),
            'user_id' => null,
            'tong_tien' => 20000,
            'tong_thanh_toan' => 20000,
            'phuong_thuc' => 'tienmat',
            'trang_thai' => 'da_thanh_toan',
        ], $them));
    }

    public function test_t_dh01_cot_moi_dung_kieu_nullable_va_index(): void
    {
        $cot = collect(Schema::getColumns('invoices'))->keyBy('name');

        foreach (['kenh', 'ma_tra_cuu', 'ten_khach_dat', 'so_dienthoai_dat', 'ghi_chu_khach', 'han_giu'] as $ten) {
            $this->assertTrue($cot->has($ten), "Thiếu cột {$ten}");
        }
        $this->assertFalse($cot['kenh']['nullable'], 'kenh phải NOT NULL');
        $this->assertSame('pos', trim((string) $cot['kenh']['default'], "()'\""), 'kenh mặc định pos');
        foreach (['ma_tra_cuu', 'ten_khach_dat', 'so_dienthoai_dat', 'ghi_chu_khach', 'han_giu', 'user_id'] as $ten) {
            $this->assertTrue($cot[$ten]['nullable'], "Cột {$ten} phải nullable");
        }

        $indexes = collect(Schema::getIndexes('invoices'));
        $this->assertTrue($indexes->contains(fn ($i) => $i['columns'] === ['kenh']), 'Thiếu index kenh');
        $this->assertTrue($indexes->contains(fn ($i) => $i['columns'] === ['so_dienthoai_dat']), 'Thiếu index so_dienthoai_dat');
        $this->assertTrue($indexes->contains(fn ($i) => $i['columns'] === ['ma_tra_cuu'] && $i['unique']), 'Thiếu unique ma_tra_cuu');
    }

    public function test_t_dh01_tao_don_user_id_null_va_kenh_mac_dinh_pos(): void
    {
        $don = $this->donHangMau([
            'kenh' => 'online',
            'ma_tra_cuu' => 'TC-'.uniqid(),
            'ten_khach_dat' => 'Khách vãng lai',
            'so_dienthoai_dat' => '0901234567',
            'ghi_chu_khach' => 'Gọi trước khi nhận hàng',
            'han_giu' => now()->addHours(24),
        ]);

        $this->assertNull($don->user_id);
        $this->assertNull($don->user);
        $this->assertSame('online', $don->kenh);
        $this->assertSame('Khách vãng lai', $don->ten_khach_dat);
        $this->assertSame('0901234567', $don->so_dienthoai_dat);
        $this->assertSame('Gọi trước khi nhận hàng', $don->ghi_chu_khach);
        $this->assertNotNull($don->han_giu);

        $donPos = $this->donHangMau();
        $this->assertSame('pos', $donPos->fresh()->kenh, 'Đơn không truyền kenh phải mặc định pos');
    }

    public function test_t_dh01_ma_tra_cuu_unique_va_cho_phep_nhieu_null(): void
    {
        $this->donHangMau(['ma_tra_cuu' => 'TC-TRUNG']);
        try {
            $this->donHangMau(['ma_tra_cuu' => 'TC-TRUNG']);
            $this->fail('ma_tra_cuu trùng phải bị chặn bởi unique index');
        } catch (QueryException $e) {
            $this->assertStringContainsString('constraint', strtolower($e->getMessage()));
        }

        $this->donHangMau(['ma_tra_cuu' => null]);
        $this->donHangMau(['ma_tra_cuu' => null]);
        $this->assertSame(2, DonHang::whereNull('ma_tra_cuu')->count(), 'unique phải cho phép nhiều NULL (đơn POS)');
    }

    public function test_t_dh01_enum_trang_thai_va_phuong_thuc_giu_nguyen(): void
    {
        $userId = User::where('role', 'thungan')->value('id');

        foreach ([
            ['trang_thai', 'cho_xac_nhan'],
            ['phuong_thuc', 'qr_online'],
        ] as [$cot, $giaTri]) {
            try {
                $this->donHangMau(['user_id' => $userId, $cot => $giaTri]);
                $this->fail("Enum {$cot} phải từ chối giá trị mới '{$giaTri}'");
            } catch (QueryException $e) {
                $this->assertStringContainsString('constraint', strtolower($e->getMessage()), "Enum {$cot} phải từ chối '{$giaTri}'");
            }
        }
    }

    public function test_t_dh01_down_chan_khi_con_don_null_va_sach_khi_khong_con(): void
    {
        $don = $this->donHangMau();
        $migration = require base_path(self::MIGRATION);

        try {
            $migration->down();
            $this->fail('down() phải chặn khi còn hóa đơn user_id NULL');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('user_id', $e->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('invoices', 'kenh'), 'down() bị chặn thì không được drop cột');

        $don->delete();
        $migration->down();
        $this->assertFalse(Schema::hasColumn('invoices', 'kenh'));
        $this->assertFalse(Schema::hasColumn('invoices', 'ma_tra_cuu'));
        $this->assertFalse(Schema::hasColumn('invoices', 'han_giu'));
        $cot = collect(Schema::getColumns('invoices'))->keyBy('name');
        $this->assertFalse($cot['user_id']['nullable'], 'user_id phải về NOT NULL sau down()');

        $migration->up();
        $this->assertTrue(Schema::hasColumn('invoices', 'kenh'), 'up() lại phải sạch sau down()');
    }

    public function test_cau_hinh_online_co_mac_dinh_theo_thiet_ke(): void
    {
        $this->assertSame(30, config('pos.online_qr_phut'));
        $this->assertSame(4, config('pos.online_pickup_gio'));
        $this->assertSame(3, config('pos.online_max_don_mo'));
        $this->assertSame(10, config('pos.online_max_soluong'));
        $this->assertSame(50, config('pos.online_giu_toi_da_phan_tram'));
        $this->assertSame('5,1', config('pos.online_throttle_dat'));
    }

    public function test_view_in_hoa_don_hien_don_online_khi_user_id_null(): void
    {
        $thungan = User::where('role', 'thungan')->firstOrFail();
        $sanPham = SanPham::firstOrFail();

        $donOnline = $this->donHangMau();
        ChiTietDonHang::create([
            'invoice_id' => $donOnline->id,
            'product_id' => $sanPham->id,
            'soluong' => 1,
            'don_gia' => 20000,
            'gia_von' => 10000,
            'thanh_tien' => 20000,
        ]);

        $this->actingAs($thungan)->get(route('donhang.inhoadon', $donOnline))
            ->assertOk()
            ->assertSee('Thu ngân: Đơn online');

        $donPos = $this->donHangMau(['user_id' => $thungan->id]);
        ChiTietDonHang::create([
            'invoice_id' => $donPos->id,
            'product_id' => $sanPham->id,
            'soluong' => 1,
            'don_gia' => 20000,
            'gia_von' => 10000,
            'thanh_tien' => 20000,
        ]);
        $this->actingAs($thungan)->get(route('donhang.inhoadon', $donPos))
            ->assertOk()
            ->assertSee('Thu ngân: Thu ngân');
    }

    public function test_danh_sach_hoa_don_hien_don_online_khi_user_id_null(): void
    {
        $thungan = User::where('role', 'thungan')->firstOrFail();
        $thungan->update(['name' => 'NV P0 Danh Sach']);

        $this->donHangMau();
        $this->donHangMau(['user_id' => $thungan->id]);

        $this->actingAs($thungan)->get(route('donhang.danhsach'))
            ->assertOk()
            ->assertSee('Đơn online')
            ->assertSee('NV P0 Danh Sach');
    }

    public function test_export_hoa_don_hien_don_online_khi_user_id_null(): void
    {
        $thungan = User::where('role', 'thungan')->firstOrFail();

        $donOnline = $this->donHangMau();
        $donPos = $this->donHangMau(['user_id' => $thungan->id]);

        $rows = (new HoaDonExport(null, null))->collection();

        $rowOnline = $rows->firstWhere(0, $donOnline->ma_hoadon);
        $this->assertNotNull($rowOnline);
        $this->assertSame('Đơn online', $rowOnline[2]);

        $rowPos = $rows->firstWhere(0, $donPos->ma_hoadon);
        $this->assertNotNull($rowPos);
        $this->assertSame($thungan->name, $rowPos[2]);
    }
}