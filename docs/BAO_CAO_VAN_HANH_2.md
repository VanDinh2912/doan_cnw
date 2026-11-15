# Báo cáo VẬN HÀNH 2 — Sửa Lỗi A/D/E, xác nhận B, rà soát liên kết hồ sơ (2026-11-16, `main`)

Phạm vi: làm trực tiếp trên `main` sau khi Stage 3-4 đã merge. Nhánh nguồn
`fix/loi-van-hanh-1` được cherry-pick từng commit (quyết định ghi ở mục 0);
Lỗi E làm mới trên `main` vì phần lớn đã có sẵn từ `fix/anh-url-validate`.

## 0. Baseline và quyết định cherry-pick

- Baseline trước khi sửa (cùng cây `0b0e778`): **643 pass / 42 skip / 0 fail /
  2823 assertions** (số đã xác nhận ở Stage 3-4; lượt chạy lại bị hủy giữa
  chừng vì code bắt đầu thay đổi — số cuối cùng ở mục 6).
- `git diff --stat main...fix/loi-van-hanh-1`: 13 file, gồm 4 commit cho Lỗi A
  (`dfd4009` test + `003714a` fix) và Lỗi D (`46bb0bc` test + `7d5dedf` fix),
  2 commit Lỗi C (SĐT — đã được Stage 1/3-4 xử lý, không cherry-pick), 2 commit
  Lỗi B (`987d06b` test đã có bản tương đương trên main, `aae9ae8` fix — nội
  dung đã nằm trong main qua merge P2, không cherry-pick).
- **Quyết định: cherry-pick Lỗi A + Lỗi D** (cả test lẫn fix, giữ thứ tự
  test → fix). Cả 4 commit apply sạch, không xung đột:
  - `ce5cfec` ← `dfd4009` (test Lỗi A)
  - `9ed15b1` ← `003714a` (fix Lỗi A)
  - `6ed1db3` ← `46bb0bc` (test Lỗi D)
  - `fad78ff` ← `7d5dedf` (fix Lỗi D)
- Lỗi E: **làm mới trên main** — `AnhSanPhamUrl` + migration nới cột 500 đã có
  từ `fix/anh-url-validate`; phần còn thiếu (giới hạn động theo cột thật, JS
  không khóa nút Lưu) được bổ sung trong commit `77a6ac6`.

## 1. LỖI A — Dùng điểm khi khách chưa có điểm (ưu tiên tiền)

**Tái hiện (trên code trước fix):** khách 0 điểm nhập `diem_su_dung=500` ở POS
tiền mặt lẫn QR → `RuntimeException` (không phải 422) → JSON lỗi chung/500;
khách lẻ nhập điểm cũng lỗi tương tự; số âm/thập phân/chữ/vượt điểm/vượt tổng
tiền đều rơi vào nhánh `Throwable` chung.

**Nguyên nhân gốc:** `DonHangController::postThanhToan`/`postTaoQr` và
`ThanhToanChuyenKhoanService::taoHoaDonChoThanhToan` đã kiểm tra
`lockForUpdate` + so điểm trong transaction, nhưng ném `RuntimeException`
thay vì `ValidationException` → client nhận 500/422 không nhất quán, không
có `errors.diem_su_dung`, UI không biết trường nào sai.

**Sau fix (cherry-pick `003714a`):**
- Validation `diem_su_dung` có message tiếng Việt (`integer`, `min:0`) +
  `customer_id.exists`; `diem_su_dung > 0` mà không có `customer_id` →
  `ValidationException` "Cần chọn khách hàng trước khi dùng điểm."
- Trong transaction (khóa `lockForUpdate` khách): điểm dùng > điểm hiện có →
  422 "vượt quá điểm hiện có"; quy đổi vượt tổng → 422 "vượt quá giá trị
  hóa đơn". `postTaoQr` bắt `ValidationException` trả JSON 422 kèm `errors`;
  `postThanhToan` rethrow và trả JSON 422 khi request AJAX/JSON.
- UI POS (`pos/index.blade.php`): ô "Dùng điểm" `disabled` mặc định, chỉ mở
  khi chọn khách; hiện "Điểm hiện có: N", set `max`, kẹp giá trị khi gõ,
  reset về 0 khi đổi SĐT — không im lặng cắt.
- Đặt hàng online: không cho dùng điểm — giữ nguyên.

**Test:** `LoiADungDiemTest` 10/10 xanh trên main (34 assertions): 0 điểm,
khách lẻ, âm, thập phân/chữ, vượt điểm, vượt tổng tiền ở cả tiền mặt lẫn QR;
trừ điểm đúng 1 lần; hủy đơn `cho_thanh_toan` hoàn điểm đúng; hai hóa đơn
tuần tự cùng dùng điểm → đơn 2 bị 422.

## 2. LỖI D — Dashboard không phản ánh tồn kho

**Nguyên nhân gốc:** dashboard chỉ hiển thị `SUM(products.soluong)` ("tổng
tồn tốt") — con số đã trừ hàng giữ nhưng nhãn "Tổng tồn" khiến người dùng
hiểu là tồn vật lý; không có chỉ số "đang giữ" nên chênh lệch giữa hai khái
niệm không giải thích được; lỗi mạng khi auto-refresh bị nuốt im lặng.

**Sau fix (cherry-pick `7d5dedf`):**
- `TongQuanKhoService::chiSo()` trả thêm `ton_kha_dung` (= tổng tồn đã trừ
  giữ), `dang_giu` (SUM `invoice_details.soluong` join `invoices` trạng thái
  `cho_thanh_toan` — gồm cả đơn online pickup/giao tận nơi chưa thanh toán),
  `tong_thuc_te` = khả dụng + đang giữ.
- `tongquan.blade.php`: 4 thẻ có nhãn + chú thích "Số mặt hàng đang kinh
  doanh / Tồn khả dụng / Đang giữ chờ thanh toán / Tổng hàng thực tế" + đoạn
  chú thích cách tính; catch lỗi fetch hiện cảnh báo kèm `e.message` thay vì
  im lặng.
- Không đổi logic ghi kho.

**Test:** `LoiDDashboardTonKhoTest` xanh trên main (1 test, 35 assertions) —
tái hiện đủ 4 bước: hoàn tất POS tiền mặt, tạo đơn QR chờ, webhook hoàn
tất, hủy quá hạn; số dashboard khớp từng bước.

## 3. LỖI E — Dán URL ảnh sản phẩm

**Trạng thái trên main trước sửa:** đã có `AnhSanPhamUrl` (https-only, chặn
javascript:/data:/userinfo/khoảng trắng, không tải URL phía server — chống
SSRF), migration `2026_11_13_000002` nới cột lên 500 (viết sẵn, chưa áp DB
thật), nhưng rule **hardcode 150** → sau khi migrate sẽ từ chối oan URL
151–500; JS xem trước **khóa cứng nút Lưu** khi ảnh không tải được
(hotlink-protection/timeout/Content-Type lạ) → người dùng không lưu được
URL hợp lệ.

**Sau sửa (commit `77a6ac6`):**
- `KiemTraSchema::doDaiCotVarchar(bang, cot)`: đọc
  `information_schema.COLUMNS.CHARACTER_MAXIMUM_LENGTH` (MySQL) hoặc
  `PRAGMA table_info` (SQLite), cache tĩnh, `null` khi không đọc được.
- `AnhSanPhamUrl::gioiHanDoDai()` = giới hạn cột thật, fallback `MAX_DO_DAI=150`
  khi schema không đọc được (SQLite grammar `varchar` không ghi độ dài → luôn
  fallback 150 = đúng DB thật hiện tại). Thông báo "quá dài" in đúng số giới
  hạn hiện hành.
- Form: chữ "tối đa N ký tự" lấy từ `gioiHanUrlAnh` controller truyền;
  preview lỗi → cảnh báo + checkbox **"Vẫn lưu liên kết này"** (phải tích lại
  cho mỗi URL mới bị cảnh báo) thay vì khóa nút — server vẫn validate định
  dạng là nguồn đúng.
- Giữ nguyên: chỉ https; từ chối javascript:/data:/userinfo; server không
  tải URL.

**Test:**
- `SanPhamAnhUrlTest` +1 test khóa fallback 150 trên SQLite (143 test nhóm
  ảnh xanh).
- `AnhUrlDoDaiMysqlCopyTest` (mới, MySQL-copy): áp migration lên 500 trên
  `doan_cnw_copy` → `gioiHanDoDai()=500`, URL đúng 500 ký tự lưu được, 501 bị
  từ chối kèm lỗi "tối đa 500 ký tự", `down()` trả 150 → 151 bị từ chối lại.
  Tự dọn sản phẩm/user/migration row; **1 pass / 15 assertions**.

## 4. LỖI B — Tạo QR (xác nhận, không làm lại)

Đã có tương đương + mở rộng hơn `aae9ae8` trong main (qua merge P2):
- `postTaoQr` thiếu `POS_BANK_*` → 422 thân thiện từng biến thiếu, log chỉ
  `Str::mask` số tài khoản (`DonHangController.php:80-108`).
- `DonHang::scopeQuaHanCho` guard `Schema::hasColumn('invoices','kenh')` →
  fallback 15 phút khi thiếu cột (`DonHang.php:88`).
- `taoHoaDonChoThanhToan` chỉ ghi cột online khi `$online` khác rỗng và
  `hasColumn('kenh')`; cột M3 chỉ ghi khi `hasColumn('hinh_thuc_nhan')`
  (`ThanhToanChuyenKhoanService.php:145-162`).

**Test:** `LoiBTaoQrTest` 6/6 + `LoiBThieuCotOnlineTest` 4/4 xanh (55
assertions) — đủ cấu hình OK, thiếu `bank_code`/`bank_account` → 422 không
lộ số TK, POS chạy khi DB thiếu cột online+giao hàng, log không chứa số TK
đầy đủ.

**Khoảng hở còn lại so với `aae9ae8`:** không có — mọi thay đổi của commit
đó đã nằm trong main (main còn guard thêm cột M3). `git cherry` chỉ còn đánh
dấu `aae9ae8` là `+` vì patch-id khác do ngữ cảnh đã tiến hóa, không phải
thiếu tính năng.

## 5. LIÊN KẾT HỒ SƠ ĐÃ TẠO TRƯỚC KHI VÁ (chỉ đọc, KHÔNG sửa dữ liệu)

Khảo sát chỉ đọc trên `doan_cnw` (2026-11-16):

- Tổng khách: **101**; có `user_id`: **1** (customer_id=102 ↔ user_id=4,
  role `khachhang`; hồ sơ và tài khoản tạo cùng giây 2026-11-12 08:05:20 —
  nhiều khả năng đăng ký hợp lệ tự tạo hồ sơ mới, không phải auto-link hồ sơ
  cũ).
- Cờ nghi vấn (tài khoản tạo SAU hồ sơ + hồ sơ có hóa đơn POS): **0**.
- Không phát hiện cặp auto-link đáng ngờ → **không cần gỡ liên kết hàng loạt**.

**Đề xuất lệnh gỡ liên kết nhầm (CHƯA triển khai, KHÔNG chạy):**
`php artisan khachhang:go-lien-ket {customer_id} --ly-do="..."` — mặc định
**dry-run** in customer_id/user_id/ngày tạo trước khi làm; `--ap-dung` mới
`UPDATE customers SET user_id=NULL` trong transaction, ghi `activity_logs`
(action `go_lien_ket`) và từ chối nếu hồ sơ có đơn online `cho_thanh_toan`
(để tránh mồ côi đơn giữ hàng). Thay thế tạm thời hiện tại: admin vào
`/khachhang/sua/{id}` → select "Tài khoản khách hàng" → "— Chưa liên kết —"
→ Lưu (đã có sẵn, có audit log qua `GhiNhatKy`).

**Quy trình nhân viên liên kết tài khoản khách với hồ sơ mua tại quầy** (đã
thêm vào README mục 7): chỉ admin/thu ngân; bắt buộc khách đăng nhập tài
khoản trên chính thiết bị của họ + đưa hóa đơn gần nhất/đọc mã hóa đơn để
đối chiếu SĐT-tên; thao tác tại form sửa khách hàng; mọi gán/gỡ đều vào
`activity_logs`; không gán theo SĐT trùng một mình.

## 6. Kết quả kiểm tra

- Full suite SQLite (`XDEBUG_MODE=off php artisan test --compact`):
  **655 passed / 43 skipped / 0 failed / 2895 assertions, 982.53s**.
  Lượt chạy đầu có 1 fail `PosWorkflowTest::customer_cannot_redeem_more_
  points_than_balance` — assertion cũ chờ flash `error`, trong khi Lỗi A cố
  ý đổi sang `ValidationException` (errors bag `diem_su_dung`); đã cập nhật
  assertion (`3c45de9`, nêu lý do trong comment) và chạy lại toàn suite xanh.
- MySQL-copy filter chính xác `MysqlCopyTest|KhoaDongMysqlTest` trên
  `doan_cnw_copy`: **43 passed / 356 assertions, 17.14s** (42 cũ +
  `AnhUrlDoDaiMysqlCopyTest` mới).
- `php artisan view:cache`: ✅ sau khi sửa Blade.
- Skip 43 = test `*MysqlCopyTest`/`KhoaDongMysqlTest` cần MySQL strict — đã
  chạy riêng xanh trên bản sao, không phải skip bỏ quên.

## 7. Migration cần áp lên `doan_cnw` (viết sẵn, CHƯA áp)

Thứ tự đề xuất (backup trước, đối chiếu `information_schema` sau mỗi bước,
gỡ guard `KiemTraSchema` chỉ khi đã xác nhận cột tồn tại, cuối cùng chạy
`php artisan he-thong:kiem-tra`):

1. `2026_11_12_000001_add_online_order_fields_to_invoices_table.php` (M1)
2. `2026_11_13_000001_make_stock_movements_user_id_nullable.php`
3. `2026_11_13_000002_tang_do_dai_hinh_anh_products_len_500.php` ← Lỗi E
4. `2026_11_14_000003_add_delivery_fields_to_invoices_table.php` (M3)
5. `2026_11_15_000001_extend_activity_logs_for_audit.php`
6. `2026_11_15_000002_add_khoa_luc_and_bat_buoc_doi_mat_khau_to_users_table.php`

(Lưu ý: quan sát chỉ đọc 2026-11-14/15 cho thấy M1+M3 **có thể đã áp** trên
`doan_cnw` — kiểm tra `migrations`/`information_schema` trước khi chạy lại;
migration Laravel tự bỏ qua bản đã ghi.)

## 8. Nhánh còn lại và việc tồn đọng

- `fix/loi-van-hanh-1`: sau cherry-pick chỉ còn Lỗi C (SĐT — đã được Stage
  1/3-4 xử lý tương đương; `git cherry` còn `+` do patch-id khác ngữ cảnh)
  và các commit đã cherry-pick. Không merge nguyên nhánh.
- `feat/sepay-doi-soat`: 6 commit đối soát SePay API chủ động — chưa merge.
- Việc còn lại: P2 bước 3–4 (webhook `trang_thai_giao`, tra cứu trạng thái
  giao, tách phí ship khỏi doanh thu), P3 (hủy đơn đã thanh toán + hoàn tiền
  thủ công), SePay đối soát.
