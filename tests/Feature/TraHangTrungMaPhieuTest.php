<?php

namespace Tests\Feature;

use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\PhieuTra;
use App\Models\SanPham;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\KhoaBoSinhSo;
use Tests\TestCase;

require_once __DIR__.'/../Support/bo-sinh-so-ngau-nhien.php';

class TraHangTrungMaPhieuTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        KhoaBoSinhSo::khoa(null);
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_hai_phieu_tra_cung_giay_rng_bi_khoa_trung_van_thanh_cong(): void
    {
        Carbon::setTestNow('2026-11-12 10:00:00');
        KhoaBoSinhSo::khoa(42);
        $this->seed();
        $cashier = User::where('role', 'thungan')->firstOrFail();
        $product = SanPham::where('sku', 'CS-0001')->firstOrFail();
        $invoice = DonHang::create([
            'ma_hoadon' => 'HD-TRUNGMA-001',
            'user_id' => $cashier->id,
            'tong_tien' => 100000,
            'tong_thanh_toan' => 100000,
            'tien_khach_dua' => 100000,
            'tien_thua' => 0,
            'trang_thai' => 'da_thanh_toan',
        ]);
        $detail = ChiTietDonHang::create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'soluong' => 2,
            'don_gia' => 50000,
            'gia_von' => 30000,
            'thanh_tien' => 100000,
        ]);
        $payload = [
            'invoice_id' => $invoice->id,
            'hinh_thuc_hoan' => 'tienmat',
            'items' => [[
                'invoice_detail_id' => $detail->id,
                'soluong' => 1,
                'tinh_trang' => 'con_tot',
            ]],
        ];
        $tonTruoc = (int) $product->soluong;

        $this->actingAs($cashier)->post(route('trahang.luu'), $payload)->assertRedirect();
        $this->actingAs($cashier)->post(route('trahang.luu'), $payload)->assertRedirect();

        $maPhieu = PhieuTra::orderBy('id')->pluck('ma_phieu');
        $this->assertCount(2, $maPhieu);
        // Hai phiếu tạo cùng giây phải ra hai mã khác nhau — đây là hành vi cần
        // bảo vệ. Không assert hậu tố cụ thể (=42) vì khóa RNG là static toàn
        // process, dưới paratest có thể không còn hiệu lực nếu file test khác
        // trong cùng worker can thiệp; hậu tố thực tế do bộ sinh quyết định.
        $this->assertMatchesRegularExpression('/^TH20261112100000\d{2}$/', $maPhieu[0]);
        $this->assertNotSame($maPhieu[0], $maPhieu[1]);
        foreach ($maPhieu as $ma) {
            $this->assertMatchesRegularExpression('/^TH\d{16}$/', $ma);
        }
        $this->assertDatabaseHas('returns', ['ma_phieu' => $maPhieu[1], 'trang_thai' => 'hoan_thanh']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'soluong' => $tonTruoc + 2]);
        $this->assertDatabaseHas('stock_movements', ['return_id' => 1, 'loai' => 'trahang', 'soluong' => 1]);
        $this->assertDatabaseHas('stock_movements', ['return_id' => 2, 'loai' => 'trahang', 'soluong' => 1]);
    }

    public function test_het_90_hau_to_trong_giay_thi_bao_loi_va_khong_tao_phieu(): void
    {
        Carbon::setTestNow('2026-11-12 10:00:00');
        KhoaBoSinhSo::khoa(42);
        $this->seed();
        $cashier = User::where('role', 'thungan')->firstOrFail();
        $product = SanPham::where('sku', 'CS-0001')->firstOrFail();
        $invoice = DonHang::create([
            'ma_hoadon' => 'HD-TRUNGMA-002',
            'user_id' => $cashier->id,
            'tong_tien' => 100000,
            'tong_thanh_toan' => 100000,
            'tien_khach_dua' => 100000,
            'tien_thua' => 0,
            'trang_thai' => 'da_thanh_toan',
        ]);
        $detail = ChiTietDonHang::create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'soluong' => 2,
            'don_gia' => 50000,
            'gia_von' => 30000,
            'thanh_tien' => 100000,
        ]);
        foreach (range(10, 99) as $hauTo) {
            PhieuTra::create([
                'ma_phieu' => 'TH20261112100000'.$hauTo,
                'invoice_id' => $invoice->id,
                'user_id' => $cashier->id,
                'tong_hoan' => 0,
                'hinh_thuc_hoan' => 'tienmat',
                'trang_thai' => 'hoan_thanh',
            ]);
        }

        $this->actingAs($cashier)->post(route('trahang.luu'), [
            'invoice_id' => $invoice->id,
            'hinh_thuc_hoan' => 'tienmat',
            'items' => [[
                'invoice_detail_id' => $detail->id,
                'soluong' => 1,
                'tinh_trang' => 'con_tot',
            ]],
        ])->assertStatus(500);
        $this->assertDatabaseCount('returns', 90);
    }
}