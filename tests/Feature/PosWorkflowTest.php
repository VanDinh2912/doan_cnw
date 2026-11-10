<?php

namespace Tests\Feature;

use App\Models\SanPham;
use App\Models\User;
use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\NhaCungCap;
use App\Models\DonNhap;
use App\Models\ChiTietDonHang;
use App\Models\GioTam;
use App\Models\PhieuKiemKe;
use App\Models\PhieuTra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PosWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_complete_payment_and_stock_is_decremented(): void
    {
        $this->seed();
        $cashier = User::where('role', 'thungan')->firstOrFail();
        $product = SanPham::where('sku', 'CS-0001')->firstOrFail();

        $response = $this->actingAs($cashier)->withSession([
            'gio_hang' => [
                (string) $product->id => [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'ten_sanpham' => $product->ten_sanpham,
                    'gia_ban' => (float) $product->gia_ban,
                    'soluong' => 2,
                    'ton_kho' => $product->soluong,
                ],
            ],
        ])->post(route('donhang.thanhtoan'), [
            'giam_gia_phantram' => 0,
            'tien_khach_dua' => 200000,
            'phuong_thuc' => 'tienmat',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'soluong' => 5]);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('invoice_details', 1);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'loai' => 'ban',
            'soluong' => -2,
        ]);
    }

    public function test_payment_over_stock_does_not_create_invoice(): void
    {
        $this->seed();
        $cashier = User::where('role', 'thungan')->firstOrFail();
        $product = SanPham::where('sku', 'CS-0001')->firstOrFail();

        $response = $this->actingAs($cashier)->withSession([
            'gio_hang' => [(string) $product->id => [
                'id' => $product->id,
                'soluong' => 99,
                'gia_ban' => (float) $product->gia_ban,
            ]],
        ])->post(route('donhang.thanhtoan'), [
            'giam_gia_phantram' => 0,
            'tien_khach_dua' => 99999999,
            'phuong_thuc' => 'tienmat',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'soluong' => 7]);
    }

    public function test_cashier_cannot_access_inventory(): void
    {
        $this->seed();
        $cashier = User::where('role', 'thungan')->firstOrFail();

        $this->actingAs($cashier)->get(route('kho.danhsach'))->assertForbidden();
    }

    public function test_transfer_payment_uses_exact_total_and_no_change(): void
    {
        $this->seed();
        $cashier = User::where('role', 'thungan')->firstOrFail();
        $product = SanPham::where('sku', 'CS-0001')->firstOrFail();

        $response = $this->actingAs($cashier)->withSession([
            'gio_hang' => [(string) $product->id => [
                'id' => $product->id,
                'soluong' => 1,
                'gia_ban' => 1,
            ]],
        ])->post(route('donhang.thanhtoan'), [
            'giam_gia_phantram' => 10,
            'tien_khach_dua' => 1,
            'phuong_thuc' => 'chuyenkhoan',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('invoices', [
            'phuong_thuc' => 'chuyenkhoan',
            'tong_thanh_toan' => 30600,
            'tien_khach_dua' => 30600,
            'tien_thua' => 0,
        ]);
    }

    public function test_inactive_product_cannot_be_sold(): void
    {
        $this->seed();
        $cashier = User::where('role', 'thungan')->firstOrFail();
        $product = SanPham::where('sku', 'CS-0001')->firstOrFail();
        $product->update(['trang_thai' => false]);

        $response = $this->actingAs($cashier)->withSession([
            'gio_hang' => [(string) $product->id => [
                'id' => $product->id,
                'soluong' => 1,
            ]],
        ])->post(route('donhang.thanhtoan'), [
            'giam_gia_phantram' => 0,
            'tien_khach_dua' => 100000,
            'phuong_thuc' => 'tienmat',
        ]);

        $response->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_admin_can_filter_invoices_by_date(): void
    {
        $this->seed();
        $admin = User::where('role', 'admin')->firstOrFail();
        $invoiceCu = DonHang::create([
            'ma_hoadon' => 'HD20261030-0001',
            'user_id' => $admin->id,
            'tong_tien' => 100000,
            'tong_thanh_toan' => 100000,
            'tien_khach_dua' => 100000,
            'tien_thua' => 0,
        ]);
        $invoiceCu->forceFill(['created_at' => '2026-10-30 10:00:00'])->saveQuietly();
        $invoiceMoi = DonHang::create([
            'ma_hoadon' => 'HD20261101-0001',
            'user_id' => $admin->id,
            'tong_tien' => 200000,
            'tong_thanh_toan' => 200000,
            'tien_khach_dua' => 200000,
            'tien_thua' => 0,
        ]);
        $invoiceMoi->forceFill(['created_at' => '2026-11-01 10:00:00'])->saveQuietly();

        $this->actingAs($admin)
            ->get(route('donhang.danhsach', ['tu_ngay' => '2026-11-01', 'den_ngay' => '2026-11-01']))
            ->assertOk()
            ->assertSee('HD20261101-0001')
            ->assertDontSee('HD20261030-0001');
    }

    public function test_admin_can_upload_product_image_to_public_storage(): void
    {
        Storage::fake('public');
        $this->seed();
        $admin = User::where('role', 'admin')->firstOrFail();
        $category = \App\Models\LoaiSanPham::firstOrFail();

        $response = $this->actingAs($admin)->post(route('sanpham.postthem'), [
            'category_id' => $category->id,
            'sku' => 'TEST-IMAGE',
            'barcode' => '899000000001',
            'ten_sanpham' => 'Sản phẩm ảnh',
            'don_vi' => 'Cái',
            'gia_nhap' => 10000,
            'gia_ban' => 15000,
            'soluong' => 2,
            'soluong_toithieu' => 1,
            'trang_thai' => 1,
            'hinh_anh' => UploadedFile::fake()->image('product.png'),
        ]);

        $response->assertRedirect(route('sanpham.danhsach'));
        $product = SanPham::where('sku', 'TEST-IMAGE')->firstOrFail();
        Storage::disk('public')->assertExists($product->hinh_anh);
    }

    public function test_invalid_product_image_is_rejected(): void
    {
        $this->seed();
        $admin = User::where('role', 'admin')->firstOrFail();
        $category = \App\Models\LoaiSanPham::firstOrFail();

        $response = $this->actingAs($admin)->from(route('sanpham.them'))->post(route('sanpham.postthem'), [
            'category_id' => $category->id,
            'sku' => 'TEST-BAD-IMAGE',
            'ten_sanpham' => 'Ảnh sai',
            'don_vi' => 'Cái',
            'gia_nhap' => 10000,
            'gia_ban' => 15000,
            'soluong' => 1,
            'soluong_toithieu' => 1,
            'hinh_anh' => UploadedFile::fake()->create('script.exe', 100, 'application/octet-stream'),
        ]);

        $response->assertRedirect(route('sanpham.them'))->assertSessionHasErrors('hinh_anh');
        $this->assertDatabaseMissing('products', ['sku' => 'TEST-BAD-IMAGE']);
    }

    public function test_product_search_can_filter_by_category_and_exact_barcode(): void
    {
        $this->seed();
        $cashier = User::where('role', 'thungan')->firstOrFail();
        $product = SanPham::where('sku', 'CS-0001')->firstOrFail();
        $product->update(['barcode' => '8931234567890']);

        $this->actingAs($cashier)
            ->get(route('sanpham.timkiem', [
                'q' => '8931234567890',
                'chinhxac' => 1,
                'category_id' => $product->category_id,
            ]))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $product->id);
    }

    public function test_stock_manager_can_print_product_barcode_labels(): void
    {
        $this->seed();
        $stockKeeper = User::where('role', 'thukho')->firstOrFail();
        $product = SanPham::where('sku', 'CS-0001')->firstOrFail();

        $this->actingAs($stockKeeper)
            ->get(route('sanpham.tem', [$product, 'soluong_tem' => 3]))
            ->assertOk()
            ->assertSee($product->sku)
            ->assertSee('<svg', false)
            ->assertSee('grid-template-columns');
    }

    public function test_customer_earns_and_redeems_loyalty_points_during_payment(): void
    {
        $this->seed();
        $cashier = User::where('role', 'thungan')->firstOrFail();
        $product = SanPham::where('sku', 'CS-0001')->firstOrFail();
        $customer = KhachHang::create([
            'ho_ten' => 'Khách tích điểm',
            'so_dienthoai' => '0900000001',
            'diem_tichluy' => 2,
        ]);

        $response = $this->actingAs($cashier)->withSession([
            'gio_hang' => [(string) $product->id => [
                'id' => $product->id,
                'soluong' => 2,
            ]],
        ])->post(route('donhang.thanhtoan'), [
            'customer_id' => $customer->id,
            'diem_su_dung' => 2,
            'giam_gia_phantram' => 0,
            'tien_khach_dua' => 66000,
            'phuong_thuc' => 'tienmat',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('invoices', [
            'customer_id' => $customer->id,
            'diem_su_dung' => 2,
            'tien_giam_diem' => 2000,
            'tong_thanh_toan' => 66000,
            'diem_tichluy_nhan' => 0,
        ]);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'diem_tichluy' => 0]);
    }

    public function test_customer_cannot_redeem_more_points_than_balance(): void
    {
        $this->seed();
        $cashier = User::where('role', 'thungan')->firstOrFail();
        $product = SanPham::where('sku', 'CS-0001')->firstOrFail();
        $customer = KhachHang::create([
            'ho_ten' => 'Khách ít điểm',
            'so_dienthoai' => '0900000002',
            'diem_tichluy' => 1,
        ]);

        $response = $this->actingAs($cashier)->withSession([
            'gio_hang' => [(string) $product->id => ['id' => $product->id, 'soluong' => 1]],
        ])->post(route('donhang.thanhtoan'), [
            'customer_id' => $customer->id,
            'diem_su_dung' => 2,
            'giam_gia_phantram' => 0,
            'tien_khach_dua' => 100000,
            'phuong_thuc' => 'tienmat',
        ]);

        $response->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'diem_tichluy' => 1]);
    }

    public function test_admin_can_export_invoice_and_stock_reports(): void
    {
        $this->seed();
        $admin = User::where('role', 'admin')->firstOrFail();
        $product = SanPham::where('sku', 'CS-0001')->firstOrFail();

        $this->actingAs($admin)->withSession([
            'gio_hang' => [(string) $product->id => ['id' => $product->id, 'soluong' => 1]],
        ])->post(route('donhang.thanhtoan'), [
            'giam_gia_phantram' => 0,
            'tien_khach_dua' => 100000,
            'phuong_thuc' => 'tienmat',
        ])->assertRedirect();

        $this->actingAs($admin)->get(route('baocao.hoadon', ['dinhDang' => 'xlsx']))
            ->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->actingAs($admin)->get(route('baocao.hoadon', ['dinhDang' => 'pdf']))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($admin)->get(route('baocao.xuatnhapton', ['dinhDang' => 'xlsx']))
            ->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertDatabaseHas('invoice_details', ['gia_von' => $product->gia_nhap]);
    }

    public function test_stock_manager_can_create_and_receive_purchase_order_once(): void
    {
        $this->seed();
        $manager = User::where('role', 'thukho')->firstOrFail();
        $supplier = NhaCungCap::create(['ten_nhacungcap' => 'Nhà cung cấp test', 'ma_so_thue' => 'TEST-MST-001']);
        $product = SanPham::where('sku', 'CS-0001')->firstOrFail();
        $stockBefore = $product->soluong;

        $response = $this->actingAs($manager)->post(route('nhaphang.donnhap.postthem'), [
            'supplier_id' => $supplier->id,
            'items' => [[
                'product_id' => $product->id,
                'soluong_dat' => 3,
                'don_gia_nhap' => 42000,
            ]],
        ]);
        $donNhap = DonNhap::firstOrFail();
        $response->assertRedirect(route('nhaphang.donnhap.chitiet', $donNhap));

        $this->actingAs($manager)->post(route('nhaphang.donnhap.trangthai', $donNhap), ['trang_thai' => 'cho_giao']);
        $this->actingAs($manager)->post(route('nhaphang.donnhap.trangthai', $donNhap), ['trang_thai' => 'dang_giao']);
        $this->actingAs($manager)->post(route('nhaphang.donnhap.trangthai', $donNhap), ['trang_thai' => 'da_nhan'])->assertRedirect();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'soluong' => $stockBefore + 3, 'gia_nhap' => 42000]);
        $this->assertDatabaseHas('stock_movements', ['purchase_order_id' => $donNhap->id, 'soluong' => 3]);
        $this->assertDatabaseHas('purchase_orders', ['id' => $donNhap->id, 'trang_thai' => 'da_nhan']);
        $this->assertDatabaseHas('purchase_order_items', ['purchase_order_id' => $donNhap->id, 'soluong_nhan' => 3]);
    }

    public function test_stock_manager_can_create_purchase_invoice_with_pdf_attachment(): void
    {
        Storage::fake('public');
        $this->seed();
        $manager = User::where('role', 'thukho')->firstOrFail();
        $supplier = NhaCungCap::create([
            'ten_nhacungcap' => 'Nhà cung cấp hóa đơn test',
            'ma_so_thue' => 'TEST-MST-003',
        ]);
        $product = SanPham::where('sku', 'CS-0001')->firstOrFail();

        $response = $this->actingAs($manager)->post(route('hoadonnhap.postthem'), [
            'supplier_id' => $supplier->id,
            'so_hoadon' => 'HD-TEST-001',
            'ky_hieu' => 'AA/26E',
            'ngay_hoadon' => now()->toDateString(),
            'thue_suat' => 8,
            'hinh_thuc_thanh_toan' => 'chuyenkhoan',
            'items' => [[
                'product_id' => $product->id,
                'ten_hang' => $product->ten_sanpham,
                'don_vi' => 'Cái',
                'soluong' => 2,
                'don_gia' => 10000,
            ]],
            'file_goc' => UploadedFile::fake()->create('hoa-don-test.pdf', 20, 'application/pdf'),
        ]);

        $hoaDon = \App\Models\HoaDonNhap::where('so_hoadon', 'HD-TEST-001')->firstOrFail();
        $response->assertRedirect(route('hoadonnhap.xem', $hoaDon));
        $this->assertNotNull($hoaDon->file_goc);
        Storage::disk('public')->assertExists($hoaDon->file_goc);
    }

    public function test_cashier_cannot_manage_suppliers_or_purchase_orders(): void
    {
        $this->seed();
        $cashier = User::where('role', 'thungan')->firstOrFail();

        $this->actingAs($cashier)->get(route('nhaphang.nhacungcap'))->assertForbidden();
        $this->actingAs($cashier)->get(route('nhaphang.donnhap'))->assertForbidden();
    }

    public function test_admin_can_view_activity_log_and_supplier_action_is_recorded(): void
    {
        $this->seed();
        $admin = User::where('role', 'admin')->firstOrFail();

        $this->actingAs($admin)->post(route('nhaphang.nhacungcap.postthem'), [
            'ten_nhacungcap' => 'Nhà cung cấp nhật ký',
            'ma_so_thue' => 'TEST-MST-002',
        ])->assertRedirect();

        $this->actingAs($admin)->get(route('nhatky.index'))
            ->assertOk()
            ->assertSee('Thêm nhà cung cấp');
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'hanh_dong' => 'them',
            'doi_tuong' => 'nha_cung_cap',
        ]);
    }

    public function test_non_admin_cannot_view_activity_log(): void
    {
        $this->seed();
        $manager = User::where('role', 'thukho')->firstOrFail();

        $this->actingAs($manager)->get(route('nhatky.index'))->assertForbidden();
    }

    public function test_cashier_can_return_good_and_damaged_items_once(): void
    {
        $this->seed();
        $cashier = User::where('role', 'thungan')->firstOrFail();
        $product = SanPham::where('sku', 'CS-0001')->firstOrFail();
        $invoice = DonHang::create([
            'ma_hoadon' => 'HD-RETURN-001',
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
        $stockBefore = $product->soluong;

        $this->actingAs($cashier)->post(route('trahang.luu'), [
            'invoice_id' => $invoice->id,
            'hinh_thuc_hoan' => 'tienmat',
            'items' => [[
                'invoice_detail_id' => $detail->id,
                'soluong' => 1,
                'tinh_trang' => 'con_tot',
            ]],
        ])->assertRedirect();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'soluong' => $stockBefore + 1]);
        $this->assertDatabaseHas('stock_movements', ['return_id' => 1, 'loai' => 'trahang', 'soluong' => 1]);
        $this->assertDatabaseHas('returns', ['invoice_id' => $invoice->id, 'trang_thai' => 'hoan_thanh']);

        $this->actingAs($cashier)->post(route('trahang.luu'), [
            'invoice_id' => $invoice->id,
            'hinh_thuc_hoan' => 'tienmat',
            'items' => [[
                'invoice_detail_id' => $detail->id,
                'soluong' => 1,
                'tinh_trang' => 'loi_hong',
            ]],
        ])->assertRedirect();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'soluong_hong' => 1]);
    }

    public function test_return_cannot_exceed_sold_quantity(): void
    {
        $this->seed();
        $cashier = User::where('role', 'thungan')->firstOrFail();
        $product = SanPham::where('sku', 'CS-0001')->firstOrFail();
        $invoice = DonHang::create([
            'ma_hoadon' => 'HD-RETURN-002',
            'user_id' => $cashier->id,
            'tong_tien' => 100000,
            'tong_thanh_toan' => 100000,
            'tien_khach_dua' => 100000,
            'tien_thua' => 0,
            'trang_thai' => 'da_thanh_toan',
        ]);
        $detail = ChiTietDonHang::create([
            'invoice_id' => $invoice->id, 'product_id' => $product->id,
            'soluong' => 1, 'don_gia' => 100000, 'gia_von' => 30000, 'thanh_tien' => 100000,
        ]);

        $this->actingAs($cashier)->post(route('trahang.luu'), [
            'invoice_id' => $invoice->id, 'hinh_thuc_hoan' => 'tienmat',
            'items' => [['invoice_detail_id' => $detail->id, 'soluong' => 2, 'tinh_trang' => 'con_tot']],
        ])->assertStatus(422);
        $this->assertDatabaseCount('returns', 0);
    }

    /**
     * Trả hàng SAU hạn POS_HAN_TRA_NGAY phải bị từ chối (TraHangController.php:47).
     */
    public function test_return_rejected_after_return_deadline(): void
    {
        $this->seed();
        $cashier = User::where('role', 'thungan')->firstOrFail();
        $product = SanPham::where('sku', 'CS-0001')->firstOrFail();

        $invoice = DonHang::create([
            'ma_hoadon' => 'HD-RETURN-QUAHAN',
            'user_id' => $cashier->id,
            'tong_tien' => 100000,
            'tong_thanh_toan' => 100000,
            'tien_khach_dua' => 100000,
            'tien_thua' => 0,
            'phuong_thuc' => 'tienmat',
            'trang_thai' => 'da_thanh_toan',
        ]);
        // Lùi ngày tạo quá hạn trả; mọi điều kiện khác đều hợp lệ nên 422 chỉ có thể do hết hạn.
        $invoice->created_at = now()->subDays((int) config('pos.han_tra_ngay', 7) + 1);
        $invoice->save();

        $detail = ChiTietDonHang::create([
            'invoice_id' => $invoice->id, 'product_id' => $product->id,
            'soluong' => 1, 'don_gia' => 100000, 'gia_von' => 30000, 'thanh_tien' => 100000,
        ]);
        $stockBefore = $product->soluong;

        $this->actingAs($cashier)->post(route('trahang.luu'), [
            'invoice_id' => $invoice->id, 'hinh_thuc_hoan' => 'tienmat',
            'items' => [['invoice_detail_id' => $detail->id, 'soluong' => 1, 'tinh_trang' => 'con_tot']],
        ])->assertStatus(422);

        $this->assertDatabaseCount('returns', 0);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'soluong' => $stockBefore]);
        $this->assertDatabaseMissing('stock_movements', ['loai' => 'trahang']);
    }

    public function test_return_of_discounted_invoice_refunds_proportionally_and_caps_point_clawback(): void
    {
        $this->seed();
        $cashier = User::where('role', 'thungan')->firstOrFail();
        $admin = User::where('role', 'admin')->firstOrFail();
        $product = SanPham::where('sku', 'CS-0001')->firstOrFail();

        // Khách chỉ còn 2 điểm trong khi số điểm phải thu hồi là 8 -> kiểm tra chặn âm.
        $customer = KhachHang::create([
            'ho_ten' => 'Khach Giam Gia',
            'so_dienthoai' => '0933100200',
            'diem_tichluy' => 2,
        ]);

        // tong_tien 2.000.000 - giảm 10% (200.000) - 100 điểm (100.000) = thực trả 1.700.000
        $invoice = DonHang::create([
            'ma_hoadon' => 'HD-RETURN-GIAMGIA',
            'user_id' => $cashier->id,
            'customer_id' => $customer->id,
            'tong_tien' => 2000000,
            'giam_gia_phantram' => 10,
            'tien_giam' => 200000,
            'diem_su_dung' => 100,
            'tien_giam_diem' => 100000,
            'tong_thanh_toan' => 1700000,
            'tien_khach_dua' => 1700000,
            'tien_thua' => 0,
            'phuong_thuc' => 'tienmat',
            'trang_thai' => 'da_thanh_toan',
        ]);
        $detail = ChiTietDonHang::create([
            'invoice_id' => $invoice->id, 'product_id' => $product->id,
            'soluong' => 2, 'don_gia' => 1000000, 'gia_von' => 600000, 'thanh_tien' => 2000000,
        ]);
        $stockBefore = $product->soluong;

        // Trả 1/2 sản phẩm: 1 * 1.000.000 * (1.700.000 / 2.000.000) = 850.000
        $this->actingAs($cashier)->post(route('trahang.luu'), [
            'invoice_id' => $invoice->id,
            'hinh_thuc_hoan' => 'tienmat',
            'ly_do' => 'Khach doi y',
            'items' => [[
                'invoice_detail_id' => $detail->id,
                'soluong' => 1,
                'tinh_trang' => 'con_tot',
            ]],
        ])->assertRedirect();

        $phieuTra = PhieuTra::firstOrFail();

        // 850.000 > ngưỡng tự duyệt 500.000 nên phải chờ admin duyệt.
        $this->assertSame('cho_duyet', $phieuTra->trang_thai);
        $this->assertSame(850000, (int) $phieuTra->tong_hoan);
        $this->assertSame(8, (int) $phieuTra->diem_thu_hoi);
        $this->assertSame(50, (int) $phieuTra->diem_hoan);
        $this->assertSame($customer->id, (int) $phieuTra->customer_id);
        $this->assertSame($invoice->id, (int) $phieuTra->invoice_id);

        $itemTra = $phieuTra->chiTiets()->firstOrFail();
        $this->assertSame($detail->id, (int) $itemTra->invoice_detail_id);
        $this->assertSame(1, (int) $itemTra->soluong);
        $this->assertSame(850000, (int) $itemTra->thanh_tien);

        // Chưa duyệt thì kho và điểm phải giữ nguyên.
        $this->assertDatabaseHas('products', ['id' => $product->id, 'soluong' => $stockBefore]);
        $this->assertDatabaseMissing('stock_movements', ['return_id' => $phieuTra->id]);
        $this->assertSame(2, (int) $customer->fresh()->diem_tichluy);

        // Thu ngân không có quyền duyệt phiếu trả.
        $this->actingAs($cashier)->post(route('trahang.duyet', $phieuTra))->assertForbidden();

        $this->actingAs($admin)->post(route('trahang.duyet', $phieuTra))->assertRedirect();

        $this->assertSame('hoan_thanh', $phieuTra->fresh()->trang_thai);
        // Hàng còn tốt quay về tồn kho.
        $this->assertDatabaseHas('products', ['id' => $product->id, 'soluong' => $stockBefore + 1]);
        $this->assertDatabaseHas('stock_movements', [
            'return_id' => $phieuTra->id, 'loai' => 'trahang', 'soluong' => 1,
        ]);
        // Thu hồi 8 điểm nhưng khách chỉ có 2 -> trừ 2 (không âm), rồi hoàn 50 điểm
        // đã dùng theo tỷ lệ phần hàng trả: số dư cuối = 2 - 2 + 50 = 50.
        $this->assertSame(50, (int) $customer->fresh()->diem_tichluy);

        // Việc hoàn điểm phải được ghi vào nhật ký hoạt động.
        $this->assertDatabaseHas('activity_logs', [
            'hanh_dong' => 'hoan_diem_su_dung',
            'doi_tuong' => 'phieu_tra',
            'doi_tuong_id' => $phieuTra->id,
            'mo_ta' => 'Hoàn 50 điểm đã dùng cho phiếu '.$phieuTra->ma_phieu
                .' (thu hồi 2/8 điểm, số dư 2 → 50)',
        ]);
        $this->actingAs($admin)->post(route('trahang.duyet', $phieuTra))->assertStatus(422);
        $this->assertSame(50, (int) $customer->fresh()->diem_tichluy);
        $this->assertSame(1, DB::table('activity_logs')->where('hanh_dong', 'hoan_diem_su_dung')->count());
    }

    public function test_multiple_returns_accumulate_point_refund_without_exceeding_original(): void
    {
        $this->seed();
        $cashier = User::where('role', 'thungan')->firstOrFail();
        $product = SanPham::where('sku', 'CS-0001')->firstOrFail();

        $customer = KhachHang::create([
            'ho_ten' => 'Khach Tra Nhieu Lan',
            'so_dienthoai' => '0933100201',
            'diem_tichluy' => 500,
        ]);

        // 10 SP x 100.000 = 1.000.000; dùng 100 điểm (-100.000) => thực trả 900.000.
        $invoice = DonHang::create([
            'ma_hoadon' => 'HD-RETURN-NHIEULAN',
            'user_id' => $cashier->id,
            'customer_id' => $customer->id,
            'tong_tien' => 1000000,
            'diem_su_dung' => 100,
            'tien_giam_diem' => 100000,
            'tong_thanh_toan' => 900000,
            'tien_khach_dua' => 900000,
            'tien_thua' => 0,
            'phuong_thuc' => 'tienmat',
            'trang_thai' => 'da_thanh_toan',
        ]);
        $detail = ChiTietDonHang::create([
            'invoice_id' => $invoice->id, 'product_id' => $product->id,
            'soluong' => 10, 'don_gia' => 100000, 'gia_von' => 60000, 'thanh_tien' => 1000000,
        ]);

        $tra = function (int $soLuong) use ($cashier, $invoice, $detail) {
            $this->actingAs($cashier)->post(route('trahang.luu'), [
                'invoice_id' => $invoice->id,
                'hinh_thuc_hoan' => 'tienmat',
                'items' => [['invoice_detail_id' => $detail->id, 'soluong' => $soLuong, 'tinh_trang' => 'con_tot']],
            ])->assertRedirect();
        };

        // 3 SP: hoàn 270.000, thu hồi 2, hoàn điểm floor(100 * 0.3) = 30.
        $tra(3);
        $this->assertSame(30, (int) PhieuTra::latest('id')->firstOrFail()->diem_hoan);
        $this->assertSame(528, (int) $customer->fresh()->diem_tichluy); // 500 - 2 + 30

        // 3 SP nữa: hoàn thêm 30 (cộng dồn 60/100).
        $tra(3);
        $this->assertSame(30, (int) PhieuTra::latest('id')->firstOrFail()->diem_hoan);
        $this->assertSame(556, (int) $customer->fresh()->diem_tichluy); // 528 - 2 + 30

        // 4 SP cuối: hết hàng => hoàn đủ 40 còn lại, không bị floor hụt.
        $tra(4);
        $this->assertSame(40, (int) PhieuTra::latest('id')->firstOrFail()->diem_hoan);
        $this->assertSame(593, (int) $customer->fresh()->diem_tichluy); // 556 - 3 + 40

        // Tổng điểm hoàn đúng bằng điểm đã dùng, không vượt.
        $this->assertSame(100, (int) PhieuTra::where('invoice_id', $invoice->id)->sum('diem_hoan'));
        $this->assertSame(900000, (int) PhieuTra::where('invoice_id', $invoice->id)->sum('tong_hoan'));

        // Hết hàng rồi thì không trả thêm được nữa.
        $this->actingAs($cashier)->post(route('trahang.luu'), [
            'invoice_id' => $invoice->id,
            'hinh_thuc_hoan' => 'tienmat',
            'items' => [['invoice_detail_id' => $detail->id, 'soluong' => 1, 'tinh_trang' => 'con_tot']],
        ])->assertStatus(422);
        $this->assertSame(100, (int) PhieuTra::where('invoice_id', $invoice->id)->sum('diem_hoan'));
    }

    public function test_partial_returns_round_points_down_on_cumulative_original_value(): void
    {
        $this->seed();
        $cashier = User::where('role', 'thungan')->firstOrFail();
        $product = SanPham::firstOrFail();
        $customer = KhachHang::create([
            'ho_ten' => 'Khach Tra Le', 'so_dienthoai' => '0933100210', 'diem_tichluy' => 0,
        ]);
        $invoice = DonHang::create([
            'ma_hoadon' => 'HD-RETURN-LE', 'user_id' => $cashier->id, 'customer_id' => $customer->id,
            'tong_tien' => 300000, 'diem_su_dung' => 20, 'tien_giam_diem' => 20000,
            'tong_thanh_toan' => 280000, 'tien_khach_dua' => 280000, 'trang_thai' => 'da_thanh_toan',
        ]);
        $detail = ChiTietDonHang::create([
            'invoice_id' => $invoice->id, 'product_id' => $product->id, 'soluong' => 3,
            'don_gia' => 100000, 'gia_von' => 60000, 'thanh_tien' => 300000,
        ]);
        $tongDiem = 0;
        foreach ([6, 7, 7] as $diemLanNay) {
            $this->actingAs($cashier)->post(route('trahang.luu'), [
                'invoice_id' => $invoice->id, 'hinh_thuc_hoan' => 'tienmat',
                'items' => [['invoice_detail_id' => $detail->id, 'soluong' => 1, 'tinh_trang' => 'con_tot']],
            ])->assertRedirect();
            $phieu = PhieuTra::latest('id')->firstOrFail();
            $this->assertSame($diemLanNay, $phieu->diem_hoan);
            $tongDiem += $diemLanNay;
            $this->assertSame($tongDiem, (int) $customer->fresh()->diem_tichluy);
            $this->assertLessThanOrEqual(20, $tongDiem);
        }
        $this->assertSame(20, (int) PhieuTra::where('invoice_id', $invoice->id)->sum('diem_hoan'));
        $this->assertSame(279999, (int) PhieuTra::where('invoice_id', $invoice->id)->sum('tong_hoan'));
        $this->assertSame(3, DB::table('activity_logs')->where('hanh_dong', 'hoan_diem_su_dung')->count());
    }

    public function test_zero_cash_invoice_refunds_points_by_item_value_not_quantity(): void
    {
        $this->seed();
        $cashier = User::where('role', 'thungan')->firstOrFail();
        $products = SanPham::orderBy('id')->limit(2)->get();
        $customer = KhachHang::create([
            'ho_ten' => 'Khach Tra Don Bang Diem', 'so_dienthoai' => '0933100211', 'diem_tichluy' => 0,
        ]);
        $invoice = DonHang::create([
            'ma_hoadon' => 'HD-RETURN-ZERO', 'user_id' => $cashier->id, 'customer_id' => $customer->id,
            'tong_tien' => 1000000, 'diem_su_dung' => 1000, 'tien_giam_diem' => 1000000,
            'tong_thanh_toan' => 0, 'tien_khach_dua' => 0, 'trang_thai' => 'da_thanh_toan',
        ]);
        foreach ([100000, 900000] as $i => $gia) {
            $detail = ChiTietDonHang::create([
                'invoice_id' => $invoice->id, 'product_id' => $products[$i]->id, 'soluong' => 1,
                'don_gia' => $gia, 'gia_von' => 60000, 'thanh_tien' => $gia,
            ]);
            $this->actingAs($cashier)->post(route('trahang.luu'), [
                'invoice_id' => $invoice->id, 'hinh_thuc_hoan' => 'tienmat',
                'items' => [['invoice_detail_id' => $detail->id, 'soluong' => 1, 'tinh_trang' => 'con_tot']],
            ])->assertRedirect();
            $phieu = PhieuTra::latest('id')->firstOrFail();
            $this->assertSame(0, (int) $phieu->tong_hoan);
            $this->assertSame(0, $phieu->diem_thu_hoi);
            $this->assertSame(intdiv($gia, 1000), $phieu->diem_hoan);
            $this->assertDatabaseHas('stock_movements', ['return_id' => $phieu->id, 'soluong' => 1]);
        }
        $this->assertSame(1000, (int) $customer->fresh()->diem_tichluy);
        $this->assertSame(1000, (int) PhieuTra::where('invoice_id', $invoice->id)->sum('diem_hoan'));
    }

    public function test_pending_returns_reserve_quantity_and_points_until_approved(): void
    {
        $this->seed();
        $cashier = User::where('role', 'thungan')->firstOrFail();
        $admin = User::where('role', 'admin')->firstOrFail();
        $product = SanPham::firstOrFail();
        $customer = KhachHang::create([
            'ho_ten' => 'Khach Tra Cho Duyet', 'so_dienthoai' => '0933100212', 'diem_tichluy' => 0,
        ]);
        $invoice = DonHang::create([
            'ma_hoadon' => 'HD-RETURN-PENDING', 'user_id' => $cashier->id, 'customer_id' => $customer->id,
            'tong_tien' => 2000000, 'diem_su_dung' => 101, 'tien_giam_diem' => 101000,
            'tong_thanh_toan' => 1899000, 'tien_khach_dua' => 1899000, 'trang_thai' => 'da_thanh_toan',
        ]);
        $detail = ChiTietDonHang::create([
            'invoice_id' => $invoice->id, 'product_id' => $product->id, 'soluong' => 2,
            'don_gia' => 1000000, 'gia_von' => 600000, 'thanh_tien' => 2000000,
        ]);
        $payload = [
            'invoice_id' => $invoice->id, 'hinh_thuc_hoan' => 'tienmat',
            'items' => [['invoice_detail_id' => $detail->id, 'soluong' => 1, 'tinh_trang' => 'con_tot']],
        ];
        foreach ([50, 51] as $diem) {
            $this->actingAs($cashier)->post(route('trahang.luu'), $payload)->assertRedirect();
            $phieu = PhieuTra::latest('id')->firstOrFail();
            $this->assertSame('cho_duyet', $phieu->trang_thai);
            $this->assertSame($diem, $phieu->diem_hoan);
        }
        $this->assertSame(0, (int) $customer->fresh()->diem_tichluy);
        $this->assertSame(101, (int) PhieuTra::where('invoice_id', $invoice->id)->sum('diem_hoan'));
        $this->assertDatabaseMissing('stock_movements', ['invoice_id' => $invoice->id]);
        $this->actingAs($cashier)->post(route('trahang.luu'), $payload)->assertStatus(422);
        $this->assertDatabaseCount('returns', 2);
        foreach (PhieuTra::orderByDesc('id')->get() as $phieu) {
            $this->actingAs($admin)->post(route('trahang.duyet', $phieu))->assertRedirect();
            $this->actingAs($admin)->post(route('trahang.duyet', $phieu))->assertStatus(422);
        }
        $this->assertSame(92, (int) $customer->fresh()->diem_tichluy);
        $this->assertSame(2, DB::table('activity_logs')->where('hanh_dong', 'hoan_diem_su_dung')->count());
    }

    public function test_duplicate_return_detail_is_rejected_before_refunding_points(): void
    {
        $this->seed();
        $cashier = User::where('role', 'thungan')->firstOrFail();
        $product = SanPham::firstOrFail();
        $customer = KhachHang::create([
            'ho_ten' => 'Khach Tra Trung Dong', 'so_dienthoai' => '0933100213', 'diem_tichluy' => 0,
        ]);
        $invoice = DonHang::create([
            'ma_hoadon' => 'HD-RETURN-DUPLICATE', 'user_id' => $cashier->id, 'customer_id' => $customer->id,
            'tong_tien' => 100000, 'diem_su_dung' => 10, 'tien_giam_diem' => 10000,
            'tong_thanh_toan' => 90000, 'tien_khach_dua' => 90000, 'trang_thai' => 'da_thanh_toan',
        ]);
        $detail = ChiTietDonHang::create([
            'invoice_id' => $invoice->id, 'product_id' => $product->id, 'soluong' => 1,
            'don_gia' => 100000, 'gia_von' => 60000, 'thanh_tien' => 100000,
        ]);
        $item = ['invoice_detail_id' => $detail->id, 'soluong' => 1, 'tinh_trang' => 'con_tot'];
        $this->actingAs($cashier)->postJson(route('trahang.luu'), [
            'invoice_id' => $invoice->id, 'hinh_thuc_hoan' => 'tienmat', 'items' => [$item, $item],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.invoice_detail_id');
        $this->assertDatabaseCount('returns', 0);
        $this->assertSame(0, (int) $customer->fresh()->diem_tichluy);
    }

    public function test_return_of_damaged_goods_also_refunds_used_points(): void
    {
        $this->seed();
        $cashier = User::where('role', 'thungan')->firstOrFail();
        $product = SanPham::where('sku', 'CS-0001')->firstOrFail();

        $customer = KhachHang::create([
            'ho_ten' => 'Khach Tra Hang Loi',
            'so_dienthoai' => '0933100202',
            'diem_tichluy' => 100,
        ]);

        // 2 SP x 500.000 = 1.000.000; dùng 50 điểm (-50.000) => thực trả 950.000.
        $invoice = DonHang::create([
            'ma_hoadon' => 'HD-RETURN-LOIHONG',
            'user_id' => $cashier->id,
            'customer_id' => $customer->id,
            'tong_tien' => 1000000,
            'diem_su_dung' => 50,
            'tien_giam_diem' => 50000,
            'tong_thanh_toan' => 950000,
            'tien_khach_dua' => 950000,
            'tien_thua' => 0,
            'phuong_thuc' => 'tienmat',
            'trang_thai' => 'da_thanh_toan',
        ]);
        $detail = ChiTietDonHang::create([
            'invoice_id' => $invoice->id, 'product_id' => $product->id,
            'soluong' => 2, 'don_gia' => 500000, 'gia_von' => 300000, 'thanh_tien' => 1000000,
        ]);
        $tonBan = $product->soluong;
        $tonHong = $product->soluong_hong;

        // Trả 1 SP lỗi: 475.000 <= 500.000 nên tự duyệt; thu hồi 4, hoàn floor(50*0.5)=25.
        $this->actingAs($cashier)->post(route('trahang.luu'), [
            'invoice_id' => $invoice->id,
            'hinh_thuc_hoan' => 'tienmat',
            'items' => [['invoice_detail_id' => $detail->id, 'soluong' => 1, 'tinh_trang' => 'loi_hong']],
        ])->assertRedirect();

        $phieuTra = PhieuTra::firstOrFail();
        $this->assertSame('hoan_thanh', $phieuTra->trang_thai);
        $this->assertSame(475000, (int) $phieuTra->tong_hoan);
        $this->assertSame(4, (int) $phieuTra->diem_thu_hoi);
        $this->assertSame(25, (int) $phieuTra->diem_hoan);
        $this->assertSame(121, (int) $customer->fresh()->diem_tichluy); // 100 - 4 + 25

        // Hàng lỗi vào kho hỏng, không cộng tồn bán được.
        $product->refresh();
        $this->assertSame($tonBan, (int) $product->soluong);
        $this->assertSame($tonHong + 1, (int) $product->soluong_hong);
        $this->assertDatabaseHas('stock_movements', [
            'return_id' => $phieuTra->id, 'loai' => 'trahang_hong', 'soluong' => 1,
        ]);
    }

    /**
     * Biên làm tròn: round() từng dòng tiền làm tổng hoàn hụt vài đồng so với
     * tong_thanh_toan, nhưng trả HẾT hàng thì vẫn phải hoàn đủ điểm đã dùng.
     */
    public function test_full_return_refunds_all_used_points_despite_money_rounding(): void
    {
        $this->seed();
        $cashier = User::where('role', 'thungan')->firstOrFail();

        $customer = KhachHang::create([
            'ho_ten' => 'Khach Lam Tron',
            'so_dienthoai' => '0933100203',
            'diem_tichluy' => 100,
        ]);

        // 3 dòng x 100.000 = 300.000; dùng 20 điểm (-20.000) => thực trả 280.000.
        $invoice = DonHang::create([
            'ma_hoadon' => 'HD-RETURN-LAMTRON',
            'user_id' => $cashier->id,
            'customer_id' => $customer->id,
            'tong_tien' => 300000,
            'diem_su_dung' => 20,
            'tien_giam_diem' => 20000,
            'tong_thanh_toan' => 280000,
            'tien_khach_dua' => 280000,
            'tien_thua' => 0,
            'phuong_thuc' => 'tienmat',
            'trang_thai' => 'da_thanh_toan',
        ]);

        $items = [];
        // invoice_details unique theo (invoice_id, product_id) nên cần 3 SP khác nhau.
        $products = SanPham::orderBy('id')->limit(3)->get();
        for ($i = 0; $i < 3; $i++) {
            $detail = ChiTietDonHang::create([
                'invoice_id' => $invoice->id, 'product_id' => $products[$i]->id,
                'soluong' => 1, 'don_gia' => 100000, 'gia_von' => 60000, 'thanh_tien' => 100000,
            ]);
            $items[] = ['invoice_detail_id' => $detail->id, 'soluong' => 1, 'tinh_trang' => 'con_tot'];
        }

        $this->actingAs($cashier)->post(route('trahang.luu'), [
            'invoice_id' => $invoice->id,
            'hinh_thuc_hoan' => 'tienmat',
            'items' => $items,
        ])->assertRedirect();

        $phieuTra = PhieuTra::firstOrFail();
        // Mỗi dòng round(93.333,33) = 93.333 -> tổng 279.999, hụt 1đ so với 280.000.
        $this->assertSame(279999, (int) $phieuTra->tong_hoan);
        // Nếu chỉ floor theo tiền thì ra 19; trả hết hàng phải hoàn đủ 20.
        $this->assertSame(20, (int) $phieuTra->diem_hoan);
        $this->assertSame(118, (int) $customer->fresh()->diem_tichluy); // 100 - 2 + 20
    }

    public function test_cashier_can_hold_and_restore_cart_without_changing_stock(): void
    {
        $this->seed();
        $cashier = User::where('role', 'thungan')->firstOrFail();
        $product = SanPham::where('sku', 'CS-0001')->firstOrFail();
        $cart = [(string) $product->id => [
            'id' => $product->id, 'sku' => $product->sku, 'ten_sanpham' => $product->ten_sanpham,
            'gia_ban' => (float) $product->gia_ban, 'soluong' => 1, 'ton_kho' => $product->soluong,
        ]];
        $this->actingAs($cashier)->withSession(['gio_hang' => $cart])
            ->post(route('hold.giu'), ['ten_goi' => 'Khách áo xanh'])->assertRedirect();
        $this->assertDatabaseHas('held_carts', ['ten_goi' => 'Khách áo xanh']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'soluong' => 7]);
        $gioTam = GioTam::firstOrFail();
        $this->actingAs($cashier)->get(route('hold.khoiphuc', $gioTam))->assertRedirect(route('pos.index'));
        $this->assertSame(1, session('gio_hang')[(string) $product->id]['soluong']);
    }

    public function test_cashier_cannot_restore_another_cashiers_held_cart(): void
    {
        $this->seed();
        $cashier = User::where('role', 'thungan')->firstOrFail();
        $other = User::factory()->create(['role' => 'thungan']);
        $gioTam = GioTam::create(['user_id' => $other->id, 'ten_goi' => 'Giỏ khác', 'du_lieu' => []]);
        $this->actingAs($cashier)->get(route('hold.khoiphuc', $gioTam))->assertForbidden();
    }

    public function test_stock_manager_can_submit_stocktake_and_admin_can_approve_adjustment(): void
    {
        $this->seed();
        $manager = User::where('role', 'thukho')->firstOrFail();
        $admin = User::where('role', 'admin')->firstOrFail();
        $product = SanPham::where('sku', 'CS-0001')->firstOrFail();

        $this->actingAs($manager)->post(route('kiemke.store'), [])->assertRedirect();
        $phieu = PhieuKiemKe::firstOrFail();
        $item = $phieu->chiTiets()->where('product_id', $product->id)->firstOrFail();
        $this->actingAs($manager)->post(route('kiemke.submit', $phieu), [
            'soluong_thuc' => [$item->id => $item->soluong_he_thong + 2],
        ])->assertRedirect();
        $this->assertDatabaseHas('stocktake_items', ['id' => $item->id, 'chenh_lech' => 2]);

        $this->actingAs($admin)->post(route('kiemke.approve', $phieu))->assertRedirect();
        $this->assertDatabaseHas('stocktakes', ['id' => $phieu->id, 'trang_thai' => 'da_duyet']);
        $this->assertDatabaseHas('stock_movements', ['stocktake_id' => $phieu->id, 'loai' => 'dieuchinh', 'soluong' => 2]);
    }
}
