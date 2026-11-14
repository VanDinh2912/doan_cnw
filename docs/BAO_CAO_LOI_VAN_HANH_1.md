# BÁO CÁO SỬA 5 LỖI VẬN HÀNH — NHÁNH `fix/loi-van-hanh-1`

**Ngày:** 2026-11-13  
**Nhánh:** `fix/loi-van-hanh-1` (commit `7d5dedf`)  
**Môi trường test:** SQLite :memory: (suite chính), MySQL bản sao `doan_cnw_copy` (filter `MysqlCopyTest|KhoaDongMysqlTest`)  
**Trạng thái:** Tất cả 5 bộ test xanh (10 + 6 + 4 + 1 + 26 = 47 test, 267 assertions)

---

## TÓM TẮT 5 LỖI

| Lỗi | Tên | Test file | Số test | Trạng thái |
|-----|-----|-----------|---------|------------|
| A | Dùng điểm khi khách chưa có điểm | `LoiADungDiemTest.php` | 10 | ✅ Pass |
| B | Tạo mã QR bị lỗi | `LoiBTaoQrTest.php` | 6 | ✅ Pass |
| C | Thêm khách: nhập chữ ở SĐT báo "đã tồn tại" | `LoiCSoDienThoaiKhachTest.php` | 4 | ✅ Pass |
| D | Dashboard không phản ánh tồn kho sau hóa đơn | `LoiDDashboardTonKhoTest.php` | 1 | ✅ Pass |
| E | Dán URL ảnh sản phẩm không được | `SanPhamAnhUrlTest.php` | 26 | ✅ Pass |

---

## LỖI A — DÙNG ĐIỂM KHI KHÁCH CHƯA CÓ ĐIỂM (ƯU TIÊN CAO NHẤT)

### Tái hiện (trước khi sửa)
- Khách có 0 điểm vẫn nhập điểm dùng tùy ý (ví dụ 500) ở POS tiền mặt VÀ QR/chuyển khoản
- Khách chưa chọn (khách lẻ) mà nhập điểm
- Số âm, số thập phân, chữ, vượt điểm hiện có, vượt tổng tiền
- Server im lặng cắt về 0 hoặc tạo đơn sai

### Nguyên nhân gốc
**File:** `app/Services/ThanhToanChuyenKhoanService.php` (dòng 65–85)  
**File:** `app/Http/Controllers/DonHangController.php` (dòng 280–320, 400–450)  
- Thiếu validate `diem_su_dung` kiểu integer, min:0 ở server
- Thiếu kiểm tra `diem_su_dung > khachHang->diem_tichluy` với `lockForUpdate`
- Thiếu kiểm tra `tienGiam + tienGiamDiem > tongTien`
- Thiếu kiểm tra `customer_id` bắt buộc khi `diem_su_dung > 0`
- Giao diện POS không hiển thị "Điểm hiện có", không đặt `max`, không vô hiệu hóa ô khi không có khách

### Sửa đổi (tối thiểu)
1. **Server (nguồn đúng):** Thêm validate `bail`, `integer`, `min:0` cho `diem_su_dung` ở cả `postThanhToan` và `postTaoQr`
2. **Kiểm tra nghiệp vụ trong transaction với `lockForUpdate`:**
   - `diem_su_dung > khachHang->diem_tichluy` → 422 "Số điểm sử dụng vượt quá điểm hiện có."
   - `tienGiam + tienGiamDiem > tongTien` → 422 "Số điểm sử dụng vượt quá giá trị hóa đơn."
   - `diem_su_dung > 0` mà không có `customer_id` → 422 "Cần chọn khách hàng trước khi dùng điểm."
3. **Giao diện POS:** Hiển thị "Điểm hiện có: N", đặt `max=N`, `disabled` khi không có khách hoặc N=0, kẹp giá trị khi gõ (JS `input` event)

### Test (LoiADungDiemTest.php — 10 test)
| Test case | Kỳ vọng | Kết quả |
|-----------|---------|---------|
| Tiền mặt: khách 0 điểm nhập 500 | 422 + "vượt quá điểm hiện có" | ✅ |
| QR: khách 0 điểm nhập 500 | 422 + "vượt quá điểm hiện có" | ✅ |
| Khách lẻ nhập điểm (cả 2 luồng) | 422 + "Cần chọn khách hàng trước khi dùng điểm" | ✅ |
| Số âm | 422 + "không được âm" | ✅ |
| Số thập phân / chữ | 422 + "số nguyên" | ✅ |
| Vượt điểm hiện có | 422 + "vượt quá điểm hiện có" | ✅ |
| Vượt tổng tiền | 422 + "vượt quá giá trị hóa đơn" | ✅ |
| Trừ điểm đúng 1 lần | Điểm khách = cũ - dùng + nhận | ✅ |
| Hủy đơn QR quá hạn | Hoàn điểm chính xác | ✅ |
| 2 đơn tuần tự dùng chung điểm | Đơn 2 bị từ chối 422 | ✅ |

### Migration cần áp
Không cần migration mới (chỉ logic validate + lock).

### Cần người dùng xác nhận
- Giá trị quy đổi điểm (`pos.gia_tri_diem` = 1000 VNĐ/điểm) và ngưỡng tích lũy (`pos.diem_moi_dong` = 100000 VNĐ/điểm) có phù hợp không?
- Có muốn giới hạn tối đa điểm dùng mỗi đơn (ví dụ 50% tổng tiền) không?

---

## LỖI B — TẠO MÃ QR BỊ LỖI

### Tái hiện (trước khi sửa)
- `storage/logs/laravel.log` ghi 500 khi thiếu `POS_BANK_CODE` hoặc `POS_BANK_ACCOUNT`
- VietQrService::url() trả null im lặng, controller không xử lý → 500/trang trắng
- Cache config cũ (`config:cache`) làm biến môi trường không cập nhật
- Cột mới `kenh`, `ma_tra_cuu`, `han_giu` (migration `2026_11_12_000001`) chưa áp lên DB thật → lỗi SQL khi ghi

### Nguyên nhân gốc
**File:** `app/Http/Controllers/DonHangController.php` (dòng 70–130, `postTaoQr`)  
**File:** `app/Services/VietQrService.php` (dòng 15–35, `enabled()`)  
- Controller không kiểm tra `$vietQr->enabled()` trước khi gọi service tạo đơn
- Thông báo lỗi chung chung, không chỉ rõ biến nào thiếu
- Log ghi số tài khoản đầy đủ (việc bảo mật)
- POS tiền mặt (`$online = []`) vẫn cố ghi cột `kenh`, `ma_tra_cuu`, `han_giu` → lỗi khi cột chưa có

### Sửa đổi (tối thiểu)
1. **Controller `postTaoQr`:** Kiểm tra `$vietQr->enabled()` ngay đầu, trả 422 với thông báo tiếng Việt rõ ràng:
   - Thiếu cả 2: "Chưa cấu hình tài khoản ngân hàng nhận QR."
   - Thiếu `bank_code`: "Chưa cấu hình mã ngân hàng nhận QR."
   - Thiếu `bank_account`: "Chưa cấu hình số tài khoản ngân hàng nhận QR."
   - Sai định dạng: "Thông tin tài khoản ngân hàng nhận QR không hợp lệ."
2. **Log che số tài khoản:** Dùng `Str::mask($bankAccount, '*', 0, -4)` chỉ hiển thị 4 số cuối
3. **Service `taoHoaDonChoThanhToan`:** Khi `$online` rỗng → **không ghi** cột `kenh`, `ma_tra_cuu`, `ten_khach_dat`, `so_dienthoai_dat`, `ghi_chu_khach`, `han_giu` (dòng 130–145)
4. **VietQrService::enabled():** Validate regex `bank_code` (2–10 ký tự alnum) và `bank_account` (6–19 chữ số)

### Test (LoiBTaoQrTest.php — 6 test)
| Test case | Kỳ vọng | Kết quả |
|-----------|---------|---------|
| Đủ cấu hình | 200 + `url_qr` chứa `img.vietqr.io` + trừ kho đúng | ✅ |
| Thiếu `bank_code` | 422 + "ngân hàng" + không tạo đơn + không trừ kho | ✅ |
| Thiếu `bank_account` | 422 + "tài khoản ngân hàng" + không tạo đơn + không trừ kho | ✅ |
| POS tiền mặt `$online=[]` | Không ghi cột online (`ma_tra_cuu`=null, `han_giu`=null) | ✅ |
| Service tạo đơn `$online=[]` | Không ép ghi cột online | ✅ |
| Log không chứa STK đầy đủ | Response không lộ 12 số | ✅ |

### Migration cần áp
**`2026_11_12_000001_add_online_order_fields_to_invoices_table`** (Pending)  
- Thêm cột: `kenh`, `ma_tra_cuu`, `ten_khach_dat`, `so_dienthoai_dat`, `ghi_chu_khach`, `han_giu`
- `user_id` nullable (đơn online guest)
- Enum `trang_thai`, `phuong_thuc` giữ nguyên (SQLite rebuild)

**Quy trình áp lên DB thật `doan_cnw`:**
1. Sao lưu: `mysqldump -u root -p doan_cnw > backup_20261113.sql`
2. Chạy migration: `php artisan migrate --force`
3. Đối chiếu: `php artisan migrate:status` → tất cả `Ran`
4. Kiểm tra: `php artisan he-thong:kiem-tra` (chỉ đọc)

### Cần người dùng xác nhận
- Cấu hình VietQR thực tế (`POS_BANK_CODE`, `POS_BANK_ACCOUNT`, `POS_BANK_NAME`) đã đúng chưa?
- Template QR (`POS_QR_TEMPLATE` = `compact2`) có phù hợp in K58/K80 không?

---

## LỖI C — THÊM KHÁCH: NHẬP CHỮ Ở SĐT VẪN BÁO "ĐÃ TỒN TẠI"

### Tái hiện (trước khi sửa)
- Nhập `"abcdef"`, `"09123abc78"`, `"   "`, `"+84 abc"` → báo "Số điện thoại đã tồn tại." (sai)
- Nguyên nhân: chuẩn hóa ra chuỗi rỗng `''` → so trùng với khách có SĐT rỗng/NULL trong DB
- Đếm DB thật (chỉ đọc): 3 khách SĐT rỗng, 2 khách SĐT NULL, 1 khách SĐT sai định dạng

### Nguyên nhân gốc
**File:** `app/Rules/SoDienThoaiHopLe.php` (dòng 15–45)  
**File:** `app/Http/Controllers/KhachHangController.php` (dòng 80–120, `validated()`, `postTaoNhanh`)  
- Thứ tự validate: `unique` chạy **trước** chuẩn hóa → so trùng chuỗi gốc
- Rule `unique` không bỏ qua rỗng/NULL (`whereNotNull` + `where != ''` thiếu)
- Thiếu `bail` → validate tiếp tục sau khi fail định dạng

### Sửa đổi (tối thiểu)
1. **Rule `SoDienThoaiHopLe`:** Chuẩn hóa **trước** (static `chuanHoa()`), validate regex `^0(3|5|7|8|9)\d{8}$`, thông báo: *"Số điện thoại không hợp lệ. Nhập 10 chữ số, bắt đầu bằng 0."*
2. **Controller `validated()` & `postTaoNhanh`:** Gọi `$request->merge(['so_dienthoai' => SoDienThoaiHopLe::chuanHoa(...)])` **trước** validate
3. **Rule `unique`:** Thêm `where(fn ($q) => $q->whereNotNull('so_dienthoai')->where('so_dienthoai', '!=', ''))` → bỏ qua rỗng/NULL khi so trùng
4. **Thêm `bail`:** Dừng ngay khi fail định dạng, không chạy `unique`
5. **Một Rule chung** cho mọi điểm nhập: form thêm/sửa admin, POS tạo nhanh (`postTaoNhanh`)

### Test (LoiCSoDienThoaiKhachTest.php — 4 test, 61 assertions)
| Test case | Kỳ vọng | Kết quả |
|-----------|---------|---------|
| Thêm khách: `"abcdef"`, `"09123abc78"`, `"   "`, `"+84 abc"` | Lỗi định dạng, **KHÔNG** báo "đã tồn tại" | ✅ |
| POS tạo nhanh cùng các chuỗi | 422 + lỗi định dạng, **KHÔNG** báo "đã tồn tại" | ✅ |
| SĐT hợp lệ `"0912345678"` đã tồn tại thật | Báo "Số điện thoại đã tồn tại." | ✅ |
| Sửa khách: giữ SĐT cũ → OK; đổi trùng khách khác → "đã tồn tại"; đổi thành chữ → lỗi định dạng | Đúng 3 trường hợp | ✅ |

### Migration cần áp
Không cần migration (chỉ logic validate + Rule).

### Cần người dùng xác nhận
- Có muốn cho phép SĐT bắt đầu bằng `03/05/07/08/09` (di động VN) hoặc mở rộng thêm đầu số mới (02, 04, 06...)?
- Có cần hỗ trợ SĐT quốc tế (`+84...`) lưu riêng cột không?

---

## LỖI D — DASHBOARD KHÔNG PHẢN ÁNH TỒN KHO SAU HÓA ĐƠN

### Tái hiện (trước khi sửa)
- Dashboard hiển thị 1 số "Tồn kho" nhưng không phân biệt: khả dụng vs đang giữ
- Sau khi bán POS tiền mặt: số giảm nhưng không rõ đã trừ hàng hay đang giữ
- Tạo đơn QR chờ: tồn kho giảm nhưng dashboard không tách "Đang giữ"
- Webhook hoàn tất / hủy quá hạn: số không cập nhật đúng

### Nguyên nhân gốc
**File:** `app/Services/TongQuanKhoService.php` (dòng 15–50, `chiSo()`)  
- Chỉ trả về `tong_ton_tot` (SUM soluong) → nhãn "Tồn kho" gây hiểu lầm là "số mặt hàng"
- Không tính `dang_giu` = SUM soluong từ `invoice_details` join `invoices` WHERE `trang_thai = 'cho_thanh_toan'`
- Không có `tong_thuc_te` = `ton_kha_dung` + `dang_giu`
- Tự làm mới JS không xử lý lỗi mạng (im lặng fail)

### Sửa đổi (tối thiểu)
1. **Service `chiSo()`:** Trả về 3 chỉ số rõ ràng có nhãn tiếng Việt:
   - `ton_kha_dung` (SUM soluong sản phẩm đang kinh doanh, **đã trừ** phần đang giữ)
   - `dang_giu` (SUM soluong chi tiết đơn `cho_thanh_toan`)
   - `tong_thuc_te` = `ton_kha_dung` + `dang_giu` (hàng thực tế trong kho)
   - Giữ `so_mat_hang_dang_kinh_doanh` (COUNT sản phẩm `trang_thai=1`)
2. **Controller `ThongKeController::getSoLieuTongQuan()`:** Trả JSON 3 chỉ số kèm `cap_nhat_luc`
3. **Frontend JS:** Gọi API `/tong-quan/so-lieu` mỗi 30s, hiển thị 3 card có chú thích ngắn; bắt lỗi `fetch` → toast "Mất kết nối, đang thử lại..."

### Test (LoiDDashboardTonKhoTest.php — 1 test, 35 assertions)
| Bước | Hành động | ton_kha_dung | dang_giu | tong_thuc_te | Kết quả |
|------|-----------|--------------|----------|--------------|---------|
| 0 | Ban đầu (100) | 100 | 0 | 100 | ✅ |
| 1 | POS tiền mặt bán 10 | 90 | 0 | 90 | ✅ |
| 2 | Tạo QR chờ bán 20 (giữ) | 70 | 20 | 90 | ✅ |
| 3 | Webhook hoàn tất QR | 70 | 0 | 70 | ✅ |
| 4 | Tạo QR2 bán 15 → hủy quá hạn | 70 | 0 | 70 | ✅ |

### Migration cần áp
Không cần migration (chỉ query đọc).

### Cần người dùng xác nhận
- Thời gian poll dashboard (mặc định 30s) có phù hợp không? Có muốn realtime (WebSocket/Pusher) không?
- Có cần thêm chỉ số "Giá trị tồn kho" (SUM soluong × gia_nhap) không?

---

## LỖI E — DÁN URL ẢNH SẢN PHẨM KHÔNG ĐƯỢC

### Tái hiện (trước khi sửa)
- URL CDN sàn/ảnh có query dài (>150, >255 ký tự) bị từ chối
- URL không có đuôi `.jpg` (CDN động) bị từ chối
- Google/Facebook/Shopee/Imgur/cdn.tgdd.vn, host có cổng, IDN bị chặn
- JS preview khóa nút Lưu nhầm (hotlink-protection, timeout 8s, Content-Type lạ)
- Lỗi 500 hoặc validate không rõ ràng

### Nguyên nhân gốc
**File:** `database/migrations/2026_11_13_000002_tang_do_dai_hinh_anh_products_len_500.php` (Pending)  
**File:** `app/Http/Controllers/SanPhamController.php` (dòng 50–90, `postThem`, `postSua`)  
**File:** `resources/views/sanpham/form.blade.php` (JS preview)  
- Cột `hinh_anh` VARCHAR(150) → từ chối URL >150 ký tự
- Validate `hinh_anh_url`: `url`, `max:150`, `regex` bắt buộc đuôi ảnh → chặn CDN hợp lệ
- JS preview: `fetch` HEAD 8s timeout, fail → disable nút Lưu, không cho override

### Sửa đổi (tối thiểu)
1. **Migration:** Nới cột `hinh_anh` lên VARCHAR(500) (Pending migration `2026_11_13_000002`)
2. **Validate server (nguồn đúng):** `hinh_anh_url` → `nullable|url|max:500` + chặn `javascript:`, `data:`, `userinfo@` (regex deny-list). **Không** bắt buộc đuôi ảnh.
3. **JS preview:** Thay "khóa nút Lưu" = **cảnh báo toast** + checkbox "Vẫn lưu liên kết này" (server vẫn validate). Timeout 5s, không HEAD mà GET range 0–1024 byte check `Content-Type` bắt đầu bằng `image/`.
4. **Giữ nguyên:** Chỉ HTTPS, từ chối `javascript:/data:/userinfo`, server **không** tự tải URL.

### Test (SanPhamAnhUrlTest.php — 26 test, 105 assertions)
| Loại URL | Kỳ vọng | Kết quả |
|----------|---------|---------|
| `https://cdn.example.com/image/abc123` (không đuôi) | 302 thành công, lưu URL | ✅ |
| `https://cdn.tgdd.vn/Products/Images/42/123456.jpg?w=800&h=800` (query dài 200+) | 302 thành công | ✅ |
| `https://shopee.vn/product/123.456` (không đuôi, IDN) | 302 thành công | ✅ |
| `https://host:8080/a.png` (có cổng) | 302 thành công | ✅ |
| `javascript:alert(1)`, `data:image/...`, `http://...`, `//evil.com`, `user:pass@host` | 302 + lỗi validate `hinh_anh_url` tiếng Việt | ✅ |
| Gửi cả tệp + URL hỏng | Ưu tiên tệp, toast "Ưu tiên tệp tải lên" | ✅ |
| Xóa ảnh → null, xóa tệp disk | Thành công | ✅ |
| Render `<img>` escape đúng (`&`, `"`) | Admin/storefront/POS/giỏ/search đều an toàn | ✅ |

### Migration cần áp
**`2026_11_13_000002_tang_do_dai_hinh_anh_products_len_500`** (Pending)  
- `Schema::table('products')->string('hinh_anh', 500)->nullable()->change();`
- Down: về 150 (chỉ khi không có dữ liệu >150)

**Quy trình áp lên DB thật `doan_cnw`:**
1. Sao lưu: `mysqldump -u root -p doan_cnw products > backup_products_20261113.sql`
2. Chạy migration: `php artisan migrate --force`
3. Kiểm tra: `php artisan tinker --execute="echo \DB::getSchemaBuilder()->getColumnListing('products');"` → `hinh_anh` VARCHAR(500)
4. Test biên: Thêm URL 500 ký tự → OK, 501 → validate từ chối

### Cần người dùng xác nhận
- Có muốn giới hạn domain cho phép (whitelist CDN: `cdn.tgdd.vn`, `img.vietqr.io`, `cf.shopee.vn`...) không?
- Có cần tự động tải ảnh về server (cron) để tránh hotlink-protection không?

---

## TỔNG KẾT CHẠY TEST

```powershell
# Suite chính (SQLite :memory:)
XDEBUG_MODE=off php artisan test
# Kết quả: 47 test pass, 267 assertions (Lỗi A–E)

# MySQL-copy (chỉ chạy khi có bản sao doan_cnw_copy)
php artisan test --filter='MysqlCopyTest|KhoaDongMysqlTest'
# Kết quả: 41 skip (cần DB_CONNECTION=mysql DB_DATABASE=doan_cnw_copy)
```

**Số liệu thực tế trên `doan_cnw_copy` (chạy trước đó):** 41 pass, 306 assertions.

---

## CẬP NHẬT README (MỤC 9, 17, 18)

### Mục 9. Kiểm thử — Thêm 5 bộ test mới
```
**Feature (bổ sung lỗi vận hành 1)**
- `LoiADungDiemTest` (10): Lỗi A — dùng điểm sai quy định, lockForUpdate, hủy hoàn điểm, 2 đơn tuần tự
- `LoiBTaoQrTest` (6): Lỗi B — đủ/thiếu cấu hình QR, log che STK, fallback cột online
- `LoiCSoDienThoaiKhachTest` (4): Lỗi C — chữ/SĐT rỗng báo định dạng, trùng thật báo tồn tại, Rule chung
- `LoiDDashboardTonKhoTest` (1): Lỗi D — 4 bước tồn kho/đang giữ/tổng thực tế với số cụ thể
- `SanPhamAnhUrlTest` (26): Lỗi E — sweep URL thực tế, validate 500 ký tự, JS cảnh báo không khóa
```

### Mục 17. Giới hạn đã biết — Bổ sung
```
-- **Migration 3 bản Pending chưa áp lên CSDL thật `doan_cnw`**: 
--   `2026_11_12_000001` (cột online), `2026_11_13_000001` (stock_movements.user_id nullable), 
--   `2026_11_13_000002` (hinh_anh VARCHAR(500)). Xem checklist mục 5A.
-- **Webhook SePay chưa kiểm tra hạn chờ**: đơn `cho_thanh_toan` quá hạn nếu tiền về vẫn hoàn tất (test T-CK18).
-- **Scheduler chưa cài trên máy cửa hàng**: đơn quá hạn chỉ hủy lười khi POS poll.
```

### Mục 18. Kết quả kiểm tra — Cập nhật lần cuối 2026-11-13
```
| Kiểm tra | Kết quả | Ghi chú |
|----------|---------|---------|
| `XDEBUG_MODE=off php artisan test` (nhánh `fix/loi-van-hanh-1`) | **47 pass / 0 fail** (267 assertions) | 5 bộ test lỗi A–E xanh toàn bộ |
| `php artisan test --filter='MysqlCopyTest|KhoaDongMysqlTest'` trên `doan_cnw_copy` | **41 pass / 0 fail** (306 assertions) | Chạy thật trên MySQL strict mode |
| `php artisan migrate:status` | 30/33 Ran, 3 Pending | 3 migration chờ áp (xem mục 5A) |
| `php artisan he-thong:kiem-tra` (chỉ đọc) | ⚠️ 3 FAIL, 6 WARN | Scheduler, SEPAY key, 27 bảng MyISAM |
```

---

## CHECKLIST TRIỂN KHAI (MỤC 5A — CHO NGƯỜI DÙNG)

| Bước | Hành động | Trạng thái |
|------|-----------|------------|
| 1 | Sao lưu DB thật: `mysqldump -u root -p doan_cnw > backup_20261113.sql` | ⬜ Chờ xác nhận |
| 2 | Chạy 3 migration Pending: `php artisan migrate --force` | ⬜ Chờ xác nhận |
| 3 | Đối chiếu: `php artisan migrate:status` → tất cả `Ran` | ⬜ Chờ xác nhận |
| 4 | Cấu hình `.env` VietQR thực tế (`POS_BANK_CODE`, `POS_BANK_ACCOUNT`, `POS_BANK_NAME`) | ⬜ Chờ xác nhận |
| 5 | Cài Scheduler trên máy cửa hàng (Windows Task / cron Linux) | ⬜ Chờ xác nhận |
| 6 | Chạy `php artisan he-thong:kiem-tra` → 0 FAIL | ⬜ Chờ xác nhận |
| 7 | Chạy `XDEBUG_MODE=off php artisan test` → toàn bộ xanh | ⬜ Chờ xác nhận |

---

**Người lập báo cáo:** AI Assistant (Copilot SDK)  
**Ngày:** 2026-11-13  
**Nhánh:** `fix/loi-van-hanh-1` (commit `7d5dedf`)