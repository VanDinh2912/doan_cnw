# Báo cáo áp migration lên doan_cnw — 2026-11-16

Áp 5 migration pending lên DB thật `doan_cnw` (MySQL 8.4.7, WAMP
`D:\Study\xampp`). Xác nhận người dùng: Lê Văn Định, 22:13. Tag git:
`backup/truoc-migrate-doan-cnw-20261116`. Không commit dữ liệu/backup.

## 1. Điều kiện tiên quyết — tất cả đạt

- Thư mục `D:\Study\xampp\www\DA`, nhánh `main`, HEAD `43ca81e` (chỉ docs sau
  `e54533e`), git status/stash sạch; đủ file bắt buộc.
- `migrate:status`: đúng **5 Pending** khớp tên trong sổ tay.
- MySQL `mysql.exe -u root` không mật khẩu kết nối được; `doan_cnw_dev` chưa có.
- Tiến trình `artisan serve` của DA (port 8081) đã tắt theo xác nhận của người
  dùng; serve của larashop (DB khác) giữ nguyên; không có `schedule:work`.
- Full suite paratest trước khi đụng DB: **792 test / 3461 assertions /
  45 skip / 0 fail** (594,47s).
- Chụp trạng thái TRƯỚC: 27 bảng, toàn InnoDB, `activity_logs` = 67 dòng
  (không bất thường), `SHOW CREATE TABLE` 5 bảng chính đã lưu.

## 2. Sao lưu và kiểm chứng

| Bước | Kết quả |
|---|---|
| `php artisan sao-luu:csdl` (DB_DUMP_BINARY chỉ đường mysqldump) | `storage/app/backups/doan_cnw-20261116-152541.sql` — 382,3 KB |
| `mysqldump --single-transaction --routines --result-file` | `D:\Study\xampp\www\doan_cnw_truoc_migrate_20261116_2216.sql` — 391.296 byte, 27 CREATE TABLE, "Dump completed" |
| Tạo `doan_cnw_dev` + nạp dump | OK |
| **Kiểm chứng khôi phục** | **27/27 bảng khớp số dòng (tổng 2.164 dòng), engine InnoDB, tiếng Việt đúng dấu** |

## 3. Diễn tập trên doan_cnw_dev — sạch

- `migrate --pretend`: chỉ `ALTER TABLE ... ADD COLUMN` (nullable hoặc
  `DEFAULT 0/'web'`) + `ADD INDEX`; **không** DROP/TRUNCATE/DELETE/RENAME/UPDATE.
- `migrate --force`: 5/5 DONE (~0,54s); `migrate:status` 0 pending.
- Số dòng mọi bảng dữ liệu **không đổi** (chỉ `migrations` +5 bản ghi);
  engine InnoDB; đủ cột mới.
- `he-thong:kiem-tra`: 1 FAIL **có sẵn từ trước** (thiếu `SEPAY_WEBHOOK_API_KEY`
  trong `.env` — người dùng đã xác nhận tiếp tục; không liên quan migration,
  hiện diện cả trên DB thật trước khi áp). WARN: Telegram token, chưa chạy
  `sepay:doi-soat`, `APP_ENV=local`, `APP_DEBUG` bật, chưa cache, `LOG_STACK`.
- `demo:kiemtra-du-lieu`: báo "customers không đúng" (101 vs 100 kỳ vọng của
  bộ seed demo) — **có sẵn trên DB thật**, không phải do migration; các kiểm tra
  toàn vẹn phía sau chạy thủ công thay thế: SKU trùng 0, mã GD trùng 0,
  hóa đơn/movement mồ côi 0, đối soát `products.soluong` vs `stock_movements`
  **0 lệch** trên cả hai DB.
- Smoke HTTP trên dev (`serve :8099`): `/`, `/gio-hang`, `/dangnhap`,
  `/dang-ky` → 200; `laravel.log` không phát sinh ERROR mới.
- MySQL-copy `--filter='MysqlCopyTest|KhoaDongMysqlTest'` trên `doan_cnw_copy`:
  **45 pass / 373 assertions** — không hồi quy.

## 4. Áp lên doan_cnw — thành công

- Sao lưu lại ngay trước khi áp:
  `D:\Study\xampp\www\doan_cnw_truoc_migrate_that_20261116_2235.sql` —
  391.296 byte; số dòng mọi bảng khớp snapshot TRƯỚC (dữ liệu không đổi
  trong lúc diễn tập).
- `php artisan down --retry=60` → bảo trì; chụp mốc cuối 27 bảng.
- `migrate --pretend` trên DB thật: **giống hệt** kết quả diễn tập.
- `php artisan migrate --force` — từng migration DONE:

  | Migration | Thời gian |
  |---|---|
  | `add_ma_tham_chieu_to_payment_webhooks_table` | 176,61 ms |
  | `extend_activity_logs_for_audit` | 246,81 ms |
  | `add_khoa_luc_and_bat_buoc_doi_mat_khau_to_users_table` | 43,20 ms |
  | `add_can_hoan_tien_to_invoices_table` | 75,37 ms |
  | `add_tien_hoan_phi_giao_to_returns_table` | 28,23 ms |

  Tổng ~0,57 giây, không lỗi giữa chừng.

- Kiểm chứng sau áp: `migrate:status` **0 pending**; số dòng mọi bảng dữ liệu
  **bằng mốc cuối** (chỉ `migrations` +5); engine toàn InnoDB; đủ cột mới;
  `he-thong:kiem-tra` WARN "5 migration chưa áp dụng" đã hết (còn FAIL
  SEPAY_WEBHOOK_API_KEY có sẵn như trên).
- `config:clear` + `route:clear` + `view:clear` + `cache:clear` → `php artisan up`.
- Smoke SAU up (chỉ GET công khai, `serve :8099` rồi tắt): `/`, `/gio-hang`,
  `/dangnhap`, `/dang-ky` → 200; `laravel.log` không tăng dòng (0 ERROR mới).

## 5. Việc người dùng cần làm tiếp

1. **Chép 3 tệp sao lưu ra USB/ổ khác** (đường dẫn ở mục 2 và
   `SO_TAY_TRIEN_KHAI.md` mục 1b).
2. Cấu hình `SEPAY_WEBHOOK_API_KEY` trong `.env` nếu dùng webhook SePay
   (hiện webhook sẽ bị 401 — FAIL có sẵn, không phải do migration).
3. Cân nhắc `TELEGRAM_*`, `APP_ENV=production`, `APP_DEBUG=false`,
   `LOG_STACK=daily`, `config:cache/route:cache/view:cache` khi chạy thật.
4. Hai cờ `POS_DAT_HANG_ONLINE_BAT`/`POS_GIAO_TAN_NOI_BAT` vẫn **false** —
   bật sau khi kiểm tra tay theo `SO_TAY_TRIEN_KHAI.md` mục 5.
5. `doan_cnw_dev` đang giữ lại để đối chiếu — xóa khi không cần.
6. Guard schema (`KiemTraSchema`, `Schema::hasColumn`) **giữ nguyên** — chỉ gỡ
   sau ≥ 1 tuần chạy ổn định.

## 6. Trạng thái cuối

- Ứng dụng: **đã `up` (live)**, không còn bảo trì.
- Git: commit `docs:` cập nhật sổ tay + báo cáo này; working tree sạch; không push.
- Biến môi trường tạm (`DB_DATABASE` cho dev/copy): chỉ truyền theo tiến trình,
  không ghi `.env`, không còn sót.
