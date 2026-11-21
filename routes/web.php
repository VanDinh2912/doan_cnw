<?php

use Illuminate\Support\Facades\Route;

// Public storefront — no forced login.
Route::name('storefront.')->group(function (): void {
    Route::get('/', [\App\Http\Controllers\StorefrontController::class, 'home'])->name('home');
    Route::get('/danh-muc/{loaiSanPham}', [\App\Http\Controllers\StorefrontController::class, 'category'])->name('category');
    Route::get('/san-pham/{sanPham}', [\App\Http\Controllers\StorefrontController::class, 'product'])->name('product');
    Route::get('/tim-kiem', [\App\Http\Controllers\StorefrontController::class, 'search'])->name('search');

    Route::prefix('gio-hang')->name('cart.')->group(function (): void {
        Route::get('/', [\App\Http\Controllers\StorefrontCartController::class, 'index'])->name('index');
        Route::get('/them/{sanPham}', [\App\Http\Controllers\StorefrontCartController::class, 'add'])->name('add');
        Route::put('/cap-nhat', [\App\Http\Controllers\StorefrontCartController::class, 'update'])->name('update');
        Route::get('/xoa/{sanPham}', [\App\Http\Controllers\StorefrontCartController::class, 'remove'])->name('remove');
        Route::delete('/lam-moi', [\App\Http\Controllers\StorefrontCartController::class, 'clear'])->name('clear');
    });
});

// Tin tức phía khách (guest) — bài đã duyệt + đang hiện; bình luận phải đăng nhập.
Route::prefix('bai-viet')->name('tintuc.')->group(function (): void {
    Route::get('/', [\App\Http\Controllers\TinTucController::class, 'getDanhSach'])->name('danhsach');
    Route::get('/{chude_slug}', [\App\Http\Controllers\TinTucController::class, 'getTheoChuDe'])->name('chude');
    Route::get('/{chude_slug}/{bv_slug}', [\App\Http\Controllers\TinTucController::class, 'getChiTiet'])->name('chitiet');
    Route::post('/{chude_slug}/{bv_slug}/binh-luan', [\App\Http\Controllers\TinTucController::class, 'postBinhLuan'])
        ->middleware(['auth', 'throttle:5,1'])
        ->name('binhluan');
});

// Liên hệ / góp ý / khiếu nại / ứng tuyển phía khách — công khai, không bắt
// đăng nhập (kênh kháng nghị nên khách bị khóa vẫn gửi được). Honeypot +
// throttle giống form đặt hàng.
Route::get('/lien-he', [\App\Http\Controllers\LienHeController::class, 'getForm'])->name('lien-he.form');
Route::post('/lien-he', [\App\Http\Controllers\LienHeController::class, 'postGui'])
    ->middleware('throttle:5,1')
    ->name('lien-he.gui');
Route::get('/lien-he/cam-on', [\App\Http\Controllers\LienHeController::class, 'getCamOn'])->name('lien-he.cam-on');
Route::get('/tuyen-dung', [\App\Http\Controllers\LienHeController::class, 'getTuyenDung'])->name('tuyen-dung');
// Trang tĩnh footer — slug lạ 404; nội dung ở config/trang_tinh.php.
Route::get('/trang/{slug}', [\App\Http\Controllers\LienHeController::class, 'getTrangTinh'])
    ->where('slug', '[a-z0-9-]+')
    ->name('trang-tinh');

Route::get('/dangnhap', [\App\Http\Controllers\AuthController::class, 'getDangNhap'])
    ->name('auth.dangnhap');
Route::post('/dangnhap', [\App\Http\Controllers\AuthController::class, 'postDangNhap'])
    ->middleware('throttle:10,1')
    ->name('auth.postdangnhap');
Route::get('/dang-ky', [\App\Http\Controllers\AuthController::class, 'getDangKy'])
    ->name('auth.dangky');
Route::post('/dang-ky', [\App\Http\Controllers\AuthController::class, 'postDangKy'])
    ->name('auth.postdangky');
// Google OAuth — nút đăng nhập chỉ hiện khi đã điền GOOGLE_CLIENT_ID/SECRET trong .env.
Route::get('/auth/google', [\App\Http\Controllers\DangNhapGoogleController::class, 'getRedirect'])
    ->middleware('throttle:10,1')
    ->name('auth.google');
Route::get('/auth/google/callback', [\App\Http\Controllers\DangNhapGoogleController::class, 'getCallback'])
    ->middleware('throttle:10,1')
    ->name('auth.google.callback');
Route::get('/dangxuat', [\App\Http\Controllers\AuthController::class, 'getDangXuat'])
    ->middleware('auth')
    ->name('auth.dangxuat');

// Đổi mật khẩu (cho mọi vai trò đã đăng nhập, loại trừ khỏi middleware bat.buoc.doi.mat.khau)
Route::middleware(['auth'])->group(function (): void {
    Route::get('/doi-mat-khau', [\App\Http\Controllers\DoiMatKhauController::class, 'getForm'])->name('doi-mat-khau');
    Route::post('/doi-mat-khau', [\App\Http\Controllers\DoiMatKhauController::class, 'postDoi'])->name('doi-mat-khau.post');
});

Route::post('/api/webhook/thanhtoan', [\App\Http\Controllers\WebhookController::class, 'postThanhToan'])
    ->withoutMiddleware('web')
    ->middleware('throttle:60,1')
    ->name('webhook.thanhtoan');

// Đặt hàng online guest (P1/P2) — công khai, không bắt buộc đăng nhập.
// Cờ POS_DAT_HANG_ONLINE_BAT tắt → toàn bộ nhóm route trả 404.
Route::prefix('dat-hang')->name('dat-hang.')
    ->middleware('dat.hang.online')
    ->group(function (): void {
    Route::get('/', [\App\Http\Controllers\DatHangOnlineController::class, 'getForm'])->name('form');
    Route::post('/', [\App\Http\Controllers\DatHangOnlineController::class, 'postDat'])
        ->middleware('throttle:'.config('pos.online_throttle_dat', '5,1'))
        ->name('dat');
    Route::get('/qr/{ma_tra_cuu}', [\App\Http\Controllers\DatHangOnlineController::class, 'getQr'])->name('qr');
    Route::get('/thanh-cong/{ma_tra_cuu}', [\App\Http\Controllers\DatHangOnlineController::class, 'getThanhCong'])->name('thanhcong');
});

Route::middleware(['auth', 'khoa.luc', 'bat.buoc.doi.mat.khau'])->group(function (): void {
    Route::get('/thong-tin-ca-nhan', [\App\Http\Controllers\ThongTinCaNhanController::class, 'getProfile'])->name('profile.show');
    Route::post('/thong-tin-ca-nhan', [\App\Http\Controllers\ThongTinCaNhanController::class, 'postProfile'])->name('profile.update');
    Route::get('/thong-tin-ca-nhan/don-hang', [\App\Http\Controllers\ThongTinCaNhanController::class, 'getOrders'])->name('profile.orders');
    Route::get('/thong-tin-ca-nhan/don-hang/{donHang}', [\App\Http\Controllers\ThongTinCaNhanController::class, 'getOrderDetail'])->name('profile.orders.show');
});

Route::prefix('don-hang')->name('don-hang.')
    ->middleware('dat.hang.online')
    ->group(function (): void {
    Route::get('/tra-cuu', [\App\Http\Controllers\DatHangOnlineController::class, 'getTraCuu'])->name('tracuu');
    Route::post('/tra-cuu', [\App\Http\Controllers\DatHangOnlineController::class, 'postTraCuu'])
        ->middleware('throttle:'.config('pos.online_throttle_dat', '5,1'))
        ->name('posttracuu');
    // {ma_tra_cuu} phải đăng ký SAU /tra-cuu để không nuốt mất đường tra cứu.
    Route::get('/{ma_tra_cuu}', [\App\Http\Controllers\DatHangOnlineController::class, 'getTrangThai'])->name('trangthai');
    Route::post('/{ma_tra_cuu}/trang-thai', [\App\Http\Controllers\DatHangOnlineController::class, 'postPoll'])->name('poll');
    Route::post('/{ma_tra_cuu}/huy', [\App\Http\Controllers\DatHangOnlineController::class, 'postHuy'])->name('huy');
});

Route::middleware(['auth', 'staff.only', 'khoa.luc', 'bat.buoc.doi.mat.khau', 'bat.buoc.doi.pin'])->group(function (): void {
    Route::get('/tongquan', [\App\Http\Controllers\ThongKeController::class, 'getTongQuan'])->name('tongquan');

    // PIN xác nhận thao tác nhạy cảm (ngoại lệ: không gắn xac.nhan.pin)
    Route::get('/xac-nhan-pin', [\App\Http\Controllers\PinController::class, 'getNhap'])->name('pin.nhap');
    Route::post('/xac-nhan-pin/kiem-tra', [\App\Http\Controllers\PinController::class, 'postKiemTra'])
        ->middleware('throttle:20,1')->name('pin.kiemtra');
    Route::post('/xac-nhan-pin/xac-nhan', [\App\Http\Controllers\PinController::class, 'postXacNhan'])
        ->middleware('throttle:10,1')->name('pin.xacnhan');
    Route::get('/doi-pin', [\App\Http\Controllers\PinController::class, 'getDoiPin'])->name('pin.doi');
    Route::post('/doi-pin', [\App\Http\Controllers\PinController::class, 'postDoiPin'])
        ->middleware('throttle:10,1')->name('pin.doi.post');
    // NV tu xin cap/dat lai PIN (ghi ly do, admin duyet qua /taikhoan)
    Route::get('/pin/xin-cap', [\App\Http\Controllers\PinController::class, 'getXinCap'])->name('pin.xincap');
    Route::post('/pin/xin-cap', [\App\Http\Controllers\PinController::class, 'postXinCap'])
        ->middleware('throttle:10,1')->name('pin.xincap.post');
    // NV tu xem PIN tam admin vua cap — hien 1 lan, tu huy
    Route::get('/pin/tam-cua-toi', [\App\Http\Controllers\PinController::class, 'getTamCuaToi'])->name('pin.tam');
    Route::post('/taikhoan/dat-lai-pin/{user}', [\App\Http\Controllers\PinController::class, 'postDatLaiPin'])
        ->middleware(['role:admin', 'xac.nhan.pin:nghiemtrong', 'throttle:10,1'])->name('taikhoan.datlaipin');

    Route::get('/sanpham/timkiem', [\App\Http\Controllers\SanPhamController::class, 'getTimKiem'])
        ->middleware('role:admin,thungan')
        ->name('sanpham.timkiem');

    Route::middleware('role:admin,thungan')->group(function (): void {
        Route::get('/trahang/hoadon/{donHang}', [\App\Http\Controllers\TraHangController::class, 'getChon'])->name('trahang.chon');
        Route::post('/trahang', [\App\Http\Controllers\TraHangController::class, 'postTraHang'])->name('trahang.luu')->middleware('xac.nhan.pin:thuong');
        Route::get('/trahang', [\App\Http\Controllers\TraHangController::class, 'getDanhSach'])->name('trahang.danhsach');
        Route::get('/trahang/xem/{phieuTra}', [\App\Http\Controllers\TraHangController::class, 'getXem'])->name('trahang.xem');
        Route::get('/pos', [\App\Http\Controllers\DonHangController::class, 'getPos'])->name('pos.index');
        Route::get('/pos/vietqr', [\App\Http\Controllers\DonHangController::class, 'getVietQr'])->name('pos.vietqr');
        Route::post('/donhang/taoqr', [\App\Http\Controllers\DonHangController::class, 'postTaoQr'])->name('donhang.taoqr');
        Route::get('/donhang/trangthai/{donHang}', [\App\Http\Controllers\DonHangController::class, 'getTrangThai'])->name('donhang.trangthai');
        Route::post('/donhang/xacnhan/{donHang}', [\App\Http\Controllers\DonHangController::class, 'postXacNhanThanhToan'])->name('donhang.xacnhan')->middleware('xac.nhan.pin:thuong');
        Route::post('/donhang/huycho/{donHang}', [\App\Http\Controllers\DonHangController::class, 'postHuyCho'])->name('donhang.huycho');
        Route::post('/donhang/giahan/{donHang}', [\App\Http\Controllers\DonHangController::class, 'postGiaHan'])->name('donhang.giahan');
        Route::post('/hold', [\App\Http\Controllers\HoldController::class, 'giu'])->name('hold.giu');
        Route::get('/hold', [\App\Http\Controllers\HoldController::class, 'danhSach'])->name('hold.danhsach');
        Route::get('/hold/{gioTam}/khoiphuc', [\App\Http\Controllers\HoldController::class, 'khoiPhuc'])->name('hold.khoiphuc');
        Route::post('/hold/{gioTam}/xoa', [\App\Http\Controllers\HoldController::class, 'xoa'])->name('hold.xoa');
        Route::get('/giohang/them/{sanPham}', [\App\Http\Controllers\GioHangController::class, 'getThem'])
            ->name('giohang.them');
        Route::put('/giohang/capnhat', [\App\Http\Controllers\GioHangController::class, 'getCapNhat'])
            ->name('giohang.capnhat');
        Route::get('/giohang/xoa/{sanPham}', [\App\Http\Controllers\GioHangController::class, 'getXoa'])
            ->name('giohang.xoa');
        Route::delete('/giohang/lammoi', [\App\Http\Controllers\GioHangController::class, 'getLamMoi'])
            ->name('giohang.lammoi');
        Route::post('/donhang/thanhtoan', [\App\Http\Controllers\DonHangController::class, 'postThanhToan'])
            ->name('donhang.thanhtoan');
        Route::get('/donhang', [\App\Http\Controllers\DonHangController::class, 'getDanhSach'])
            ->name('donhang.danhsach');
        Route::get('/donhang/inhoadon/{donHang}', [\App\Http\Controllers\DonHangController::class, 'getInHoaDon'])
            ->name('donhang.inhoadon');
        Route::get('/khachhang/timkiem', [\App\Http\Controllers\KhachHangController::class, 'getTimKiem'])
            ->name('khachhang.timkiem');
        Route::post('/khachhang/nhanh', [\App\Http\Controllers\KhachHangController::class, 'postTaoNhanh'])
            ->name('khachhang.nhanh');
        Route::get('/khachhang', [\App\Http\Controllers\KhachHangController::class, 'getDanhSach'])
            ->name('khachhang.danhsach');
        Route::get('/khachhang/them', [\App\Http\Controllers\KhachHangController::class, 'getThem'])
            ->name('khachhang.them');
        Route::post('/khachhang/them', [\App\Http\Controllers\KhachHangController::class, 'postThem'])
            ->name('khachhang.postthem')->middleware('xac.nhan.pin:thuong');
        Route::get('/khachhang/sua/{khachHang}', [\App\Http\Controllers\KhachHangController::class, 'getSua'])
            ->name('khachhang.sua');
        Route::put('/khachhang/sua/{khachHang}', [\App\Http\Controllers\KhachHangController::class, 'postSua'])
            ->name('khachhang.postsua')->middleware('xac.nhan.pin:thuong');
        Route::delete('/khachhang/xoa/{khachHang}', [\App\Http\Controllers\KhachHangController::class, 'getXoa'])
            ->name('khachhang.xoa')->middleware('xac.nhan.pin:nghiemtrong');
        // Chi tiết hồ sơ khách (admin + thu ngân xem; read-only).
        // Đặt SAU mọi route literal để {khachHang} không nuốt timkiem/nhanh/them.
        Route::get('/khachhang/{khachHang}', [\App\Http\Controllers\KhachHangController::class, 'getChiTiet'])
            ->name('khachhang.chitiet');

        // Quản lý liên hệ/góp ý/khiếu nại/ứng tuyển — admin + thu ngân
        // (thủ kho bị chặn bởi nhóm role:admin,thungan). URI /lien-he/quan-ly
        // để không đụng form public /lien-he.
        Route::get('/lien-he/quan-ly', [\App\Http\Controllers\LienHeAdminController::class, 'getDanhSach'])
            ->name('lienhe.danhsach');
        Route::get('/lien-he/quan-ly/{lienHe}', [\App\Http\Controllers\LienHeAdminController::class, 'getChiTiet'])
            ->name('lienhe.chitiet');
        Route::post('/lien-he/quan-ly/{lienHe}/cap-nhat', [\App\Http\Controllers\LienHeAdminController::class, 'postCapNhat'])
            ->name('lienhe.capnhat')->middleware('xac.nhan.pin:thuong');
    });

    // Khóa/mở khóa hồ sơ khách — CHỈ admin (server chặn, không chỉ ẩn nút).
    Route::middleware('role:admin')->group(function (): void {
        Route::post('/khachhang/khoa/{khachHang}', [\App\Http\Controllers\KhachHangController::class, 'postKhoa'])
            ->name('khachhang.khoa')->middleware('xac.nhan.pin:nghiemtrong');
        Route::post('/khachhang/mo-khoa/{khachHang}', [\App\Http\Controllers\KhachHangController::class, 'postMoKhoa'])
            ->name('khachhang.mokhoa')->middleware('xac.nhan.pin:nghiemtrong');
        Route::post('/khachhang/dat-lai-mat-khau/{khachHang}', [\App\Http\Controllers\KhachHangController::class, 'postDatLaiMatKhau'])
            ->middleware('throttle:10,1')
            ->name('khachhang.datlaimatkhau')->middleware('xac.nhan.pin:nghiemtrong');
    });

    // P3: màn hình nhân viên xử lý đơn online (admin/thu ngân/thủ kho) — ngoài nhóm role:admin,thungan.
    Route::middleware('role:admin,thungan,thukho')->group(function (): void {
        Route::get('/donhang-online/so-luong', [\App\Http\Controllers\DonHangOnlineController::class, 'getSoLuongCanXuLy'])
            ->name('donhangonline.soluong');
        Route::get('/donhang-online', [\App\Http\Controllers\DonHangOnlineController::class, 'getDanhSach'])
            ->name('donhangonline.danhsach');
        Route::get('/donhang-online/{donHang}', [\App\Http\Controllers\DonHangOnlineController::class, 'getChiTiet'])
            ->name('donhangonline.chitiet');
        Route::post('/donhang-online/{donHang}/trangthai', [\App\Http\Controllers\DonHangOnlineController::class, 'postChuyenTrangThai'])
            ->name('donhangonline.trangthai')->middleware('xac.nhan.pin:thuong');
        // Xác nhận khách đã nhận hàng (pickup) — admin + thu ngân, PIN mức thường
        Route::post('/donhang-online/{donHang}/nhan-hang', [\App\Http\Controllers\DonHangOnlineController::class, 'postNhanHang'])
            ->middleware('role:admin,thungan')
            ->name('donhangonline.nhanhang')->middleware('xac.nhan.pin:thuong');
        // HM4: hủy đơn đã thanh toán + hoàn tiền thủ công — chỉ admin
        Route::post('/donhang-online/{donHang}/huy-da-thanh-toan', [\App\Http\Controllers\DonHangOnlineController::class, 'postHuyDonDaThanhToan'])
            ->middleware('role:admin')
            ->name('donhangonline.huydathanhtoan')->middleware('xac.nhan.pin:nghiemtrong');
        Route::get('/donhang-online-can-hoan-tien', [\App\Http\Controllers\DonHangOnlineController::class, 'getDonCanHoanTien'])
            ->middleware('role:admin')
            ->name('donhangonline.canhoantien');
        Route::post('/donhang-online/{donHang}/da-hoan-tien', [\App\Http\Controllers\DonHangOnlineController::class, 'postDanhDauDaHoanTien'])
            ->middleware('role:admin')
            ->name('donhangonline.dahoantien')->middleware('xac.nhan.pin:nghiemtrong');
        Route::get('/donhang-online/{donHang}/phieu-giao', [\App\Http\Controllers\DonHangOnlineController::class, 'getPhieuGiao'])
            ->name('donhangonline.phieugiaohang');

        // Hãng sản xuất: cả 3 vai trò xem; chỉ admin thêm/sửa/xóa (chặn server).
        Route::get('/hangsxuat', [\App\Http\Controllers\HangsxuatController::class, 'getDanhSach'])
            ->name('hangsxuat.danhsach');
        // Export Excel hãng: admin + thủ kho (thu ngân bị chặn bởi route role).
        Route::get('/hangsxuat/xuat-excel', [\App\Http\Controllers\HangsxuatController::class, 'getXuatExcel'])
            ->middleware('role:admin,thukho')
            ->name('hangsxuat.xuatexcel')->middleware('xac.nhan.pin:thuong');
        Route::middleware('role:admin')->group(function (): void {
            Route::get('/hangsxuat/them', [\App\Http\Controllers\HangsxuatController::class, 'getThem'])
                ->name('hangsxuat.them');
            Route::post('/hangsxuat/them', [\App\Http\Controllers\HangsxuatController::class, 'postThem'])
                ->name('hangsxuat.postthem')->middleware('xac.nhan.pin:thuong');
            Route::get('/hangsxuat/sua/{hang}', [\App\Http\Controllers\HangsxuatController::class, 'getSua'])
                ->name('hangsxuat.sua');
            Route::put('/hangsxuat/sua/{hang}', [\App\Http\Controllers\HangsxuatController::class, 'postSua'])
                ->name('hangsxuat.postsua')->middleware('xac.nhan.pin:thuong');
            Route::delete('/hangsxuat/xoa/{hang}', [\App\Http\Controllers\HangsxuatController::class, 'getXoa'])
                ->name('hangsxuat.xoa')->middleware('xac.nhan.pin:nghiemtrong');
        });
    });

    Route::middleware('role:admin,thukho')->group(function (): void {
        // Quản lý tài khoản nhân viên (chỉ admin)
        Route::prefix('taikhoan')->name('taikhoan.')->middleware('role:admin')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\TaiKhoanNhanVienController::class, 'index'])->name('danhsach');
            Route::get('/them', [\App\Http\Controllers\TaiKhoanNhanVienController::class, 'create'])->name('them');
            Route::post('/', [\App\Http\Controllers\TaiKhoanNhanVienController::class, 'store'])->name('store')->middleware('xac.nhan.pin:nghiemtrong');
            Route::get('/sua/{user}', [\App\Http\Controllers\TaiKhoanNhanVienController::class, 'edit'])->name('sua');
            Route::put('/sua/{user}', [\App\Http\Controllers\TaiKhoanNhanVienController::class, 'update'])->name('update')->middleware('xac.nhan.pin:nghiemtrong');
            Route::post('/khoa/{user}', [\App\Http\Controllers\TaiKhoanNhanVienController::class, 'khoa'])->name('khoa')->middleware('xac.nhan.pin:nghiemtrong');
            Route::post('/mo-khoa/{user}', [\App\Http\Controllers\TaiKhoanNhanVienController::class, 'moKhoa'])->name('mokhoa')->middleware('xac.nhan.pin:nghiemtrong');
            Route::post('/dat-lai-mat-khau/{user}', [\App\Http\Controllers\TaiKhoanNhanVienController::class, 'datLaiMatKhau'])->name('datlaimatkhau')->middleware('xac.nhan.pin:nghiemtrong');
        });
        Route::get('/hoadonnhap', [\App\Http\Controllers\HoaDonNhapController::class, 'getDanhSach'])->name('hoadonnhap.danhsach');
        Route::get('/hoadonnhap/them', [\App\Http\Controllers\HoaDonNhapController::class, 'getThem'])->name('hoadonnhap.them');
        Route::post('/hoadonnhap/them', [\App\Http\Controllers\HoaDonNhapController::class, 'postThem'])->name('hoadonnhap.postthem')->middleware('xac.nhan.pin:thuong');
        Route::post('/hoadonnhap/boc-tach-pdf', [\App\Http\Controllers\HoaDonNhapController::class, 'postBocTachPdf'])->name('hoadonnhap.boctachpdf')->middleware('xac.nhan.pin:thuong');
        Route::get('/hoadonnhap/xem/{hoaDon}', [\App\Http\Controllers\HoaDonNhapController::class, 'getXem'])->name('hoadonnhap.xem');
        Route::get('/hoadonnhap/sua/{hoaDon}', [\App\Http\Controllers\HoaDonNhapController::class, 'getSua'])->name('hoadonnhap.sua');
        Route::post('/hoadonnhap/sua/{hoaDon}', [\App\Http\Controllers\HoaDonNhapController::class, 'postSua'])->name('hoadonnhap.postsua')->middleware('xac.nhan.pin:thuong');
        Route::post('/hoadonnhap/xacnhan/{hoaDon}', [\App\Http\Controllers\HoaDonNhapController::class, 'postXacNhan'])->name('hoadonnhap.xacnhan')->middleware('xac.nhan.pin:nghiemtrong');
        Route::post('/hoadonnhap/huy/{hoaDon}', [\App\Http\Controllers\HoaDonNhapController::class, 'postHuy'])->name('hoadonnhap.huy')->middleware('xac.nhan.pin:nghiemtrong');
        Route::get('/loaisanpham/goiy', [\App\Http\Controllers\LoaiSanPhamController::class, 'getGoiY'])->name('loaisanpham.goiy');
        Route::get('/kiemke', [\App\Http\Controllers\KiemKeController::class, 'index'])->name('kiemke.index');
        Route::get('/kiemke/them', [\App\Http\Controllers\KiemKeController::class, 'create'])->name('kiemke.create');
        Route::post('/kiemke', [\App\Http\Controllers\KiemKeController::class, 'store'])->name('kiemke.store')->middleware('xac.nhan.pin:thuong');
        Route::get('/kiemke/{phieu}', [\App\Http\Controllers\KiemKeController::class, 'show'])->name('kiemke.show');
        Route::post('/kiemke/{phieu}/gui', [\App\Http\Controllers\KiemKeController::class, 'submit'])->name('kiemke.submit')->middleware('xac.nhan.pin:thuong');
        Route::get('/nhaphang/nhacungcap', [\App\Http\Controllers\NhapHangController::class, 'getNhaCungCap'])->name('nhaphang.nhacungcap');
        Route::get('/nhaphang/nhacungcap/them', [\App\Http\Controllers\NhapHangController::class, 'getThemNhaCungCap'])->name('nhaphang.nhacungcap.them');
        Route::post('/nhaphang/nhacungcap/them', [\App\Http\Controllers\NhapHangController::class, 'postThemNhaCungCap'])->name('nhaphang.nhacungcap.postthem')->middleware('xac.nhan.pin:thuong');
        Route::get('/nhaphang/nhacungcap/sua/{nhaCungCap}', [\App\Http\Controllers\NhapHangController::class, 'getSuaNhaCungCap'])->name('nhaphang.nhacungcap.sua');
        Route::put('/nhaphang/nhacungcap/sua/{nhaCungCap}', [\App\Http\Controllers\NhapHangController::class, 'postSuaNhaCungCap'])->name('nhaphang.nhacungcap.postsua')->middleware('xac.nhan.pin:thuong');
        Route::delete('/nhaphang/nhacungcap/xoa/{nhaCungCap}', [\App\Http\Controllers\NhapHangController::class, 'xoaNhaCungCap'])->name('nhaphang.nhacungcap.xoa')->middleware('xac.nhan.pin:nghiemtrong');
        Route::get('/nhaphang/donnhap', [\App\Http\Controllers\NhapHangController::class, 'getDonNhap'])->name('nhaphang.donnhap');
        Route::get('/nhaphang/donnhap/them', [\App\Http\Controllers\NhapHangController::class, 'getThemDonNhap'])->name('nhaphang.donnhap.them');
        Route::post('/nhaphang/donnhap/them', [\App\Http\Controllers\NhapHangController::class, 'postThemDonNhap'])->name('nhaphang.donnhap.postthem')->middleware('xac.nhan.pin:thuong');
        Route::get('/nhaphang/donnhap/{donNhap}', [\App\Http\Controllers\NhapHangController::class, 'getChiTietDonNhap'])->name('nhaphang.donnhap.chitiet');
        Route::post('/nhaphang/donnhap/{donNhap}/trangthai', [\App\Http\Controllers\NhapHangController::class, 'capNhatTrangThai'])->name('nhaphang.donnhap.trangthai')->middleware('xac.nhan.pin:nghiemtrong');
        Route::get('/kho', [\App\Http\Controllers\KhoController::class, 'getDanhSach'])->name('kho.danhsach');
        Route::get('/kho/nhap', [\App\Http\Controllers\KhoController::class, 'getNhapKho'])->name('kho.nhapkho');
        Route::post('/kho/nhap', [\App\Http\Controllers\KhoController::class, 'postNhapKho'])->name('kho.postnhapkho')->middleware('xac.nhan.pin:thuong');
        Route::get('/kho/lichsu', [\App\Http\Controllers\KhoController::class, 'getLichSu'])->name('kho.lichsu');
        Route::get('/sanpham', [\App\Http\Controllers\SanPhamController::class, 'getDanhSach'])
            ->name('sanpham.danhsach');
        Route::get('/sanpham/them', [\App\Http\Controllers\SanPhamController::class, 'getThem'])
            ->name('sanpham.them');
        Route::post('/sanpham/them', [\App\Http\Controllers\SanPhamController::class, 'postThem'])
            ->name('sanpham.postthem')->middleware('xac.nhan.pin:thuong');
        Route::get('/sanpham/sua/{sanPham}', [\App\Http\Controllers\SanPhamController::class, 'getSua'])
            ->name('sanpham.sua');
        Route::get('/sanpham/tem/{sanPham}', [\App\Http\Controllers\SanPhamController::class, 'getTem'])
            ->name('sanpham.tem');
        Route::put('/sanpham/sua/{sanPham}', [\App\Http\Controllers\SanPhamController::class, 'postSua'])
            ->name('sanpham.postsua')->middleware('xac.nhan.pin:thuong');
        Route::delete('/sanpham/xoa/{sanPham}', [\App\Http\Controllers\SanPhamController::class, 'getXoa'])
            ->name('sanpham.xoa')->middleware('xac.nhan.pin:nghiemtrong');
        // Export san pham ra Excel: admin + thu kho (nhom route da gioi han).
        Route::get('/sanpham/xuat-excel', [\App\Http\Controllers\SanPhamExcelController::class, 'getXuatExcel'])
            ->name('sanpham.xuatexcel')->middleware('xac.nhan.pin:thuong');
        // Import san pham 2 buoc (upload -> preview -> xac nhan): CHI admin.
        Route::middleware('role:admin')->group(function (): void {
            Route::get('/sanpham/import-mau', [\App\Http\Controllers\SanPhamExcelController::class, 'getFileMau'])
                ->name('sanpham.importmau');
            Route::get('/sanpham/import-excel', [\App\Http\Controllers\SanPhamExcelController::class, 'getForm'])
                ->name('sanpham.import');
            Route::post('/sanpham/import-excel', [\App\Http\Controllers\SanPhamExcelController::class, 'postUpload'])
                ->middleware('throttle:10,1')
                ->name('sanpham.import.upload');
            Route::post('/sanpham/import-excel/xac-nhan', [\App\Http\Controllers\SanPhamExcelController::class, 'postXacNhan'])
                ->middleware('throttle:10,1')
                ->name('sanpham.import.xacnhan')->middleware('xac.nhan.pin:nghiemtrong');
        });
        Route::get('/loaisanpham', [\App\Http\Controllers\LoaiSanPhamController::class, 'getDanhSach'])
            ->name('loaisanpham.danhsach');
        Route::get('/loaisanpham/them', [\App\Http\Controllers\LoaiSanPhamController::class, 'getThem'])
            ->name('loaisanpham.them');
        Route::post('/loaisanpham/them', [\App\Http\Controllers\LoaiSanPhamController::class, 'postThem'])
            ->name('loaisanpham.postthem')->middleware('xac.nhan.pin:thuong');
        Route::get('/loaisanpham/sua/{loai}', [\App\Http\Controllers\LoaiSanPhamController::class, 'getSua'])
            ->name('loaisanpham.sua');
        Route::put('/loaisanpham/sua/{loai}', [\App\Http\Controllers\LoaiSanPhamController::class, 'postSua'])
            ->name('loaisanpham.postsua')->middleware('xac.nhan.pin:thuong');
        Route::delete('/loaisanpham/xoa/{loai}', [\App\Http\Controllers\LoaiSanPhamController::class, 'getXoa'])
            ->name('loaisanpham.xoa')->middleware('xac.nhan.pin:nghiemtrong');
    });

    // So lieu tong quan (JSON poll) — moi nhan vien staff deu xem duoc
    // (trang /tongquan da render cung so lieu phia server)
    Route::get('/tong-quan/so-lieu', [\App\Http\Controllers\ThongKeController::class, 'getSoLieuTongQuan'])
        ->middleware('throttle:30,1')
        ->name('tongquan.solieu');

    Route::middleware('role:admin')->group(function (): void {
        Route::post('/kiemke/{phieu}/duyet', [\App\Http\Controllers\KiemKeController::class, 'approve'])->name('kiemke.approve')->middleware('xac.nhan.pin:nghiemtrong');
        Route::get('/nhatky', [\App\Http\Controllers\NhatKyController::class, 'index'])->name('nhatky.index');
        Route::get('/nhatky/xuat-csv', [\App\Http\Controllers\NhatKyController::class, 'export'])->name('nhatky.export')->middleware('xac.nhan.pin:nghiemtrong');
        Route::get('/doisoat', [\App\Http\Controllers\DoiSoatController::class, 'index'])->name('doisoat.index');
        Route::get('/doisoat/{webhook}', [\App\Http\Controllers\DoiSoatController::class, 'show'])->name('doisoat.show');
        Route::post('/doisoat/{webhook}/xuly', [\App\Http\Controllers\DoiSoatController::class, 'xuLy'])->name('doisoat.xuly')->middleware('xac.nhan.pin:nghiemtrong');
        Route::post('/trahang/duyet/{phieuTra}', [\App\Http\Controllers\TraHangController::class, 'postDuyet'])->name('trahang.duyet')->middleware('xac.nhan.pin:nghiemtrong');
        Route::get('/thongke', [\App\Http\Controllers\ThongKeController::class, 'getTrangThongKe'])
            ->name('thongke.index');
        Route::get('/thongke/doanhthu', [\App\Http\Controllers\ThongKeController::class, 'getDoanhThu'])->name('thongke.doanhthu');
        Route::get('/thongke/chua-co-gia-von', [\App\Http\Controllers\ThongKeController::class, 'getDongChuaCoGiaVon'])->name('thongke.chuagivon');
        Route::get('/thongke/topsanpham', [\App\Http\Controllers\ThongKeController::class, 'getTopSanPham'])->name('thongke.topsanpham');
        Route::get('/baocao/hoadon/{dinhDang}', [\App\Http\Controllers\BaoCaoController::class, 'getHoaDon'])->name('baocao.hoadon')->middleware('xac.nhan.pin:nghiemtrong');

        // Quản trị tin tức: chủ đề, bài viết, bình luận (chỉ admin).
        Route::prefix('tin-tuc')->group(function (): void {
            Route::get('/chu-de', [\App\Http\Controllers\ChuDeController::class, 'getDanhSach'])->name('chude.danhsach');
            Route::get('/chu-de/them', [\App\Http\Controllers\ChuDeController::class, 'getThem'])->name('chude.them');
            Route::post('/chu-de/them', [\App\Http\Controllers\ChuDeController::class, 'postThem'])->name('chude.postthem')->middleware('xac.nhan.pin:thuong');
            Route::get('/chu-de/sua/{chuDe}', [\App\Http\Controllers\ChuDeController::class, 'getSua'])->name('chude.sua');
            Route::put('/chu-de/sua/{chuDe}', [\App\Http\Controllers\ChuDeController::class, 'postSua'])->name('chude.postsua')->middleware('xac.nhan.pin:thuong');
            Route::delete('/chu-de/xoa/{chuDe}', [\App\Http\Controllers\ChuDeController::class, 'getXoa'])->name('chude.xoa')->middleware('xac.nhan.pin:nghiemtrong');

            Route::get('/bai-viet', [\App\Http\Controllers\BaiVietController::class, 'getDanhSach'])->name('baiviet.danhsach');
            Route::get('/bai-viet/them', [\App\Http\Controllers\BaiVietController::class, 'getThem'])->name('baiviet.them');
            Route::post('/bai-viet/them', [\App\Http\Controllers\BaiVietController::class, 'postThem'])->name('baiviet.postthem')->middleware('xac.nhan.pin:thuong');
            Route::get('/bai-viet/sua/{baiViet}', [\App\Http\Controllers\BaiVietController::class, 'getSua'])->name('baiviet.sua');
            Route::put('/bai-viet/sua/{baiViet}', [\App\Http\Controllers\BaiVietController::class, 'postSua'])->name('baiviet.postsua')->middleware('xac.nhan.pin:thuong');
            Route::post('/bai-viet/duyet/{baiViet}', [\App\Http\Controllers\BaiVietController::class, 'postDuyet'])->name('baiviet.duyet')->middleware('xac.nhan.pin:thuong');
            Route::post('/bai-viet/kich-hoat/{baiViet}', [\App\Http\Controllers\BaiVietController::class, 'postKichHoat'])->name('baiviet.kichhoat')->middleware('xac.nhan.pin:thuong');
            Route::delete('/bai-viet/xoa/{baiViet}', [\App\Http\Controllers\BaiVietController::class, 'getXoa'])->name('baiviet.xoa')->middleware('xac.nhan.pin:nghiemtrong');

            Route::get('/binh-luan', [\App\Http\Controllers\BinhLuanBaiVietController::class, 'getDanhSach'])->name('binhluan.danhsach');
            Route::post('/binh-luan/duyet/{binhLuan}', [\App\Http\Controllers\BinhLuanBaiVietController::class, 'postDuyet'])->name('binhluan.duyet')->middleware('xac.nhan.pin:thuong');
            Route::post('/binh-luan/an/{binhLuan}', [\App\Http\Controllers\BinhLuanBaiVietController::class, 'postAn'])->name('binhluan.an')->middleware('xac.nhan.pin:thuong');
            Route::delete('/binh-luan/xoa/{binhLuan}', [\App\Http\Controllers\BinhLuanBaiVietController::class, 'getXoa'])->name('binhluan.xoa')->middleware('xac.nhan.pin:nghiemtrong');
        });
    });

    Route::get('/baocao/xuatnhapton/{dinhDang}', [\App\Http\Controllers\BaoCaoController::class, 'getXuatNhapTon'])
        ->middleware('role:admin,thukho')
        ->name('baocao.xuatnhapton')->middleware('xac.nhan.pin:thuong');
});
