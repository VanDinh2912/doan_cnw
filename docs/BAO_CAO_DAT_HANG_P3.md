# BÁO CÁO P3 + P2 BƯỚC 4 — Màn hình xử lý đơn online cho nhân viên

Làm trực tiếp trên `main`. Cờ `POS_DAT_HANG_ONLINE_BAT` / `POS_GIAO_TAN_NOI_BAT`
giữ nguyên mặc định `false` — người dùng tự bật sau khi kiểm tra tay.

## HM1 — Danh sách đơn online (nhân viên)

- `app/Http/Controllers/DonHangOnlineController.php`: `getDanhSach` (lọc hình thức
  nhận/trạng thái TT/trạng thái giao/khoảng ngày VN/từ khóa; paginate 20;
  eager `khachHang` — không N+1), `getChiTiet` (full SĐT/địa chỉ, `Cache-Control:
  no-store`), `getSoLuongCanXuLy` (JSON badge).
- `routes/web.php`: nhóm `role:admin,thungan,thukho` (đặt NGOÀI nhóm
  `role:admin,thungan` để thủ kho vào được).
- `resources/views/donhangonline/danhsach.blade.php` + `chitiet.blade.php`;
  SĐT danh sách che `090****567` (`CheThongTin::soDienThoai`).
- `layouts/master.blade.php`: nav "Đơn online" (admin/thungan/thukho) + badge
  JS poll 30s chỉ khi tab hiển thị, lỗi mạng hiện "!"; admin thêm "Đơn cần hoàn tiền".
- `donhang/danhsach.blade.php`: đơn `kenh=online` có link "Đơn online" → chi tiết.
- Guard `KiemTraSchema`: thiếu cột online → view "Cần áp migration", không 500;
  badge trả 0.
- Test: `tests/Feature/DonHangOnlineNhanVienTest.php` (5 test).

## HM2 — Máy trạng thái giao

- `postChuyenTrangThai`: POST+CSRF, `lockForUpdate` trong transaction; chỉ
  `cho_xac_nhan→da_xac_nhan→dang_giao→da_giao`; từ chối nhảy bậc/ngược;
  đơn chưa `da_thanh_toan` hoặc không phải giao_tan_noi → từ chối; đã ở trạng
  thái đích → "đã được xử lý" (idempotent, bấm đúp an toàn).
- Nhật ký `chuyen_trang_thai_giao` (nguon=web, trước/sau chỉ chứa trạng thái —
  không PII). Lịch sử hiển thị ở trang chi tiết.
- **Điểm**: đã cộng đúng 1 lần khi `da_thanh_toan` (R2,
  `ThanhToanChuyenKhoanService::hoanTatThanhToan`) → `da_giao` KHÔNG cộng lại.
- Test: `DonHangChuyenTrangThaiTest` (5 test: thứ tự, sai bậc, đúp, vai trò,
  đơn chưa TT, pickup, không double-award điểm).

## HM3 — Pickup / QR chờ (UI thu ngân)

- `chitiet.blade.php`: đơn `cho_thanh_toan` + role admin/thungan hiện 3 nút
  "Xác nhận đã nhận tiền/giao hàng", "Gia hạn", "Hủy đơn chờ" — gọi lại endpoint
  JSON sẵn có (`donhang.xacnhan`, `donhang.giahan`, `donhang.huycho`) nên toàn bộ
  quy tắc server (idempotent, trần gia hạn, hoàn kho/điểm) tái dùng nguyên trạng.
- Nút ẩn khi đã thanh toán/hủy hoặc vai trò thủ kho.
- Test: `DonHangPickupXuLyTest` (5 test).
- Ghi nhận hành vi hiện có: đơn quá hạn nhưng scheduler chưa hủy vẫn xác nhận
  được (tiền đã về) — giữ nguyên, không đổi nghiệp vụ.

## HM4 — Hủy đơn ĐÃ thanh toán + hoàn tiền thủ công (D16)

- Migration `2026_11_16_000001_add_can_hoan_tien_to_invoices_table.php`:
  `can_hoan_tien` boolean nullable + `ghi_chu_noi_bo` varchar(255) nullable;
  guard `hasColumn`, `down()` điều kiện. **CẦN ÁP lên doan_cnw.**
- `ThanhToanChuyenKhoanService::huyDonDaThanhToan`: transaction +
  lockForUpdate (SP→khách→đơn); chỉ `da_thanh_toan`; hoàn kho movement
  `dieuchinh` dương đúng 1 lần; thu hồi `diem_tichluy_nhan` kẹp sàn 0;
  `trang_thai=da_huy`, `trang_thai_giao=da_huy_giao`, `can_hoan_tien=true`
  (guard thiếu cột vẫn hủy được, trả cờ `thieuCotCanHoanTien`).
- Route `donhangonline.huydathanhtoan` (role:admin, bắt buộc `ly_do` ≥5 ký tự);
  Telegram `thongBaoHuyDonCanHoanTien` — chỉ mã đơn+tổng+lý do rút gọn 80 ký tự,
  escape HTML, không SĐT/tên/địa chỉ.
- `donhangonline.canhoantien` (admin): danh sách `can_hoan_tien=1` + nút
  "Đã hoàn tiền" bắt buộc ghi chú → `can_hoan_tien=false` + nhật ký
  `danh_dau_da_hoan_tien`. Thiếu cột → "Cần áp migration", không 500.
- Tag backup: `backup/main-truoc-p3-huy-hoan-tien-20261116`.
- Test: `DonHangHuyDaThanhToanTest` (5 test).

## HM5 — Phiếu giao hàng (in)

- `getPhieuGiao` + `phieugiaohang.blade.php`: mã đơn, người nhận/SĐT/địa chỉ đầy
  đủ, ghi chú khách, danh sách SP (không giá vốn), phí ship, tổng, "ĐÃ THANH
  TOÁN — KHÔNG THU TIỀN KHI GIAO", ngày giờ VN; `no-store`; nhật ký `in_phieu_giao`
  không PII; chỉ đơn `kenh=online`+`giao_tan_noi` (khác → 404).
- Test: `PhieuGiaoHangTest` (1 test, 19 assertions).

## HM6 — Trả hàng đơn online (kiểm tra, không đổi code)

- `TraHangController` an toàn với `customer_id=null` (bỏ qua điểm), `user_id`
  phiếu = nhân viên tạo; trang chọn chỉ cho `da_thanh_toan`.
- **Hành vi hiện có (ghi nhận, không đổi)**: `tienDong = SL × đơn giá ×
  (tong_thanh_toan/tong_tien)` → tiền hoàn TỶ LỆ GỒM CẢ PHÍ SHIP; `diem_thu_hoi`
  tính trên tiền hoàn; `diem_hoan`=0 vì online không `diem_su_dung`; hàng lỗi
  cộng `soluong_hong`.
- Test: `TraHangDonOnlineTest` (3 test: full/partial/guest+null customer).
- **Cần người dùng xác nhận**: trả một phần có nên hoàn phí ship tỷ lệ không?

## HM7 — P2 bước 4: báo cáo tách phí ship

- `ThongKeController::getDoanhThu`: thêm `phi_giao_hang` mỗi nhóm; `doanh_thu`
  = `(tong_thanh_toan − phi_giao_hang) × (1 − tỷ lệ hoàn)`; `phi_giao_hang` =
  `phi_giao_hang × (1 − tỷ lệ hoàn)` — nhất quán công thức tỷ lệ của trả hàng.
  Đơn chưa TT/đã hủy vốn đã bị lọc `da_thanh_toan`.
- `TongQuanKhoService`: `so_hoa_don` chỉ đếm `da_thanh_toan`.
- `HoaDonExport`: thêm cột "Phí giao hàng".
- `donhang/hoadon.blade.php`: dòng "Phí giao hàng" khi >0.
- Thiếu cột `phi_giao_hang` → `(float) null = 0`, không lỗi.
- Test: `BaoCaoTachPhiGiaoTest` (4 test; 100k+30k → doanh_thu 100k, ship 30k).

## Migration chờ áp lên `doan_cnw` (theo thứ tự)

1. `2026_11_14_000001` … `2026_11_14_000003` (cột online M1 + giao hàng M3)
2. `2026_11_15_000001` (activity_logs mở rộng), `2026_11_15_000002` (users)
3. `2026_11_16_000001` (can_hoan_tien, ghi_chu_noi_bo) — HM4

Sau khi áp: `KiemTraSchema::datLai()` tự reset ở request mới (cache tĩnh per
process — PHP-FPM cần reload/`opcache_reset` nếu cache đã ghi nhận thiếu cột).

## Cần người dùng xác nhận

- Phí ship thật (`POS_PHI_GIAO_HANG` mặc định 30.000) và vùng giao áp dụng.
- Trả hàng một phần có hoàn phí ship tỷ lệ (hành vi hiện tại: CÓ).
- Thời hạn gia hạn tối đa (`POS_ONLINE_GIA_HAN_TOI_DA_GIO` mặc định 24h).
- Định nghĩa doanh thu: hàng hóa (đã tách) vs tổng thu gồm ship.
