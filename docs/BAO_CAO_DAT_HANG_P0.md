# Báo cáo P0 + PA1 — Đặt hàng online (schema + quy tắc trần giữ pickup)

> Pha: **P0** (theo `docs/PROMPT_AGENT_DAT_HANG_ONLINE.md` mục 2) mở rộng **PA1**
> (quy tắc miền trần giữ hàng pickup). Chỉ làm P0+PA1, **dừng tại đây** — không P1,
> không merge, không áp migration lên MySQL thật `doan_cnw`.

## 1. Tóm tắt

- **Nhánh:** `feat/dat-hang-p0-schema`, tạo từ nhánh nền `docs/thiet-ke-dat-hang`
  (giả định nền — người dùng không chỉ định lại trong phiên này; xem mục 6).
- **Commit:**
  - `a63da70` — `test:` test đỏ T-DH01/T-DH02 (schema invoices, config, fallback "Đơn online").
  - `940ca6a` — `feat:` schema M1 + config + fallback "Đơn online" + `GiuHangOnlineService` (trần giữ pickup) + test. 11 file, +327/−12.
  - `docs:` (commit này) — README + `THIET_KE_DAT_HANG_ONLINE.md` (D4, §6.4) + báo cáo này.
- **Phạm vi đã làm:** migration M1 (6 cột mới + nullable hóa `user_id`), `DonHang`
  fillable/cast, config online (pickup 4h, trần 50%), fallback "Đơn online" ở 3 nơi
  đọc `invoices.user_id`, service miền chỉ-đọc `GiuHangOnlineService` + 8 test.
- **Không làm:** route/UI/guest input, gia hạn/hủy đơn online, áp migration production.

## 2. Kết quả test

### 2.1 Baseline → sau

| Mốc | Pass | Skip | Assertions | Thời gian |
|---|---|---|---|---|
| Baseline (trước P0, README cũ) | 338 | 38 | 1714 | — |
| Sau triển khai P0 (chưa service) | 347 | 39 | 1766 | 311.18s |
| **Sau P0 + PA1 (full suite cuối)** | **355** | **39** | **1790** | **348.76s** |

- Tổng test: 376 → **394** (+18: T-DH01 9 + T-DH02 1 + trần giữ 8).
- Skip 38 → 39: **chỉ** tăng 1 do `DatHangP0MysqlCopyTest` mới (ngoại lệ MySQL-copy
  được phép theo mục 6.5 prompt). Không giảm test, không tăng skip ngoài MySQL-copy.
- Chạy: `XDEBUG_MODE=off php artisan test --compact` (SQLite `:memory:`, **không** đặt
  env mysql cho full suite — tránh `RefreshDatabase` migrate:fresh bản sao).

Đoạn kết quả thật (full suite cuối, `XDEBUG_MODE=off`):

```
Tests:    39 skipped, 355 passed (1790 assertions)
Duration: 348.76s
```

### 2.2 Test T-DH mới + trạng thái

| Test | File | Số ca | Assertions | Trạng thái |
|---|---|---|---|---|
| T-DH01 (SQLite) | `DatHangP0Test` | 9 | 53 | ✅ xanh (từng đỏ ở `a63da70`) |
| T-DH02 (MySQL copy) | `DatHangP0MysqlCopyTest` | 1 | 33 | ✅ xanh trên `doan_cnw_copy` (skip trên SQLite) |
| Trần giữ pickup (PA1) | `GiuHangOnlineServiceTest` | 8 | 23 | ✅ xanh (từng đỏ trước khi viết service) |

T-DH01 phủ: cột mới đúng kiểu/nullable/index, `user_id` nhận null, `kenh` mặc định
`pos`, `ma_tra_cuu` unique (nhiều NULL cùng tồn tại), enum giữ nguyên, `down()` có
điều kiện, config online, fallback "Đơn online" ở view in/danh sách/export.

PA1 phủ: tồn 10→trần 5; giữ 3 (tồn hiện tại 7)→tồn gốc vẫn 10→trần vẫn 5 (không co);
vượt trần từ chối kèm số còn lại; tồn 1→trần 1 (cho giữ 1); không đếm QR/POS/đã trả/đã
hủy; nhiều sản phẩm độc lập; đổi config % đổi trần; số lượng 0/âm từ chối.

### 2.3 Toàn bộ 39 test skip (tên, lý do, phân loại)

Tất cả 39 skip thuộc phân loại **"cần MySQL"** — SQLite `:memory:` không có khóa
dòng/InnoDB, không cưỡng chế độ dài VARCHAR, không phải MySQL strict để thử migration.
**Không** có skip cố ý (intentional) hay lý do nào khác. Cả 3 nhóm **đã chạy thật trên
`doan_cnw_copy`** (qua biến môi trường tiến trình, không sửa `.env`):

| Nhóm skip | Số ca | Lý do skip trên SQLite | Assertions khi chạy thật trên copy |
|---|---|---|---|
| `KhoaDongMysqlTest` | 1 | Khóa dòng `SELECT ... FOR UPDATE` cần MySQL/InnoDB | **7** |
| `AnhUrlMysqlCopyTest` | 37 | Cột VARCHAR(150) thật + strict mode (35 sweep URL + biên 150/151 + multibyte legacy) | **243** |
| `DatHangP0MysqlCopyTest` | 1 | Migration M1 up/down cần MySQL strict (enum/FK giữ nguyên) | **33** |
| **Tổng** | **39** | | **283** |

Lệnh chạy copy (mỗi lệnh một tiến trình, shell không giữ env giữa các lần gọi):

```bash
export DB_CONNECTION=mysql DB_DATABASE=doan_cnw_copy DB_USERNAME=root DB_PASSWORD='' XDEBUG_MODE=off
php artisan test --filter=KhoaDongMysqlTest --compact      # 1 passed (7 assertions)
php artisan test --filter=AnhUrlMysqlCopyTest --compact    # 37 passed (243 assertions)
php artisan test --filter=DatHangP0MysqlCopyTest --compact # 1 passed (33 assertions)
```

Các test copy **không** dùng `RefreshDatabase` (giữ cột VARCHAR(150) thật), tự dọn bản
ghi trong `try/finally`, guard skip trừ khi driver `mysql` + tên DB kết thúc `_copy`.

## 3. Thay đổi theo file

| File | Thay đổi | T/R liên quan |
|---|---|---|
| `database/migrations/2026_11_12_000001_add_online_order_fields_to_invoices_table.php` | **Mới** — M1: 6 cột + nullable `user_id` + enum re-declare (SQLite); `down()` có điều kiện | T-DH01/02, R1 |
| `app/Models/DonHang.php` | fillable +6 cột; cast `han_giu => datetime` | M1, T-DH01 |
| `config/pos.php` | `online_pickup_gio` 24→4; thêm `online_giu_toi_da_phan_tram`=50 | D4, PA1 |
| `.env.example` | +6 dòng `POS_ONLINE_*` placeholder (không secret) | D4, PA1 |
| `app/Services/GiuHangOnlineService.php` | **Mới** — service miền chỉ-đọc: `dangGiuPickup`/`tonGoc`/`tran`/`kiemTra` | PA1, T3, §6.4 |
| `resources/views/donhang/hoadon.blade.php` | `user?->name ?? 'Đơn online'` | T-DH01, R? (in hóa đơn) |
| `resources/views/donhang/danhsach.blade.php` | `user?->name ?? 'Đơn online'` | T-DH01 (danh sách đơn) |
| `app/Exports/HoaDonExport.php` | `user?->name ?? 'Đơn online'` | T-DH01 (xuất báo cáo) |
| `tests/Feature/DatHangP0Test.php` | T-DH01 (9 ca); sửa assert view + enum/unique catch | T-DH01 |
| `tests/Feature/DatHangP0MysqlCopyTest.php` | T-DH02; `cotInvoices()` stdClass→array | T-DH02 |
| `tests/Feature/GiuHangOnlineServiceTest.php` | **Mới** — 8 ca trần giữ pickup | PA1, T3 |
| `README.md` | bảng env +6 dòng; mục 9/17 cập nhật 394/355/39/1790 + entry test mới | docs |
| `docs/THIET_KE_DAT_HANG_ONLINE.md` | D4 24h→4h + trần 50%; §6.4 mới; T3/T-DH13 khớp | docs |
| `docs/BAO_CAO_DAT_HANG_P0.md` | **Mới** — báo cáo này | docs |

## 4. Migration M1

### 4.1 Nội dung

`up()`:
- `Schema::table('invoices')` thêm: `kenh` string(10) default `pos` index (after id);
  `ma_tra_cuu` string(20) nullable unique; `ten_khach_dat` string(100) nullable;
  `so_dienthoai_dat` string(15) nullable index; `ghi_chu_khach` string(500) nullable;
  `han_giu` timestamp nullable.
- `Schema::table` thứ hai: `user_id` unsignedBigInteger **nullable** `->change()`.
  Riêng SQLite: khai báo lại `trang_thai` enum `['cho_thanh_toan','da_thanh_toan','da_huy']`
  default `da_thanh_toan` + `phuong_thuc` enum `['tienmat','chuyenkhoan']` default
  `tienmat` (cùng `->change()`) để giữ CHECK constraint (xem mục 5).

`down()`:
- Đếm `whereNull('user_id')`; nếu > 0 → `RuntimeException` "Không thể rollback: còn
  {N} hóa đơn có user_id NULL (đơn online). Hãy xử lý các đơn này trước khi rollback."
- Ngược lại: dropIndex `kenh`/`so_dienthoai_dat`, dropUnique `ma_tra_cuu`, dropColumn 6
  cột; `user_id` `nullable(false)->change()` + SQLite re-declare enum.

**Không thêm giá trị enum mới. Không tạo FK mới** (DB thật 0 FK — L7).

### 4.2 Kết quả up/down

- **SQLite (`:memory:`):** T-DH01 (9 ca, 53 assertions) ✅ — up tạo đúng cột/nullable/
  index, `user_id` nhận null, `kenh` default `pos`, `ma_tra_cuu` unique cho phép nhiều
  NULL, enum giữ nguyên, `down()` chặn khi còn dòng null-user và rollback sạch khi không.
- **MySQL copy (`doan_cnw_copy`, strict):** T-DH02 (1 ca, 33 assertions) ✅ — up/down
  thật, enum + FK giữ nguyên, dữ liệu POS nguyên vẹn, `down()` chặn khi còn đơn
  `user_id` NULL. Tự dọn, chạy lại được.

### 4.3 Quy trình áp lên production (người vận hành, KHÔNG phải agent)

1. **Backup trước:** `php artisan sao-luu:csdl`.
2. **Áp off-peak** (bảng `invoices` có thể lớn; `->change()`/MODIFY + thêm index):
   ```bash
   php artisan migrate --path=database/migrations/2026_11_12_000001_add_online_order_fields_to_invoices_table.php --force
   ```
3. **Đối chiếu `information_schema`** sau áp: 6 cột mới đúng kiểu/nullable/default,
   `user_id` nullable, enum `trang_thai`/`phuong_thuc` **không đổi**, index `kenh`/
   `so_dienthoai_dat` + unique `ma_tra_cuu` tồn tại. (Tiền lệ lệch 150 vs 255 — L8.)
4. **Rollback:** `down()` tự chặn nếu còn đơn online (`user_id` NULL) — phải xử lý các
   đơn đó trước. Kịch bản xấu nhất: restore backup. **Diễn tập rollback trên copy trước.**

Agent **không** chạy migration trên `doan_cnw`; chỉ viết + chứng minh trên SQLite/copy.

## 5. Sai khác so với thiết kế + xử lý mục 8

### 5.1 Sai khác (có chủ đích, theo chỉ thị PA1 của người dùng)

- **D4: pickup 24h → 4h** (giữ khóa `online_pickup_gio`, env `POS_ONLINE_PICKUP_GIO`,
  **không đổi tên khóa**). QR giữ 30 phút. Lý do: chỉ thị PA1 chốt hạn ngắn chống ôm hàng.
- **Thêm trần giữ pickup 50% tồn gốc** (`online_giu_toi_da_phan_tram`, env
  `POS_ONLINE_GIU_TOI_DA_PHAN_TRAM`) — quy tắc miền mới ngoài thiết kế gốc, hiện thực
  bằng `GiuHangOnlineService` (chỉ-đọc) + §6.4 tài liệu.
- **Cast `han_giu => datetime`** thêm vào `DonHang` (cột timestamp cần cast để dùng
  Carbon; thiết kế không nêu nhưng cần cho nhất quán).
- **Commit dùng tiếng Việt CÓ dấu** — prompt §6.7 ghi "không dấu như lịch sử repo"
  nhưng lịch sử thực tế (`git log`) dùng có dấu → tin mã/lịch sử thật, ghi nhận sai khác.
- **Workaround enum SQLite:** `->change()` dựng lại bảng làm mất CHECK enum (giới hạn
  framework). M1 khai báo lại enum y nguyên, guard theo driver. Tiền lệ mất CHECK
  `phuong_thuc` do migration `000007` (thêm FK) dựng lại bảng; M1 khôi phục. MySQL
  `->change()` = MODIFY, không đụng enum.

### 5.2 Xử lý các điểm mục 8 (bảo thủ, ghi nhận cho pha sau)

- **Polling `sdt` trên URL (T5):** thuộc P1/P2 — P0 **không** thêm route nào; ghi lại
  cho pha sau chọn phương án an toàn (session/POST).
- **Hai cột `ma_giaodich`/`ma_giao_dich`:** P0 không tạo/hoàn tất đơn mới → giữ nguyên
  hành vi ghi cả hai; không gộp/bỏ cột.
- **Webhook không kiểm tra hạn (T-CK18):** P0 không đụng webhook → giữ nguyên; `han_giu`
  chỉ ảnh hưởng scheduler/hủy lười (R7) ở pha sau.
- **Giữ kho ngay khi tạo đơn (R1):** service `tonGoc = soluong hiện tại + dangGiuPickup`
  reconstruct tồn gốc để trần không co theo mỗi đơn; **không** trừ kho lần hai.
- **Cart GET→POST (L4):** chỉ đổi ở P5 — P0 giữ nguyên link GET hiện tại.
- **Seeder guest-storefront:** user tạo trong seeder chưa dùng ở P0 (quyết định P1).
- **`kho/lichsu.blade.php:10` (stock_movements null-user):** movement của đơn online có
  thể `user_id` null — view này đọc bảng khác `invoices`, **rủi ro P1** phải null-safe;
  P0 chưa đụng (ngoài phạm vi 3 nơi đọc `invoices.user_id`).

## 6. Việc chưa làm / rủi ro còn lại / câu hỏi

### 6.1 Rủi ro race (CHƯA có test đồng thời thật)

`GiuHangOnlineService` **chỉ đọc + tính, không tự khóa**. **P1 BẮT BUỘC** gọi
`kiemTra()` trong cùng `DB::transaction` với `lockForUpdate` sản phẩm (khóa theo **id
tăng dần**, đúng mẫu R1/`ThanhToanChuyenKhoanService`) **trước khi trừ kho**; nếu không,
hai khách đặt đồng thời có thể cùng vượt trần. Ghi chú này có trong cả mã (docblock
service) và tài liệu (§6.4). **Không có test đồng thời thật** — chỉ test tuần tự.

### 6.2 Gia hạn/hủy đơn online — để P1 (kiểm tra chỉ-đọc POS hiện có)

POS **đã có** thao tác **hủy** đơn `cho_thanh_toan` (hoàn kho + điểm), nhưng **chưa có
gia hạn**:

- **Route:** `donhang.huycho` — `POST /donhang/huycho/{donHang}`
  ([routes/web.php:56](../routes/web.php#L56)), trong nhóm `auth + staff.only`
  ([:39](../routes/web.php#L39)) + `role:admin,thungan` ([:46](../routes/web.php#L46)).
- **Controller:** `postHuyCho`
  ([DonHangController.php:134](../app/Http/Controllers/DonHangController.php#L134)) —
  chặn nếu `trang_thai !== 'cho_thanh_toan'` (:136), gọi
  `huyChoThanhToan($donHang, 'Thu ngân hủy chờ thanh toán')` (:140).
- **Service:** `huyChoThanhToan`
  ([ThanhToanChuyenKhoanService.php:228](../app/Services/ThanhToanChuyenKhoanService.php#L228))
  — `DB::transaction`, `lockForUpdate` sản phẩm orderBy id (:249) + khách (:255) +
  invoice recheck (:259); **hoàn kho** `increment('soluong')` (:274) + movement
  `dieuchinh` dương; **hoàn điểm** (:289); đặt `da_huy` + ghi chú (:294).
- **Gia hạn:** chưa có route/action nào → **P1 phải thêm** (P0 không thêm UI gia hạn/hủy).

### 6.3 Chưa làm / câu hỏi cần người dùng quyết

- **Không merge, không push, không áp migration lên `doan_cnw`** (đúng chỉ thị). Chờ
  lệnh riêng của người dùng.
- **Nhánh nền:** giả định tạo từ `docs/thiet-ke-dat-hang`. Nếu người dùng muốn nền khác
  (vd `main`/`dev`), cần xác nhận trước khi merge.
- **Mặc định 4h / 50%:** đã chốt theo PA1; nếu muốn chỉnh trước khi lên production,
  sửa config/env (không cần migration).
- **P1 trở đi:** route/UI/guest input, tra cứu, webhook online, scheduler hủy lười,
  gia hạn/hủy đơn online, null-safe `stock_movements` view — **chưa làm**.

## 7. Checklist mục 7 (tự rà soát)

| # | Mục | Đạt? | Bằng chứng |
|---|---|---|---|
| 1 | Không enum mới; migration chỉ thêm cột nullable/default + nullable `user_id`; `down()` có điều kiện + ghi chú rủi ro null-user | ✅ | M1 §4.1; `down()` đếm null-user → RuntimeException; T-DH01/02 |
| 2 | Mọi nơi đọc `invoices.user_id` chịu null → "Đơn online" | ✅ | 3 nơi: `hoadon.blade.php:34`, `danhsach.blade.php:31`, `HoaDonExport.php:18`. (Bảng khác `stock_movements` — rủi ro P1, mục 5.2) |
| 3 | POS cũ không đổi hành vi; test POS/webhook/trả hàng/báo cáo vẫn xanh | ✅ | Full suite 355 pass, không sửa test cũ để cho qua |
| 4 | Không nhận giá/tổng/`customer_id`/`user_id`/trạng thái từ request guest | ✅ | P0 không route/guest input; service chỉ-đọc |
| 5 | Dữ liệu khách escape khi render + Telegram; không PII đầy đủ trong log | ✅ | P0 không render/log dữ liệu khách mới; fallback dùng `{{ }}` sẵn có |
| 6 | Route công khai mới có throttle+CSRF; route quản trị trong nhóm sẵn có | ✅ (N/A) | P0 **không** thêm route nào |
| 7 | Mỗi test T-DH tồn tại + từng đỏ trước khi xanh | ✅ | `a63da70` (test đỏ T-DH01/02); 8 ca trần giữ đỏ trước khi viết service |
| 8 | Không file tạm/secret/`.env` lọt commit | ✅ | `git diff --stat`: feat 11 file, docs 3 file; `.env.example` chỉ placeholder |

**Kết luận:** Full suite xanh (355 pass / 39 skip / 1790 assertions), cả 8 mục checklist
đạt. 39 skip đều "cần MySQL" và đã chạy thật trên `doan_cnw_copy` (283 assertions).
**Dừng tại P0+PA1** — không P1, không merge, không áp migration lên MySQL thật.