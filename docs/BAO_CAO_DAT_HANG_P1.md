# Báo cáo P1 — Đặt hàng online guest PICKUP (checkout + tra cứu + gia hạn)

> Pha: **P1** (theo `docs/PROMPT_AGENT_DAT_HANG_ONLINE.md` mục 2/§8). Nhánh
> `feat/dat-hang-p1-checkout` tạo từ nhánh nền `feat/dat-hang-p0-schema`. Phạm vi
> chốt theo quyết định người dùng: **chỉ guest PICKUP + khung khóa chung**; QR
> online để **P2**. **Dừng tại đây** — không merge, không push, không áp migration
> lên MySQL thật `doan_cnw`.

## 1. Tóm tắt

- **Nhánh:** `feat/dat-hang-p1-checkout` (từ `feat/dat-hang-p0-schema`, HEAD nền `23689d1`).
- **Commit (tách theo mối quan tâm, đúng mẫu P0):**
  - `test:` — 4 file test mới (T-DH03..08/12/13 + bảo vệ). Đỏ trước khi xanh: tại
    commit này controller/route/M2 chưa tồn tại nên 27 test SQLite P1 chưa thể xanh.
  - `feat:` — M2 + `CheThongTin` + mở rộng R1 + `DatHangOnlineController` + routes +
    4 view + config gia hạn + Telegram escape + gia hạn thu ngân + null-safe lịch sử
    kho + nút "Đặt hàng" ở giỏ.
  - `docs:` — báo cáo này + README (bảng env, mục 9, mục 17).
- **Phạm vi đã làm (6 điểm bàn giao, xem mục 5.1):** checkout guest pickup giữ hàng
  qua R1 (khóa dòng sản phẩm id tăng dần + `GiuHangOnlineService::kiemTra` trong
  transaction, trước khi trừ kho); tra cứu mã + SĐT (SĐT chỉ trong thân POST);
  polling/trang thái bằng chứng session; khách tự hủy; thu ngân xác nhận/hủy (tái
  dùng R2/R3) + gia hạn có trần config; che SĐT nhật ký/Telegram; escape ghi chú;
  null-safe `stock_movements.user_id`; M2 nullable; test đồng thời khóa dòng thật.
- **Không làm:** QR online checkout (P2); không đổi webhook; không merge/push/áp
  migration production. Scheduler nền đã sửa ở T1 (mục 6.1 — đã xử lý).

## 2. Kết quả test

### 2.1 Baseline → sau

| Mốc | Pass | Skip | Assertions | Tổng test | Thời gian |
|---|---|---|---|---|---|
| Baseline P0+PA1 (HEAD nền `23689d1`) | 355 | 39 | 1790 | 394 | 348.76s |
| **Sau P1 (full suite cuối, SQLite)** | **382** | **41** | **1898** | **423** | **200.94s** |

- Tổng test 394 → **423** (+29: 27 test SQLite P1 + 2 test MySQL-copy P1).
- Pass 355 → 382 (+27 test SQLite P1). Skip 39 → 41 (**chỉ** +2 do
  `DatHangP1MysqlCopyTest` mới — ngoại lệ MySQL-copy được phép). Assertions
  1790 → 1898 (+108 của 27 test SQLite P1; 23 assertions của copy P1 tính riêng
  ở mục 2.3 vì skip trên SQLite).
- Chạy: `XDEBUG_MODE=off php artisan test --compact` (SQLite `:memory:`, **không**
  đặt env mysql cho full suite — tránh `RefreshDatabase` migrate:fresh bản sao).
- Không giảm test, không sửa test cũ để cho qua, không tăng skip ngoài MySQL-copy.

Đoạn kết quả thật (full suite cuối, `XDEBUG_MODE=off`, exit 0):

```
Tests:    41 skipped, 382 passed (1898 assertions)
Duration: 200.94s
```

### 2.2 Test T-DH mới + trạng thái

| Nhóm test | File | Số ca | Trạng thái |
|---|---|---|---|
| T-DH03..07 + trần + đa sản phẩm + M2 (SQLite) | `DatHangP1Test` | 10 | ✅ xanh (đỏ trước khi có controller/R1/M2) |
| T-DH08/12 + T5 tra cứu/polling/hủy (SQLite) | `DatHangP1TraCuuTest` | 7 | ✅ xanh |
| Bảo vệ 6 điểm bàn giao (SQLite) | `DatHangP1BaoVeTest` | 10 | ✅ xanh |
| T-DH02b (M2 up/down) + T-DH13 (khóa dòng) — MySQL copy | `DatHangP1MysqlCopyTest` | 2 | ✅ xanh trên `doan_cnw_copy` (skip trên SQLite) |

3 nhóm SQLite P1 chạy thật: **27 passed (108 assertions), 28.74s**
(`--filter='DatHangP1Test|DatHangP1TraCuuTest|DatHangP1BaoVeTest'`).

Phủ chính:
- **`DatHangP1Test` (10):** checkout tạo đơn giữ kho + movement `user_id` null; giá
  tính lại từ DB (giỏ session mang giá giả 1đ → server tính 99000); vượt tồn không
  tạo đơn; double-submit cùng token chỉ một đơn; token lệch bị từ chối; validate
  thiếu tên/SĐT sai/ghi chú quá dài/phuong_thuc≠pickup; SP ẩn/hết hàng/đã xóa bị từ
  chối toàn bộ; trần pickup 50% áp trong đường tạo đơn (6>5 từ chối, 5=5 được, giữ
  5+1>5 từ chối); nhiều sản phẩm trừ kho đúng từng dòng; M2 `user_id` nullable SQLite.
- **`DatHangP1TraCuuTest` (7):** sai mã + sai SĐT cùng thông báo chung "Không tìm
  thấy đơn hàng"; tra cứu đúng redirect không lộ SĐT trên URL; polling chỉ trả
  `trang_thai` (exactJson, không PII); polling/trang thái yêu cầu bằng chứng session
  (chưa xác thực → 403/redirect); khách tự hủy đơn chờ hoàn kho + movement dieuchinh;
  hủy đơn đã thanh toán bị từ chối; polling hủy lười khi quá hạn giữ.
- **`DatHangP1BaoVeTest` (10):** lịch sử kho null-safe với movement `user_id` null
  ("Đơn online"); nhật ký đặt hàng che SĐT (090****567, không chứa số đầy đủ);
  `ghi_chu_khach` escape khi render (`&lt;script&gt;`); Telegram escape ghi chú + che
  SĐT; thu ngân xác nhận pickup tái dùng R2; thu ngân hủy pickup tái dùng R3 hoàn
  kho; gia hạn cộng giờ trong trần config + ghi nhật ký; gia hạn vượt trần bị từ
  chối (422, không nhật ký); gia hạn chỉ cho đơn pickup online đang chờ (QR 422);
  config có `online_gia_han_toi_da_gio`=24.

### 2.3 Toàn bộ 41 test skip (tên, lý do, phân loại) + kết quả copy thật

Tất cả 41 skip thuộc phân loại **"cần MySQL"** — SQLite `:memory:` không có khóa
dòng/InnoDB, không cưỡng chế độ dài VARCHAR, không phải MySQL strict để thử
migration. **Không** có skip cố ý hay lý do nào khác. Cả 4 nhóm **đã chạy thật trên
`doan_cnw_copy`** (qua biến môi trường tiến trình, không sửa `.env`):

| Nhóm skip | Số ca | Lý do skip trên SQLite | Assertions khi chạy thật trên copy |
|---|---|---|---|
| `KhoaDongMysqlTest` | 1 | Khóa dòng `SELECT ... FOR UPDATE` cần MySQL/InnoDB | 7 |
| `AnhUrlMysqlCopyTest` | 37 | VARCHAR(150) thật + strict mode (35 sweep + biên 150/151 + multibyte) | 243 |
| `DatHangP0MysqlCopyTest` | 1 | Migration M1 up/down cần MySQL strict | 33 |
| `DatHangP1MysqlCopyTest` | 2 | M2 up/down (T-DH02b) + khóa dòng 2 kết nối (T-DH13) cần InnoDB | 23 |
| **Tổng** | **41** | | **306** |

Lệnh chạy copy (mỗi lệnh một tiến trình; shell không giữ env giữa các lần gọi):

```bash
export DB_CONNECTION=mysql DB_DATABASE=doan_cnw_copy DB_USERNAME=root DB_PASSWORD='' XDEBUG_MODE=off
php artisan test --filter='MysqlCopyTest|KhoaDongMysqlTest' --compact
```

Kết quả thật (exit 0):

```
PASS  Tests\Feature\AnhUrlMysqlCopyTest        (37 ca)
PASS  Tests\Feature\DatHangP0MysqlCopyTest     (1 ca)
PASS  Tests\Feature\DatHangP1MysqlCopyTest
  ✓ t dh02b m2 up down tren mysql copy giu nguyen enum va du lieu   0.48s
  ✓ t dh13 khoa san pham chan tao don online dong thoi              3.44s
PASS  Tests\Feature\KhoaDongMysqlTest          (1 ca)
Tests:    41 passed (306 assertions)
Duration: 9.54s
```

**Cẩn thận filter (sự cố đã xảy ra, xem mục 5.3):** phải dùng filter chính xác
`--filter='MysqlCopyTest|KhoaDongMysqlTest'`. Filter rộng `--filter=Mysql` bắt nhầm
method `...mysqldump` của `SaoLuuCsdlTest` (dùng `RefreshDatabase`) → `migrate:fresh`
xóa sạch bản sao khi đặt env MySQL.

Các test copy **không** dùng `RefreshDatabase` (giữ schema/dữ liệu bản sao), tự áp
migration rồi dọn bản ghi trong `try/finally`, guard skip trừ khi driver `mysql` +
tên DB kết thúc `_copy`. Sau chạy, bản sao tự về đúng trạng thái trước M1/M2 (mục 4.4).

**T-DH13 — giới hạn kiểm chứng đồng thời (ghi rõ theo điểm bàn giao 1):** đây là
**tranh chấp khóa dòng InnoDB THẬT qua hai kết nối MySQL** (`khoa_phu` giữ
`lockForUpdate` sản phẩm trong transaction; kết nối mặc định hạ
`innodb_lock_wait_timeout=1` rồi gọi R1 → chờ >1s rồi ném `QueryException`
"Lock wait timeout", không đơn nào được tạo, kho nguyên vẹn; nhả khóa xong R1 tạo
đơn thành công, trừ kho đúng một lần). **Không phải mô phỏng đa luồng song song**
(hai kết nối chạy tuần tự trong một tiến trình test). Nó chứng minh khóa dòng **tuần
tự hóa** người tạo đơn nên hai đơn không cùng vượt trần. SQLite một kết nối không
kiểm chứng được → skip. Không thể kiểm chứng đồng thời đa luồng thật trong khuôn
khổ PHPUnit một tiến trình; đây là bằng chứng khóa dòng gần nhất có thể.

## 3. Thay đổi theo file

| File | Thay đổi | T/R / điểm bàn giao |
|---|---|---|
| `database/migrations/2026_11_13_000001_make_stock_movements_user_id_nullable.php` | **Mới** — M2: `stock_movements.user_id` nullable; `down()` có điều kiện; SQLite re-declare enum `loai` đủ 5 giá trị | T-DH02b, điểm 2 |
| `app/Support/CheThongTin.php` | **Mới** — `soDienThoai()`: 3 đầu + `****` + 3 cuối (090****567); <7 ký tự che toàn bộ | điểm 3/5 |
| `app/Http/Controllers/DatHangOnlineController.php` | **Mới** — getForm/postDat/getThanhCong/getTraCuu/postTraCuu/getTrangThai/postPoll/postHuy; token pull+hash_equals; bằng chứng session; chỉ gửi id+soluong vào R1; hủy lười | T-DH03..08/12, điểm 1/3/5 |
| `app/Services/ThanhToanChuyenKhoanService.php` | R1 `taoHoaDonChoThanhToan` thêm tham số `$online=[]`; inject `GiuHangOnlineService`; `kiemTra` SAU lockForUpdate TRƯỚC trừ kho; `user?->id`; ghi 6 cột online | điểm 1/5, R1 |
| `app/Http/Controllers/DonHangController.php` | **+`postGiaHan`**: guard pickup online đang chờ; transaction lockForUpdate; trần `created_at+online_gia_han_toi_da_gio`; cộng `online_pickup_gio`; nhật ký không PII | điểm 4 |
| `app/Services/ThongBaoService.php` | **+`thongBaoDonOnlineMoi`**: `e()` tên+ghi chú, che SĐT, không ma_tra_cuu, không emoji | điểm 3/5 |
| `config/pos.php` | +`online_gia_han_toi_da_gio` = env `POS_ONLINE_GIA_HAN_TOI_DA_GIO` mặc định 24 | điểm 4 |
| `.env.example` | +`POS_ONLINE_GIA_HAN_TOI_DA_GIO=24`; cập nhật comment pickup (gia hạn tối đa 24h) | điểm 4 |
| `routes/web.php` | +nhóm `dat-hang` (form GET, dat POST throttle, thanhcong GET); +nhóm `don-hang` (tracuu GET/POST throttle, trangthai GET, poll POST, huy POST); +`donhang.giahan` POST trong nhóm auth+staff.only+role | điểm 3/4/6 |
| `resources/views/dathang/form.blade.php` | **Mới** — form pickup, @csrf, hidden token+phuong_thuc, old() escape | T-DH03..07 |
| `resources/views/dathang/thanhcong.blade.php` | **Mới** — mã tra cứu/đơn/trạng thái/hạn giữ/tổng; ghi chú `{{ }}`; không SĐT/tên | điểm 5 |
| `resources/views/dathang/tracuu.blade.php` | **Mới** — mã + SĐT POST, @csrf, chú thích SĐT không lên URL | điểm 3, T-DH08 |
| `resources/views/dathang/trangthai.blade.php` | **Mới** — escape; form hủy @csrf; poll POST X-CSRF-TOKEN từ meta, `textContent`, 10s | điểm 3/5, T-DH12 |
| `resources/views/kho/lichsu.blade.php` | `user?->name ?? 'Đơn online'` (dòng 10) | điểm 2 |
| `resources/views/storefront/cart.blade.php` | nút "Thanh toán" disabled → link "Đặt hàng" tới `dat-hang.form` | checkout entry |
| `tests/Feature/DatHangP1Test.php` | **Mới** — 10 ca; helper `dat()` áp token cả session lẫn payload; `sp()` seed-once | T-DH03..07 |
| `tests/Feature/DatHangP1TraCuuTest.php` | **Mới** — 7 ca | T-DH08/12, T5 |
| `tests/Feature/DatHangP1BaoVeTest.php` | **Mới** — 10 ca | 6 điểm bàn giao |
| `tests/Feature/DatHangP1MysqlCopyTest.php` | **Mới** — 2 ca; `goM1M2()` guard `m1DaApDung()` trước khi truy vấn `ma_tra_cuu` | T-DH02b/13 |
| `README.md` | bảng env +1 dòng; mục 9 (423/382/41/1898 + 4 entry test); mục 17 (số liệu + cảnh báo filter + phạm vi P1) | docs |
| `docs/BAO_CAO_DAT_HANG_P1.md` | **Mới** — báo cáo này | docs |

## 4. Migration M2

### 4.1 Nội dung

`up()`:
- `Schema::table('stock_movements')`: `user_id` unsignedBigInteger **nullable** `->change()`.
- Riêng SQLite: khai báo lại enum `loai` **ĐẦY ĐỦ 5 giá trị**
  `['nhap','ban','dieuchinh','trahang','trahang_hong']` (cùng `->change()`) để giữ
  CHECK constraint. MySQL `->change()` = MODIFY, **không đụng enum**.

`down()`:
- Đếm `whereNull('user_id')`; nếu > 0 → `RuntimeException` "Không thể rollback: còn
  {N} biến động kho có user_id NULL (đơn online). Hãy xử lý các bản ghi này trước
  khi rollback."
- Ngược lại: `user_id` `nullable(false)->change()` + SQLite re-declare enum 5 giá trị.

**Không thêm giá trị enum mới. Không tạo FK mới** (DB thật 0 FK — L7). Lý do M2:
movement `ban` của đơn guest (R1) và movement `dieuchinh` khi hủy đơn guest (R3,
`auth()->id() ?? user_id` = null) đều có `user_id` NULL → cột phải nullable.

### 4.2 Kết quả up/down

- **SQLite (`:memory:`):** `DatHangP1Test::test_m2_stock_movements_user_id_nullable_tren_sqlite`
  ✅ — chèn movement `user_id` null thành công. Enum `loai` giữ đủ 5 giá trị nên
  test trả hàng (`trahang`/`trahang_hong`) toàn suite vẫn xanh (xem mục 5.2).
- **MySQL copy (`doan_cnw_copy`, strict):** T-DH02b ✅ — up làm `user_id` nullable,
  **không FK mới**, enum `loai` giữ nguyên, chèn movement null được; `down()` chặn
  khi còn movement null (cột không đổi); xóa bản ghi test rồi `down()` sạch về NOT
  NULL, dữ liệu movement cũ nguyên vẹn. Tự dọn, chạy lại được.

### 4.3 Quy trình áp lên production (người vận hành, KHÔNG phải agent)

1. **Backup trước:** `php artisan sao-luu:csdl`.
2. **Áp off-peak** (`stock_movements` có thể lớn; `->change()`/MODIFY):
   ```bash
   php artisan migrate --path=database/migrations/2026_11_13_000001_make_stock_movements_user_id_nullable.php --force
   ```
   (M1 của P0 cũng chưa áp lên `doan_cnw`; áp M1 trước rồi M2.)
3. **Đối chiếu `information_schema`** sau áp: `stock_movements.user_id` nullable,
   enum `loai` **không đổi** (5 giá trị), không FK mới. (Tiền lệ lệch 150 vs 255 — L8.)
4. **Rollback:** `down()` tự chặn nếu còn movement `user_id` NULL (đơn online) —
   phải xử lý các bản ghi đó trước. Kịch bản xấu nhất: restore backup. **Diễn tập
   rollback trên copy trước.**

Agent **không** chạy migration trên `doan_cnw`; chỉ viết + chứng minh trên SQLite/copy.

### 4.4 Đối chiếu schema `invoices` giữa `doan_cnw` và `doan_cnw_copy` (điểm 6, chỉ đọc)

`SHOW CREATE TABLE invoices` trên cả hai (chỉ đọc, được phép theo điểm 6):

- **Giống hệt về cấu trúc:** cùng cột, cùng khóa (PRIMARY + `invoices_user_id_foreign`
  + các index), `ENGINE=InnoDB`, `DEFAULT CHARSET=utf8mb4`,
  `COLLATE=utf8mb4_unicode_ci`. Cả hai **đều trước M1** (chưa có cột `kenh`),
  `user_id bigint unsigned NOT NULL`.
- **Khác duy nhất (không phải cấu trúc):**
  - `AUTO_INCREMENT`: real = **9** (không đổi), copy = **18** (do test copy insert
    rồi xóa đơn — counter không reset bởi DELETE; bản sao scratch, chấp nhận được).
  - Annotation `CHARACTER SET utf8mb4` per-column: copy hiện tường minh (từ
    mysqldump), real kế thừa từ bảng — **cosmetic**, cùng charset/collation.
- Sau chuẩn hóa bỏ annotation per-column, chỉ còn **1 dòng khác** là AUTO_INCREMENT.

**Vệ sinh sau chạy (chỉ đọc, xác minh lại cuối phiên):**

| DB | invoices.kenh | M1 row | M2 row | invoices.user_id | stock_movements.user_id | P1CONC | P1-M2-TEST | invoices | stock_movements |
|---|---|---|---|---|---|---|---|---|---|
| `doan_cnw` (real) | chưa có | 0 | 0 | NOT NULL | NOT NULL | n/a | 0 | 5 | 306 |
| `doan_cnw_copy` | chưa có | 0 | 0 | NOT NULL | NOT NULL | n/a | 0 | 5 | 306 |

→ Real **không bị chạm** (tiền M1/M2). Copy tự dọn về **đúng clone real** (cùng số
dòng, cùng cấu trúc); M2 đã rollback, không còn bản ghi test P1.

## 5. Sai khác so với thiết kế + xử lý 6 điểm bàn giao + mục 8

### 5.1 Sáu điểm bàn giao (đều đã xử lý)

| # | Điểm bàn giao | Xử lý | Bằng chứng |
|---|---|---|---|
| 1 | `kiemTra()` trong cùng transaction với lockForUpdate id tăng dần, trước trừ kho; test 2 kết nối MySQL | R1 gọi `giuHangOnline->kiemTra` SAU `lockForUpdate` TRƯỚC decrement (`ThanhToanChuyenKhoanService.php:52-56`); T-DH13 hai kết nối, `innodb_lock_wait_timeout=1` | T-DH13 ✅ (3.44s, "Lock wait timeout"); ghi rõ không phải đa luồng (mục 2.3) |
| 2 | `kho/lichsu.blade.php:10` null-safe → "Đơn online" + test render movement null | `user?->name ?? 'Đơn online'` | `test_view_lich_su_kho_null_safe_voi_movement_user_null` ✅ |
| 3 | SĐT không lên URL/query (T5); tra cứu/polling POST hoặc bằng chứng session; log/nhật ký che SĐT | tra cứu POST; polling POST + bằng chứng `don_hang_xac_thuc[ma]`; `CheThongTin::soDienThoai` trong nhật ký + Telegram | `test_tra_cuu_dung_redirect_khong_lo_sdt_tren_url`, `test_polling_chi_tra_trang_thai_khong_pii`, `test_nhat_ky_dat_hang_online_che_sdt` ✅ |
| 4 | Xác nhận pickup dùng lại `donhang.huycho`; thêm gia hạn (cộng giờ, trần config, nhật ký); nhật ký không nhạy cảm | xác nhận/hủy tái dùng R2/R3; `postGiaHan` trần `created_at+24h`, cộng 4h, nhật ký chỉ ma_hoadon+datetime | `test_thu_ngan_xac_nhan_pickup...r2`, `...huy...r3`, `test_gia_han_cong_gio_trong_tran_config_ghi_nhat_ky`, `...vuot_tran...bi_tu_choi` ✅ |
| 5 | Giá/tổng/tồn/trạng thái/customer_id/user_id bỏ qua input khách, tính lại từ DB trong transaction; ghi_chu escape render + Telegram | `postDat` chỉ gửi id+soluong; R1 tính giá/tổng từ DB, `user_id=null`, `customer_id=null`; `{{ }}` render + `e()` Telegram | `test_gia_tinh_lai_tu_db_khong_tin_gia_session`, `test_ghi_chu_khach_escape_khi_render`, `test_telegram_don_online_escape_ghi_chu_va_che_sdt` ✅ |
| 6 | Báo cáo skip + kết quả copy thật; đối chiếu invoices SHOW CREATE TABLE (chỉ đọc) | mục 2.3 (41 skip, 306 assertions copy) + mục 4.4 (đối chiếu) | ✅ |

### 5.2 Sai khác có chủ đích / workaround

- **M2 enum SQLite phải đủ 5 giá trị (bug đã tìm + sửa):** bản đầu M2 khai báo lại
  enum `loai` chỉ 3 giá trị `['nhap','ban','dieuchinh']` → `->change()` dựng lại bảng
  SQLite thêm CHECK chặn `trahang`/`trahang_hong` → **11 test full suite đỏ**
  (`CHECK constraint failed: loai`). Đã sửa cả `up()`/`down()` thành 5 giá trị khớp
  MySQL. **Bài học:** full suite bắt được regression mà test P1 SQLite riêng không
  thấy (P1 không exercise `trahang`). Đây là lý do bắt buộc chạy full suite.
- **Phạm vi chỉ pickup + khung khóa chung** (quyết định người dùng): QR online
  checkout để P2. R1 mở rộng bằng tham số `$online=[]` mặc định rỗng = POS giữ nguyên.
- **Lỗi nghiệp vụ guest trả 302 `back()->with('error')`** (UX form) trong khi gia
  hạn thu ngân trả **422 JSON** (API POS). Có chủ đích theo ngữ cảnh dùng.
- **Bằng chứng session cho polling (T5):** chọn `don_hang_xac_thuc[ma_tra_cuu]=true`
  thay vì SĐT trên URL; polling không có bằng chứng → 403.
- **`ma_tra_cuu` giữ NGOÀI Telegram** (chỉ `ma_hoadon`): tránh lộ capability token
  qua kênh thông báo; trang `thanhcong` gate bằng `ma_tra_cuu` (không cần bằng chứng
  session, không PII ngoài thông tin đơn).
- **Commit tiếng Việt CÓ dấu** — prompt §6.7 ghi "không dấu như lịch sử repo" nhưng
  lịch sử thật (`git log`) dùng có dấu → tin mã/lịch sử thật (như P0 đã ghi nhận).

### 5.3 Sự cố vận hành: xóa nhầm bản sao + khôi phục (real không bị chạm)

Trong lúc chạy test copy, filter rộng `--filter=Mysql` bắt nhầm
`SaoLuuCsdlTest::test_bao_loi_ro_khi_thieu_mysqldump` (dùng `RefreshDatabase`) → dưới
env MySQL nó chạy `migrate:fresh` **xóa sạch `doan_cnw_copy`**. Khôi phục:
1. Xác minh `doan_cnw` (real) **nguyên vẹn** (chỉ đọc).
2. `mysqldump` real **chỉ đọc** ra file tạm **ngoài repo**.
3. `DROP` + `CREATE` + import lại `doan_cnw_copy` từ bản dump → copy về đúng clone real.
4. Xóa file tạm.

Real **không** bị ghi/xóa ở bất kỳ bước nào. Từ đó luôn dùng filter chính xác
`--filter='MysqlCopyTest|KhoaDongMysqlTest'` (đã ghi cảnh báo vào README mục 17).
Đây là sự cố trên **bản sao scratch**, không phải DB thật; ghi lại để minh bạch.

### 5.4 Xử lý các điểm mục 8 (bảo thủ)

- **Polling `sdt` trên URL (T5):** đã xử lý bằng POST + bằng chứng session (điểm 3).
- **Hai cột `ma_giaodich`/`ma_giao_dich`:** R1 không đổi phần ghi hai cột → giữ nguyên.
- **Webhook không kiểm tra hạn (T-CK18):** P1 không đụng webhook → giữ nguyên hành vi.
- **Giữ kho ngay khi tạo đơn (R1):** trừ kho **một lần**; hủy lười/hủy khách hoàn kho
  qua R3 (không trừ hai lần).
- **Cart GET→POST (L4):** chỉ đổi ở P5; P1 giữ route mutation giỏ hiện tại, chỉ thêm
  link "Đặt hàng" (GET tới form) thay nút disabled.
- **Số dòng trôi:** tin mã thật (đã đọc lại toàn bộ controller/service/view/route).

## 6. Việc chưa làm / rủi ro còn lại / câu hỏi

### 6.1 ĐÃ XỬ LÝ (T1, 2026-11-14) — scheduler nền từng hủy nhầm đơn pickup

**Phát hiện khi rà cuối pha (chỉ đọc):** lệnh nền `donhang:huy-qua-han`
(`HuyHoaDonQuaHan.php`, chạy `everyMinute()` theo `routes/console.php:9`) từng hủy
**mọi** đơn `cho_thanh_toan` có `created_at < now - cho_thanh_toan_phut` (**15 phút**),
**không** lọc `kenh`/`han_giu` → đơn pickup online giữ 4h sẽ bị hủy sau 15 phút,
phá vỡ cửa sổ giữ hàng + gia hạn.

**Đã sửa (được duyệt, commit `ab306fc`, test tái hiện `be2f287`):** thêm
`DonHang::scopeQuaHanCho` làm **nguồn quy tắc quá hạn duy nhất**:

- Pickup online (`kenh=online` + `phuong_thuc=tienmat` + `han_giu` có giá trị):
  hủy theo **`han_giu < now`**, không áp hạn 15 phút.
- Mọi đơn còn lại (POS, QR, đơn cũ `han_giu` null): giữ nguyên
  `created_at + cho_thanh_toan_phut`.

Scope dùng chung ở **scheduler** `donhang:huy-qua-han`, **POS poll**
(`DonHangController@getTrangThai` — hủy lười) và **health-check**
`KiemTraHeThong::kiemTraDonQuaHan`. Webhook (T-CK18), `huyNeuQuaHan` guest và R3
giữ nguyên. 13 ca test mới (`HuyDonPickupQuaHanTest`) + 71 ca liên quan xanh.
**Không còn chặn triển khai ở mục này.**

### 6.2 Việc chưa làm (đúng phạm vi)

- **QR online checkout (P2):** R1 đã có khung `$online` nhưng controller P1 chỉ nhận
  `phuong_thuc=pickup`; QR để pha sau.
- **Không merge, không push, không áp migration M1/M2 lên `doan_cnw`** (đúng chỉ thị).
  Chờ lệnh riêng của người dùng.

### 6.3 Rủi ro còn lại / câu hỏi

- **Race:** T-DH13 chứng minh khóa dòng **tuần tự hóa** người tạo đơn (hai kết nối,
  không đa luồng song song thật). Không thể kiểm chứng đồng thời đa luồng trong
  PHPUnit một tiến trình; đây là bằng chứng gần nhất (mục 2.3).
- **Cấu hình trần:** pickup 4h, gia hạn tối đa 24h, trần giữ 50% — operator chỉnh
  qua env/config trước production (không cần migration).
- **Câu hỏi cần người dùng quyết:** thời điểm merge/áp migration (scheduler mục 6.1
  đã sửa xong ở T1).

## 7. Checklist mục 7 (tự rà soát)

| # | Mục | Đạt? | Bằng chứng |
|---|---|---|---|
| 1 | Không enum mới; migration chỉ nullable/default + nullable `user_id`; `down()` có điều kiện + ghi chú rủi ro null-user | ✅ | M2 §4.1: `user_id` nullable, enum `loai` giữ 5 giá trị (không thêm); `down()` đếm null → RuntimeException; T-DH02b |
| 2 | Mọi nơi đọc `invoices.user_id`/`stock_movements.user_id` chịu null → "Đơn online" | ✅ | P0: 3 nơi đọc invoices; P1: `kho/lichsu.blade.php:10` (stock_movements) + test render movement null. Còn `hold/danhsach`, `kiemke/index` đọc user POS luôn có thu ngân (ngoài đường guest online) |
| 3 | POS cũ không đổi hành vi; test POS/webhook/trả hàng/báo cáo vẫn xanh | ✅ | Full suite 382 pass; R1 mặc định `$online=[]` = POS; không sửa test cũ. (Scheduler nền **không đổi** — rủi ro tích hợp mục 6.1, không phải regression POS) |
| 4 | Không nhận giá/tổng/`customer_id`/`user_id`/trạng thái từ request guest | ✅ | `postDat` validate chỉ ten/sdt/ghi_chu/phuong_thuc/token; chỉ gửi id+soluong vào R1; R1 tính lại từ DB, `user_id=null`,`customer_id=null`; `test_gia_tinh_lai_tu_db...` |
| 5 | Dữ liệu khách escape render + Telegram; không PII đầy đủ trong log | ✅ | `{{ }}`/`e()` ghi_chu+tên; `CheThongTin` che SĐT nhật ký+Telegram; test escape + che SĐT |
| 6 | Route công khai mới có throttle+CSRF; route quản trị trong nhóm sẵn có | ✅ | `dat-hang.dat`/`don-hang.posttracuu` throttle `online_throttle_dat`; form @csrf; poll POST X-CSRF-TOKEN; `donhang.giahan` trong auth+staff.only+role:admin,thungan |
| 7 | Mỗi test T-DH tồn tại + từng đỏ trước khi xanh | ✅ | 4 file test mới (29 ca); commit `test:` đứng trước `feat:` (tại commit test, controller/route/M2 chưa có → đỏ); phát triển theo T-DH03..08/12/13 |
| 8 | Không file tạm/secret/`.env` lọt commit | ✅ | Chỉ add file P1 theo tên; loại 4 file carry-over (`BAO_CAO_KIEM_TRA_LUONG_DA.md`, `BAO_CAO_XU_LY_OPERA_GX_VA_CMD_WINDOWS.md`, `PROMPT_AGENT_DAT_HANG_ONLINE.md`, `chay-schedule-hidden.vbs`); `.env.example` chỉ placeholder; không secret trong code/log/báo cáo |

**Kết luận:** Full suite xanh (**382 pass / 41 skip / 1898 assertions**, 200.94s,
exit 0); copy xanh (**41 pass / 306 assertions**, 9.54s); cả 8 mục checklist đạt.
41 skip đều "cần MySQL" và đã chạy thật trên `doan_cnw_copy`. Real `doan_cnw`
không bị chạm; copy tự dọn về đúng clone real. **Dừng tại P1** — không merge, không
push, không áp migration lên MySQL thật. Rủi ro chặn triển khai ở mục 6.1 (scheduler
hủy nhầm đơn pickup) **đã xử lý ở T1** — xem mục 8 cho đợt T2–T5 tiếp theo.
## 8. Đợt T2–T5 (2026-11-14): D12 chống ôm hàng + chốt chặn RefreshDatabase + rà UI thu ngân

> Nền: P1 pickup (`e52e235` + T1 `be2f287`/`ab306fc`). Phần P2 dở được bảo toàn
> nguyên vẹn ở nhánh `wip/p2-giao-tan-noi` (commit `3105eaa`, tạo từ HEAD P2
> `ed190b6` + toàn bộ thay đổi chưa commit); nhánh `feat/dat-hang-p2-giao-tan-noi`
> không bị đụng. Không dựa vào controller/R1 của P2.

### 8.1 T2 — D12 chống ôm hàng trên luồng pickup P1

**Commit:** `52b40ab` (test: 9 ca chứng cứ chuẩn hóa SĐT) + `d14b96d` (feat: triển khai).

| Hạng mục | Triển khai | Vị trí |
|---|---|---|
| Honeypot | Ô `trang_web` ẩn (`aria-hidden="true"`, ngoài màn hình, `tabindex=-1`); bị điền → từ chối thông báo chung, cấp lại token, không tạo đơn/không trừ kho | `resources/views/dathang/form.blade.php` (khối honeypot), `DatHangOnlineController@postDat` (đầu hàm) |
| 3 đơn mở/SĐT chuẩn hóa | Đếm đơn `kenh=online + phuong_thuc=tienmat + cho_thanh_toan` có SĐT chuẩn hóa trùng; đủ `online_max_don_mo` (3) → `RuntimeException` thông báo chung. Kiểm tra **TRONG transaction R1, SAU `lockForUpdate` sản phẩm, TRƯỚC trừ kho**; đơn `da_huy`/`da_thanh_toan`/POS/QR không tính | `ThanhToanChuyenKhoanService::kiemTraGioiHanDonMo` (gọi trong `taoHoaDonChoThanhToan`) |
| Chuẩn hóa SĐT | `SoDienThoai::chuanHoa`: bỏ khoảng trắng/chấm/gạch/ngoặc, `+84`/`84` đầu → `0`; đơn mới lưu SĐT chuẩn hóa | `app/Support/SoDienThoai.php` (mới) |
| Trần sản phẩm + số lượng dòng | Tối đa `online_max_sanpham` (10, **config mới** `POS_ONLINE_MAX_SANPHAM`) sản phẩm khác nhau/đơn; mỗi dòng 1..`online_max_soluong` (10); kiểm tra trước transaction, từ chối chung | `DatHangOnlineController@postDat`; `config/pos.php`; `.env.example` (THÊM dòng mới) |
| Thông báo chung | Mọi từ chối D12: `'Không thể tạo đơn hàng. Vui lòng thử lại.'` — không lộ lý do chi tiết/dữ liệu đơn khác | hằng `LOI_CHUNG` trong controller; service ném cùng chuỗi |
| Throttle 5/phút/IP → 429 | **Đã có sẵn** ở route từ P1 (`throttle:online_throttle_dat`), giữ nguyên, test xác nhận | `routes/web.php:42-44` |

**Rà 12 test đỏ `e52e235`: KHÔNG ca nào phải sửa.** Cả 12 đã viết cho nền P1
(route `dat-hang.dat`, `phuong_thuc=pickup`, giỏ session, không tham số khách
hàng/redirect QR của P2). Bằng chứng: `git diff e52e235 HEAD --
tests/Feature/DatHangP1ChongOmHangTest.php` = rỗng. Baseline chạy thật trước khi
sửa: **6 đỏ / 6 xanh** (30 assertions, 51.04s) — 6 đỏ: `honeypot_bi_dien...`,
`form_hien_honeypot...`, `vuot_3_don_mo...`, `vuot_10_san_pham...`,
`vuot_so_luong_moi_dong...`, `so_luong_dong_bang_0...` (đỏ vì thiếu triển khai,
không phải lệch nền). Sau feat: **12/12 xanh** (39 assertions, 29.78s).

**9 ca chứng cứ thêm** (không nằm trong 12 ca đăng ký): `SoDienThoaiTest` (6, unit)
+ `DatHangP1ChuanHoaSdtTest` (3, feature — đơn cũ lưu `+84`/`090.123 4567` vẫn tính
vào giới hạn 3 của `0901234567`; SĐT khác không tính chéo). Lý do: 12 ca D12 chỉ
thử SĐT cùng dạng, chưa chứng minh phần "chuẩn hóa".

**Hồi quy:** nhóm 69 ca liên quan (`DatHangP1Test|DatHangP1TraCuuTest|DatHangP1BaoVeTest|
ThanhToanChuyenKhoanTest|GiuHangOnlineServiceTest|HuyDonPickupQuaHanTest`) xanh
308 assertions trước commit; POS không đổi hành vi (kiểm tra 3 đơn chỉ chạy khi
`$laOnlinePickup`, trần SP/số lượng chỉ nằm trong controller online).

### 8.2 T3 — chốt chặn RefreshDatabase

**Commit:** `711fff0` (test:).

- `tests/TestCase.php` chặn ở `setUpTraits()` — ngay trước
  `RefreshDatabase::refreshDatabase()`: nếu kết nối mặc định không phải
  `sqlite` + `:memory:` → `RuntimeException` tiếng Việt (nêu kết nối hiện tại +
  cách chạy copy đúng), **trước khi kịp kết nối/migrate:fresh**.
- **Vì sao không đặt vào chính hook `beforeRefreshingDatabase()`:** trait
  `RefreshDatabase` use ở lớp con **ghi đè** method cùng tên của lớp cha (PHP
  precedence: trait > inherited) — đặt guard ở lớp cha dưới tên hook sẽ bị vô
  hiệu hóa thầm lặng. Đã kiểm chứng bằng fatal error tương thích chữ ký khi thử
  phương án hook trực tiếp. `setUpTraits` không bị trait nào của lớp con ghi đè.
- Test chứng minh `RefreshDatabaseGuardTest` (3 ca): mô phỏng cấu hình mysql
  (`createApplication` override, port 9 + DB giả `doan_cnw_mo_phong` — **không
  kết nối thật**, guard hỏng thì cũng refused ngay): (1) guard ném đúng
  `RuntimeException` thuần trong setUp trước tầng kết nối (loại trừ
  `QueryException`), thông báo tiếng Việt chứa `sqlite :memory:` + tên kết nối;
  (2) sqlite `:memory:` đi qua; (3) sqlite file bị chặn.
- 4 lớp MySQL-copy (`AnhUrlMysqlCopyTest`, `DatHangP0MysqlCopyTest`,
  `DatHangP1MysqlCopyTest`, `KhoaDongMysqlTest`) **không** dùng trait
  `RefreshDatabase` (kiểm chứng bằng grep `use RefreshDatabase;` = 0) → không bị
  ảnh hưởng; vẫn chạy được với env MySQL + filter chính xác.

### 8.3 T4 — rà chỉ đọc: thu ngân thấy đơn pickup online ở đâu (không làm UI)

Đã kiểm chứng từng dòng bằng đọc trực tiếp file:

- **Thấy đơn:** `GET /donhang` (`DonHangController@getDanhSach`,
  `app/Http/Controllers/DonHangController.php:199`) query `DonHang::with('user')->latest()`,
  chỉ lọc `tu_ngay/den_ngay`, **không lọc kenh/trang_thai** → đơn pickup online
  hiện trong danh sách; cột Thu ngân hiện **"Đơn online"** khi `user` null
  (`resources/views/donhang/danhsach.blade.php:31`), badge vàng "Chờ thanh toán"
  (dòng 37-38). Không có cột/badge kenh, `ma_tra_cuu`, `han_giu`/countdown.
- **POS không có panel đơn chờ:** `getPos` (`DonHangController.php:24`) chỉ truyền
  `loais`; `resources/views/pos/index.blade.php` chỉ có tìm sản phẩm + giỏ session
  + modal VietQR cho hóa đơn thu ngân vừa tự tạo (`currentInvoiceId`).
- **Nút xác nhận/hủy:** chỉ trong modal VietQR POS — `#btn-modal-xac-nhan`
  (`pos/index.blade.php:162-164`, fetch `donhang.xacnhan` dòng 487) và
  `#btn-modal-huy-cho` (dòng 159-161, fetch `donhang.huycho` dòng 521); chỉ áp
  dụng cho hóa đơn QR vừa tạo phiên này (`if (!currentInvoiceId) return;`),
  **không với tới đơn pickup online**.
- **Gia hạn:** route `donhang.giahan` + `postGiaHan`
  (`DonHangController.php:153`, guard pickup online đang chờ) đã có nhưng
  **không có nút/form/JS nào gọi** (grep toàn bộ `resources/views`, `resources/js`,
  `public/js` = 0 caller; chỉ tests gọi).
- **Danh sách hóa đơn:** đơn chưa thanh toán chỉ có nút **disabled** "Chưa thanh
  toán" (`danhsach.blade.php:50`); nhánh `da_thanh_toan` mới có Xem/in + Đổi trả.

**Kết luận T4 (báo cáo, không sửa UI):** đơn pickup online hiện ở `/donhang`
nhưng thu ngân **không có nút xác nhận/hủy/gia hạn nào cho chúng trên giao diện**;
API gia hạn "mồ côi UI". Khoảng trống này thuộc phạm vi UI (handoff FE), không
làm trong đợt này.

### 8.4 T5 — kết quả chạy thật (2026-11-14)

**Full suite** (`XDEBUG_MODE=off php artisan test --compact`, SQLite `:memory:`,
exit 0):

```
Tests:    41 skipped, 419 passed (2007 assertions)
Duration: 656.56s
```

- Tổng **460 test** = 423 (báo cáo P1 gốc) + 13 (T1 `HuyDonPickupQuaHanTest`)
  + 24 đợt này (12 D12 + 3 chuẩn hóa SĐT feature + 6 unit `SoDienThoaiTest`
  + 3 `RefreshDatabaseGuardTest`). Pass 382 → **419** (+37, không giảm test nào).
- Thời gian 656.56s dài hơn lượt 200.94s trước do máy chạy chậm hơn ở thời điểm
  đo (có tiến trình MySQL + lượt copy chạy song song đầu phiên); giữ nguyên số
  thật, không quy đổi.
- **41 skip phân loại "cần MySQL"** (không skip cố ý/lý do khác), xác minh bằng
  lượt chạy riêng `--filter='MysqlCopyTest|KhoaDongMysqlTest'` trên SQLite:
  **41 skipped (0 assertions), 1.75s**:

| Nhóm skip | Số ca | Lý do |
|---|---|---|
| `AnhUrlMysqlCopyTest` | 37 | VARCHAR(150) thật + MySQL strict |
| `DatHangP0MysqlCopyTest` | 1 | M1 up/down cần MySQL strict |
| `DatHangP1MysqlCopyTest` | 2 | M2 up/down + khóa dòng 2 kết nối cần InnoDB |
| `KhoaDongMysqlTest` | 1 | Khóa dòng `SELECT ... FOR UPDATE` cần InnoDB |

**MySQL-copy** (env tiến trình `DB_CONNECTION=mysql DB_DATABASE=doan_cnw_copy
DB_USERNAME=root DB_PASSWORD=''`, **filter chính xác**
`--filter='MysqlCopyTest|KhoaDongMysqlTest'`, `XDEBUG_MODE=off`, exit 0):

```
Tests:    41 passed (306 assertions)
Duration: 11.05s
```

Cả 4 nhóm PASS (T-DH02b 0.47s, T-DH13 3.41s, khóa dòng 2.13s). **Vệ sinh sau
chạy (chỉ đọc):** `doan_cnw_copy` về đúng clone real — invoices 5,
stock_movements 306, `P1CONC` 0, `P1-M2-TEST` 0, M1/M2 rows 0. `doan_cnw` thật
không bị chạm (không kết nối tới trong cả phiên).

**Commit đợt này (tách bạch):** `52b40ab` test: (9 ca chuẩn hóa) → `d14b96d`
feat: (D12) → `711fff0` test: (chốt chặn T3 + 3 ca) → commit docs: (báo cáo +
README 9/17). Không merge/push; không migrate/seed MySQL thật; không đọc/ghi
`.env` (env copy đặt bằng biến tiến trình).
