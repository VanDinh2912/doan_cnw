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

Route::get('/dangnhap', [\App\Http\Controllers\AuthController::class, 'getDangNhap'])
    ->name('auth.dangnhap');
Route::post('/dangnhap', [\App\Http\Controllers\AuthController::class, 'postDangNhap'])
    ->middleware('throttle:10,1')
    ->name('auth.postdangnhap');
Route::get('/dang-ky', [\App\Http\Controllers\AuthController::class, 'getDangKy'])
    ->name('auth.dangky');
Route::post('/dang-ky', [\App\Http\Controllers\AuthController::class, 'postDangKy'])
    ->name('auth.postdangky');
Route::get('/dangxuat', [\App\Http\Controllers\AuthController::class, 'getDangXuat'])
    ->middleware('auth')
    ->name('auth.dangxuat');

Route::post('/api/webhook/thanhtoan', [\App\Http\Controllers\WebhookController::class, 'postThanhToan'])
    ->withoutMiddleware('web')
    ->middleware('throttle:60,1')
    ->name('webhook.thanhtoan');

// Đặt hàng online guest pickup (P1) — công khai, không bắt buộc đăng nhập.
Route::prefix('dat-hang')->name('dat-hang.')->group(function (): void {
    Route::get('/', [\App\Http\Controllers\DatHangOnlineController::class, 'getForm'])->name('form');
    Route::post('/', [\App\Http\Controllers\DatHangOnlineController::class, 'postDat'])
        ->middleware('throttle:'.config('pos.online_throttle_dat', '5,1'))
        ->name('dat');
    Route::get('/qr/{ma_tra_cuu}', [\App\Http\Controllers\DatHangOnlineController::class, 'getQr'])->name('qr');
    Route::get('/thanh-cong/{ma_tra_cuu}', [\App\Http\Controllers\DatHangOnlineController::class, 'getThanhCong'])->name('thanhcong');
});

Route::middleware(['auth'])->group(function (): void {
    Route::get('/thong-tin-ca-nhan', [\App\Http\Controllers\ThongTinCaNhanController::class, 'getProfile'])->name('profile.show');
    Route::post('/thong-tin-ca-nhan', [\App\Http\Controllers\ThongTinCaNhanController::class, 'postProfile'])->name('profile.update');
    Route::get('/thong-tin-ca-nhan/don-hang', [\App\Http\Controllers\ThongTinCaNhanController::class, 'getOrders'])->name('profile.orders');
});

Route::prefix('don-hang')->name('don-hang.')->group(function (): void {
    Route::get('/tra-cuu', [\App\Http\Controllers\DatHangOnlineController::class, 'getTraCuu'])->name('tracuu');
    Route::post('/tra-cuu', [\App\Http\Controllers\DatHangOnlineController::class, 'postTraCuu'])
        ->middleware('throttle:'.config('pos.online_throttle_dat', '5,1'))
        ->name('posttracuu');
    // {ma_tra_cuu} phải đăng ký SAU /tra-cuu để không nuốt mất đường tra cứu.
    Route::get('/{ma_tra_cuu}', [\App\Http\Controllers\DatHangOnlineController::class, 'getTrangThai'])->name('trangthai');
    Route::post('/{ma_tra_cuu}/trang-thai', [\App\Http\Controllers\DatHangOnlineController::class, 'postPoll'])->name('poll');
    Route::post('/{ma_tra_cuu}/huy', [\App\Http\Controllers\DatHangOnlineController::class, 'postHuy'])->name('huy');
});

Route::middleware(['auth', 'staff.only'])->group(function (): void {
    Route::get('/tongquan', [\App\Http\Controllers\ThongKeController::class, 'getTongQuan'])->name('tongquan');

    Route::get('/sanpham/timkiem', [\App\Http\Controllers\SanPhamController::class, 'getTimKiem'])
        ->middleware('role:admin,thungan')
        ->name('sanpham.timkiem');

    Route::middleware('role:admin,thungan')->group(function (): void {
        Route::get('/trahang/hoadon/{donHang}', [\App\Http\Controllers\TraHangController::class, 'getChon'])->name('trahang.chon');
        Route::post('/trahang', [\App\Http\Controllers\TraHangController::class, 'postTraHang'])->name('trahang.luu');
        Route::get('/trahang', [\App\Http\Controllers\TraHangController::class, 'getDanhSach'])->name('trahang.danhsach');
        Route::get('/trahang/xem/{phieuTra}', [\App\Http\Controllers\TraHangController::class, 'getXem'])->name('trahang.xem');
        Route::get('/pos', [\App\Http\Controllers\DonHangController::class, 'getPos'])->name('pos.index');
        Route::get('/pos/vietqr', [\App\Http\Controllers\DonHangController::class, 'getVietQr'])->name('pos.vietqr');
        Route::post('/donhang/taoqr', [\App\Http\Controllers\DonHangController::class, 'postTaoQr'])->name('donhang.taoqr');
        Route::get('/donhang/trangthai/{donHang}', [\App\Http\Controllers\DonHangController::class, 'getTrangThai'])->name('donhang.trangthai');
        Route::post('/donhang/xacnhan/{donHang}', [\App\Http\Controllers\DonHangController::class, 'postXacNhanThanhToan'])->name('donhang.xacnhan');
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
            ->name('khachhang.postthem');
        Route::get('/khachhang/sua/{khachHang}', [\App\Http\Controllers\KhachHangController::class, 'getSua'])
            ->name('khachhang.sua');
        Route::put('/khachhang/sua/{khachHang}', [\App\Http\Controllers\KhachHangController::class, 'postSua'])
            ->name('khachhang.postsua');
        Route::delete('/khachhang/xoa/{khachHang}', [\App\Http\Controllers\KhachHangController::class, 'getXoa'])
            ->name('khachhang.xoa');
    });

    Route::middleware('role:admin,thukho')->group(function (): void {
        Route::get('/hoadonnhap', [\App\Http\Controllers\HoaDonNhapController::class, 'getDanhSach'])->name('hoadonnhap.danhsach');
        Route::get('/hoadonnhap/them', [\App\Http\Controllers\HoaDonNhapController::class, 'getThem'])->name('hoadonnhap.them');
        Route::post('/hoadonnhap/them', [\App\Http\Controllers\HoaDonNhapController::class, 'postThem'])->name('hoadonnhap.postthem');
        Route::post('/hoadonnhap/boc-tach-pdf', [\App\Http\Controllers\HoaDonNhapController::class, 'postBocTachPdf'])->name('hoadonnhap.boctachpdf');
        Route::get('/hoadonnhap/xem/{hoaDon}', [\App\Http\Controllers\HoaDonNhapController::class, 'getXem'])->name('hoadonnhap.xem');
        Route::get('/hoadonnhap/sua/{hoaDon}', [\App\Http\Controllers\HoaDonNhapController::class, 'getSua'])->name('hoadonnhap.sua');
        Route::post('/hoadonnhap/sua/{hoaDon}', [\App\Http\Controllers\HoaDonNhapController::class, 'postSua'])->name('hoadonnhap.postsua');
        Route::post('/hoadonnhap/xacnhan/{hoaDon}', [\App\Http\Controllers\HoaDonNhapController::class, 'postXacNhan'])->name('hoadonnhap.xacnhan');
        Route::post('/hoadonnhap/huy/{hoaDon}', [\App\Http\Controllers\HoaDonNhapController::class, 'postHuy'])->name('hoadonnhap.huy');
        Route::get('/loaisanpham/goiy', [\App\Http\Controllers\LoaiSanPhamController::class, 'getGoiY'])->name('loaisanpham.goiy');
        Route::get('/kiemke', [\App\Http\Controllers\KiemKeController::class, 'index'])->name('kiemke.index');
        Route::get('/kiemke/them', [\App\Http\Controllers\KiemKeController::class, 'create'])->name('kiemke.create');
        Route::post('/kiemke', [\App\Http\Controllers\KiemKeController::class, 'store'])->name('kiemke.store');
        Route::get('/kiemke/{phieu}', [\App\Http\Controllers\KiemKeController::class, 'show'])->name('kiemke.show');
        Route::post('/kiemke/{phieu}/gui', [\App\Http\Controllers\KiemKeController::class, 'submit'])->name('kiemke.submit');
        Route::get('/nhaphang/nhacungcap', [\App\Http\Controllers\NhapHangController::class, 'getNhaCungCap'])->name('nhaphang.nhacungcap');
        Route::get('/nhaphang/nhacungcap/them', [\App\Http\Controllers\NhapHangController::class, 'getThemNhaCungCap'])->name('nhaphang.nhacungcap.them');
        Route::post('/nhaphang/nhacungcap/them', [\App\Http\Controllers\NhapHangController::class, 'postThemNhaCungCap'])->name('nhaphang.nhacungcap.postthem');
        Route::get('/nhaphang/nhacungcap/sua/{nhaCungCap}', [\App\Http\Controllers\NhapHangController::class, 'getSuaNhaCungCap'])->name('nhaphang.nhacungcap.sua');
        Route::put('/nhaphang/nhacungcap/sua/{nhaCungCap}', [\App\Http\Controllers\NhapHangController::class, 'postSuaNhaCungCap'])->name('nhaphang.nhacungcap.postsua');
        Route::delete('/nhaphang/nhacungcap/xoa/{nhaCungCap}', [\App\Http\Controllers\NhapHangController::class, 'xoaNhaCungCap'])->name('nhaphang.nhacungcap.xoa');
        Route::get('/nhaphang/donnhap', [\App\Http\Controllers\NhapHangController::class, 'getDonNhap'])->name('nhaphang.donnhap');
        Route::get('/nhaphang/donnhap/them', [\App\Http\Controllers\NhapHangController::class, 'getThemDonNhap'])->name('nhaphang.donnhap.them');
        Route::post('/nhaphang/donnhap/them', [\App\Http\Controllers\NhapHangController::class, 'postThemDonNhap'])->name('nhaphang.donnhap.postthem');
        Route::get('/nhaphang/donnhap/{donNhap}', [\App\Http\Controllers\NhapHangController::class, 'getChiTietDonNhap'])->name('nhaphang.donnhap.chitiet');
        Route::post('/nhaphang/donnhap/{donNhap}/trangthai', [\App\Http\Controllers\NhapHangController::class, 'capNhatTrangThai'])->name('nhaphang.donnhap.trangthai');
        Route::get('/kho', [\App\Http\Controllers\KhoController::class, 'getDanhSach'])->name('kho.danhsach');
        Route::get('/kho/nhap', [\App\Http\Controllers\KhoController::class, 'getNhapKho'])->name('kho.nhapkho');
        Route::post('/kho/nhap', [\App\Http\Controllers\KhoController::class, 'postNhapKho'])->name('kho.postnhapkho');
        Route::get('/kho/lichsu', [\App\Http\Controllers\KhoController::class, 'getLichSu'])->name('kho.lichsu');
        Route::get('/sanpham', [\App\Http\Controllers\SanPhamController::class, 'getDanhSach'])
            ->name('sanpham.danhsach');
        Route::get('/sanpham/them', [\App\Http\Controllers\SanPhamController::class, 'getThem'])
            ->name('sanpham.them');
        Route::post('/sanpham/them', [\App\Http\Controllers\SanPhamController::class, 'postThem'])
            ->name('sanpham.postthem');
        Route::get('/sanpham/sua/{sanPham}', [\App\Http\Controllers\SanPhamController::class, 'getSua'])
            ->name('sanpham.sua');
        Route::get('/sanpham/tem/{sanPham}', [\App\Http\Controllers\SanPhamController::class, 'getTem'])
            ->name('sanpham.tem');
        Route::put('/sanpham/sua/{sanPham}', [\App\Http\Controllers\SanPhamController::class, 'postSua'])
            ->name('sanpham.postsua');
        Route::delete('/sanpham/xoa/{sanPham}', [\App\Http\Controllers\SanPhamController::class, 'getXoa'])
            ->name('sanpham.xoa');
        Route::get('/loaisanpham', [\App\Http\Controllers\LoaiSanPhamController::class, 'getDanhSach'])
            ->name('loaisanpham.danhsach');
        Route::get('/loaisanpham/them', [\App\Http\Controllers\LoaiSanPhamController::class, 'getThem'])
            ->name('loaisanpham.them');
        Route::post('/loaisanpham/them', [\App\Http\Controllers\LoaiSanPhamController::class, 'postThem'])
            ->name('loaisanpham.postthem');
        Route::get('/loaisanpham/sua/{loai}', [\App\Http\Controllers\LoaiSanPhamController::class, 'getSua'])
            ->name('loaisanpham.sua');
        Route::put('/loaisanpham/sua/{loai}', [\App\Http\Controllers\LoaiSanPhamController::class, 'postSua'])
            ->name('loaisanpham.postsua');
        Route::delete('/loaisanpham/xoa/{loai}', [\App\Http\Controllers\LoaiSanPhamController::class, 'getXoa'])
            ->name('loaisanpham.xoa');
    });

    Route::middleware('role:admin')->group(function (): void {
        Route::get('/tong-quan/so-lieu', [\App\Http\Controllers\ThongKeController::class, 'getSoLieuTongQuan'])
            ->middleware('throttle:30,1')
            ->name('tongquan.solieu');
        Route::post('/kiemke/{phieu}/duyet', [\App\Http\Controllers\KiemKeController::class, 'approve'])->name('kiemke.approve');
        Route::get('/nhatky', [\App\Http\Controllers\NhatKyController::class, 'index'])->name('nhatky.index');
        Route::get('/nhatky/xuat-csv', [\App\Http\Controllers\NhatKyController::class, 'export'])->name('nhatky.export');
        Route::get('/doisoat', [\App\Http\Controllers\DoiSoatController::class, 'index'])->name('doisoat.index');
        Route::get('/doisoat/{webhook}', [\App\Http\Controllers\DoiSoatController::class, 'show'])->name('doisoat.show');
        Route::post('/doisoat/{webhook}/xuly', [\App\Http\Controllers\DoiSoatController::class, 'xuLy'])->name('doisoat.xuly');
        Route::post('/trahang/duyet/{phieuTra}', [\App\Http\Controllers\TraHangController::class, 'postDuyet'])->name('trahang.duyet');
        Route::get('/thongke', [\App\Http\Controllers\ThongKeController::class, 'getTrangThongKe'])
            ->name('thongke.index');
        Route::get('/thongke/doanhthu', [\App\Http\Controllers\ThongKeController::class, 'getDoanhThu'])->name('thongke.doanhthu');
        Route::get('/thongke/topsanpham', [\App\Http\Controllers\ThongKeController::class, 'getTopSanPham'])->name('thongke.topsanpham');
        Route::get('/baocao/hoadon/{dinhDang}', [\App\Http\Controllers\BaoCaoController::class, 'getHoaDon'])->name('baocao.hoadon');
    });

    Route::get('/baocao/xuatnhapton/{dinhDang}', [\App\Http\Controllers\BaoCaoController::class, 'getXuatNhapTon'])
        ->middleware('role:admin,thukho')
        ->name('baocao.xuatnhapton');
});
