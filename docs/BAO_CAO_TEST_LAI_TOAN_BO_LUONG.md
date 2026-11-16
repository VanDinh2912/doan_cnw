# Báo cáo test lại toàn bộ luồng — 2026-11-16

Chạy lại toàn bộ bộ test trên `main` tại commit `e54533e` (sau quy tắc hoàn phí
giao). Working tree sạch trước khi chạy.

## 1. Kết quả tổng

| Suite | Lệnh | Kết quả | Thời gian |
|---|---|---|---|
| Full suite (SQLite `:memory:`) | `vendor/bin/paratest` (16 tiến trình) | **792 test / 3461 assertions / 45 skipped / 0 fail** | 6m56.7s (416.71s) |
| MySQL strict copy | `php artisan test --compact --filter='MysqlCopyTest\|KhoaDongMysqlTest'` với `DB_CONNECTION=mysql DB_DATABASE=doan_cnw_copy` | **45 pass / 373 assertions / 0 fail** | 30.26s |

- 45 skipped = các test `*MysqlCopyTest`/`KhoaDongMysqlTest` chỉ chạy trên MySQL
  (skip có chủ đích trên SQLite) — đã chạy riêng và xanh ở dòng 2.
- ParaTest chỉ chạy với SQLite `:memory:` mỗi tiến trình — an toàn; KHÔNG chạy
  paratest khi env trỏ MySQL.

## 2. Phạm vi luồng đã kiểm chứng (nhóm test chính)

- **Xác thực & tài khoản**: `AuthFlowTest`, `BaoMatDangNhapTest`,
  `TaiKhoanGuardTest`, `TaiKhoanNhanVienStage3Test`, `NguoiDungCommandTest`,
  `DoiMatKhau`/`khoa.luc`/`bat.buoc.doi_mat_khau` middleware.
- **POS bán hàng**: `PosWorkflowTest`, `PosBaoVeTest`, `ThanhToanChuyenKhoanTest`,
  `LoiADungDiemTest`, `LoiBTaoQrTest`, `ToanVenGiaoDichTest`,
  `DonHangChuyenTrangThaiTest`, `DonHangQuyenChuSoHuuTest`,
  `HuyDonPickupQuaHanTest`, `GiuHangOnlineServiceTest`.
- **Đặt hàng online (P0→P3)**: `DatHangP0Test`, `DatHangP1*` (tra cứu, chống ôm
  hàng, chuẩn hóa SĐT, bảo vệ), `DatHangP2*` (form/checkout, QR online, giao tận
  nơi, bước 3 webhook/trạng thái giao), `DatHangCoBatTest` (cờ bật/tắt + thông
  báo giỏ hàng), `DonHangOnlineNhanVienTest`, `DonHangOnlinePhanQuyenTest`
  (ma trận vai trò), `DonHangPickupXuLyTest`, `PhieuGiaoHangTest`,
  `DonHangHuyDaThanhToanTest` (hủy đơn đã thanh toán + hoàn tiền thủ công).
- **Trả hàng**: `TraHangDonOnlineTest`, `TraHangPhiGiaoTest` (10 — quy tắc hoàn
  phí giao: tiền hàng sau giảm giá, admin tick một lần/đơn, điểm không tính
  ship), `TraHangTrungMaPhieuTest`, `BaoCaoTachPhiGiaoTest`.
- **Kho & nhập hàng**: `NhapKhoCanhBaoTest`, `LoiDDashboardTonKhoTest`,
  kiểm kê, `PdfInvoiceParserTest`, `AnhUrl*` (rule URL ảnh + sweep).
- **Khách hàng**: `KhachHangLichSuDonTest`, `KhachHangSoDienThoaiTest`,
  `LienKetHoSoTheoSDTTest`, `SoDienThoai*`.
- **Báo cáo/thống kê/export**: `ThongKe`, `BaoCao`, `TongQuanSoLieuTest`,
  `DoiSoatTest`, `Sepay*` (API client, đối soát, kiểm tra kết nối).
- **Hạ tầng/an toàn**: `RouteKhoiTest`, `LogBiMatTest`, `LenhNguyHiemTest`,
  `ChanLenhPhaHuyTest`, `SaoLuuCsdlTest`, `KiemTraHeThongTest`,
  `*GuardTest` (chặn RefreshDatabase/Truncation ngoài sqlite), `Storefront*`,
  `TacVuNenTest`, `ThongBaoLoiTest`, `NhatKy*`.
- **MySQL strict copy** (45 test): migration up/down M1/M3, hoàn tất thanh toán,
  webhook, ảnh URL sweep, khóa đồng, hoàn phí giao decimal — trên `doan_cnw_copy`,
  tự dọn dữ liệu, không đụng `doan_cnw`.

## 3. Ghi chú

- Không test nào fail; không assertion nào bị xóa/giảm trong đợt test lại này.
- Hai kỳ vọng cũ ở `TraHangDonOnlineTest` đã đổi có chủ đích theo quy tắc hoàn
  phí giao (xem `docs/BAO_CAO_HOAN_TIEN_SHIP.md` mục 5).
- `migrate:status` trên DB cấu hình hiện tại: **5 migration pending** (đúng thứ
  tự `docs/SO_TAY_TRIEN_KHAI.md` mục 1) — chưa áp lên `doan_cnw`.
- Hai cờ `POS_DAT_HANG_ONLINE_BAT`/`POS_GIAO_TAN_NOI_BAT` vẫn mặc định `false`
  trong code; test bật cờ trong `tests/TestCase.php`.
