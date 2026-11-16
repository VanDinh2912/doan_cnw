# TIẾN ĐỘ P3 + P2 BƯỚC 4 — Đơn online cho nhân viên

Làm trực tiếp trên `main`. Đọc file này trước khi tiếp tục; cập nhật sau mỗi hạng mục.

## Bước 0 — hiện trạng (đã khảo sát)

- Route nhân viên đơn hàng: `donhang.taoqr`, `donhang.trangthai`, `donhang.xacnhan`,
  `donhang.huycho`, `donhang.giahan`, `donhang.danhsach`, `donhang.inhoadon`
  (routes/web.php ~d92-114, nhóm auth+staff.only+khoa.luc+bat_buoc.doi.mat.khau).
- `DonHangController::getDanhSach` (d254): lọc tu_ngay/den_ngay, paginate 20,
  view `donhang/danhsach` — đơn online hiện nhãn "Đơn online" (user null),
  nút Xác nhận/Hủy chờ dùng chung `donhang.xacnhan`/`donhang.huycho`.
- `postGiaHan` (d208): chỉ đơn `kenh=online`+`phuong_thuc=tienmat`+`cho_thanh_toan`,
  trần `created_at + online_gia_han_toi_da_gio`; có lockForUpdate + nhật ký.
- `postXacNhanThanhToan` → `ThanhToanChuyenKhoanService::hoanTatThanhToan`
  (idempotent, lock products→customer→invoice); `postHuyCho` → `huyChoThanhToan`
  (hoàn kho movement `dieuchinh`, hoàn điểm đã dùng).
- Điểm: cộng `diem_tichluy_nhan` khi `hoanTatThanhToan` (customer.increment);
  thu hồi `diem_su_dung` khi `huyChoThanhToan`; trả hàng qua `PhieuTra` (trahang.*).
- Cột invoices online: M1 `kenh,ma_tra_cuu,ten_khach_dat,so_dienthoai_dat,
  ghi_chu_khach,han_giu`; M3 `hinh_thuc_nhan,ten_nguoi_nhan,sdt_nguoi_nhan,
  dia_chi_giao,phi_giao_hang,trang_thai_giao` (migration
  `2026_11_14_000001/000003`). Chưa có `can_hoan_tien`/`ghi_chu_noi_bo` → cần
  migration viết sẵn cho HM4.
- Layout `resources/views/layouts/master.blade.php`: nav nhóm "Bán hàng"
  (admin,thungan d36), "Kho" (admin,thukho d55); `@yield('content')`,
  `@stack('scripts')`. CheckRole nhận nhiều vai trò.
- Helper sẵn có: `KiemTraSchema::coCot/coBang/datLai`, `CheThongTin::soDienThoai`
  (mask `090****567`), accessor `trang_thai_tieng_viet`, `hinh_thuc_nhan_tieng_viet`,
  `trang_thai_giao_tieng_viet` trên DonHang; `NhatKyService::ghi`.

## Trạng thái hạng mục

- [x] HM1 Danh sách đơn online — code + test `DonHangOnlineNhanVienTest` 5/5 xanh;
  ĐANG CHỜ full suite + MySQL-copy xanh rồi commit (test:/feat:/docs:)
- [ ] HM2 Chuyển trạng thái giao (máy trạng thái)
- [ ] HM3 Pickup/QR chờ: UI thu ngân (xác nhận/hủy/gia hạn)
- [ ] HM4 Hủy đơn đã thanh toán (admin) + đánh dấu hoàn tiền (D16)
- [ ] HM5 Phiếu giao hàng (in)
- [ ] HM6 Trả hàng đơn online
- [ ] HM7 P2 bước 4: báo cáo tách phí ship
- [ ] HM8 Hoàn tất: suite + docs + README

## Quyết định thiết kế HM1

- Controller mới `App\Http\Controllers\DonHangOnlineController` (staff):
  `getDanhSach`, `getChiTiet`, `getSoLuongCanXuLy` (JSON badge).
- Route: `/donhang-online` (name `donhangonline.*`), role `admin,thungan,thukho`.
- Guard `KiemTraSchema::coCot('invoices','kenh')` → thiếu cột thì view thông báo
  "Cần áp migration", không 500; badge trả 0.
- Danh sách: mask SĐT (`CheThongTin`), filter hinh_thuc_nhan/trang_thai/
  trang_thai_giao/ngày/từ khóa (mã đơn, tên, SĐT, mã tra cứu), paginate 20.
- Chi tiết: full SĐT/địa chỉ cho nhân viên, `Cache-Control: no-store`,
  eager `chiTietDonHangs.sanPham`, không lộ giá nhập.
- Badge: link nav "Đơn online" + count đơn giao `cho_xac_nhan` + pickup
  `cho_thanh_toan` còn hạn; JS poll JSON 30s, lỗi mạng hiện cảnh báo.
- `donhang/danhsach`: đơn `kenh=online` thêm link "Xử lý" → trang chi tiết.

## Lệnh tiếp tục

- Test nhanh: `php artisan test --compact --filter=DonHangOnline`
- Full suite: `$env:XDEBUG_MODE='off'; php artisan test --compact` (PowerShell)
- MySQL-copy: `DB_CONNECTION=mysql DB_DATABASE=doan_cnw_copy DB_USERNAME=root DB_PASSWORD='' php artisan test --compact --filter='MysqlCopyTest|KhoaDongMysqlTest'`
- Tag backup trước HM4 (hủy đơn đã thanh toán, thu hồi điểm):
  `git tag backup/main-truoc-p3-huy-hoan-tien-<yyyymmdd>`
