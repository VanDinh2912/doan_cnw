<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\LoaiSanPham;
use App\Models\SanPham;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HangSanXuatTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private ?LoaiSanPham $loaiCache = null;

    private function loai(): LoaiSanPham
    {
        return $this->loaiCache ??= LoaiSanPham::create(['ten_loai' => 'Củ sạc', 'ma_loai' => 'CS']);
    }

    private function sanPham(array $override = []): SanPham
    {
        return SanPham::create(array_merge([
            'category_id' => $this->loai()->id,
            'sku' => 'SKU-'.fake()->unique()->numberBetween(1000, 999999),
            'ten_sanpham' => 'Củ sạc nhanh '.fake()->word(),
            'don_vi' => 'Cái',
            'gia_nhap' => 50000,
            'gia_ban' => 90000,
            'soluong' => 10,
            'soluong_toithieu' => 5,
            'trang_thai' => true,
        ], $override));
    }

    // ---------- CRUD + phân quyền ----------

    public function test_admin_them_sua_xoa_hang(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('hangsxuat.postthem'), [
                'ten' => 'Hoco',
                'kich_hoat' => '1',
                'logo' => UploadedFile::fake()->image('logo.png', 100, 100),
            ])
            ->assertRedirect(route('hangsxuat.danhsach'));

        $hang = Brand::firstOrFail();
        $this->assertSame('hoco', $hang->slug);
        $this->assertTrue($hang->kich_hoat);
        $this->assertNotNull($hang->logo);
        Storage::disk('public')->assertExists($hang->logo);

        $this->actingAs($admin)
            ->put(route('hangsxuat.postsua', $hang), [
                'ten' => 'HOCO Việt Nam',
                'slug' => '',
                'kich_hoat' => '1',
            ])
            ->assertRedirect(route('hangsxuat.danhsach'));
        // Slug giữ nguyên khi không nhập slug mới.
        $this->assertSame('HOCO Việt Nam', $hang->fresh()->ten);
        $this->assertSame('hoco', $hang->fresh()->slug);

        $this->actingAs($admin)
            ->delete(route('hangsxuat.xoa', $hang))
            ->assertRedirect(route('hangsxuat.danhsach'));
        $this->assertDatabaseMissing('brands', ['id' => $hang->id]);
    }

    public function test_thungan_va_thukho_chi_xem_khong_ghi_duoc(): void
    {
        $hang = Brand::create(['ten' => 'Remax', 'slug' => 'remax', 'kich_hoat' => true]);

        foreach (['thungan', 'thukho'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('hangsxuat.danhsach'))->assertOk();
            $this->actingAs($user)->get(route('hangsxuat.them'))->assertForbidden();
            $this->actingAs($user)->post(route('hangsxuat.postthem'), ['ten' => 'X'])->assertForbidden();
            $this->actingAs($user)->get(route('hangsxuat.sua', $hang))->assertForbidden();
            $this->actingAs($user)->put(route('hangsxuat.postsua', $hang), ['ten' => 'Y'])->assertForbidden();
            $this->actingAs($user)->delete(route('hangsxuat.xoa', $hang))->assertForbidden();
        }

        $this->assertDatabaseHas('brands', ['ten' => 'Remax']);
    }

    public function test_xoa_hang_dang_co_san_pham_bi_chan(): void
    {
        $hang = Brand::create(['ten' => 'Borofone', 'slug' => 'borofone', 'kich_hoat' => true]);
        $this->sanPham(['brand_id' => $hang->id]);

        $this->actingAs($this->admin())
            ->delete(route('hangsxuat.xoa', $hang))
            ->assertRedirect();

        $this->assertDatabaseHas('brands', ['id' => $hang->id]);
        $this->assertDatabaseHas('products', ['brand_id' => $hang->id]);
    }

    public function test_logo_tu_choi_svg_va_file_gia_mjpg(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        // SVG thật → từ chối.
        $svg = new UploadedFile(
            $this->taoTepTam('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            'logo.svg',
            'image/svg+xml',
            null,
            true
        );
        $this->actingAs($admin)
            ->post(route('hangsxuat.postthem'), ['ten' => 'Hang A', 'logo' => $svg])
            ->assertSessionHasErrors('logo');
        $this->assertDatabaseMissing('brands', ['ten' => 'Hang A']);

        // File SVG đổi đuôi .jpg → MIME thật vẫn bị chặn.
        $gia = new UploadedFile(
            $this->taoTepTam('<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
            'logo.jpg',
            'image/jpeg',
            null,
            true
        );
        $this->actingAs($admin)
            ->post(route('hangsxuat.postthem'), ['ten' => 'Hang B', 'logo' => $gia])
            ->assertSessionHasErrors('logo');
        $this->assertDatabaseMissing('brands', ['ten' => 'Hang B']);
    }

    private function taoTepTam(string $noiDung): string
    {
        $path = tempnam(sys_get_temp_dir(), 'logo_');
        file_put_contents($path, $noiDung);

        return $path;
    }

    public function test_ten_hang_trung_bi_chan_va_slug_la_bi_chan(): void
    {
        Brand::create(['ten' => 'Hoco', 'slug' => 'hoco', 'kich_hoat' => true]);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('hangsxuat.postthem'), ['ten' => 'Hoco'])
            ->assertSessionHasErrors('ten');

        $this->actingAs($admin)
            ->post(route('hangsxuat.postthem'), ['ten' => 'Hang C', 'slug' => 'HANG!XAU'])
            ->assertSessionHasErrors('slug');
    }

    // ---------- Form sản phẩm ----------

    public function test_form_san_pham_luu_brand_id_va_id_la_bi_chan(): void
    {
        $hang = Brand::create(['ten' => 'Remax', 'slug' => 'remax', 'kich_hoat' => true]);
        $admin = $this->admin();
        $loai = $this->loai();

        $this->actingAs($admin)->post(route('sanpham.postthem'), [
            'category_id' => $loai->id,
            'brand_id' => $hang->id,
            'sku' => 'SKU-BR-1',
            'ten_sanpham' => 'Tai nghe Remax',
            'don_vi' => 'Cái',
            'gia_nhap' => 100000,
            'gia_ban' => 200000,
            'soluong' => 5,
            'soluong_toithieu' => 2,
            'trang_thai' => '1',
        ])->assertRedirect(route('sanpham.danhsach'));

        $this->assertDatabaseHas('products', ['sku' => 'SKU-BR-1', 'brand_id' => $hang->id]);

        // brand_id không tồn tại → 422 validation, không 500.
        $this->actingAs($admin)->post(route('sanpham.postthem'), [
            'category_id' => $loai->id,
            'brand_id' => 99999,
            'sku' => 'SKU-BR-2',
            'ten_sanpham' => 'SP test',
            'don_vi' => 'Cái',
            'gia_nhap' => 1,
            'gia_ban' => 1,
            'soluong' => 1,
            'soluong_toithieu' => 1,
        ])->assertSessionHasErrors('brand_id');
        $this->assertDatabaseMissing('products', ['sku' => 'SKU-BR-2']);
    }

    public function test_dropdown_sua_sp_giu_hang_da_tat(): void
    {
        $hangTat = Brand::create(['ten' => 'Hang Cu', 'slug' => 'hang-cu', 'kich_hoat' => false]);
        $sp = $this->sanPham(['brand_id' => $hangTat->id]);

        $response = $this->actingAs($this->admin())->get(route('sanpham.sua', $sp));
        $response->assertOk()->assertSee('Hang Cu');
    }

    // ---------- Storefront ----------

    public function test_storefront_loc_theo_hang_va_bo_qua_gia_tri_la(): void
    {
        $hangA = Brand::create(['ten' => 'Hoco', 'slug' => 'hoco', 'kich_hoat' => true]);
        $hangB = Brand::create(['ten' => 'Remax', 'slug' => 'remax', 'kich_hoat' => true]);
        $hangTat = Brand::create(['ten' => 'Hang Tat', 'slug' => 'hang-tat', 'kich_hoat' => false]);

        $spA = $this->sanPham(['brand_id' => $hangA->id, 'ten_sanpham' => 'Củ sạc Hoco X1']);
        $spB = $this->sanPham(['brand_id' => $hangB->id, 'ten_sanpham' => 'Củ sạc Remax Y2']);
        $spTat = $this->sanPham(['brand_id' => $hangTat->id, 'ten_sanpham' => 'Củ sạc HangTat Z3']);

        // Lọc theo hãng A trên trang tìm kiếm.
        $response = $this->get(route('storefront.search', ['brand_id' => $hangA->id]));
        $response->assertOk()->assertSee($spA->ten_sanpham)->assertDontSee($spB->ten_sanpham);

        // brand_id không tồn tại / hãng đã tắt → bỏ qua, không 500.
        $this->get(route('storefront.search', ['brand_id' => 99999]))->assertOk();
        $resTat = $this->get(route('storefront.search', ['brand_id' => $hangTat->id]));
        $resTat->assertOk()->assertSee($spA->ten_sanpham)->assertSee($spB->ten_sanpham);

        // Hãng đã tắt không xuất hiện trong dropdown lọc (kiểm tra thẻ option).
        $this->get(route('storefront.search'))->assertOk()->assertDontSee('>'.$hangTat->ten.'</option>', false);

        // SP gán hãng đã tắt vẫn hiển thị tên hãng ở trang chi tiết.
        $this->get(route('storefront.product', $spTat))->assertOk()->assertSee('Hang Tat');

        // Lọc theo hãng trên trang danh mục.
        $cat = $spA->loaiSanPham;
        $this->get(route('storefront.category', ['loaiSanPham' => $cat->id, 'brand_id' => $hangB->id]))
            ->assertOk()
            ->assertSee($spB->ten_sanpham)
            ->assertDontSee($spA->ten_sanpham);
    }

    public function test_trang_chi_tiet_hien_thi_hang(): void
    {
        $hang = Brand::create(['ten' => 'Borofone', 'slug' => 'borofone', 'kich_hoat' => true]);
        $sp = $this->sanPham(['brand_id' => $hang->id]);

        $this->get(route('storefront.product', $sp))
            ->assertOk()
            ->assertSee('Borofone');
    }

    // ---------- Tình trạng tồn ----------

    public function test_nhan_tinh_trang_ton_theo_nguong(): void
    {
        // Hết hàng.
        $sp = $this->sanPham(['soluong' => 0, 'soluong_toithieu' => 5]);
        $this->assertSame(['is-out', 'Hết hàng'], $sp->tinhTrangTon());

        // Sắp hết: 0 < soluong <= toithieu VÀ <= 5.
        $sp = $this->sanPham(['soluong' => 3, 'soluong_toithieu' => 5]);
        $this->assertSame(['is-low', 'Sắp hết'], $sp->tinhTrangTon());

        // Toithieu cao nhưng tồn > 5 → vẫn Còn hàng.
        $sp = $this->sanPham(['soluong' => 10, 'soluong_toithieu' => 20]);
        $this->assertSame(['is-in', 'Còn hàng'], $sp->tinhTrangTon());

        // Tồn <= 5 nhưng > toithieu → Còn hàng.
        $sp = $this->sanPham(['soluong' => 3, 'soluong_toithieu' => 2]);
        $this->assertSame(['is-in', 'Còn hàng'], $sp->tinhTrangTon());

        // Storefront card hiển thị nhãn.
        $sp = $this->sanPham(['soluong' => 2, 'soluong_toithieu' => 5, 'ten_sanpham' => 'SP Sap Het Test']);
        $this->get(route('storefront.search'))->assertOk()->assertSee('Sắp hết');
    }

    // ---------- Backfill ----------

    public function test_backfill_dry_run_khong_ghi_va_apply_map_dung(): void
    {
        $sp1 = $this->sanPham(['ten_sanpham' => 'Củ sạc nhanh Nhãn hiệu: Hoco. Model: C24.']);
        $sp2 = $this->sanPham(['ten_sanpham' => 'Tai nghe Bluetooth Hiệu: Remax. Model: RB-1.']);
        $sp3 = $this->sanPham(['ten_sanpham' => 'SP không nhãn hiệu nào']);
        // SP gán tay → không bị ghi đè.
        $hangTay = Brand::create(['ten' => 'Tay', 'slug' => 'tay', 'kich_hoat' => true]);
        $sp4 = $this->sanPham(['ten_sanpham' => 'Cáp Nhãn hiệu: Hoco. Model: C1.', 'brand_id' => $hangTay->id]);

        // Dry-run: không ghi gì.
        $this->artisan('app:backfill-product-brands')
            ->expectsOutputToContain('DRY-RUN')
            ->assertSuccessful();
        $this->assertSame(1, Brand::count()); // chỉ 'Tay'
        $this->assertNull($sp1->fresh()->brand_id);
        $this->assertNull($sp3->fresh()->brand_id);

        // Apply.
        $this->artisan('app:backfill-product-brands', ['--apply' => true])->assertSuccessful();

        $hoco = Brand::where('ten', 'Hoco')->firstOrFail();
        $remax = Brand::where('ten', 'Remax')->firstOrFail();
        $this->assertSame($hoco->id, $sp1->fresh()->brand_id);
        $this->assertSame($remax->id, $sp2->fresh()->brand_id);
        $this->assertNull($sp3->fresh()->brand_id);          // không khớp → NULL
        $this->assertSame($hangTay->id, $sp4->fresh()->brand_id); // không ghi đè gán tay
        $this->assertSame('Cáp Nhãn hiệu: Hoco. Model: C1.', $sp4->fresh()->ten_sanpham); // tên giữ nguyên

        // Idempotent: chạy lại không nhân đôi hãng, không đổi gì.
        $this->artisan('app:backfill-product-brands', ['--apply' => true])->assertSuccessful();
        $this->assertSame(3, Brand::count());
        $this->assertSame(1, Brand::where('ten', 'Hoco')->count());
    }

    public function test_backfill_khong_phan_biet_hoa_thuong_va_giu_nguyen_ten_sp(): void
    {
        $sp1 = $this->sanPham(['ten_sanpham' => 'SP A Nhãn hiệu: Hoco. Model: X.']);
        $sp2 = $this->sanPham(['ten_sanpham' => 'SP B Nhãn hiệu: HOCO. Model: Y.']);

        $this->artisan('app:backfill-product-brands', ['--apply' => true])->assertSuccessful();

        $this->assertSame(1, Brand::where('ten', 'like', '%oco%')->orWhere('ten', 'like', '%OCO%')->count());
        $this->assertSame(Brand::firstOrFail()->id, $sp1->fresh()->brand_id);
        $this->assertSame($sp1->fresh()->brand_id, $sp2->fresh()->brand_id);
    }

    public function test_backfill_dinh_dang_dau_phay_sau_ten_hang(): void
    {
        // "Nhãn hiệu: X, Model : Y." — hãng kết thúc bằng dấu phẩy, không phải chấm.
        $sp = $this->sanPham(['ten_sanpham' => 'Dây cáp sạc Type-C dài 1m. Nhãn hiệu: Hoco, Model : BX101A. Mới 100%']);

        $this->artisan('app:backfill-product-brands', ['--apply' => true])->assertSuccessful();

        $hang = Brand::where('ten', 'Hoco')->firstOrFail();
        $this->assertSame($hang->id, $sp->fresh()->brand_id);
        // Không được tạo hãng rác chứa "Model".
        $this->assertSame(1, Brand::count());
    }

    public function test_backfill_sp_nhieu_hang_thi_de_null(): void
    {
        $sp = $this->sanPham(['ten_sanpham' => 'Combo Nhãn hiệu: Hoco. Hiệu: Remax. Model: Z.']);

        $this->artisan('app:backfill-product-brands', ['--apply' => true])->assertSuccessful();

        $this->assertNull($sp->fresh()->brand_id);
    }

    public function test_audit_log_ghi_khi_apply_backfill(): void
    {
        $this->sanPham(['ten_sanpham' => 'Củ sạc Nhãn hiệu: Hoco. Model: A.']);

        $this->artisan('app:backfill-product-brands', ['--apply' => true])->assertSuccessful();

        $this->assertDatabaseHas('activity_logs', [
            'hanh_dong' => 'backfill_hang_san_xuat',
            'nguon' => 'console',
        ]);
    }
}
