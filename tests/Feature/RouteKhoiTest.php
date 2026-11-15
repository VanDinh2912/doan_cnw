<?php

namespace Tests\Feature;

use App\Models\BienDongKho;
use App\Models\ChiTietDonHang;
use App\Models\ChiTietDonNhap;
use App\Models\ChiTietHoaDonNhap;
use App\Models\ChiTietKiemKe;
use App\Models\ChiTietPhieuTra;
use App\Models\DonHang;
use App\Models\DonNhap;
use App\Models\GioTam;
use App\Models\HoaDonNhap;
use App\Models\KhachHang;
use App\Models\LoaiSanPham;
use App\Models\NhaCungCap;
use App\Models\NhatKyHoatDong;
use App\Models\PaymentWebhook;
use App\Models\PhieuKiemKe;
use App\Models\PhieuTra;
use App\Models\SanPham;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test khói toàn bộ route GET: mỗi vai trò chỉ được 200/302 hoặc 403,
 * tuyệt đối không 500. Dữ liệu tạo >= 3 bản ghi mỗi mô hình có quan hệ để
 * preventLazyLoading() thực sự kích hoạt (Builder chỉ chặn khi hydrate > 1).
 */
class RouteKhoiTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, User> */
    private array $nguoiDung = [];

    private DonHang $hoaDonDaThanhToan;
    private PhieuTra $phieuTra;
    private LoaiSanPham $loai;
    private SanPham $sanPham;
    private KhachHang $khachHang;
    private NhaCungCap $nhaCungCap;
    private HoaDonNhap $hoaDonNhap;
    private DonNhap $donNhap;
    private GioTam $gioTam;
    private PhieuKiemKe $phieuKiemKe;
    private PaymentWebhook $webhook;

    /** @var list<array<string, mixed>> */
    private array $bangRoute = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->taoNguoiDung();
        $this->taoDuLieu();
        $this->dungBangRoute();
    }

    public function test_route_get_khong_tham_so_vai_tro_khach(): void
    {
        $this->chaySmoke('guest');
    }

    public function test_route_get_khong_tham_so_vai_tro_thu_ngan(): void
    {
        $this->chaySmoke('thungan');
    }

    public function test_route_get_khong_tham_so_vai_tro_thu_kho(): void
    {
        $this->chaySmoke('thukho');
    }

    public function test_route_get_khong_tham_so_vai_tro_quan_tri(): void
    {
        $this->chaySmoke('admin');
    }

    private function chaySmoke(string $vaiTro): void
    {
        if ($vaiTro !== 'guest') {
            $this->actingAs($this->nguoiDung[$vaiTro]);
        }

        foreach ($this->bangRoute as $route) {
            $ten = $route['name'] ?? $route['uri'];
            $uri = isset($route['name'])
                ? route($route['name'], $route['params'] ?? [])
                : $route['uri'];

            $status = $this->get($uri)->getStatusCode();

            if ($vaiTro === 'guest') {
                $duKien = $route['guest'] ?? 302;
                $this->assertSame(
                    $duKien,
                    $status,
                    "Khách gọi {$ten} ({$uri}) phải trả {$duKien}, nhận {$status}."
                );
                continue;
            }

            if (in_array($vaiTro, $route['roles'] ?? [], true)) {
                $duKien = $route['status'] ?? 200;
                $this->assertSame(
                    $duKien,
                    $status,
                    "{$vaiTro} gọi {$ten} ({$uri}) phải trả {$duKien}, nhận {$status}."
                );
                continue;
            }

            $this->assertSame(
                403,
                $status,
                "{$vaiTro} không có quyền nhưng {$ten} ({$uri}) trả {$status} thay vì 403."
            );
        }
    }

    private function taoNguoiDung(): void
    {
        $this->nguoiDung = [
            'admin' => User::factory()->create(['role' => 'admin']),
            'thungan' => User::factory()->create(['role' => 'thungan']),
            'thukho' => User::factory()->create(['role' => 'thukho']),
        ];
    }

    private function taoDuLieu(): void
    {
        $thuNgan = $this->nguoiDung['thungan'];
        $thuKho = $this->nguoiDung['thukho'];

        // Loại + sản phẩm: 3 loại, mỗi loại 3 sản phẩm.
        $this->loai = LoaiSanPham::create(['ten_loai' => 'Sạc dự phòng']);
        $cacLoai = [$this->loai];
        foreach (['Cáp sạc', 'Tai nghe'] as $tenLoai) {
            $cacLoai[] = LoaiSanPham::create(['ten_loai' => $tenLoai]);
        }

        $tatCaSanPham = [];
        foreach ($cacLoai as $i => $loai) {
            for ($j = 1; $j <= 3; $j++) {
                $tatCaSanPham[] = SanPham::create([
                    'category_id' => $loai->id,
                    'sku' => 'SP-'.$i.'-'.$j,
                    'barcode' => '893000'.$i.$j,
                    'ten_sanpham' => $loai->ten_loai.' '.$j,
                    'gia_nhap' => 100000,
                    'gia_ban' => 150000,
                    'soluong' => 10,
                    'soluong_toithieu' => 2,
                    'trang_thai' => true,
                ]);
            }
        }
        $this->sanPham = $tatCaSanPham[0];

        // Khách hàng.
        $cacKhachHang = [];
        foreach (['Nguyễn Văn A', 'Trần Thị B', 'Lê Văn C'] as $i => $hoTen) {
            $cacKhachHang[] = KhachHang::create([
                'ho_ten' => $hoTen,
                'so_dienthoai' => '090000000'.($i + 1),
                'dia_chi' => 'Địa chỉ '.($i + 1),
                'diem_tichluy' => 10,
            ]);
        }
        $this->khachHang = $cacKhachHang[0];

        // Hóa đơn có chi tiết: 3 hóa đơn đã thanh toán.
        foreach (range(1, 3) as $i) {
            $hoaDon = DonHang::create([
                'ma_hoadon' => 'HD20261111'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'user_id' => $thuNgan->id,
                'customer_id' => $cacKhachHang[$i - 1]->id,
                'tong_tien' => 300000,
                'tong_thanh_toan' => 300000,
                'tien_khach_dua' => 300000,
                'phuong_thuc' => 'tienmat',
                'trang_thai' => 'da_thanh_toan',
                'thoi_gian_thanh_toan' => now(),
            ]);

            foreach (array_slice($tatCaSanPham, ($i - 1) * 2, 2) as $sanPham) {
                ChiTietDonHang::create([
                    'invoice_id' => $hoaDon->id,
                    'product_id' => $sanPham->id,
                    'soluong' => 1,
                    'don_gia' => 150000,
                    'gia_von' => 100000,
                    'thanh_tien' => 150000,
                ]);
            }

            if ($i === 1) {
                $this->hoaDonDaThanhToan = $hoaDon;
            }
        }

        // Nhà cung cấp.
        $cacNhaCungCap = [];
        foreach (['Nhà cung cấp A', 'Nhà cung cấp B', 'Nhà cung cấp C'] as $i => $ten) {
            $cacNhaCungCap[] = NhaCungCap::create([
                'ten_nhacungcap' => $ten,
                'nguoi_lienhe' => 'Liên hệ '.($i + 1),
                'so_dienthoai' => '091000000'.($i + 1),
            ]);
        }
        $this->nhaCungCap = $cacNhaCungCap[0];

        // Đơn nhập (purchase order): 3 đơn, mỗi đơn 2 dòng.
        foreach (range(1, 3) as $i) {
            $donNhap = DonNhap::create([
                'ma_don' => 'DN202611110'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'supplier_id' => $cacNhaCungCap[$i - 1]->id,
                'user_id' => $thuKho->id,
                'trang_thai' => 'cho_duyet',
                'tong_tien' => 500000,
                'ngay_dukien' => now()->addDays(3),
            ]);

            foreach (array_slice($tatCaSanPham, ($i - 1) * 2, 2) as $sanPham) {
                ChiTietDonNhap::create([
                    'purchase_order_id' => $donNhap->id,
                    'product_id' => $sanPham->id,
                    'soluong_dat' => 5,
                    'soluong_nhan' => 0,
                    'don_gia_nhap' => 100000,
                    'thanh_tien' => 500000,
                ]);
            }

            if ($i === 1) {
                $this->donNhap = $donNhap;
            }
        }

        // Hóa đơn nhập: 3 hóa đơn tạm, mỗi hóa đơn 2 dòng.
        foreach (range(1, 3) as $i) {
            $hoaDonNhap = HoaDonNhap::create([
                'ma_phieu' => 'PN202611110'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'so_hoadon' => '000000'.($i + 10),
                'ky_hieu' => 'K1',
                'mau_so' => '1C26',
                'ngay_hoadon' => now()->toDateString(),
                'supplier_id' => $cacNhaCungCap[$i - 1]->id,
                'user_id' => $thuKho->id,
                'trang_thai' => 'nhap_tam',
                'tong_tien_hang' => 400000,
                'thue_suat' => 8,
                'tien_thue' => 32000,
                'tong_thanh_toan' => 432000,
            ]);

            foreach (array_slice($tatCaSanPham, ($i - 1) * 2, 2) as $stt => $sanPham) {
                ChiTietHoaDonNhap::create([
                    'purchase_invoice_id' => $hoaDonNhap->id,
                    'stt' => $stt + 1,
                    'product_id' => $sanPham->id,
                    'ten_hang' => $sanPham->ten_sanpham,
                    'don_vi' => 'Cái',
                    'soluong' => 2,
                    'don_gia' => 100000,
                    'thanh_tien' => 200000,
                    'category_id' => $sanPham->category_id,
                    'gia_ban_de_xuat' => 150000,
                ]);
            }

            if ($i === 1) {
                $this->hoaDonNhap = $hoaDonNhap;
            }
        }

        // Biến động kho: 3 bản ghi.
        foreach (array_slice($tatCaSanPham, 0, 3) as $sanPham) {
            BienDongKho::create([
                'product_id' => $sanPham->id,
                'user_id' => $thuKho->id,
                'purchase_invoice_id' => $this->hoaDonNhap->id,
                'loai' => 'nhap',
                'soluong' => 5,
                'ghi_chu' => 'Nhập kho khói',
            ]);
        }

        // Phiếu trả hàng: 3 phiếu, mỗi phiếu 1 dòng.
        $chiTietHoaDon = $this->hoaDonDaThanhToan->chiTietDonHangs()->firstOrFail();
        foreach (range(1, 3) as $i) {
            $phieuTra = PhieuTra::create([
                'ma_phieu' => 'PT202611110'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'invoice_id' => $this->hoaDonDaThanhToan->id,
                'user_id' => $thuNgan->id,
                'customer_id' => $cacKhachHang[0]->id,
                'tong_hoan' => 150000,
                'diem_thu_hoi' => 0,
                'diem_hoan' => 0,
                'hinh_thuc_hoan' => 'tienmat',
                'trang_thai' => 'cho_duyet',
            ]);

            ChiTietPhieuTra::create([
                'return_id' => $phieuTra->id,
                'invoice_detail_id' => $chiTietHoaDon->id,
                'product_id' => $chiTietHoaDon->product_id,
                'soluong' => 1,
                'don_gia' => 150000,
                'gia_von' => 100000,
                'thanh_tien' => 150000,
                'tinh_trang' => 'con_tot',
            ]);

            if ($i === 1) {
                $this->phieuTra = $phieuTra;
            }
        }

        // Nhật ký: 3 bản ghi.
        foreach (range(1, 3) as $i) {
            NhatKyHoatDong::create([
                'user_id' => $this->nguoiDung['admin']->id,
                'hanh_dong' => 'kiem_tra',
                'doi_tuong' => 'he_thong',
                'doi_tuong_id' => $i,
                'mo_ta' => 'Bản ghi khói '.$i,
                'ip' => '127.0.0.1',
            ]);
        }

        // Webhook cần đối soát: 3 bản ghi, có hóa đơn để nạp quan hệ.
        foreach (range(1, 3) as $i) {
            $webhook = PaymentWebhook::create([
                'nha_cung_cap' => 'sepay',
                'ma_giao_dich_ngoai' => 'KHOI-'.$i,
                'invoice_id' => $this->hoaDonDaThanhToan->id,
                'so_tien' => 300000,
                'ket_qua' => 'can_doi_soat',
                'ly_do' => 'don_da_huy',
                'ma_hoadon' => 'HD20261111'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'noi_dung' => 'THANH TOAN DON KHOI '.$i,
                'payload' => ['id' => $i],
                'trang_thai_xu_ly' => 'chua_xu_ly',
            ]);

            if ($i === 1) {
                $this->webhook = $webhook;
            }
        }

        // Kiểm kê: 3 phiếu, mỗi phiếu 2 dòng.
        foreach (range(1, 3) as $i) {
            $phieu = PhieuKiemKe::create([
                'ma_phieu' => 'KK202611110'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'user_id' => $thuKho->id,
                'trang_thai' => 'ban_nhap',
            ]);

            foreach (array_slice($tatCaSanPham, ($i - 1) * 2, 2) as $sanPham) {
                ChiTietKiemKe::create([
                    'stocktake_id' => $phieu->id,
                    'product_id' => $sanPham->id,
                    'soluong_he_thong' => 10,
                ]);
            }

            if ($i === 1) {
                $this->phieuKiemKe = $phieu;
            }
        }

        // Giỏ tạm: 3 bản ghi thuộc thu ngân.
        foreach (range(1, 3) as $i) {
            $gioTam = GioTam::create([
                'user_id' => $thuNgan->id,
                'ten_goi' => 'Giỏ khói '.$i,
                'du_lieu' => [
                    (string) $this->sanPham->id => [
                        'ten_sanpham' => $this->sanPham->ten_sanpham,
                        'soluong' => 1,
                        'gia_ban' => 150000,
                    ],
                ],
            ]);

            if ($i === 1) {
                $this->gioTam = $gioTam;
            }
        }
    }

    private function dungBangRoute(): void
    {
        $this->bangRoute = [
            // Công khai.
            ['name' => 'storefront.home', 'guest' => 200, 'roles' => ['admin', 'thungan', 'thukho']],
            ['name' => 'storefront.search', 'guest' => 200, 'roles' => ['admin', 'thungan', 'thukho']],
            ['name' => 'storefront.cart.index', 'guest' => 200, 'roles' => ['admin', 'thungan', 'thukho']],
            ['name' => 'storefront.product', 'params' => ['sanPham' => $this->sanPham->id], 'guest' => 200, 'roles' => ['admin', 'thungan', 'thukho']],
            ['name' => 'storefront.category', 'params' => ['loaiSanPham' => $this->loai->id], 'guest' => 200, 'roles' => ['admin', 'thungan', 'thukho']],
            ['name' => 'storefront.cart.add', 'params' => ['sanPham' => $this->sanPham->id], 'guest' => 302, 'status' => 302, 'roles' => ['admin', 'thungan', 'thukho']],
            ['name' => 'storefront.cart.remove', 'params' => ['sanPham' => $this->sanPham->id], 'guest' => 302, 'status' => 302, 'roles' => ['admin', 'thungan', 'thukho']],
            ['name' => 'auth.dangnhap', 'guest' => 200, 'status' => 302, 'roles' => ['admin', 'thungan', 'thukho']],
            ['name' => 'auth.dangky', 'guest' => 200, 'status' => 302, 'roles' => ['admin', 'thungan', 'thukho']],
            ['uri' => '/up', 'guest' => 200, 'roles' => ['admin', 'thungan', 'thukho']],

            // Chung cho nhân viên.
            ['name' => 'tongquan', 'roles' => ['admin', 'thungan', 'thukho']],

            // Nhóm quyền admin + thu ngân.
            ['name' => 'sanpham.timkiem', 'roles' => ['admin', 'thungan']],
            ['name' => 'trahang.danhsach', 'roles' => ['admin', 'thungan']],
            ['name' => 'trahang.chon', 'params' => ['donHang' => $this->hoaDonDaThanhToan->id], 'roles' => ['admin', 'thungan']],
            ['name' => 'trahang.xem', 'params' => ['phieuTra' => $this->phieuTra->id], 'roles' => ['admin', 'thungan']],
            ['name' => 'pos.index', 'roles' => ['admin', 'thungan']],
            ['name' => 'pos.vietqr', 'roles' => ['admin', 'thungan']],
            ['name' => 'donhang.trangthai', 'params' => ['donHang' => $this->hoaDonDaThanhToan->id], 'roles' => ['admin', 'thungan']],
            ['name' => 'donhang.danhsach', 'roles' => ['admin', 'thungan']],
            ['name' => 'donhang.inhoadon', 'params' => ['donHang' => $this->hoaDonDaThanhToan->id], 'roles' => ['admin', 'thungan']],
            ['name' => 'hold.danhsach', 'roles' => ['admin', 'thungan']],
            ['name' => 'hold.khoiphuc', 'params' => ['gioTam' => $this->gioTam->id], 'status' => 302, 'roles' => ['admin', 'thungan']],
            ['name' => 'giohang.them', 'params' => ['sanPham' => $this->sanPham->id], 'status' => 302, 'roles' => ['admin', 'thungan']],
            ['name' => 'giohang.xoa', 'params' => ['sanPham' => $this->sanPham->id], 'status' => 302, 'roles' => ['admin', 'thungan']],
            ['name' => 'khachhang.danhsach', 'roles' => ['admin', 'thungan']],
            ['name' => 'khachhang.them', 'roles' => ['admin', 'thungan']],
            ['name' => 'khachhang.sua', 'params' => ['khachHang' => $this->khachHang->id], 'roles' => ['admin', 'thungan']],
            ['name' => 'khachhang.timkiem', 'roles' => ['admin', 'thungan']],

            // Nhóm quyền admin + thủ kho.
            ['name' => 'hoadonnhap.danhsach', 'roles' => ['admin', 'thukho']],
            ['name' => 'hoadonnhap.them', 'roles' => ['admin', 'thukho']],
            ['name' => 'hoadonnhap.xem', 'params' => ['hoaDon' => $this->hoaDonNhap->id], 'roles' => ['admin', 'thukho']],
            ['name' => 'hoadonnhap.sua', 'params' => ['hoaDon' => $this->hoaDonNhap->id], 'roles' => ['admin', 'thukho']],
            ['name' => 'loaisanpham.goiy', 'roles' => ['admin', 'thukho']],
            ['name' => 'kiemke.index', 'roles' => ['admin', 'thukho']],
            ['name' => 'kiemke.create', 'roles' => ['admin', 'thukho']],
            ['name' => 'kiemke.show', 'params' => ['phieu' => $this->phieuKiemKe->id], 'roles' => ['admin', 'thukho']],
            ['name' => 'nhaphang.nhacungcap', 'roles' => ['admin', 'thukho']],
            ['name' => 'nhaphang.nhacungcap.them', 'roles' => ['admin', 'thukho']],
            ['name' => 'nhaphang.nhacungcap.sua', 'params' => ['nhaCungCap' => $this->nhaCungCap->id], 'roles' => ['admin', 'thukho']],
            ['name' => 'nhaphang.donnhap', 'roles' => ['admin', 'thukho']],
            ['name' => 'nhaphang.donnhap.them', 'roles' => ['admin', 'thukho']],
            ['name' => 'nhaphang.donnhap.chitiet', 'params' => ['donNhap' => $this->donNhap->id], 'roles' => ['admin', 'thukho']],
            ['name' => 'kho.danhsach', 'roles' => ['admin', 'thukho']],
            ['name' => 'kho.nhapkho', 'roles' => ['admin', 'thukho']],
            ['name' => 'kho.lichsu', 'roles' => ['admin', 'thukho']],
            ['name' => 'sanpham.danhsach', 'roles' => ['admin', 'thukho']],
            ['name' => 'sanpham.them', 'roles' => ['admin', 'thukho']],
            ['name' => 'sanpham.sua', 'params' => ['sanPham' => $this->sanPham->id], 'roles' => ['admin', 'thukho']],
            ['name' => 'sanpham.tem', 'params' => ['sanPham' => $this->sanPham->id], 'roles' => ['admin', 'thukho']],
            ['name' => 'loaisanpham.danhsach', 'roles' => ['admin', 'thukho']],
            ['name' => 'loaisanpham.them', 'roles' => ['admin', 'thukho']],
            ['name' => 'loaisanpham.sua', 'params' => ['loai' => $this->loai->id], 'roles' => ['admin', 'thukho']],
            ['name' => 'baocao.xuatnhapton', 'params' => ['dinhDang' => 'xlsx'], 'roles' => ['admin', 'thukho']],

            // Chỉ admin.
            ['name' => 'nhatky.index', 'roles' => ['admin']],
            ['name' => 'doisoat.index', 'roles' => ['admin']],
            ['name' => 'doisoat.show', 'params' => ['webhook' => $this->webhook->id], 'roles' => ['admin']],
            ['name' => 'thongke.index', 'roles' => ['admin']],
            ['name' => 'thongke.doanhthu', 'roles' => ['admin']],
            ['name' => 'thongke.topsanpham', 'roles' => ['admin']],
            ['name' => 'baocao.hoadon', 'params' => ['dinhDang' => 'xlsx'], 'roles' => ['admin']],

            // Tài khoản nhân viên (chỉ admin).
            ['name' => 'taikhoan.danhsach', 'roles' => ['admin']],
            ['name' => 'taikhoan.them', 'roles' => ['admin']],
            ['name' => 'taikhoan.sua', 'params' => ['user' => $this->nguoiDung['thungan']->id], 'roles' => ['admin']],

            // Đổi mật khẩu (mọi vai trò đã đăng nhập).
            ['name' => 'doi-mat-khau', 'guest' => 302, 'status' => 200, 'roles' => ['admin', 'thungan', 'thukho']],

            // Đăng xuất đặt cuối cùng: route này hủy phiên nên phải chạy sau các route cần đăng nhập.
            ['name' => 'auth.dangxuat', 'guest' => 302, 'status' => 302, 'roles' => ['admin', 'thungan', 'thukho']],
        ];
    }
}
