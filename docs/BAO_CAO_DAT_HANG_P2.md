# Báo cáo P2: Giao tận nơi + QR trả trước

**Nhánh:** `feat/dat-hang-p2-giao-tan-noi` (tạo từ `feat/dat-hang-p1-checkout`)
**Ngày:** 2026-11-14
**Phạm vi:** Chuẩn bị schema, config, model, test + Form/Checkout cho giao_tan_noi (theo quyết định mới ghi đè D1/D2)

## Bước 0: Kiểm tra nền

### 0a. Git log và xác nhận nền

Baseline P2 (nhánh `feat/dat-hang-p1-checkout` + commit mới):

```
e52e235 test: D12 chống ôm hàng checkout pickup (honeypot, 3 đơn/SĐT, 10 SP, throttle)
ab306fc fix: scheduler/hủy lười không hủy nhầm đơn pickup online còn hạn giữ
be2f287 test: tái hiện scheduler hủy nhầm đơn pickup online còn hạn giữ
2177b24 docs: báo cáo P1 đặt hàng online guest pickup + README env/test
8ad814a feat: P1 checkout guest pickup — R1 online + khóa trần, tra cứu/polling/hủy, gia hạn
a222f38 test: test đỏ T-DH03..08/12/13 P1 đặt hàng online guest pickup
23689d1 docs: báo cáo P0+PA1 đặt hàng online, README env/test, D4 pickup 4h + trần giữ 50%
940ca6a feat: P0 đặt hàng online — schema M1, config, fallback Đơn online, trần giữ pickup
a63da70 test: test đỏ T-DH01/T-DH02 P0 đặt hàng online (schema invoices, config, fallback Đơn online)
e1e0a6d docs: báo cáo hiện trạng luồng đặt hàng storefront + thiết kế đặt hàng online tối thiểu
14971db docs: báo cáo validate URL ảnh (nguyên nhân cột 150, bằng chứng, UX), README 9/17/18
9bf69d0 feat: kiểm tra ảnh URL phía trình duyệt, chặn nút Lưu khi URL hỏng
45e7485 test: exception thật phải render trang 500 tiếng Việt khi tắt debug
30fbad9 fix: giới hạn URL ảnh 150 ký tự theo cột thật, báo lỗi tiếng Việt thay vì 500
81b6354 test: gửi cả tệp lẫn URL hỏng thì tệp phải thắng, không bị chặn validate
```

**Xác nhận các sửa nền đã có:**
- ✅ Sửa scheduler: `ab306fc` - đơn pickup hủy theo `han_giu`, không bị hủy ở mốc 15 phút
- ✅ Chống ôm hàng D12: `e52e235` - honeypot, tối đa 3 đơn mở/SĐT, 10 SP/dòng, throttle 5/phút/IP
- ✅ Chốt chặn RefreshDatabase trong tests/TestCase.php: TestCase.php hiện là abstract class trống, các test MySQL-copy tự skip nếu không phải MySQL bản sao (không dùng RefreshDatabase)

**Kết luận:** Đủ điều kiện tiếp tục P2.

### 0b. Baseline full suite

Chạy với `XDEBUG_MODE=off php artisan test`:

```
Tests:    47 skipped, 408 passed (2002 assertions)
Duration: 301.36s
```

**47 skipped** là các test MySQL-copy (cần `DB_CONNECTION=mysql DB_DATABASE=doan_cnw_copy`) và một số test khác phụ thuộc môi trường.

### 0c. Test M3 trên SQLite + MySQL-copy

**SQLite (RefreshDatabase):**
- `tests/Feature/DatHangP2MigrationTest.php` - 7 test pass, 39 assertions

**MySQL-copy:**
- `tests/Feature/DatHangP2MysqlCopyTest.php` - 1 test (up/down, kiểm tra cột, enum, FK, dữ liệu cũ)
- Test skip khi không có `DB_CONNECTION=mysql DB_DATABASE=doan_cnw_copy`
- Cần chạy sau khi đối chiếu `SHOW CREATE TABLE invoices` với `doan_cnw`

### 0d. Làm rõ cột người đặt hàng vs người nhận

**Cột người đặt hàng (M1 - P0):**
- `ten_khach_dat` (varchar 100): Tên người đặt hàng (guest hoặc khách đăng nhập)
- `so_dienthoai_dat` (varchar 15): SĐT người đặt hàng (khóa tra cứu đơn)
- `ghi_chu_khach` (varchar 500): Ghi chú của người đặt

**Cột người nhận (M3 - P2):**
- `ten_nguoi_nhan` (varchar 100): Tên người nhận hàng (chỉ dùng khi `hinh_thuc_nhan = giao_tan_noi`)
- `sdt_nguoi_nhan` (varchar 15): SĐT người nhận (chỉ dùng khi `hinh_thuc_nhan = giao_tan_noi`)
- `dia_chi_giao` (varchar 255): Địa chỉ giao (chỉ dùng khi `hinh_thuc_nhan = giao_tan_noi`)

**Phân biệt:**
- Người đặt hàng = người tạo đơn (guest hoặc khách đăng nhập)
- Người nhận = người nhận hàng (có thể khác người đặt)
- Khi `hinh_thuc_nhan = tai_cua_hang`: `ten_nguoi_nhan`, `sdt_nguoi_nhan`, `dia_chi_giao` = null (không cần vì nhận tại cửa hàng)
- Khi `hinh_thuc_nhan = giao_tan_noi`: bắt buộc điền cả 3 cột người nhận

**Mặc định người nhận = người đặt:**
- Khi chọn `tai_cua_hang`: không cần điền người nhận (nhận tại cửa hàng)
- Khi chọn `giao_tan_noi`: form có thể điền sẵn `ten_nguoi_nhan` = `ten_khach_dat`, `sdt_nguoi_nhan` = `so_dienthoai_dat` (nếu khách đăng nhập), nhưng khách có thể sửa

**Cột dùng cho tra cứu đơn:**
- `ma_tra_cuu`: Mã tra cứu ngẫu nhiên (dùng chung cho cả pickup và giao)
- `so_dienthoai_dat`: SĐT người đặt (khóa tra cứu chính)
- `sdt_nguoi_nhan`: SĐT người nhận (KHÔNG dùng tra cứu, chỉ để giao hàng)

## Bước 1: Form + Checkout cho giao_tan_noi

### Config mới

**File:** `config/pos.php`

```php
'phi_giao_hang' => (int) env('POS_PHI_GIAO_HANG', 30000),
'mien_phi_giao_tu' => (int) env('POS_MIEN_PHI_GIAO_TU', 0),
'online_tu_dong_xac_nhan' => env('POS_ONLINE_TU_DONG_XAC_NHAN', false) === true,
'giao_hang_tinh' => env('POS_GIAO_HANG_TINH', ''),
```

**File:** `.env.example`

```env
# P2 Giao tận nơi: phí giao hàng cố định (mặc định 30000), miễn phí khi tiền hàng >= ngưỡng (0 = tắt), tự động xác nhận đơn giao
POS_PHI_GIAO_HANG=30000
POS_MIEN_PHI_GIAO_TU=0
POS_ONLINE_TU_DONG_XAC_NHAN=false
# P2: Danh sách tỉnh/thành được giao (phân tách dấu phẩy, rỗng = không giới hạn)
POS_GIAO_HANG_TINH=
```

### Form đặt hàng

**File:** `resources/views/dathang/form.blade.php`

- Thêm radio button chọn `hinh_thuc_nhan`: `tai_cua_hang` (mặc định) hoặc `giao_tan_noi`
- Khi chọn `giao_tan_noi`:
  - Hiển thị các trường: `ten_nguoi_nhan`, `sdt_nguoi_nhan`, `dia_chi_giao`
  - Ẩn tùy chọn "Trả tại quầy" (chỉ cho QR)
  - Tự động chọn "QR trả trước"
  - Hiển thị phí giao hàng và tổng cần chuyển khoản (chỉ để hiển thị, server tính lại)
- JavaScript xử lý đổi hiển thị theo lựa chọn
- Escape toàn bộ đầu ra, giữ `old()` cho form

### Controller

**File:** `app/Http/Controllers/DatHangOnlineController.php`

#### getForm
- Truyền thêm `phiGiaoHang` và `mienPhiGiaoTu` vào view

#### postDat
- Validate:
  - `hinh_thuc_nhan`: nullable, in:tai_cua_hang,giao_tan_noi (mặc định tai_cua_hang)
  - `ten_nguoi_nhan`, `sdt_nguoi_nhan`, `dia_chi_giao`: required_if khi giao_tan_noi
  - `sdt_nguoi_nhan`: regex SĐT chung
  - `dia_chi_giao`: max 255
- Kiểm tra `giao_tan_noi` phải dùng QR (phuong_thuc=qr)
- Kiểm tra vùng giao (nếu config `giao_hang_tinh` không rỗng):
  - Tìm tỉnh/thành trong địa chỉ (case-insensitive)
  - Nếu không tìm thấy → trả lỗi tiếng Việt
- Tính `phi_giao_hang`:
  - Chỉ tính khi `hinh_thuc_nhan = giao_tan_noi`
  - Lấy từ config `pos.phi_giao_hang`
  - Nếu `mien_phi_giao_tu > 0` và `tong_tien >= mien_phi_giao_tu` → phí = 0
- Tạo đơn với các cột mới:
  - `hinh_thuc_nhan`, `ten_nguoi_nhan`, `sdt_nguoi_nhan`, `dia_chi_giao`, `phi_giao_hang`
  - `phuong_thuc`: chuyenkhoan (QR) hoặc tienmat (pickup)
  - `han_giu`: QR = now + online_qr_phut (30 phút), pickup = now + online_pickup_gio (4 giờ)
- Bỏ qua mọi trường tiền/trạng thái/customer_id/user_id từ request

### Service

**File:** `app/Services/ThanhToanChuyenKhoanService.php`

- Tính `tong_thanh_toan = tong_tien - tien_giam - tien_giam_diem + phi_giao_hang`
- `phi_giao_hang` chỉ tính khi `hinh_thuc_nhan = giao_tan_noi`
- `trang_thai_giao = null` cho tới khi thanh toán (D15)
- POS không đổi hành vi (không truyền hinh_thuc_nhan → mặc định tai_cua_hang, phi_giao_hang = 0)

### Test

**File:** `tests/Feature/DatHangP2FormCheckoutTest.php` - 10 test pass, 41 assertions

Test cases:
1. Form hiển thị lựa chọn hình thức nhận
2. Giao_tan_noi thiếu người nhận bị từ chối
3. Giao_tan_noi thiếu SĐT người nhận bị từ chối
4. Giao_tan_noi thiếu địa chỉ bị từ chối
5. Giao_tan_noi trả tại quầy bị từ chối (phải dùng QR)
6. Giao_tan_noi tính phí giao hàng đúng
7. Giao_tan_noi miễn phí khi đạt ngưỡng
8. Request cố sửa phí/tổng/trạng thái bị bỏ qua
9. Giao_tan_noi ngoài vùng giao bị từ chối
10. Tai_cua_hang không đổi hành vi P1

**Sửa test P1:**
- `tests/Feature/DatHangP1Test.php`: Cập nhật test validate để tương thích P2 (tai_cua_hang cho cả QR)

### Kết quả

```
Tests:    48 skipped, 418 passed (2042 assertions)
Duration: 511.76s
```

So với baseline P2 (408 passed, 47 skipped, 2002 assertions):
- Thêm 10 test mới (P2 Form Checkout)
- Thêm 1 test MySQL-copy skip (P2 MysqlCopyTest)
- Tổng cộng: +11 test, +40 assertions

### Phạm vi P2 (còn lại)

Theo prompt gốc, P2 cần 9 việc. Đã hoàn thành:
1. ✅ Migration M3 (chỉ viết sẵn; kiểm tra M1 trước để không tạo cột trùng)
2. ✅ Form đặt hàng (chọn hình thức nhận, nhập thông tin giao)
3. ✅ Checkout (tính phí ship, tạo đơn với các cột mới)

Còn lại:
4. ⏳ Trang QR online: dùng VietQrService với số tiền = tong_thanh_toan (đã gồm phí ship), nội dung chuyển khoản = mã hóa đơn, đếm ngược theo han_giu, polling bằng POST + bằng chứng session
5. ⏳ Webhook hoàn tất: tái dùng đường hoàn tất hiện có; nếu đơn kenh=online + giao_tan_noi thì đặt trang_thai_giao theo D15, idempotent
6. ⏳ Tra cứu đơn (mã + SĐT, POST): hiển thị trạng thái thanh toán + trạng thái giao, địa chỉ chỉ cho người đã xác thực
7. ⏳ Báo cáo/dashboard: phi_giao_hang không tính vào doanh thu/lợi nhuận hàng hóa
8. ⏳ Test đỏ (test từng kịch bản): QR sinh đúng số tiền gồm ship; webhook đủ tiền hoàn tất; webhook lặp idempotent; quá 30 phút hủy + hoàn kho; không bị hủy ở mốc 15 phút; đơn pickup QR/trả tại quầy không đổi hành vi; honeypot, throttle, giới hạn 3 đơn mở/SĐT; khách đăng nhập điền sẵn đúng; Telegram escape và che; polling không PII; migration M3 up/down SQLite + MySQL-copy; test POS/webhook/trả hàng hiện có xanh nguyên trạng
9. ⏳ Báo cáo (bảng trước/sau, danh sách việc P3, cần người dùng xác nhận)

Đơn giao_tan_noi tạo xong ở bước này chuyển sang trang QR hiện có/placeholder rõ ràng (chưa implement VietQrService cho đơn online).

## Tóm tắt

P2 mở rộng đặt hàng online theo quyết định mới:
- **D1'**: Hai hình thức nhận: `tai_cua_hang` (pickup) và `giao_tan_noi` (giao tận nơi)
- **D2'**: `giao_tan_noi` BẮT BUỘC thanh toán QR trả trước; `tai_cua_hang` cho cả QR và trả tại quầy
- **D13**: Phí giao hàng CỐ ĐỊNH từ config
- **D14**: Trạng thái giao tách khỏi thanh toán (cột `trang_thai_giao`)
- **D15**: Cờ tự động xác nhận đơn giao
- **D16**: Hủy đơn đã thanh toán + hoàn tiền = THỦ CÔNG (P3)
- **D17**: Hạn QR online từ `han_giu` (online_qr_phut = 30)

Đợt này chỉ hoàn thành **phần chuẩn bị** (migration, config, model, test test đỏ migration). Các phần thực thi (form, checkout, QR, webhook, tra cứu, báo cáo) để các pha sau.

## 1. Migration M3: Thêm cột giao tận nơi

**File:** `database/migrations/2026_11_14_000003_add_delivery_fields_to_invoices_table.php`

### Cột thêm vào `invoices`:

| Cột | Kiểu | Nullable | Default | Index | Ý nghĩa |
|---|---|---|---|---|---|
| `hinh_thuc_nhan` | varchar(20) | NO | `tai_cua_hang` | - | Hình thức nhận: `tai_cua_hang` hoặc `giao_tan_noi` |
| `ten_nguoi_nhan` | varchar(100) | YES | null | - | Tên người nhận (chỉ dùng khi giao_tan_noi) |
| `sdt_nguoi_nhan` | varchar(15) | YES | null | - | SĐT người nhận (chỉ dùng khi giao_tan_noi) |
| `dia_chi_giao` | varchar(255) | YES | null | - | Địa chỉ giao (chỉ dùng khi giao_tan_noi) |
| `phi_giao_hang` | decimal(10,0) | NO | 0 | - | Phí giao hàng (tính từ config) |
| `trang_thai_giao` | varchar(50) | YES | null | YES | Trạng thái giao: `cho_xac_nhan`, `da_xac_nhan`, `dang_giao`, `da_giao`, `da_huy_giao` |

### `down()` có điều kiện:

```php
$soDonGiao = DB::table('invoices')->where('hinh_thuc_nhan', 'giao_tan_noi')->count();
if ($soDonGiao > 0) {
    throw new RuntimeException("Không thể rollback: còn {$soDonGiao} hóa đơn giao tận nơi...");
}
```

### Rủi ro khi áp lên MySQL thật:

1. **Backup trước**: dùng `sao-luu:csdl` hoặc mysqldump
2. **Thêm cột nullable/default**: MySQL 8 hỗ trợ `ALGORITHM=INSTANT` cho ADD COLUMN có default
3. **KHÔNG đụng enum**: migration không thêm giá trị vào enum `trang_thai` hay `phuong_thuc`
4. **Metadata lock**: chạy lúc thấp điểm
5. **DB thật 0 FK**: migration không tạo FK mới
6. **Lệch migration/DB thật**: sau khi chạy, đối chiếu `information_schema` với `doan_cnw_copy`
7. **Rollback**: `down()` có điều kiện; kịch bản xấu nhất là restore backup

## 2. Config mới

**File:** `config/pos.php`

```php
'phi_giao_hang' => (int) env('POS_PHI_GIAO_HANG', 30000),
'mien_phi_giao_tu' => (int) env('POS_MIEN_PHI_GIAO_TU', 0),
'online_tu_dong_xac_nhan' => env('POS_ONLINE_TU_DONG_XAC_NHAN', false) === true,
```

**File:** `.env.example`

```env
# P2 Giao tận nơi: phí giao hàng cố định (mặc định 30000), miễn phí khi tiền hàng >= ngưỡng (0 = tắt), tự động xác nhận đơn giao
POS_PHI_GIAO_HANG=30000
POS_MIEN_PHI_GIAO_TU=0
POS_ONLINE_TU_DONG_XAC_NHAN=false
```

### Ý nghĩa:

- `POS_PHI_GIAO_HANG`: Phí giao hàng cố định (mặc định 30000). Giá trị giữ chỗ chờ chủ cửa hàng xác nhận thực tế.
- `POS_MIEN_PHI_GIAO_TU`: Ngưỡng miễn phí giao hàng. Nếu `tong_tien` (chưa có phí ship) >= ngưỡng này thì `phi_giao_hang = 0`. Giá trị `0` = tắt (không miễn phí).
- `POS_ONLINE_TU_DONG_XAC_NHAN`: Khi `true` và webhook hoàn tất đơn `giao_tan_noi` thì đặt `trang_thai_giao = da_xac_nhan`; khi `false` đặt `cho_xac_nhan`.

## 3. Model DonHang

**File:** `app/Models/DonHang.php`

Thêm vào `$fillable`:

```php
'hinh_thuc_nhan', 'ten_nguoi_nhan', 'sdt_nguoi_nhan', 'dia_chi_giao', 'phi_giao_hang', 'trang_thai_giao',
```

## 4. Test Migration M3

**File:** `tests/Feature/DatHangP2MigrationTest.php`

### Test cases:

1. **test_t_dh_m3_cot_moi_dung_kieu_nullable_va_index**: Kiểm tra cột đúng kiểu, nullable, default, index
2. **test_t_dh_m3_tao_don_giao_tan_noi**: Tạo đơn giao_tan_noi với đầy đủ thông tin
3. **test_t_dh_m3_tao_don_tai_cua_hang_mac_dinh**: Tạo đơn pickup, các cột giao null, phí = 0
4. **test_t_dh_m3_dia_chi_giao_max_255_ky_tu**: Bỏ qua trên SQLite (kiểm tra trên MySQL copy)
5. **test_t_dh_m3_down_chan_khi_con_don_giao_tan_noi**: Down() chặn khi còn đơn giao_tan_noi
6. **test_t_dh_m3_down_sach_khi_khong_con_don_giao_tan_noi**: Down() sạch khi không còn đơn giao_tan_noi
7. **test_cau_hinh_p2_co_mac_dinh_theo_thiet_ke**: Config đúng giá trị mặc định

### Kết quả:

```
PASS  Tests\Feature\DatHangP2MigrationTest
✓ t dh m3 cot moi dung kieu nullable va index
✓ t dh m3 tao don giao tan noi
✓ t dh m3 tao don tai cua hang mac dinh
✓ t dh m3 dia chi giao max 255 ky tu
✓ t dh m3 down chan khi con don giao tan noi
✓ t dh m3 down sach khi khong con don giao tan noi
✓ cau hinh p2 co mac dinh theo thiet ke

Tests:    7 passed (39 assertions)
Duration: 31.15s
```

## 5. Cập nhật tài liệu thiết kế

**File:** `docs/THIET_KE_DAT_HANG_ONLINE.md`

Thêm mục **11.5 QUYẾT ĐỊNH MỚI (P2 - Giao tận nơi + QR trả trước)** ghi rõ các quyết định mới (D1', D2', D13-D17) và ghi đè các quyết định cũ.

## 6. Phạm vi P2 (để làm)

Theo prompt gốc, P2 cần làm 9 việc:

1. ✅ **Migration M3** (chỉ viết sẵn; kiểm tra M1 trước để không tạo cột trùng) - ĐÃ HOÀN THÀNH
2. ⏳ **Form đặt hàng**: chọn hình thức nhận; chọn giao tận nơi thì bắt buộc họ tên người nhận, SĐT, địa chỉ; giao tận nơi chỉ cho phương thức QR; hiển thị phí giao hàng và tổng cần chuyển khoản
3. ⏳ **Checkout** (mở rộng R1 qua tham số `$online`): tính lại giá, tính phi_giao_hang theo config, tong_thanh_toan = tiền hàng + phí, tạo đơn kenh=online, phuong_thuc QR, han_giu = now + online_qr_phut, giữ kho
4. ⏳ **Trang QR online**: dùng VietQrService với số tiền = tong_thanh_toan (đã gồm phí ship), nội dung chuyển khoản = mã hóa đơn, đếm ngược theo han_giu, polling bằng POST + bằng chứng session
5. ⏳ **Webhook hoàn tất**: tái dùng đường hoàn tất hiện có; nếu đơn kenh=online + giao_tan_noi thì đặt trang_thai_giao theo D15, idempotent
6. ⏳ **Tra cứu đơn** (mã + SĐT, POST): hiển thị trạng thái thanh toán + trạng thái giao, địa chỉ chỉ cho người đã xác thực
7. ⏳ **Báo cáo/dashboard**: phi_giao_hang không tính vào doanh thu/lợi nhuận hàng hóa
8. ⏳ **Test** (đỏ trước): giao tận nơi thiếu từng trường bị từ chối; địa chỉ quá dài/ký tự lạ/HTML escape; phí ship đúng theo config; QR sinh đúng số tiền gồm ship; webhook đủ tiền hoàn tất; webhook lặp idempotent; quá 30 phút hủy + hoàn kho; không bị hủy ở mốc 15 phút; đơn pickup QR/trả tại quầy không đổi hành vi; honeypot, throttle, giới hạn 3 đơn mở/SĐT; khách đăng nhập điền sẵn đúng; Telegram escape và che; polling không PII; migration M3 up/down SQLite + MySQL-copy; test POS/webhook/trả hàng hiện có xanh nguyên trạng
9. ⏳ **Báo cáo** theo mục 9 của prompt gốc: bảng "trước/sau" luồng, danh sách việc để P3, "Cần người dùng xác nhận"

Đợt này chỉ hoàn thành việc 1 (migration + config + model + test). Các việc 2-9 để các pha sau.

## 7. Bảng "trước/sau" luồng

### Trước (P1 - chỉ pickup tại cửa hàng):

```
Khách → Giỏ → Form pickup (tên, SĐT, ghi chú) → Checkout → QR hoặc trả tại quầy
```

### Sau (P2 - thêm giao tận nơi):

```
Khách → Giỏ → Form chọn hình thức nhận:
  - Tai_cua_hang: QR hoặc trả tại quầy (giữ nguyên P1)
  - Giao_tan_noi: BẮT BUỘC QR trả trước + điền tên/SĐT/địa chỉ → Checkout → QR (tổng = tiền hàng + phí ship)
```

### Điểm khác biệt chính:

| Khía cạnh | Trước (P1) | Sau (P2) |
|---|---|---|
| Hình thức nhận | Chỉ `tai_cua_hang` | `tai_cua_hang` + `giao_tan_noi` |
| Phương thức thanh toán | Cả QR và trả tại quầy | `giao_tan_noi`: BẮT BUỘC QR; `tai_cua_hang`: cả hai |
| Phí giao hàng | Không có | Cố định từ config, miễn phí khi đạt ngưỡng |
| Trạng thái giao | Không có (dùng trạng thái thanh toán) | Tách biệt: `trang_thai_giao` |
| Tự động xác nhận | Không | Có cờ `POS_ONLINE_TU_DONG_XAC_NHAN` |
| Hủy đơn đã thanh toán | Không làm | THỦ CÔNG (P3) |

## 8. Danh sách việc để P3

P3 cần làm:

1. **Màn hình quản lý đơn online** cho thủ kho/thu ngân:
   - Danh sách đơn online lọc theo kenh, hinh_thuc_nhan, trang_thai, trang_thai_giao
   - Nút chuyển trạng thái giao (cho_xac_nhan → da_xac_nhan → dang_giao → da_giao → da_huy_giao)
   - Nút hủy đơn đã thanh toán (thủ công)
   - Nút hoàn tiền thủ công

2. **Tự động xác nhận trên giao diện**:
   - Checkbox "Tự động xác nhận đơn giao khi webhook hoàn tất" (ghi vào config POS_ONLINE_TU_DONG_XAC_NHAN)

3. **Tính điểm khi giao**:
   - Chỉ cộng điểm khi `trang_thai_giao = da_giao` (hoặc `da_thanh_toan` cho pickup)

4. **Trả hàng đơn online**:
   - Đơn online là `invoices` nên trả hàng chạy nguyên vẹn (không cần sửa)

5. **Báo cáo doanh thu có/không gồm phí ship**:
   - Xác nhận với chủ cửa hàng: phi_giao_hàng có tính vào doanh thu không
   - Nếu có: tong_thanh_toan = tiền hàng + phí ship
   - Nếu không: tong_thanh_toan = tiền hàng, phi_giao_hang tách riêng

## 9. Cần người dùng xác nhận

| Vấn đề | Câu hỏi | Gợi ý |
|---|---|---|
| Giá trị phí giao hàng | `POS_PHI_GIAO_HANG = 30000` có đúng không? | Chờ chủ cửa hàng xác nhận |
| Ngưỡng miễn phí | `POS_MIEN_PHI_GIAO_TU = 0` (tắt) hay đặt ngưỡng? | Gợi ý: 300000-500000 |
| Tự động xác nhận | `POS_ONLINE_TU_DONG_XAC_NHAN = false` (mặc định) có đúng không? | Thủ ngân thích xác nhận thủ công hay tự động? |
| Phạm vi giao hàng | Có giới hạn phạm vi giao hàng (quận/huyện/khoảng cách)? | Nếu có: cần thêm cột `khoang_cach` hoặc `pham_vi_giao` |
| Định nghĩa doanh thu | Phi_giao_hàng có tính vào doanh thu không? | Gợi ý: KHÔNG tính (doanh thu hàng hóa), phí ship tách riêng |
| Test MySQL copy | Có muốn test migration M3 trên `doan_cnw_copy` trước khi áp lên DB thật? | Gợi ý: CÓ (đối chiếu `information_schema`) |

## 10. Quy trình áp migration lên MySQL thật

1. **Sao lưu**:
   ```bash
   php artisan sao-luu:csdl
   ```
   Hoặc mysqldump thủ công. Xác nhận file backup đọc được.

2. **Thử trên doan_cnw_copy**:
   - Đổi `DB_DATABASE=doan_cnw_copy` trong `.env` (không sửa `.env.example`)
   - Chạy migration:
     ```bash
     php artisan migrate --path=database/migrations/2026_11_14_000003_add_delivery_fields_to_invoices_table.php
     ```
   - Kiểm tra:
     ```sql
     SHOW CREATE TABLE invoices;
     ```
   - Đối chiếu với `doan_cnw` (DB thật) để đảm bảo không lệch
   - Rollback:
     ```bash
     php artisan migrate:rollback --step=1
     ```

3. **Áp lên DB thật** (lúc thấp điểm):
   - Đổi `DB_DATABASE=doan_cnw` trong `.env`
   - Chạy migration
   - Kiểm tra `information_schema`:
     ```sql
     SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = 'doan_cnw' AND TABLE_NAME = 'invoices'
     ORDER BY ORDINAL_POSITION;
     ```
   - Kiểm tra index:
     ```sql
     SHOW INDEX FROM invoices;
     ```

4. **Rollback nếu cần**:
   - Chỉ rollback khi không còn đơn `giao_tan_noi`
   - Nếu còn đơn: xử lý thủ công (xóa hoặc chuyển sang `tai_cua_hang`)

## 11. Tệp đã sửa/tạo

| Tệp | Loại | Mô tả |
|---|---|---|
| `database/migrations/2026_11_14_000003_add_delivery_fields_to_invoices_table.php` | Tạo mới | Migration M3 |
| `config/pos.php` | Sửa | Thêm 3 config mới |
| `.env.example` | Sửa | Thêm 3 env mới |
| `app/Models/DonHang.php` | Sửa | Thêm 6 cột vào fillable |
| `tests/Feature/DatHangP2MigrationTest.php` | Tạo mới | Test migration M3 |
| `docs/THIET_KE_DAT_HANG_ONLINE.md` | Sửa | Thêm mục 11.5 quyết định mới |
| `docs/BAO_CAO_DAT_HANG_P2.md` | Tạo mới | Báo cáo này |

## 12. Tiếp theo

P2 cần tiếp tục với:
- Form đặt hàng (chọn hình thức nhận, nhập thông tin giao)
- Checkout (tính phí ship, tạo đơn với các cột mới)
- Trang QR online (polling, hạn 30 phút)
- Webhook hoàn tất (đặt trang_thai_giao theo cờ tự động xác nhận)
- Tra cứu đơn (hiển thị trạng thái giao)
- Báo cáo/dashboard (phi_giao_hang tách doanh thu)
- Test đỏ (test từng kịch bản)
- Test MySQL copy (migration M3)
