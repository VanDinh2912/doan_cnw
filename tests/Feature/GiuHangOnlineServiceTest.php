<?php

namespace Tests\Feature;

use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\SanPham;
use App\Models\User;
use App\Services\GiuHangOnlineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * PA1 (P0 mở rộng — chỉ đạo người dùng 2026-11-13): quy tắc trần giữ hàng pickup.
 * Service chỉ đọc + tính, không ghi dữ liệu. Đơn pickup = kenh online +
 * phuong_thuc tienmat (trả tại quầy) + cho_thanh_toan; không đếm QR (chuyenkhoan),
 * POS (kenh pos), da_thanh_toan, da_huy. Không có test đồng thời thật — race giữa
 * hai khách đặt cùng lúc để P1 xử lý bằng DB::transaction + lockForUpdate.
 */
class GiuHangOnlineServiceTest extends TestCase
{
    use RefreshDatabase;

    private GiuHangOnlineService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->service = app(GiuHangOnlineService::class);
    }

    private function sanPhamTon(int $ton, int $chiSo = 0): SanPham
    {
        $sp = SanPham::orderBy('id')->skip($chiSo)->firstOrFail();
        $sp->update(['soluong' => $ton]);

        return $sp->fresh();
    }

    private function taoDon(string $kenh, string $phuongThuc, string $trangThai, SanPham $sp, int $soluong, ?int $userId = null): DonHang
    {
        $don = DonHang::create([
            'ma_hoadon' => 'GH-'.uniqid(),
            'user_id' => $userId,
            'tong_tien' => 1000 * $soluong,
            'tong_thanh_toan' => 1000 * $soluong,
            'phuong_thuc' => $phuongThuc,
            'trang_thai' => $trangThai,
            'kenh' => $kenh,
        ]);
        ChiTietDonHang::create([
            'invoice_id' => $don->id,
            'product_id' => $sp->id,
            'soluong' => $soluong,
            'don_gia' => 1000,
            'gia_von' => 500,
            'thanh_tien' => 1000 * $soluong,
        ]);

        return $don;
    }

    public function test_tran_mac_dinh_50_phan_tram_ton_goc(): void
    {
        $sp = $this->sanPhamTon(10);

        $this->assertSame(0, $this->service->dangGiuPickup($sp->id));
        $this->assertSame(10, $this->service->tonGoc($sp));
        $this->assertSame(5, $this->service->tran($sp));
        $this->assertTrue($this->service->kiemTra($sp->id, 5));
    }

    public function test_tran_khong_co_lai_khi_da_giu(): void
    {
        $sp = $this->sanPhamTon(10);
        $this->taoDon('online', 'tienmat', 'cho_thanh_toan', $sp, 3);
        $sp->update(['soluong' => 7]); // mô phỏng trừ kho khi tạo đơn chờ (R1)
        $sp = $sp->fresh();

        $this->assertSame(3, $this->service->dangGiuPickup($sp->id));
        $this->assertSame(10, $this->service->tonGoc($sp), 'tồn gốc = tồn hiện tại + đang giữ');
        $this->assertSame(5, $this->service->tran($sp), 'trần không co lại khi đã giữ');
        $this->assertTrue($this->service->kiemTra($sp->id, 2)); // 3 + 2 = 5 <= 5
    }

    public function test_vuot_tran_tu_choi_va_bao_so_con_lai(): void
    {
        $sp = $this->sanPhamTon(10);
        $this->taoDon('online', 'tienmat', 'cho_thanh_toan', $sp, 3);
        $sp->update(['soluong' => 7]);
        $sp = $sp->fresh();

        try {
            $this->service->kiemTra($sp->id, 3); // 3 + 3 = 6 > 5
            $this->fail('Vượt trần phải bị từ chối');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('2', $e->getMessage(), 'phải nêu số còn có thể giữ (5 - 3 = 2)');
            $this->assertStringContainsString('giữ', $e->getMessage());
        }
    }

    public function test_ton_1_cho_giu_1(): void
    {
        $sp = $this->sanPhamTon(1);

        $this->assertSame(1, $this->service->tran($sp), 'max(1, floor(50% x 1)) = 1');
        $this->assertTrue($this->service->kiemTra($sp->id, 1));

        try {
            $this->service->kiemTra($sp->id, 2);
            $this->fail('tồn 1 trần 1 phải từ chối đặt 2');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('giữ', $e->getMessage());
        }
    }

    public function test_khong_dem_qr_pos_da_thanh_toan_da_huy(): void
    {
        $sp = $this->sanPhamTon(10);
        $thungan = User::where('role', 'thungan')->firstOrFail();

        $this->taoDon('online', 'chuyenkhoan', 'cho_thanh_toan', $sp, 4); // QR online
        $this->taoDon('pos', 'tienmat', 'cho_thanh_toan', $sp, 4, $thungan->id); // POS thu ngân
        $this->taoDon('online', 'tienmat', 'da_thanh_toan', $sp, 4); // đã thanh toán
        $this->taoDon('online', 'tienmat', 'da_huy', $sp, 4); // đã hủy
        $this->taoDon('online', 'tienmat', 'cho_thanh_toan', $sp, 2); // pickup thật

        $this->assertSame(2, $this->service->dangGiuPickup($sp->id), 'chỉ đếm pickup chưa thanh toán');
    }

    public function test_nhieu_san_pham_doc_lap(): void
    {
        $a = $this->sanPhamTon(10, 0);
        $b = $this->sanPhamTon(10, 1);
        $this->taoDon('online', 'tienmat', 'cho_thanh_toan', $a, 3);

        $this->assertSame(3, $this->service->dangGiuPickup($a->id));
        $this->assertSame(0, $this->service->dangGiuPickup($b->id));
        $this->assertSame(5, $this->service->tran($b->fresh()));
        $this->assertTrue($this->service->kiemTra($b->id, 5));
    }

    public function test_doi_config_phan_tram_thay_doi_tran(): void
    {
        $sp = $this->sanPhamTon(10);

        config(['pos.online_giu_toi_da_phan_tram' => 80]);
        $this->assertSame(8, $this->service->tran($sp->fresh()));
        $this->assertTrue($this->service->kiemTra($sp->id, 8));

        config(['pos.online_giu_toi_da_phan_tram' => 20]);
        $this->assertSame(2, $this->service->tran($sp->fresh()));
    }

    public function test_so_luong_0_hoac_am_bi_tu_choi(): void
    {
        $sp = $this->sanPhamTon(10);

        foreach ([0, -1] as $sl) {
            try {
                $this->service->kiemTra($sp->id, $sl);
                $this->fail("Số lượng {$sl} phải bị từ chối");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('lớn hơn 0', $e->getMessage());
            }
        }
    }
}