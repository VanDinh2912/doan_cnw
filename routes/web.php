<?php

use Illuminate\Support\Facades\Route;

Route::get('/dangnhap', [\App\Http\Controllers\AuthController::class, 'getDangNhap'])
    ->name('auth.dangnhap');
Route::post('/dangnhap', [\App\Http\Controllers\AuthController::class, 'postDangNhap'])
    ->name('auth.postdangnhap');
Route::get('/dangxuat', [\App\Http\Controllers\AuthController::class, 'getDangXuat'])
    ->middleware('auth')
    ->name('auth.dangxuat');

Route::middleware('auth')->group(function (): void {
    Route::get('/', fn () => redirect()->route('tongquan'))->name('trangchu');
    Route::get('/tongquan', [\App\Http\Controllers\ThongKeController::class, 'getTongQuan'])->name('tongquan');

    Route::get('/sanpham/timkiem', [\App\Http\Controllers\SanPhamController::class, 'getTimKiem'])
        ->middleware('role:admin,thungan')
        ->name('sanpham.timkiem');

    Route::middleware('role:admin,thungan')->group(function (): void {
        Route::get('/pos', [\App\Http\Controllers\DonHangController::class, 'getPos'])->name('pos.index');
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
