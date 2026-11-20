# BAO_CAO_NEXT — Việc tiếp theo / phân tích chưa triển khai

Tài liệu tổng hợp các phân tích và đề xuất cho giai đoạn tiếp theo.
Cập nhật: 2026-11-20. Trạng thái: **phân tích, chưa code** — chờ duyệt.

---

## PHẦN 1 — THUẾ (hiện trạng)

### 1.1 Thuế trên hóa đơn NHẬP — ĐÃ CÓ

Áp dụng cho `purchase_invoices` / `hoa_don_nhap` (chứng từ mua vào từ NCC):

| Thành phần | Chi tiết |
|---|---|
| Cột DB | `thue_suat` decimal(5,2), `tien_thue`, `tong_tien_hang`, `tong_thanh_toan` — migration `2026_01_01_000018_create_purchase_invoices_tables.php` |
| Giá trị cho phép | Chỉ `0 / 5 / 8 / 10` (%) — validate `in:0,5,8,10` ở `HoaDonNhapController::luu()` |
| Mặc định form | `thue_suat = 8`, `hinh_thuc_thanh_toan = chuyenkhoan` |
| Công thức | `tien_thue = round(tong_tien_hang × thue_suat / 100)`; `tong_thanh_toan = tong_tien_hang + tien_thue` |
| Kiểm tra lại | `HoaDonNhapService` đối chiếu lại tổng tiền hàng/thuế/tổng thanh toán trước khi xác nhận nhập kho |
| PDF parser | `PdfInvoiceParserService` bóc `thue_suat` + `tien_thue` từ hóa đơn NCC (cùng `ma_so_thue`) |
| `ma_so_thue` NCC | Mã số thuế doanh nghiệp — **định danh**, bắt buộc khi lưu HĐ nhập; KHÔNG phải thuế suất |
| Giá vốn | `gia_nhap` lấy `don_gia` dòng hàng — **không cộng thuế**; thuế chỉ là chứng từ kế toán, không vào doanh thu/lợi nhuận |

Nguồn dữ liệu: `DuLieuMau300Seeder` + `SyncTaiLieuDataCommand` import `thue_suat`/`tien_thue`/`ma_so_thue` từ Excel `TaiLieu/`.

### 1.2 Thuế GTGT khi BÁN — CHƯA TRIỂN KHAI

- `invoices` / `invoice_details` (POS), đơn online (`don_hang*`), storefront: chỉ có `tong_tien`, `giam_gia`, `tien_giam`, `diem`, `phi_giao` — **không có cột thuế/VAT nào**.
- Giá bán là giá thuần; không có khái niệm thuế suất đầu ra trong `config/trang_tinh.php`.
- README mục "Hóa đơn nhập" + mục 14 đã ghi rõ gap này.

### 1.3 Nếu cần làm thuế bán (đề xuất, chưa làm)

- Quyết định trước: thuế tính **trên giá bán** (VAT-inclusive, phổ biến bán lẻ VN) hay **cộng thêm** — ảnh hưởng toàn bộ giá hiển thị storefront/POS.
- Phạm vi tối thiểu: cột `thue_suat`+`tien_thue` trên `invoices`/`don_hang`, tách `tong_thanh_toan = tong_tien + thue` hoặc giá đã gồm thuế; báo cáo doanh thu hiện tại phải quy đổi.
- Liên quan: xuất hóa đơn điện tử (HĐĐT) là bài toán riêng, lớn hơn nhiều — ngoài phạm vi đồ án trừ khi yêu cầu.

---

## PHẦN 2 — QUẢN LÝ TÀI KHOẢN KHÁCH HÀNG

### 2.1 Hiện trạng

| Thành phần | Tình trạng |
|---|---|
| `users` (role `khachhang`) | name, email (unique), password (hash bcrypt), `khoa_luc`, timestamps — **không có `last_login_at`/lịch sử login** |
| `password_reset_tokens` | Bảng có sẵn (mặc định Laravel) nhưng **chưa dùng** — không có route "quên mật khẩu" |
| Liên kết | `customers.user_id` → `users.id`; admin gán/gỡ qua form khách (`KhachHangController::taiKhoanCoTheLienKet`, 1 user chỉ gắn 1 hồ sơ) |
| Admin xem | Trang `/khachhang/{id}` card "Thông tin hồ sơ" hiện `Tên (email)` TK liên kết hoặc "Chưa liên kết tài khoản" |
| Admin làm | Khóa/mở khách → đồng bộ `users.khoa_luc`; `taikhoan/dat-lai-mat-khau` **chỉ cho nhân viên** (admin/thungan/thukho) |
| Khách tự phục vụ | `/thong-tin-ca-nhan` (sửa hồ sơ, xem đơn), `/doi-mat-khau` (đổi MK khi đã login); **không có quên MK** |
| Artisan | `nguoidung:doi-matkhau` — CLI only |
| Flag sẵn có | `bat_buoc_doi_mat_khau` (đang dùng cho NV) — tái dùng được cho MK tạm |
| Audit | `activity_logs` ghi mọi thao tác admin |

### 2.2 Admin nên được xem/quản lý gì (theo thực tế)

**NÊN xem:** email đăng nhập, vai trò, trạng thái khóa, `created_at`; `last_login_at` (cần bổ sung); lịch sử login gần đây (phase sau).

**NÊN làm:** đặt lại MK → MK tạm hiện 1 lần + `bat_buoc_doi_mat_khau` + audit kèm lý do; khóa/mở (có); liên kết/gỡ (có).

**TUYỆT ĐỐI KHÔNG:** xem/giải mã MK (bất khả thi — hash 1 chiều); impersonate không audit; sửa email tùy ý (email là định danh reset MK).

### 2.3 Kịch bản rủi ro & phương án

| Tình huống | Hiện tại | Đề xuất |
|---|---|---|
| Quên MK, còn email | ❌ Kẹt, phải nhờ admin/CLI | Flow "Quên MK" qua email — `password_reset_tokens` + Laravel Password Broker có sẵn |
| Quên MK + mất email | Admin reset thủ công | Admin reset + lý do bắt buộc + audit; xác minh qua `so_dien_thoai` trong hồ sơ khách |
| Đổi SĐT/email | Admin sửa tự do | Cảnh báo khi đổi email của TK đã liên kết; audit log |
| TK trùng/nghi chiếm đoạt | `email` unique đã chặn | `last_login_at` + IP login gần nhất để admin soi |
| Khách muốn xóa TK | Chưa có | Cho khách tự vô hiệu TK; **giữ `customers`** (hồ sơ mua hàng) tách biệt |
| Mất SĐT (định danh tra đơn) | Tra cứu đã yêu cầu mã đơn + SĐT (2 yếu tố) — đúng chuẩn | Cân nhắc xác minh nhẹ thêm |

### 2.4 Đề xuất theo phase

- **Phase 1 — Admin quản lý TK khách — ✅ ĐÃ TRIỂN KHAI (2026-11-20):**
  card "Tài khoản" hiện email/ngày tạo/trạng thái khóa/`last_login_at` +
  nút "Đặt lại MK" (admin-only, route `role:admin` + `throttle:10,1`);
  MK tạm ≥12 ký tự không ký tự dễ nhầm, hiện 1 lần (response 200 +
  `Cache-Control: no-store`, không flash/redirect), hash bcrypt,
  `bat_buoc_doi_mat_khau=1`, `remember_token` mới, xóa `sessions.user_id`;
  audit `dat_lai_mat_khau` chỉ ghi người làm + lý do. Migration
  `add_last_login_at_to_users_table` (additive) đã áp cả dev lẫn prod.
  Throttle đăng nhập `RateLimiter` 5 lần sai/15 phút theo email thường
  hóa + IP — áp cho MỌI role (endpoint dùng chung), xóa bộ đếm khi login
  đúng, email không tồn tại cũng bị đếm (không lộ TK). `last_login_at`
  cập nhật qua `DB::table` (không đụng `updated_at`) ở cả login MK lẫn
  Google. Test: `tests/Feature/QuanLyTaiKhoanKhachHangTest.php` (16 test).
  Bỏ qua: cảnh báo đổi email (form không cho sửa email TK), phân biệt
  TK Google (schema không lưu provider).
- **Phase 2 — Khách tự phục hồi:** flow quên MK qua email (broker có sẵn); hoàn thiện vòng đời TK. **Điều kiện:** cần SMTP thật — hiện `LOG_MAILER`.
- **Phase 3 — Nâng cao, chi phí 0đ** (xem mục 2.5).

### 2.5 Phase 3 — phương án chi phí = 0

**Thay OTP SMS:**
- **TOTP (Google/Microsoft Authenticator)** — chuẩn RFC 6238, offline, free; Laravel package `pragmarx/google2fa` (open source). Quét QR 1 lần → nhập mã 6 số.
- **Backup codes** — 10 mã dùng 1 lần, đi kèm TOTP.
- Mã qua email — free *chỉ khi* đã có SMTP.

**Giám sát (chỉ tốn DB):**
- `last_login_at` + IP + user-agent mỗi lần login (~10 dòng trong AuthController)
- Bảng `login_histories` (user_id, ip, ua, success/fail) — tái dùng pattern `activity_logs`
- Cảnh báo "đăng nhập thiết bị/IP mới" — so IP/UA với lần trước
- Heuristic: N lần sai MK → khóa tạm; login IP mới + đổi MK trong 5 phút → flag admin

**Chống brute-force (Laravel throttle có sẵn):**
- ~~Rate-limit login~~ — **đã làm ở Phase 1** (5 sai/15ph email+IP); còn
  register/tra cứu/quên MK
- **Cloudflare Turnstile** — CAPTCHA miễn phí, ẩn hoàn toàn; đặt ở login/register/liên hệ/tra cứu
- Lockout progressive: sai 5 lần → 15', tiếp → 1h (`RateLimiter` ~20 dòng)

**Quản lý phiên (`sessions` đang DB driver):**
- "Đăng xuất tất cả thiết bị khác" ở profile — xóa sessions của user
- Danh sách phiên đang hoạt động (IP, UA, thời điểm) cho khách tự thu hồi
- Tự thu hồi session khi đổi/reset MK

**Kiểm tra MK rò rỉ (free):**
- HaveIBeenPwned **k-anonymity API** — gửi 5 ký tự đầu hash, không lộ MK; cảnh báo khi đặt/đổi MK

**Phía admin (free):**
- Dashboard "TK bất thường": fail liên tiếp, đổi MK gần đây, IP lạ — query trên log đã có
- Chính sách MK: tối thiểu 8 ký tự + không trùng 5 MK gần nhất

**Đề xuất Phase 3 thực tế nhất (0đ, không phụ thuộc dịch vụ ngoài):**
TOTP (nếu cần 2FA thật) HOẶC bỏ 2FA, tập trung `last_login_at` + `login_histories` + throttle + Turnstile + session revoke. Phần email cảnh báo để sau khi có SMTP.

---

## Ghi chú vận hành liên quan

- Mail hiện `LOG_MAILER` — Phase 2 và email-cảnh-báo chỉ phát huy khi cấu hình SMTP thật.
- SePay vẫn dừng hoàn toàn; không liên quan các phần trên.
- Mọi thao tác admin mới (reset MK, đổi email...) bắt buộc ghi `activity_logs` kèm lý do.
