# SỔ TAY TRIỂN KHAI — Web POS (Laravel 12, MySQL `doan_cnw`)

Tài liệu vận hành: migration chờ áp, quy trình nâng cấp an toàn, biến môi trường,
gỡ guard sau migrate, dựng máy cửa hàng, bật đặt hàng online.

> Nguyên tắc: KHÔNG chạy `migrate`/`seed`/`demo:reset` trực tiếp lên `doan_cnw`.
> Mọi migration đều có guard hoặc `down()` điều kiện; code hiện tại chịu được cả
> khi chưa migrate (guard `KiemTraSchema`/`Schema::hasColumn`).

## 1. Migration CHƯA áp lên `doan_cnw`

Kết quả `php artisan migrate:status` (chỉ đọc) ngày 2026-11-16 — 4 migration pending,
+ 1 migration hoàn phí giao (mục 1, dòng 5). Laravel chạy pending theo
thứ tự tên file, nên **thứ tự áp thực tế** là:

| # | Migration | Mục đích | Thêm/sửa | Guard | Rủi ro | Hoàn tác |
|---|-----------|----------|----------|-------|--------|----------|
| 1 | `2026_11_11_000001_add_ma_tham_chieu_to_payment_webhooks_table` | Đối soát SePay: mã tham chiếu ngân hàng để gộp hiển thị webhook↔API | `payment_webhooks.ma_tham_chieu` varchar(100) nullable + index | ❌ chưa có guard — nên bọc `Schema::hasColumn` trước khi áp (xem mục 6) | Thấp: chỉ thêm cột nullable + index | `down()` drop index+column |
| 2 | `2026_11_15_000001_extend_activity_logs_for_audit` | Nhật ký hoạt động chi tiết (audit) | Mở rộng `activity_logs`: thêm cột mô tả/đối tượng/nguồn… | ✅ có guard | Trung bình: bảng log lớn → nên sao lưu + chạy giờ vắng; thêm cột nullable/index | `down()` có điều kiện |
| 3 | `2026_11_15_000002_add_khoa_luc_and_bat_buoc_doi_mat_khau_to_users_table` | Khóa tài khoản + ép đổi mật khẩu lần đầu | `users.khoa_luc`, `users.bat_buoc_doi_mat_khau` | ✅ có guard | Thấp | `down()` có điều kiện |
| 4 | `2026_11_16_000001_add_can_hoan_tien_to_invoices_table` | P3: cờ cần hoàn tiền thủ công + ghi chú nội bộ | `invoices.can_hoan_tien` bool, `invoices.ghi_chu_noi_bo` varchar(255) | ✅ có guard | Thấp | `down()` có điều kiện |
| 5 | `2026_11_16_000002_add_tien_hoan_phi_giao_to_returns_table` | Quy tắc hoàn phí giao: tick admin một lần/đơn | `returns.tien_hoan_phi_giao` unsigned int default 0 | ✅ có guard | Thấp: chỉ thêm cột có default; thiếu cột → trả hàng chạy hành vi cũ, tick ship báo 422 | `down()` có điều kiện |

## 1b. Nhật ký áp migration

**2026-11-16 ~22:3x** — đã áp thành công 5 migration lên `doan_cnw` (MySQL 8.4.7,
InnoDB), xác nhận của Lê Văn Định lúc 22:13. Tag git:
`backup/truoc-migrate-doan-cnw-20261116`.

- Danh sách đã áp (đúng thứ tự): `2026_11_11_000001_add_ma_tham_chieu_to_payment_webhooks_table`,
  `2026_11_15_000001_extend_activity_logs_for_audit`,
  `2026_11_15_000002_add_khoa_luc_and_bat_buoc_doi_mat_khau_to_users_table`,
  `2026_11_16_000001_add_can_hoan_tien_to_invoices_table`,
  `2026_11_16_000002_add_tien_hoan_phi_giao_to_returns_table`.
- Kiểm chứng: 27 bảng, số dòng mọi bảng dữ liệu **không đổi** so với mốc trước
  (chỉ bảng `migrations` +5 bản ghi); engine toàn InnoDB; đủ cột mới
  (`payment_webhooks.ma_tham_chieu`, `users.khoa_luc`/`bat_buoc_doi_mat_khau`,
  `invoices.can_hoan_tien`/`ghi_chu_noi_bo`, `returns.tien_hoan_phi_giao`,
  `activity_logs` +6 cột +2 index); `migrate:status` 0 pending.
- Thời gian áp: ~0,6 giây tổng (từng migration 28–247 ms), trong chế độ bảo trì.
- Sao lưu (KHÔNG nằm trong repo, chỉ ghi tên + kích thước):
  - `D:\Study\xampp\www\doan_cnw_truoc_migrate_20261116_2216.sql` — 382,3 KB
    (bản chính, đã kiểm chứng khôi phục khớp 27 bảng/2164 dòng trên `doan_cnw_dev`);
  - `D:\Study\xampp\www\doan_cnw_truoc_migrate_that_20261116_2235.sql` — 382,3 KB
    (bản ngay trước khi áp thật);
  - `storage/app/backups/doan_cnw-20261116-152541.sql` — bản của `sao-luu:csdl`.
  **Người dùng cần chép các tệp này ra USB/ổ khác.**
- `he-thong:kiem-tra` sau áp: còn **1 FAIL có sẵn từ trước** — thiếu
  `SEPAY_WEBHOOK_API_KEY` trong `.env` (webhook SePay sẽ bị 401; cần người dùng
  cấu hình, không liên quan migration). WARN còn lại: Telegram thiếu token,
  chưa chạy `sepay:doi-soat`, `APP_ENV=local`, `APP_DEBUG` bật, chưa cache
  config/route/view, `LOG_STACK` chưa phải `daily`.
- Guard `KiemTraSchema`/`Schema::hasColumn`: **GIỮ NGUYÊN**, chỉ gỡ sau ít nhất
  một tuần chạy ổn định (theo dõi log lỗi thiếu cột).
- DB diễn tập `doan_cnw_dev` được **giữ lại** (chứa schema mới nhất, dữ liệu =
  snapshot 22:16) để đối chiếu; xóa khi không cần nữa.
- Chi tiết đầy đủ: `docs/BAO_CAO_AP_MIGRATION.md`.

**2026-11-17 ~06:5x** — đã áp thành công 3 migration tin tức lên `doan_cnw`
(xác nhận của Lê Văn Định 14:38). Commit code trước đó: `12dfd10` (Google OAuth),
`e8cb5d2` (tin tức).

- Danh sách đã áp: `2026_11_17_000001_create_news_topics_table`,
  `2026_11_17_000002_create_news_posts_table`,
  `2026_11_17_000003_create_news_comments_table` — toàn bộ CREATE TABLE mới,
  không ALTER bảng cũ.
- Kiểm chứng: số dòng mọi bảng cũ **không đổi** (chỉ `migrations` 41→44);
  3 bảng mới InnoDB; `TinTucSeeder` idempotent (3 chủ đề + 3 bài viết);
  819 tests xanh trên `doan_cnw_dev`; smoke test `/bai-viet` 200.
- Sao lưu: `D:\Study\xampp\www\backups\doan_cnw_truoc_migrate_tintuc_20261117_0634.sql`
  (~395 KB, 27 bảng, đã kiểm chứng khôi phục khớp số dòng trên `doan_cnw_dev`).

**2026-11-17 ~12:0x** — đã áp thành công 1 migration email đơn online lên
`doan_cnw` (xác nhận của Lê Vă Định trong phiên, commit `03938c0`).

- Danh sách đã áp: `2026_11_17_000004_add_email_dat_to_invoices_table` —
  `ALTER TABLE invoices ADD email_dat varchar(150) NULL` (additive, nullable).
- Kiểm chứng: số dòng mọi bảng **không đổi** (chỉ `migrations` 44→45);
  engine toàn InnoDB; `migrate:status` 0 pending; 825 tests xanh.
- Sao lưu: `D:\Study\xampp\www\backups\doan_cnw_truoc_migrate_email_that_20261117_1158.sql`
  (~404 KB) + bản diễn tập `doan_cnw_truoc_migrate_email_20261117_0706.sql` (~407 KB).
- Sau áp: bật `POS_DAT_HANG_ONLINE_BAT=true` trong `.env` local
  (`POS_GIAO_TAN_NOI_BAT` giữ `false`); smoke test `/dat-hang`, `/don-hang/tra-cuu` OK.
- `demo:kiemtra-du-lieu` báo `customers=101` vs kỳ vọng 100 — **lệch dữ liệu có
  sẵn từ trước** (khách đăng ký thêm), không liên quan migration (migration chỉ
  thêm cột `invoices.email_dat`).
- `he-thong:kiem-tra` sau áp: vẫn 1 FAIL có sẵn — thiếu `SEPAY_WEBHOOK_API_KEY`.

## 2. Quy trình nâng cấp an toàn

```powershell
# 0) Sao lưu trước (ghi vào storage/app/backups)
php artisan sao-luu:csdl

# 1) Thử nạp bản sao lưu vào DB TẠM để chắc file backup dùng được
#    (tạo DB tạm, ví dụ doan_cnw_restore_test, nạp file .sql vừa tạo)

# 2) Diễn tập trên DB dev — KHÔNG dùng doan_cnw_copy (đó là DB test MySQL-copy)
#    Tạo bản dev từ dump:
#      mysqldump -u root doan_cnw > doan_cnw_truoc_migrate.sql
#      mysql -u root -e "CREATE DATABASE doan_cnw_dev CHARACTER SET utf8mb4"
#      mysql -u root doan_cnw_dev < doan_cnw_truoc_migrate.sql
#    Trỏ .env tạm sang doan_cnw_dev rồi:
php artisan migrate --pretend          # xem SQL sẽ chạy, chưa ghi
php artisan migrate --force            # áp thật trên DB dev
#    Đối chiếu:
php artisan tinker --execute="dump(Schema::getColumnListing('invoices'));"   # hoặc information_schema
php artisan he-thong:kiem-tra           # không còn FAIL
php artisan down && php artisan up      # tắt/bật bảo trì đúng quy trình

# 3) Áp lên doan_cnw thật: lặp lại bước 2 với DB thật SAU GIỜ CA
#    php artisan migrate --pretend  →  migrate --force  →  he-thong:kiem-tra

# 4) Kế hoạch hoàn tác: php artisan migrate:rollback --step=N (down() có điều kiện)
#    hoặc khôi phục từ file sao lưu bước 0 nếu rollback không đủ.
```

## 3. Biến môi trường production

| Biến | Bắt buộc? | Mặc định | Ý nghĩa |
|------|-----------|----------|---------|
| `APP_ENV` | ✅ | `production` | Môi trường chạy |
| `APP_DEBUG` | ✅ | `false` | BẮT BUỘC false trên production |
| `APP_URL` | ✅ | — | Phải khớp địa chỉ truy cập thật (ảnh QR, link) |
| `APP_KEY` | ✅ | — | Khóa mã hóa; không đổi sau khi có dữ liệu |
| `POS_BANK_ACCOUNT` / `POS_BANK_CODE` / `POS_BANK_NAME` | ✅ (POS QR) | — | TK ngân hàng nhận chuyển khoản; thiếu → tạo QR báo 422 thân thiện |
| `POS_QR_TEMPLATE` | tùy chọn | `compact2` | Mẫu QR VietQR |
| `POS_CHO_THANH_TOAN_PHUT` | tùy chọn | `15` | Hạn giữ đơn chờ thanh toán |
| `SEPAY_WEBHOOK_API_KEY` | ✅ (webhook) | — | Khóa xác thực webhook SePay gọi vào |
| `SEPAY_API_TOKEN` | tùy chọn | trống | Khóa đối soát chủ động (KHÁC webhook key); trống = tắt đối soát |
| `SEPAY_API_BASE_URL` / `SEPAY_API_VERSION` | tùy chọn | `https://userapi.sepay.vn/v2` / `v2` | Endpoint SePay API |
| `SEPAY_API_TIMEOUT` / `_RETRIES` / `_PER_PAGE` / `_MIN_INTERVAL_MS` / `_MAX_RETRY_AFTER` | tùy chọn | 15 / 3 / 100 / 350 / 30 | Timeout, retry, phân trang, nhịp gọi, trần Retry-After |
| `SEPAY_DOI_SOAT_TELEGRAM_TOI_DA` | tùy chọn | `5` | Số tin Telegram chi tiết mỗi lần đối soát |
| `TELEGRAM_BOT_TOKEN` / `TELEGRAM_CHAT_ID` | tùy chọn | trống | Thông báo vận hành; trống = tắt |
| `POS_PHI_GIAO_HANG` | tùy chọn | `30000` | Phí giao hàng đơn online |
| `POS_MIEN_PHI_GIAO_TU` | tùy chọn | `0` | Miễn phí ship từ đơn này |
| `POS_GIAO_HANG_TINH` | tùy chọn | trống | Tỉnh/thành được giao (trống = mọi nơi?) — XÁC NHẬN nghiệp vụ |
| `POS_ONLINE_PICKUP_GIO` | tùy chọn | `4` | Số giờ giữ đơn pickup |
| `POS_ONLINE_QR_PHUT` | tùy chọn | `30` | Hiệu lực mã QR |
| `POS_ONLINE_GIA_HAN_TOI_DA_GIO` | tùy chọn | `24` | Trần tổng giờ sau gia hạn |
| `POS_ONLINE_GIU_TOI_DA_PHAN_TRAM` | tùy chọn | `50` | Trần % tồn giữ cho đơn online |
| `POS_ONLINE_MAX_DON_MO` / `_MAX_SOLUONG` / `_MAX_SANPHAM` / `_THROTTLE_DAT` | tùy chọn | 3 / 10 / 10 / `5,1` | Chống spam đặt hàng |
| `POS_ONLINE_TU_DONG_XAC_NHAN` | tùy chọn | `false` | true → webhook tự đặt `da_xac_nhan` cho đơn giao |
| `POS_DAT_HANG_ONLINE_BAT` | tùy chọn | **`false`** | Cờ tổng mở đặt hàng online |
| `POS_GIAO_TAN_NOI_BAT` | tùy chọn | **`false`** | Cờ giao tận nơi (chỉ ý nghĩa khi cờ trên bật) |
| `HOTLINE` | tùy chọn | `1900 0000` | Hotline hiển thị storefront (footer, thanh tin cậy, thông báo giỏ hàng khi cờ tắt) — nguồn duy nhất `config/storefront.php` |
| `LOG_CHANNEL` / `LOG_LEVEL` / `LOG_STACK` / `LOG_DAILY_DAYS` | tùy chọn | `stack`/`debug`… | Logging; production nên `daily` + `info` |
| `CACHE_STORE` / `SESSION_DRIVER` / `QUEUE_CONNECTION` | ✅ | `database`/`database`/`database` | Production nên `database` (hoặc redis nếu có) |
| `DB_*` | ✅ | — | Trỏ `doan_cnw` |

## 4. Guard có thể gỡ SAU KHI migrate xong

Giữ guard là an toàn (idempotent, cache theo request); chỉ gỡ nếu muốn code sạch.
Nếu gỡ mà DB rollback/thiếu cột → 500. **Đề xuất: giữ guard, chi phí ~0.**

| Vị trí | Guard | Gỡ khi cột/bảng đã chắc chắn có |
|--------|-------|----------------------------------|
| `app/Support/KiemTraSchema.php` | toàn bộ cache schema | không cần gỡ (là tiện ích) |
| `app/Models/Concerns/GhiNhatKy.php:33,51` | `coBang/coCot('activity_logs')` | sau migration 11_15_000001 |
| `app/Services/NhatKyService.php:25,45` | như trên | sau migration 11_15_000001 |
| `app/Http/Middleware/CheckKhoaLuc.php`, `CheckBatBuocDoiMatKhau.php` | đọc thuộc tính user đã nạp (không gọi schema mỗi request) | sau migration 11_15_000002 |
| `app/Http/Controllers/TaiKhoanNhanVienController.php:315` | `coCot('users','khoa_luc'/'bat_buoc_doi_mat_khau')` | sau migration 11_15_000002 |
| `app/Http/Controllers/NhatKyController.php:41` | `Schema::hasColumn('activity_logs','nguon')` | sau migration 11_15_000001 |
| `app/Http/Controllers/DonHangOnlineController.php:50,56,81,128,241,264,273,310-312` | cột online/giao/hoàn tiền | sau migration 11_12/11_14/11_16 (đã Ran) + 11_16_000001 |
| `app/Services/ThanhToanChuyenKhoanService.php:149,159,287,423,474,480` | `kenh`, `hinh_thuc_nhan`, `trang_thai_giao`, `can_hoan_tien`, `ghi_chu_noi_bo` | sau các migration tương ứng |
| `app/Models/DonHang.php:110` | `Schema::hasColumn('invoices','kenh')` | sau migration 11_12_000001 (đã Ran) |
| `app/Rules/AnhSanPhamUrl.php:22` | `doDaiCotVarchar('products','hinh_anh')` | sau migration 11_13_000002 (đã Ran) — giữ cũng được |

## 5. Dựng máy cửa hàng (production)

```powershell
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan storage:link
# .env production: APP_ENV=production APP_DEBUG=false APP_URL=https://<địa-chỉ-thật>
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache   # nếu dùng
# Windows Task Scheduler: chạy mỗi phút
php artisan schedule:run
# Tạo tài khoản nhân viên thật (admin → /taikhoan), KHÓA tài khoản demo/seeder.
```

## 6. Quy trình bật đặt hàng online (sau khi migrate + kiểm tra tay)

Hai cờ `POS_DAT_HANG_ONLINE_BAT` + `POS_GIAO_TAN_NOI_BAT` mặc định **false**.
Chỉ bật sau khi kiểm tra tay đủ 8 bước trên máy thật:

1. Đặt đơn QR pickup → mã QR hiện đúng `APP_URL`.
2. Mô phỏng tiền về (SePay test webhook hoặc `ThanhToanChuyenKhoanService` trên dev) → đơn `da_thanh_toan`, kho trừ đúng 1 lần.
3. Đơn `giao_tan_noi` đã thanh toán → màn hình "Đơn online" hiện badge → chuyển `cho_xac_nhan → da_xac_nhan → dang_giao → da_giao` đúng thứ tự, sai thứ tự bị từ chối.
4. In phiếu giao hàng: đủ người nhận/SĐT/địa chỉ/SP/phí ship/tổng/"ĐÃ THANH TOÁN", không giá vốn.
5. Hủy đơn đã thanh toán (admin, có lý do) → hoàn kho 1 lần, thu hồi điểm không âm, vào danh sách "Đơn cần hoàn tiền", Telegram không PII.
6. Đánh dấu "Đã hoàn tiền" (admin, có ghi chú) → biến mất khỏi danh sách.
7. Tài khoản thủ kho: thấy chuyển trạng thái + phiếu giao; KHÔNG thấy nút thu ngân (xác nhận tiền/hủy chờ/gia hạn) và không hủy được đơn đã thanh toán (403).
8. Đơn quá hạn: `donhang:huy-qua-han` hủy đúng giờ, hàng hoàn kho, không còn xác nhận được.

Sau 8 bước đạt → đặt `POS_DAT_HANG_ONLINE_BAT=true` (và `POS_GIAO_TAN_NOI_BAT=true`
nếu giao tận nơi), `php artisan config:cache`, theo dõi `he-thong:kiem-tra` + Telegram.

## 7. Việc còn lại / cần xác nhận

- Migration `ma_tham_chieu` (mục 1 #1) chưa có guard `hasColumn` — thêm trước khi áp
  hoặc chấp nhận rủi ro thấp (bảng `payment_webhooks` chắc chắn tồn tại).
- "Cần người dùng xác nhận": phí ship thật, vùng giao (`POS_GIAO_HANG_TINH`),
  hoàn phí ship khi trả hàng, thời hạn gia hạn tối đa, định nghĩa doanh thu.
