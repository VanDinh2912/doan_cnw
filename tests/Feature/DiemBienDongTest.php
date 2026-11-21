<?php

namespace Tests\Feature;

use App\Models\ChiTietDonHang;
use App\Models\DiemBienDong;
use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\LoaiSanPham;
use App\Models\PhieuTra;
use App\Models\SanPham;
use App\Models\User;
use App\Services\DiemService;
use App\Services\ThanhToanChuyenKhoanService;
use App\Support\KiemTraSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DiemBienDongTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;
    private User $admin;
    private User $thungan2;
    private SanPham $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->cashier = User::where('role', 'thungan')->firstOrFail();
        $this->admin = User::where('role', 'admin')->firstOrFail();
        $this->thungan2 = User::create([
            'name' => 'Thu kho', 'email' => 'thukho.diem@test.local',
            'password' => bcrypt('12345678'), 'role' => 'thukho',
        ]);
        $this->product = SanPham::where('sku', 'CS-0001')->firstOrFail();
        Config::set('pos.gia_tri_diem', 1000);
        Config::set('pos.diem_moi_dong', 10000);
        Config::set('pos.bank_code', 'MB');
        Config::set('pos.bank_account', '0987654321');
    }

    private function khach(int $diem = 0): KhachHang
    {
        $k = KhachHang::create([
            'ho_ten' => 'Khach So '.uniqid(),
            'so_dienthoai' => '09'.random_int(10000000, 99999999),
        ]);
        $k->diem_tichluy = $diem;
        $k->save();

        return $k;
    }

    private function gioHang(): array
    {
        return ['gio_hang' => [(string) $this->product->id => [
            'id' => $this->product->id, 'sku' => $this->product->sku,
            'ten_sanpham' => $this->product->ten_sanpham,
            'gia_ban' => (float) $this->product->gia_ban,
            'soluong' => 1, 'ton_kho' => $this->product->soluong,
        ]]];
    }

    private function soTheoLoai(int $customerId, string $loai): \Illuminate\Support\Collection
    {
        return DiemBienDong::where('customer_id', $customerId)->where('loai', $loai)->get();
    }

    // ---------- POS tiền mặt ----------

    public function test_pos_tienmat_ghi_so_dung_va_tich_diem(): void
    {
        $kh = $this->khach(30);
        DiemService::ghi($kh->id, DiemService::LOAI_SO_DAU_KY, 30, 30, ['su_kien' => 'test-pos-ky']);

        $this->actingAs($this->cashier)->withSession($this->gioHang())
            ->post(route('donhang.thanhtoan'), [
                'giam_gia_phantram' => 0, 'tien_khach_dua' => 99999999,
                'phuong_thuc' => 'tienmat', 'customer_id' => $kh->id, 'diem_su_dung' => 10,
            ])->assertRedirect();

        $kh->refresh();
        // Điểm nhận tính trên tổng sau khi trừ điểm đã dùng — lấy từ chính hóa đơn.
        $don = DonHang::where('customer_id', $kh->id)->latest('id')->firstOrFail();
        $tichMongDoi = (int) $don->diem_tichluy_nhan;
        $this->assertSame(30 - 10 + $tichMongDoi, (int) $kh->diem_tichluy);

        $dung = $this->soTheoLoai($kh->id, DiemService::LOAI_DUNG);
        $tich = $this->soTheoLoai($kh->id, DiemService::LOAI_TICH);
        $this->assertCount(1, $dung);
        $this->assertSame(-10, $dung->first()->diem);
        $this->assertSame(20, $dung->first()->so_du_sau);
        $this->assertCount(1, $tich);
        $this->assertSame($tichMongDoi, $tich->first()->diem);
        $this->assertSame((int) $kh->diem_tichluy, $tich->first()->so_du_sau);
        // Tổng sổ = số dư
        $this->assertSame((int) $kh->diem_tichluy,
            (int) DiemBienDong::where('customer_id', $kh->id)->sum('diem'));
    }

    // ---------- QR / đơn chờ thanh toán ----------

    public function test_qr_ghi_so_khi_tao_va_khi_xac_nhan_idempotent(): void
    {
        $kh = $this->khach(50);
        $service = app(ThanhToanChuyenKhoanService::class);

        $don = $service->taoHoaDonChoThanhToan(
            [['id' => $this->product->id, 'soluong' => 1]],
            ['customer_id' => $kh->id, 'diem_su_dung' => 15, 'giam_gia_phantram' => 0],
            $this->cashier
        );

        $this->assertSame(35, (int) $kh->fresh()->diem_tichluy);
        $dung = DiemBienDong::where('invoice_id', $don->id)->where('loai', DiemService::LOAI_DUNG)->get();
        $this->assertCount(1, $dung);
        $this->assertSame(-15, $dung->first()->diem);
        $this->assertSame(35, $dung->first()->so_du_sau);

        // Xác nhận thanh toán → cộng điểm nhận; gọi lặp không ghi thêm
        $service->hoanTatThanhToan($don->fresh(), null, $this->cashier);
        $service->hoanTatThanhToan($don->fresh(), null, $this->cashier);

        $tich = DiemBienDong::where('invoice_id', $don->id)->where('loai', DiemService::LOAI_TICH)->get();
        $this->assertCount(1, $tich, 'Webhook/click lặp không được ghi dòng tich_diem thứ hai');
        $this->assertSame((int) $don->fresh()->diem_tichluy_nhan, $tich->first()->diem);
        $this->assertSame((int) $kh->fresh()->diem_tichluy, $tich->first()->so_du_sau);
    }

    public function test_huy_don_cho_ghi_so_hoan_diem(): void
    {
        $kh = $this->khach(50);
        $service = app(ThanhToanChuyenKhoanService::class);
        $don = $service->taoHoaDonChoThanhToan(
            [['id' => $this->product->id, 'soluong' => 1]],
            ['customer_id' => $kh->id, 'diem_su_dung' => 15, 'giam_gia_phantram' => 0],
            $this->cashier
        );

        $service->huyChoThanhToan($don->fresh(), 'test hủy');

        $this->assertSame(50, (int) $kh->fresh()->diem_tichluy);
        $hoan = DiemBienDong::where('invoice_id', $don->id)->where('loai', DiemService::LOAI_HOAN_DIEM_HUY)->get();
        $this->assertCount(1, $hoan);
        $this->assertSame(15, $hoan->first()->diem);
        $this->assertSame(50, $hoan->first()->so_du_sau);
    }

    public function test_huy_don_da_thanh_toan_ghi_so_thu_hoi(): void
    {
        $kh = $this->khach(30);
        $don = DonHang::create([
            'ma_hoadon' => 'HD-SO-'.uniqid(), 'user_id' => $this->cashier->id,
            'customer_id' => $kh->id, 'tong_tien' => 100000, 'tong_thanh_toan' => 100000,
            'tien_khach_dua' => 100000, 'tien_thua' => 0, 'phuong_thuc' => 'tienmat',
            'trang_thai' => 'da_thanh_toan', 'diem_tichluy_nhan' => 10,
        ]);
        ChiTietDonHang::create([
            'invoice_id' => $don->id, 'product_id' => $this->product->id,
            'soluong' => 1, 'don_gia' => 100000, 'gia_von' => 50000, 'thanh_tien' => 100000,
        ]);

        $service = app(ThanhToanChuyenKhoanService::class);
        $service->huyDonDaThanhToan($don->fresh(), 'test thu hồi');

        $this->assertSame(20, (int) $kh->fresh()->diem_tichluy);
        $thuHoi = DiemBienDong::where('invoice_id', $don->id)->where('loai', DiemService::LOAI_THU_HOI_HUY)->get();
        $this->assertCount(1, $thuHoi);
        $this->assertSame(-10, $thuHoi->first()->diem);
        $this->assertSame(20, $thuHoi->first()->so_du_sau);
    }

    // ---------- Trả hàng ----------

    private function donDaThanhToan(KhachHang $kh, int $diemDung = 0, int $diemNhan = 0): DonHang
    {
        $don = DonHang::create([
            'ma_hoadon' => 'HD-TRA-'.uniqid(), 'user_id' => $this->cashier->id,
            'customer_id' => $kh->id, 'tong_tien' => 100000, 'tong_thanh_toan' => 100000,
            'tien_khach_dua' => 100000, 'tien_thua' => 0, 'phuong_thuc' => 'tienmat',
            'trang_thai' => 'da_thanh_toan', 'diem_su_dung' => $diemDung,
            'tien_giam_diem' => $diemDung * 1000,
            'diem_tichluy_nhan' => $diemNhan,
        ]);
        ChiTietDonHang::create([
            'invoice_id' => $don->id, 'product_id' => $this->product->id,
            'soluong' => 2, 'don_gia' => 50000, 'gia_von' => 30000, 'thanh_tien' => 100000,
        ]);

        return $don;
    }

    public function test_tra_hang_ghi_so_thu_hoi_va_hoan_diem(): void
    {
        $kh = $this->khach(100);
        DiemService::ghi($kh->id, DiemService::LOAI_SO_DAU_KY, 100, 100, ['su_kien' => 'test-tra-ky']);
        $don = $this->donDaThanhToan($kh, 10, 10);
        $ct = $don->chiTietDonHangs()->firstOrFail();

        // Trả toàn bộ → thu hồi điểm nhận + hoàn điểm đã dùng
        $this->actingAs($this->cashier)->post(route('trahang.luu'), [
            'invoice_id' => $don->id, 'hinh_thuc_hoan' => 'tienmat', 'ly_do' => 'test',
            'items' => [['invoice_detail_id' => $ct->id, 'soluong' => 2, 'tinh_trang' => 'con_tot']],
        ])->assertRedirect();

        // Phiếu nhỏ được tự duyệt ngay khi lưu; phiếu chờ duyệt thì admin duyệt.
        $phieu = PhieuTra::latest('id')->firstOrFail();
        if ($phieu->trang_thai === 'cho_duyet') {
            $this->actingAs($this->admin)->post(route('trahang.duyet', $phieu))->assertRedirect();
            $phieu->refresh();
        }
        $this->assertSame('hoan_thanh', $phieu->trang_thai);

        $thuHoi = DiemBienDong::where('return_id', $phieu->id)->where('loai', DiemService::LOAI_THU_HOI_TRA)->get();
        $hoan = DiemBienDong::where('return_id', $phieu->id)->where('loai', DiemService::LOAI_HOAN_DIEM_TRA)->get();
        $this->assertCount(1, $thuHoi);
        $this->assertCount(1, $hoan);
        // Tổng sổ = số dư thực tế
        $this->assertSame((int) $kh->fresh()->diem_tichluy,
            (int) DiemBienDong::where('customer_id', $kh->id)->sum('diem'));
    }

    // ---------- Điều chỉnh điểm ----------

    public function test_dieu_chinh_diem_admin_ok_ghi_so(): void
    {
        $kh = $this->khach(100);

        $this->actingAs($this->admin)->post(route('khachhang.dieuchinhdiem', $kh), [
            'so_diem' => 50, 'ly_do' => 'Tri an khach hang than thiet',
        ])->assertRedirect(route('khachhang.chitiet', $kh));

        $this->assertSame(150, (int) $kh->fresh()->diem_tichluy);
        $gd = DiemBienDong::where('customer_id', $kh->id)->where('loai', DiemService::LOAI_DIEU_CHINH)->sole();
        $this->assertSame(50, $gd->diem);
        $this->assertSame(150, $gd->so_du_sau);
        $this->assertSame($this->admin->id, $gd->user_id);
        $this->assertSame('Tri an khach hang than thiet', $gd->ly_do);
    }

    public function test_dieu_chinh_diem_quyen_va_validate(): void
    {
        $kh = $this->khach(100);

        // Thu ngân / thủ kho bị chặn 403
        $this->actingAs($this->cashier)->post(route('khachhang.dieuchinhdiem', $kh), [
            'so_diem' => 10, 'ly_do' => 'Thu ngan thu',
        ])->assertForbidden();
        $this->actingAs($this->thungan2)->post(route('khachhang.dieuchinhdiem', $kh), [
            'so_diem' => 10, 'ly_do' => 'Thu kho thu',
        ])->assertForbidden();

        // Thiếu lý do / lý do ngắn
        $this->actingAs($this->admin)->post(route('khachhang.dieuchinhdiem', $kh), [
            'so_diem' => 10, 'ly_do' => '',
        ])->assertSessionHasErrors('ly_do');
        $this->actingAs($this->admin)->post(route('khachhang.dieuchinhdiem', $kh), [
            'so_diem' => 10, 'ly_do' => 'ab',
        ])->assertSessionHasErrors('ly_do');

        // = 0, vượt giới hạn, làm âm số dư
        $this->actingAs($this->admin)->post(route('khachhang.dieuchinhdiem', $kh), [
            'so_diem' => 0, 'ly_do' => 'Ly do hop le day du',
        ])->assertSessionHas('error');
        $this->actingAs($this->admin)->post(route('khachhang.dieuchinhdiem', $kh), [
            'so_diem' => 501, 'ly_do' => 'Ly do hop le day du',
        ])->assertSessionHas('error');
        $this->actingAs($this->admin)->post(route('khachhang.dieuchinhdiem', $kh), [
            'so_diem' => -150, 'ly_do' => 'Ly do hop le day du',
        ])->assertSessionHasErrors('so_diem');

        $this->assertSame(100, (int) $kh->fresh()->diem_tichluy);
        $this->assertSame(0, DiemBienDong::where('customer_id', $kh->id)->count());
    }

    public function test_route_dieu_chinh_diem_co_middleware_pin_nghiem_trong(): void
    {
        $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName('khachhang.dieuchinhdiem');
        $this->assertNotNull($route);
        $muc = collect($route->gatherMiddleware())->first(
            fn ($m) => str_contains((string) $m, 'xac.nhan.pin')
        );
        $this->assertStringContainsString('nghiemtrong', (string) $muc);
        // Chỉ admin
        $this->assertContains('role:admin', array_map('strval', $route->gatherMiddleware()));
    }

    // ---------- Chặn sửa điểm qua form khách ----------

    public function test_put_sua_khach_khong_nhan_diem_tichluy(): void
    {
        $kh = $this->khach(77);

        $this->actingAs($this->admin)->put(route('khachhang.postsua', $kh), [
            'ho_ten' => 'Ten Moi', 'so_dienthoai' => $kh->so_dienthoai,
            'dia_chi' => 'Dia chi moi', 'diem_tichluy' => 9999,
        ])->assertRedirect();

        $kh->refresh();
        $this->assertSame('Ten Moi', $kh->ho_ten);
        $this->assertSame(77, (int) $kh->diem_tichluy);
    }

    // ---------- Trang điểm của khách ----------

    public function test_trang_diem_khach_chi_thay_cua_minh_va_label(): void
    {
        $userA = User::create([
            'name' => 'Khach A', 'email' => 'diem.a@test.local',
            'password' => bcrypt('12345678'), 'role' => 'khachhang',
        ]);
        $khA = $this->khach(40);
        $khA->user_id = $userA->id;
        $khA->save();

        // Điều chỉnh để có dòng dieu_chinh (ly_do nội bộ không lộ)
        $khA->diem_tichluy = 45;
        $khA->save();
        DiemService::ghi($khA->id, DiemService::LOAI_DIEU_CHINH, 5, 45, [
            'user_id' => $this->admin->id, 'ly_do' => 'Ly do noi bo khong duoc lo',
            'su_kien' => 'test-diem-trang-a',
        ]);

        $res = $this->actingAs($userA)->get(route('profile.diem'))->assertOk();
        $res->assertSee('45', false); // số dư sau 40+5
        $res->assertSee('Điều chỉnh bởi cửa hàng', false);
        $res->assertDontSee('Ly do noi bo khong duoc lo', false);
        $res->assertSee('Điểm tích lũy', false);

        // Khách khác không thấy số dư/biến động của A — trang chỉ render dữ liệu của chính mình
        $userB = User::create([
            'name' => 'Khach B', 'email' => 'diem.b@test.local',
            'password' => bcrypt('12345678'), 'role' => 'khachhang',
        ]);
        $khB = $this->khach(0);
        $khB->user_id = $userB->id;
        $khB->save();

        $resB = $this->actingAs($userB)->get(route('profile.diem'))->assertOk();
        $resB->assertDontSee('Điều chỉnh bởi cửa hàng', false);
    }

    // ---------- Thẻ công nợ điểm ----------

    public function test_tongquan_hien_the_cong_no_diem(): void
    {
        $this->khach(30);
        $this->khach(20);

        $this->actingAs($this->admin)->get(route('tongquan'))->assertOk()
            ->assertSee('Công nợ điểm', false)
            ->assertSee('50.000', false); // 50 điểm x 1000đ
    }

    // ---------- Lệnh đối soát ----------

    public function test_doi_soat_khop_va_lech(): void
    {
        $khop = $this->khach(30);
        DiemService::ghi($khop->id, DiemService::LOAI_SO_DAU_KY, 30, 30, ['su_kien' => 'test-khop']);

        $lech = $this->khach(20); // có số dư nhưng chưa có sổ

        $this->artisan('diem:doi-soat')->assertSuccessful();

        // Sau khi ghi sổ cho $lech thì khớp
        DiemService::ghi($lech->id, DiemService::LOAI_SO_DAU_KY, 20, 20, ['su_kien' => 'test-lech']);
        $this->artisan('diem:doi-soat')->assertSuccessful();
    }

    // ---------- Khởi tạo số dư đầu kỳ ----------

    public function test_khoi_tao_so_dau_ky_idempotent(): void
    {
        $kh = $this->khach(25);

        $this->artisan('diem:khoi-tao-so')->assertSuccessful(); // dry-run
        $this->assertSame(0, DiemBienDong::count());

        $this->artisan('diem:khoi-tao-so', ['--apply' => true])->assertSuccessful();
        $this->assertSame(1, DiemBienDong::count());
        $gd = DiemBienDong::sole();
        $this->assertSame(DiemService::LOAI_SO_DAU_KY, $gd->loai);
        $this->assertSame(25, $gd->diem);
        $this->assertSame(25, $gd->so_du_sau);

        // Chạy lại --apply không nhân đôi
        $this->artisan('diem:khoi-tao-so', ['--apply' => true])->assertSuccessful();
        $this->assertSame(1, DiemBienDong::count());
    }

    // ---------- Guard: bảng chưa tồn tại ----------

    public function test_thieu_bang_so_van_chay_binh_thuong(): void
    {
        Schema::drop('diem_bien_dong');
        KiemTraSchema::datLai();
        $this->assertFalse(DiemService::sanSang());
        $this->assertNull(DiemService::ghi(1, DiemService::LOAI_TICH, 1, 1));

        // POS thanh toán vẫn thành công, không ghi sổ, không lỗi
        $kh = $this->khach(5);
        $this->actingAs($this->cashier)->withSession($this->gioHang())
            ->post(route('donhang.thanhtoan'), [
                'giam_gia_phantram' => 0, 'tien_khach_dua' => 99999999,
                'phuong_thuc' => 'tienmat', 'customer_id' => $kh->id, 'diem_su_dung' => 0,
            ])->assertRedirect();
        $this->assertTrue(DiemService::sanSang() === false);
    }
}
