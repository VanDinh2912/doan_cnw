<?php

/*
|--------------------------------------------------------------------------
| Phân loại mức bảo vệ thao tác — NGUỒN DUY NHẤT
|--------------------------------------------------------------------------
| Mỗi route GHI (POST/PUT/PATCH/DELETE) và GET xuất dữ liệu phải có đúng
| một dòng ở đây, hoặc nằm trong NGOAI_LE của PinKiemKeRouteTest.
| Middleware pin.muc đọc file này theo TÊN ROUTE:
|
|   K  : không yêu cầu gì (bán hàng hằng ngày, tra cứu, storefront...)
|   M1 : PIN riêng mức THƯỜNG (cửa sổ POS_PIN_CUA_SO_PHUT) — admin cũng vậy
|   M2 : nhân viên cần MÃ DUYỆT do admin cấp (một lần, ràng buộc thao tác);
|        admin dùng PIN riêng mức NGHIÊM TRỌNG mỗi lần
|   A  : chỉ admin — PIN riêng mức NGHIÊM TRỌNG mỗi lần; nhân viên 403
|
| Route có ĐIỀU KIỆN theo giá trị (controller tự leo thang, trả 423
| CAN_MA_DUYET): sanpham.postsua (đổi gia_ban/trang_thai_ban -> M2),
| donhang.thanhtoan + donhang.taoqr (vượt ngưỡng giảm giá/điểm -> M2).
| Mức nền của chúng ghi ở đây; middleware chỉ xử lý mức nền.
|
| Chuyển route giữa các mức = sửa đúng một dòng trong file này.
*/
return [
    // ------------------------- K — không PIN -------------------------
    'webhook.thanhtoan' => 'K',
    // Auth / khách / storefront
    'auth.postdangky' => 'K', 'auth.postdangnhap' => 'K', 'auth.dangxuat' => 'K',
    'doi-mat-khau.post' => 'K',
    'dat-hang.dat' => 'K', 'don-hang.posttracuu' => 'K',
    'don-hang.huy' => 'K', 'don-hang.poll' => 'K',
    'storefront.cart.update' => 'K', 'storefront.cart.clear' => 'K',
    'giohang.capnhat' => 'K', 'giohang.lammoi' => 'K',
    'lien-he.gui' => 'K', 'lienhe.capnhat' => 'K',
    'tintuc.binhluan' => 'K', 'profile.update' => 'K', 'profile.xacthuc' => 'K',
    // POS bán hàng hằng ngày
    'donhang.giahan' => 'K', 'donhang.huycho' => 'K',
    'donhang.thanhtoan' => 'K', // + dieu kien: vuot nguong giam gia/diem -> M2 (controller)
    'donhang.taoqr' => 'K',     // + dieu kien: vuot nguong diem -> M2 (controller)
    'hold.giu' => 'K', 'hold.xoa' => 'K',
    'khachhang.nhanh' => 'K', 'khachhang.postthem' => 'K',
    // Thêm/sửa nội dung, danh mục không nhạy cảm
    'sanpham.postthem' => 'K',
    'loaisanpham.postthem' => 'K', 'loaisanpham.postsua' => 'K',
    'hangsxuat.postthem' => 'K', 'hangsxuat.postsua' => 'K',
    'nhaphang.nhacungcap.postthem' => 'K', 'nhaphang.nhacungcap.postsua' => 'K',
    'chude.postthem' => 'K', 'chude.postsua' => 'K',
    'baiviet.postthem' => 'K', 'baiviet.postsua' => 'K',
    'baiviet.duyet' => 'K', 'baiviet.kichhoat' => 'K',
    'binhluan.duyet' => 'K', 'binhluan.an' => 'K',
    // Import: bước upload/preview không ghi DB
    'sanpham.import.upload' => 'K',
    // Hạ tầng PIN — chính cơ chế xác thực
    'pin.kiemtra' => 'K', 'pin.xacnhan' => 'K', 'pin.xincap.post' => 'K',
    // Tạo yêu cầu mã duyệt — bản thân nó là cơ chế M2, không cần PIN
    'duyet.gui' => 'K',
    'storage.local.upload' => 'K',

    // ------------------------- M1 — PIN thường -------------------------
    'khachhang.postsua' => 'M1',
    'sanpham.postsua' => 'M1', // + dieu kien: doi gia_ban/trang_thai_ban -> M2 (controller)
    'donhangonline.trangthai' => 'M1',
    'donhangonline.nhanhang' => 'M1',
    'hoadonnhap.postthem' => 'M1', 'hoadonnhap.boctachpdf' => 'M1', 'hoadonnhap.postsua' => 'M1',
    'nhaphang.donnhap.postthem' => 'M1',
    'kiemke.store' => 'M1', 'kiemke.submit' => 'M1',
    'binhluan.xoa' => 'M1',
    'hangsxuat.xuatexcel' => 'M1', 'sanpham.xuatexcel' => 'M1', 'baocao.xuatnhapton' => 'M1',

    // ------------------------- M2 — mã duyệt -------------------------
    'trahang.luu' => 'M2',
    'donhang.xacnhan' => 'M2',
    'khachhang.xoa' => 'M2',
    'sanpham.xoa' => 'M2',
    'loaisanpham.xoa' => 'M2',
    'hangsxuat.xoa' => 'M2',
    'nhaphang.nhacungcap.xoa' => 'M2',
    'nhaphang.donnhap.trangthai' => 'M2',
    'kho.postnhapkho' => 'M2',
    'hoadonnhap.xacnhan' => 'M2',
    'hoadonnhap.huy' => 'M2',

    // ------------------------- A — admin, PIN nghiêm trọng -------------------------
    'khachhang.khoa' => 'A', 'khachhang.mokhoa' => 'A',
    'khachhang.datlaimatkhau' => 'A', 'khachhang.dieuchinhdiem' => 'A',
    'donhangonline.huydathanhtoan' => 'A', 'donhangonline.dahoantien' => 'A',
    'kiemke.approve' => 'A',
    'nhatky.export' => 'A', 'doisoat.xuly' => 'A', 'trahang.duyet' => 'A', 'baocao.hoadon' => 'A',
    'taikhoan.store' => 'A', 'taikhoan.update' => 'A', 'taikhoan.khoa' => 'A',
    'taikhoan.mokhoa' => 'A', 'taikhoan.datlaimatkhau' => 'A', 'taikhoan.datlaipin' => 'A',
    'chude.xoa' => 'A', 'baiviet.xoa' => 'A',
    'sanpham.import.xacnhan' => 'A',
    'duyet.capma' => 'A', 'duyet.tuchoi' => 'A',
];
