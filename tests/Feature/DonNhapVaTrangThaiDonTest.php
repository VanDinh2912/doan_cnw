<?php

namespace Tests\Feature;

use App\Models\BienDongKho;
use App\Models\ChiTietDonHang;
use App\Models\ChiTietDonNhap;
use App\Models\DonHang;
use App\Models\DonNhap;
use App\Models\NhaCungCap;
use App\Models\SanPham;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Hồi quy 2 lỗi phát hiện khi kiểm thử HTTP trên doan_cnw_dev:
 *  1. Trang chi tiết đơn nhập ở trạng thái cho_duyet không có nút chuyển
 *     trạng thái (blade lặp sai key so với map backend).
 *  2. Trang trạng thái đơn online lazy-load sanPham → local.ERROR
 *     (preventLazyLoading bật ngoài production).
 */
class DonNhapVaTrangThaiDonTest extends TestCase
{
    use RefreshDatabase;

    public function test_don_nhap_cho_duyet_hien_form_chuyen_trang_thai(): void
    {
        $this->seed();
        $manager = User::where('role', 'thukho')->firstOrFail();
        $supplier = NhaCungCap::create(['ten_nhacungcap' => 'NCC UI test', 'ma_so_thue' => 'UI-MST-01']);
        $product = SanPham::orderBy('id')->firstOrFail();

        $donNhap = DonNhap::create([
            'ma_don' => 'DN-UI-001', 'supplier_id' => $supplier->id, 'user_id' => $manager->id,
            'trang_thai' => 'cho_duyet', 'tong_tien' => 100000,
        ]);
        ChiTietDonNhap::create([
            'purchase_order_id' => $donNhap->id, 'product_id' => $product->id,
            'soluong_dat' => 1, 'soluong_nhan' => 0, 'don_gia_nhap' => 100000, 'thanh_tien' => 100000,
        ]);

        $res = $this->actingAs($manager)->get(route('nhaphang.donnhap.chitiet', $donNhap));

        $res->assertOk();
        // Đơn chờ duyệt phải có nút chuyển sang chờ giao và nút hủy
        $res->assertSee('name="trang_thai" value="cho_giao"', false);
        $res->assertSee('name="trang_thai" value="da_huy"', false);
        // Không được hiển thị chuyển thẳng sang dang_giao/da_nhan (backend cấm)
        $this->assertStringNotContainsString('value="dang_giao"', $res->getContent());
        $this->assertStringNotContainsString('value="da_nhan"', $res->getContent());
    }

    public function test_trang_thai_don_khong_lazy_load_san_pham(): void
    {
        $this->seed();
        $sp = SanPham::orderBy('id')->firstOrFail();
        $sp->update(['soluong' => 10, 'trang_thai' => true]);

        $don = DonHang::create([
            'ma_hoadon' => 'HD-'.uniqid(), 'user_id' => null, 'customer_id' => null,
            'kenh' => 'online', 'phuong_thuc' => 'tienmat', 'trang_thai' => 'cho_thanh_toan',
            'ma_tra_cuu' => 'MAUI000001', 'ten_khach_dat' => 'Khách UI',
            'so_dienthoai_dat' => '0901234567',
            'tong_tien' => $sp->gia_ban * 2, 'tong_thanh_toan' => $sp->gia_ban * 2,
            'giam_gia_phantram' => 0, 'tien_giam' => 0, 'diem_su_dung' => 0,
            'tien_giam_diem' => 0, 'diem_tichluy_nhan' => 0, 'tien_khach_dua' => 0,
            'tien_thua' => 0, 'han_giu' => now()->addHours(4),
        ]);
        // 2 dòng chi tiết (2 sản phẩm khác nhau) để bắt lỗi lazy-load lặp
        $sp2 = SanPham::where('id', '!=', $sp->id)->orderBy('id')->firstOrFail();
        foreach ([$sp, $sp2] as $item) {
            ChiTietDonHang::create([
                'invoice_id' => $don->id, 'product_id' => $item->id, 'soluong' => 1,
                'don_gia' => $item->gia_ban, 'gia_von' => $item->gia_nhap, 'thanh_tien' => $item->gia_ban,
            ]);
        }
        $sp->decrement('soluong', 2);
        BienDongKho::create([
            'product_id' => $sp->id, 'user_id' => null, 'invoice_id' => $don->id,
            'loai' => 'ban', 'soluong' => -2, 'ghi_chu' => 'Bán hàng '.$don->ma_hoadon,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        // preventLazyLoading đang bật (non-production): nếu thiếu eager load,
        // request sẽ ném LazyLoadingViolationException → test fail.
        $res = $this->withSession(['don_hang_xac_thuc' => ['MAUI000001' => true]])
            ->get(route('don-hang.trangthai', 'MAUI000001'));

        $res->assertOk();
        $res->assertSee($sp->ten_sanpham);

        // Số query không tăng theo số dòng chi tiết: eager load gom 1 query
        // cho chi tiết + 1 cho sản phẩm, không phải N query lazy.
        $queries = collect(DB::getQueryLog())->pluck('query')->filter(
            fn (string $q) => str_contains($q, 'san_pham') || str_contains($q, 'products')
        );
        $this->assertLessThanOrEqual(2, $queries->count(), 'Sản phẩm phải được eager load, không lazy từng dòng');
    }
}
