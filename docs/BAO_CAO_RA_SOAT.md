# BÁO CÁO RÀ SOÁT README.md — BẢN LÀM LẠI CÓ BẰNG CHỨNG

**Ngày:** 2026-11-10 (cập nhật lần 2 cùng ngày: sửa 2 test fail, thêm 4 test, xóa controller legacy)
**Nhánh:** `docs/ra-soat-readme` — dựng lại từ commit `ecc0d6d` ("feat: add e-commerce storefront and design system"); **không** merge vào `main`
**Phạm vi:** `D:\Study\xampp\www\DA` (Laravel 12 POS + storefront)
**Thay thế:** bản 2026-11-09 (bị đánh giá thiếu bằng chứng). Bản này trích `file:dòng` cho MỌI khẳng định và nêu rõ test chứng minh; khẳng định không có test ghi **"chưa có test"**.
**Kết quả test cuối cùng:** **66 passed / 0 failed** (274 assertions, 112.5s) trên SQLite `:memory:`.

### Cam kết an toàn (đã thực hiện)
- **KHÔNG** chạy `migrate:fresh`, `seed`, `demo:reset` trên DB thật `doan_cnw`. DB thật không bị đụng tới.
- Toàn bộ `php artisan test` chạy trên **SQLite `:memory:`** do `phpunit.xml:26-27` ép (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`) + trait `RefreshDatabase`. Không có config cache (`bootstrap/cache/` chỉ có `packages.php`, `services.php`).
- **KHÔNG** đọc/ghi/in/commit `.env`. Chỉ dùng `.env.example` và grep code (`env(` / `config(`).
- Tác dụng phụ cục bộ duy nhất của seeder trong test: copy PDF mẫu vào `storage/app/public/hoadonnhap/` (`DuLieuMau300Seeder.php:104-107`). Không ảnh hưởng DB thật.

---

## BƯỚC 1 — BẰNG CHỨNG CHO TỪNG KHẲNG ĐỊNH

### 1.1 `lockForUpdate` (khóa bi quan chống race)

| Vị trí | File:dòng | Test chứng minh |
|--------|-----------|-----------------|
| Khóa sản phẩm khi tạo hóa đơn chờ (sort id tăng dần) | `app/Services/ThanhToanChuyenKhoanService.php:24-26`, `:38` | `ThanhToanChuyenKhoanTest` T-CK01 (`:108`) tạo đơn chờ thành công |
| Khóa khách hàng khi tạo đơn chờ | `ThanhToanChuyenKhoanService.php:58` | T-CK10 (`:365`), T-CK11 (`:389`) điểm tích lũy |
| Khóa sản phẩm + khách + hóa đơn khi hoàn tất thanh toán | `ThanhToanChuyenKhoanService.php:165`, `:171`, `:175` | T-CK02 (`:167`), T-CK05 (`:240`) |
| **Re-check trạng thái TRONG lock** (chống double-complete) | `ThanhToanChuyenKhoanService.php:176-183` | T-CK05 (`:240`) webhook lặp lại không hoàn tất lần 2 |
| Khóa khi hủy đơn chờ + hoàn kho/điểm | `ThanhToanChuyenKhoanService.php:249`, `:255`, `:259-260` | T-CK13 (`:463`), T-CK14 (`:480`) |
| Khóa sản phẩm trong thanh toán trực tiếp (POS) | `app/Http/Controllers/DonHangController.php:222` | `PosWorkflowTest` payment (`:23`), over-stock (`:57`) |
| Khóa khách hàng trong POS | `DonHangController.php:239` | `PosWorkflowTest` loyalty (`:248`), redeem-cap (`:283`) |
| Khóa hóa đơn + chi tiết khi trả hàng | `app/Http/Controllers/TraHangController.php:44`, `:50` | `PosWorkflowTest` returns (`:432`), return-qty (`:482`) |
| Khóa sản phẩm khi áp dụng phiếu trả | `TraHangController.php:137` | `PosWorkflowTest` returns (`:432`) |
| Khóa hóa đơn nhập khi xác nhận (chỉ 1 lần) | `app/Services/HoaDonNhapService.php:24`, `:26-28` | **chưa có test trực tiếp** — chỉ được gọi gián tiếp qua seeder `DuLieuMau300Seeder.php:109`; `PosWorkflowTest:361` chỉ test tạo draft + PDF, KHÔNG test xác nhận nhập kho |
| ~~Legacy HMAC controller (dead code)~~ | ~~`app/Http/Controllers/Api/PaymentWebhookController.php:61-64`~~ | 🗑 **ĐÃ XÓA FILE** (2026-11-10) — fail-open ở `:34`, không route nào đăng ký; `route:list` vẫn **100 routes** sau khi xóa |

> **Lưu ý:** Không có test chạy song song (concurrency) thực sự để chứng minh `lockForUpdate` chặn race; các test trên chỉ chứng minh luồng tuần tự đúng. Khẳng định "chống race" dựa trên đọc code, **chưa có test concurrency**.

### 1.2 `hash_equals` (so sánh hằng thời gian)

| Vị trí | File:dòng | Test chứng minh |
|--------|-----------|-----------------|
| SePay API key — nhánh `Apikey` | `app/Services/Payment/SepayWebhook.php:27` | T-CK04 (`:218`) sai key → 401; T-CK03 (`:180`) đúng key → 200 |
| SePay API key — nhánh raw | `SepayWebhook.php:31` | T-CK04 (`:218`) |
| ~~Legacy HMAC chữ ký~~ | ~~`Api/PaymentWebhookController.php:39`~~ | 🗑 **ĐÃ XÓA FILE** (2026-11-10) cùng biến `POS_WEBHOOK_SECRET` khỏi `config/pos.php`, `phpunit.xml`, `.env.example` |

### 1.3 Idempotent theo `ma_giaodich` / `ma_giao_dich`

| Cơ chế | File:dòng | Test chứng minh |
|--------|-----------|-----------------|
| Check tồn tại CẢ HAI cột trước khi xử lý | `app/Http/Controllers/WebhookController.php:54` (`where('ma_giaodich',...)->orWhere('ma_giao_dich',...)->exists()`) | T-CK05 (`:240`) gửi webhook 2 lần, chỉ hoàn tất 1 lần |
| Re-check trong lock khi hoàn tất | `ThanhToanChuyenKhoanService.php:176-183` | T-CK05 (`:240`), T-CK11 (`:389`) |
| Ghi CẢ HAI cột khi update | `ThanhToanChuyenKhoanService.php:194-195` | T-CK02 (`:167`), T-CK05 (`:240`) |
| Unique index `payment_webhooks.ma_giao_dich_ngoai` | migration `2026_11_02_033152_create_payment_webhooks_table.php:17` | T-CK05 (`:240`) log webhook không nhân đôi |

### 1.4 Lazy expiration (hủy lười đơn chờ quá hạn)

| Vị trí | File:dòng | Test chứng minh |
|--------|-----------|-----------------|
| Hủy khi POS poll trạng thái | `app/Http/Controllers/DonHangController.php:99-103` (`getTrangThai`) | T-CK14 (`:480`) poll sau hạn → `da_huy`, kho hoàn |
| Ngưỡng phút từ config | `DonHangController.php:74`; `config/pos.php:13` (`cho_thanh_toan_phut`, default 15) | T-CK13 (`:463`), T-CK14 (`:480`) |
| Hàm hủy dùng chung | `ThanhToanChuyenKhoanService.php:228-304` (`huyChoThanhToan`) | T-CK13 (`:463`) |

> **Đính chính bản cũ:** Lazy expiration **KHÔNG** kích hoạt khi truy vấn danh sách hóa đơn; chỉ chạy qua 3 điểm gọi `huyChoThanhToan`: `DonHangController.php:101` (poll), `:140` (hủy thủ công), `HuyHoaDonQuaHan.php:33` (command). Xem Bước 3(b).

### 1.5 Hạn trả hàng 7 ngày

| Vị trí | File:dòng | Test chứng minh |
|--------|-----------|-----------------|
| Config số ngày | `config/pos.php:10` (`han_tra_ngay`, default 7) | — |
| Chặn trả quá hạn (422) | `app/Http/Controllers/TraHangController.php:47` (`abort_if($donHang->created_at->lt(now()->subDays(config('pos.han_tra_ngay', 7))), 422, ...)`) | ✅ **ĐÃ BỔ SUNG** `test_return_rejected_after_return_deadline` (`tests/Feature/PosWorkflowTest.php:512`): lùi `created_at` quá hạn, mọi điều kiện khác hợp lệ → 422, `returns` rỗng, kho giữ nguyên, không có `stock_movements` loại `trahang` |

### 1.6 Lệch giá nhập > 10% (cảnh báo, KHÔNG chặn)

| Vị trí | File:dòng | Test chứng minh |
|--------|-----------|-----------------|
| Tính lệch > 10% → warning | `app/Services/HoaDonNhapService.php:35-36` (`abs($item->don_gia - $product->gia_nhap) / $product->gia_nhap > 0.1`) | **chưa có test** — grep `lệch\|10%` trong `tests/` = 0 |
| Cảnh báo giá bán < giá nhập | `HoaDonNhapService.php:38-39` | **chưa có test** |
| Đẩy warning ra flash | `app/Http/Controllers/HoaDonNhapController.php:84-86` | **chưa có test** |

> Đây là **cảnh báo mềm**, không chặn xác nhận nhập kho. Bản cũ mô tả như ràng buộc cứng là sai.

### 1.7 Các khẳng định khác

| Khẳng định | File:dòng | Test chứng minh |
|-----------|-----------|-----------------|
| Chuyển khoản tự lấy đúng tổng, tiền thừa = 0 | `DonHangController.php:252-254`, `:273` | `PosWorkflowTest` transfer (`:89`); T-CK02 (`:167`) |
| Validate giảm giá 0–100% | `DonHangController.php:197` (`min:0`, `max:100`) | **chưa có test** biên giảm giá |
| Tích lũy / tiêu điểm khi thanh toán | `DonHangController.php:243`, `:251`, `:299-303` | `PosWorkflowTest` loyalty (`:248`), redeem-cap (`:283`) |
| Không tiêu quá số dư điểm | `ThanhToanChuyenKhoanService.php:59-65` | `PosWorkflowTest` redeem-cap (`:283`); T-CK11 (`:389`) |
| Tìm sản phẩm `limit(10)` | `app/Http/Controllers/SanPhamController.php:102` | `PosWorkflowTest` search (`:216`) — **chưa assert giới hạn 10** |
| Webhook thiếu tiền → giữ chờ | `WebhookController.php:109-121` | T-CK06 (`:274`) |
| Webhook thừa tiền → ghi chú, vẫn hoàn tất | `WebhookController.php:124-128` | T-CK07 (`:297`) |
| Chỉ nhận giao dịch tiền vào (`in`) | `WebhookController.php:49-51` | T-CK08 (`:322`) |
| Tách mã hóa đơn regex `HD([0-9]{8})([0-9]{4})` | `SepayWebhook.php:71-80` | T-CK03 (`:180`), T-CK09 (`:344`) |
| Chỉ in hóa đơn đã thanh toán (403 nếu chưa) | `DonHangController.php:180-182` | T-CK16 (`:531`) |
| Thống kê doanh thu lọc `da_thanh_toan` | `app/Http/Controllers/ThongKeController.php:37`, `:79` | T-CK16 (`:531`) — **assert yếu** (`assertIsArray` tại `:545`, không kiểm giá trị) |
| VietQR sinh URL / fallback / unconfigured | `app/Services/VietQrService.php:14-28`, `:33-40`, `:46-74` | `VietQrServiceTest` t61–t65 (`:22`, `:41`, `:58`, `:70`, `:86`) |
| Telegram tắt khi chưa cấu hình, không chặn luồng | `app/Services/ThongBaoService.php:18-23`, `:26`, `:42-46` | T-CK setUp (`:23-35`) để telegram rỗng — chỉ chứng minh nhánh TẮT; **chưa có test nhánh gửi thành công** |
| Dọn giỏ tạm hết hạn (`subDay()`) | `app/Console/Commands/XoaGioTamHetHan.php:15` | **chưa có test** |
| Trả hàng hoàn kho tốt / kho hỏng | `TraHangController.php:139`, `:142`, `:145-153` | `PosWorkflowTest` returns (`:432`), return-qty (`:482`) |

---

## BƯỚC 2 — BIẾN MÔI TRƯỜNG & XÁC THỰC WEBHOOK

### 2.1 Code thật sự đọc biến nào?

- Grep `env(` trong `app/` = **0 kết quả**. Mọi biến nghiệp vụ đi qua `config/pos.php` (chuẩn Laravel: chỉ `config()` đọc trong app).
- Bảng ánh xạ `config/pos.php:4-16` (13 biến):

| Config key | Dòng | Biến env | Default | Nơi dùng |
|-----------|------|----------|---------|----------|
| `bank_code` | `:4` | `POS_BANK_CODE` | — | `VietQrService` |
| `bank_account` | `:5` | `POS_BANK_ACCOUNT` | — | `VietQrService` |
| `bank_name` | `:6` | `POS_BANK_NAME` | — | `VietQrService` |
| `qr_template` | `:7` | `POS_QR_TEMPLATE` | `compact2` | `VietQrService.php:46-74` |
| `diem_moi_dong` | `:8` | `POS_DIEM_MOI_DONG` | `100000` | `DonHangController.php:251`; `TraHangController.php:70` |
| `gia_tri_diem` | `:9` | `POS_GIA_TRI_DIEM` | `1000` | `DonHangController.php:243` |
| `han_tra_ngay` | `:10` | `POS_HAN_TRA_NGAY` | `7` | `TraHangController.php:47` |
| `nguong_tra_khong_duyet` | `:11` | `POS_NGUONG_TRA_KHONG_DUYET` | `500000` | `TraHangController.php:71`, `:94-96` |
| **`webhook_secret`** | `:12` | **`POS_WEBHOOK_SECRET`** | rỗng | **chỉ** `Api/PaymentWebhookController.php:33` (dead code) |
| `cho_thanh_toan_phut` | `:13` | `POS_CHO_THANH_TOAN_PHUT` | `15` | `DonHangController.php:74`; `HuyHoaDonQuaHan.php:18` |
| `sepay_webhook_api_key` | `:14` | `POS_SEPAY_WEBHOOK_API_KEY` | rỗng | `SepayWebhook.php:15` |
| `telegram_bot_token` | `:15` | `POS_TELEGRAM_BOT_TOKEN` | rỗng | `ThongBaoService.php:18-23` |
| `telegram_chat_id` | `:16` | `POS_TELEGRAM_CHAT_ID` | rỗng | `ThongBaoService.php:18-23` |

### 2.2 `POS_WEBHOOK_SECRET` còn dùng không?

**Kết luận: KHÔNG có tác dụng runtime.**
- Được nạp tại `config/pos.php:12` nhưng **chỉ** được tiêu thụ bởi `app/Http/Controllers/Api/PaymentWebhookController.php:33`.
- Controller này **không có route** trỏ tới (grep `PaymentWebhookController` trong `routes/` = 0; chỉ tài liệu `TaiLieu/KeHoach/Ke hoach.md:434,445` nhắc). `route:list` không liệt kê.
- **Rủi ro tiềm ẩn:** legacy này **fail-open** — `Api/PaymentWebhookController.php:34` (`if ($secret) { ...verify... }`): nếu secret rỗng thì **bỏ qua xác thực**. Nếu ai đó route lại controller này mà quên set secret → webhook mở. Đối lập với luồng active fail-closed.
- `phpunit.xml:33` đặt `POS_WEBHOOK_SECRET` rỗng (vô hại vì dead code). `.env.example:10` vẫn liệt kê biến này → nên chú thích "legacy, không dùng".

### 2.3 Cơ chế xác thực webhook ACTIVE (SePay)

| Lớp | File:dòng | Bằng chứng |
|-----|-----------|-----------|
| Route active (có throttle) | `routes/web.php:33-35` → `POST /api/webhook/thanhtoan`, name `webhook.thanhtoan`, `throttle:60,1` | `route:list` chỉ hiện route này |
| Route trùng bị che khuất | `routes/api.php:5-6` → cùng URI, name `api.webhook.thanhtoan` | **không xuất hiện** trong `route:list` (bị `web.php` ghi đè) — nên xóa |
| CSRF exemption | `bootstrap/app.php:15-17` (`except: api/webhook/*`) | — |
| Auth API key | `WebhookController.php:31-35` → gọi `SepayWebhook::xacThuc`; sai → 401 | T-CK04 (`:218`) |
| **Fail-closed khi key rỗng** | `SepayWebhook.php:17-19` (key rỗng → trả false → 401) | **chưa có test riêng** nhánh key rỗng (T-CK setUp luôn set key) |
| Header `Authorization: Apikey <KEY>` | `SepayWebhook.php:21`, `:24` (regex), `:27`/`:31` (`hash_equals`) | T-CK03 (`:180`), T-CK04 (`:218`) |

---

## BƯỚC 3 — BỐN KIỂM TRA THEO YÊU CẦU

### 3(a) `ma_giaodich` có unique index trong migration không?

**CÓ — nhưng tồn tại HAI cột song song (nợ kỹ thuật):**

| Cột | Migration:dòng | Ràng buộc |
|-----|----------------|-----------|
| `invoices.ma_giaodich` (100) | `database/migrations/2026_01_01_000007_add_customer_loyalty_to_invoices_table.php:17` | `->nullable()->unique()` |
| `invoices.ma_giao_dich` (40) | `database/migrations/2026_11_02_033147_add_ma_giao_dich_to_invoices_table.php:15` | `->nullable()->unique()->after('phuong_thuc')` |
| `payment_webhooks.ma_giao_dich_ngoai` | `database/migrations/2026_11_02_033152_create_payment_webhooks_table.php:17` | `->unique()` |

> Cả hai cột `invoices` đều nullable + unique. Service ghi cả hai (`ThanhToanChuyenKhoanService.php:194-195`), webhook check cả hai (`WebhookController.php:54`). **Khuyến nghị gộp về một cột** để tránh lệch. (README cũ ghi sai tên migration `add_ma_gia_dich` → đã sửa thành `add_ma_giao_dich`.)

### 3(b) Có schedule/lệnh dọn đơn `cho_thanh_toan` hết hạn, hay chỉ hủy lười?

**CẢ HAI được khai báo, nhưng trên máy này chỉ hủy lười hoạt động:**

| Thành phần | File:dòng | Trạng thái |
|-----------|-----------|-----------|
| Command hủy quá hạn | `app/Console/Commands/HuyHoaDonQuaHan.php:12` (signature `donhang:huy-qua-han`), `:18` config, `:19` cutoff, `:21-24` query, `:32-38` loop hủy | T-CK15 (`:514`) test command chạy đúng |
| Khai báo lịch mỗi phút | `routes/console.php:8` (`Schedule::command('donhang:huy-qua-han')->everyMinute()`) | **chưa có test** đăng ký lịch |
| Lịch dọn giỏ tạm | `routes/console.php:7` (`Schedule::command('hold:clean')->daily()`) | **chưa có test** |
| **Task Scheduler thực tế trên máy** | `schtasks /Query` | **KHÔNG có task Laravel** → command KHÔNG tự chạy |

> **Kết luận thực tế:** Vì không có Windows Task Scheduler gọi `schedule:run`, việc hủy đơn quá hạn trên máy này **chỉ xảy ra lười** qua POS poll (`DonHangController.php:99-103`). Đơn không ai poll sẽ nằm `cho_thanh_toan` vô hạn (kho/điểm bị giữ). Cần cấu hình Task Scheduler để command chạy.

### 3(c) Webhook đến cho đơn đã hết hạn / đã hủy?

| Trạng thái đơn | Hành vi webhook | File:dòng | Test |
|----------------|-----------------|-----------|------|
| `da_huy` | Log + Telegram, trả `success:true`, **KHÔNG đổi trạng thái**, không hoàn kho lần nữa | `WebhookController.php:87-97` | ✅ **ĐÃ BỔ SUNG** `test_T_CK17_webhook_toi_don_da_huy_bi_bo_qua` (`tests/Feature/ThanhToanChuyenKhoanTest.php:561`): đơn quá hạn → chạy `donhang:huy-qua-han` → webhook tới → 200 + `"Hóa đơn đã bị hủy trước đó."`, trạng thái vẫn `da_huy`, `ma_giaodich`/`ma_giao_dich`/`thoi_gian_thanh_toan` null, tồn kho và điểm giữ nguyên, `payment_webhooks` = 0 |
| `da_thanh_toan` | Bỏ qua (idempotent), trả success | `WebhookController.php:100-102` | T-CK05 (`:240`) |
| `cho_thanh_toan` nhưng **đã quá hạn** (chưa bị hủy lười) | **VẪN hoàn tất thanh toán** — KHÔNG có kiểm tra hết hạn trong webhook | `WebhookController.php:104-131`; `ThanhToanChuyenKhoanService.php:150`, `:176-183` (chỉ check trạng thái, không check thời gian) | ✅ **ĐÃ GHI NHẬN BẰNG TEST** `test_T_CK18_webhook_toi_don_qua_han_chua_huy_van_hoan_tat` (`ThanhToanChuyenKhoanTest.php:609`): lùi `created_at` 30 phút (hạn 15), không poll/không chạy lệnh hủy → webhook vẫn trả `"Xác nhận thanh toán thành công."`, đơn thành `da_thanh_toan`, ghi cả 2 cột mã GD, cộng 25 điểm. **Test khóa chặt hành vi hiện tại, KHÔNG đổi logic** |
| Race: webhook vs hủy đồng thời | An toàn nhờ khóa + re-check trạng thái trong lock | `ThanhToanChuyenKhoanService.php:175-183` (hoàn tất), `:259-266` (hủy) | **chưa có test concurrency** |

> **Khoảng trống chính sách:** Đơn quá hạn mà chưa ai poll-hủy, nếu tiền về vẫn được hoàn tất. Cần quyết định: webhook có nên từ chối đơn quá hạn, hay chấp nhận (và command hủy phải chạy đáng tin cậy)? Hiện tại nghiêng về "chấp nhận" một cách ngầm định.

### 3(d) Hoàn tiền + thu hồi điểm khi hóa đơn gốc có giảm giá / dùng điểm?

| Cơ chế | File:dòng | Công thức / hành vi | Test |
|--------|-----------|---------------------|------|
| Hoàn tiền **theo tỷ lệ** thực trả | `TraHangController.php:64-65` | `tienDong = round(soluong * don_gia * (tong_thanh_toan / max(1, tong_tien)))` → tự bù trừ giảm giá % và giảm giá bằng điểm | ✅ **ĐÃ BỔ SUNG** `test_return_of_discounted_invoice_refunds_proportionally_and_caps_point_clawback` (`tests/Feature/PosWorkflowTest.php:553`): `tong_tien` 2.000.000, giảm 10% (200.000) + 100 điểm (100.000) ⇒ `tong_thanh_toan` 1.700.000; trả 1/2 SP → `tong_hoan` = **850.000** (= 1 × 1.000.000 × 1.700.000/2.000.000), `return_items.thanh_tien` = 850.000 |
| Điểm thu hồi | `TraHangController.php:70` | `diem_thu_hoi = floor(tongHoan / max(1, diem_moi_dong))` | ✅ test trên: `diem_thu_hoi` = **8** (= floor(850.000 / 100.000)) |
| Thu hồi **capped** tại số dư | `TraHangController.php:158` | `decrement('diem_tichluy', min($customer->diem_tichluy, $phieuTra->diem_thu_hoi))` | ✅ test trên: khách chỉ có **2** điểm nhưng phải thu hồi 8 → số dư về **0**, không âm. Trước khi duyệt số dư vẫn 2 (chưa trừ) |
| **`diem_su_dung` KHÔNG được hoàn lại** | `TraHangController.php:133-162` (`apDung` không cộng lại điểm đã tiêu) | Khách tiêu điểm để giảm giá, khi trả hàng **không nhận lại điểm đã tiêu** (chỉ bị thu hồi điểm tích lũy) | ✅ **ĐÃ GHI NHẬN BẰNG TEST** — test trên assert số dư cuối = **0**; nếu 100 điểm đã tiêu được hoàn thì số dư sẽ là 2 + 100 − 8 = 94. Đây vẫn là **khoảng trống nghiệp vụ cần chủ dự án xác nhận**, test chỉ khóa chặt hành vi hiện tại |
| Ngưỡng tự duyệt ≤ 500000 | `TraHangController.php:71`, `:94-96`; duyệt tay `:120-131` | `nguong_tra_khong_duyet` | ✅ test trên: 850.000 > 500.000 → `trang_thai = 'cho_duyet'`, kho/điểm chưa đổi; thu ngân gọi `trahang.duyet` → **403**; admin duyệt → `hoan_thanh`, hàng `con_tot` về kho (+1), có `stock_movements` loại `trahang` |
| Test trả hàng hiện có | `PosWorkflowTest:432`, `:482` | Chỉ hóa đơn **trơn** (`tong_tien = tong_thanh_toan = 100000`, không giảm giá, không điểm, không khách) | covers hoàn kho cơ bản |

> **Kết luận (cập nhật 2026-11-10):** Công thức hoàn tỷ lệ **đã được test** với hóa đơn giảm giá + dùng điểm: hoàn đúng 850.000/1.700.000 tỷ lệ, thu hồi điểm bị chặn ở số dư (không âm), ngưỡng duyệt và phân quyền duyệt đều có assert. Hai hành vi **ghi nhận chứ không sửa**: (1) `diem_su_dung` không được hoàn; (2) webhook vẫn hoàn tất đơn quá hạn chưa hủy (T-CK18). Hạn trả 7 ngày đã có test riêng (`PosWorkflowTest.php:512`).

---

## BƯỚC 4 — KẾT QUẢ TEST THẬT

**Lệnh:** `XDEBUG_MODE=off php artisan test` (trong `D:\Study\xampp\www\DA`)
**DB:** SQLite `:memory:` (`phpunit.xml:26-27`) — không đụng `doan_cnw`.

### Lượt 1 (trước khi build asset)
```
Tests:  10 failed, 52 passed (206 assertions)
Duration: 133.13s
```
- **Nguyên nhân:** cả 10 fail đều `Vite manifest not found at ... public/build/manifest.json` (chưa có `node_modules`, chưa có `public/build`). Các view dùng `@vite` trả 500 khi thiếu manifest.
- **Khắc phục (không sửa code/test):** `npm ci --no-audit --no-fund && npm run build` → vite 6.4.5, 134 modules, tạo `public/build/manifest.json`.

### Lượt 2 (sau khi build asset, TRƯỚC khi sửa)
```
Tests:  2 failed, 60 passed (228 assertions)
Duration: 91.81s
```

| Suite | Pass / Tổng |
|-------|-------------|
| `Unit\ExampleTest` | 1/1 |
| `Unit\VietQrServiceTest` | 5/5 |
| `Feature\AuthFlowTest` | 6/7 |
| `Feature\PdfInvoiceParserTest` | 3/3 |
| `Feature\PosWorkflowTest` | 23/23 |
| `Feature\StorefrontHomeTest` | 5/6 |
| `Feature\ThanhToanChuyenKhoanTest` | 16/16 |
| `Feature\ExampleTest` | 1/1 |
| **Tổng** | **60/62** |

**2 fail THẬT trên `main` (không phải môi trường):**
1. `AuthFlowTest.php:29` — `assertSee('Điện thoại nổi bật')`
2. `StorefrontHomeTest.php:21` — `assertSee('Điện thoại nổi bật')`

Cả hai tìm chữ **"Điện thoại nổi bật"** nhưng view thực tế render **"Phụ kiện nổi bật"** (`resources/views/storefront/home.blade.php:18`). Danh mục dự án là **phụ kiện điện thoại** ⇒ **view là nguồn đúng**. Đã sửa 2 test thành `assertSee('Phụ kiện nổi bật')`; **không** sửa view, **không** sửa logic.

### Lượt 3 (sau khi sửa 2 test) — 62 passed
```
Tests:  62 passed (231 assertions)
Duration: 71.77s
```

### Lượt 4 (sau khi thêm 4 test mới) — KẾT QUẢ CHÍNH THỨC
```
Tests:  66 passed (274 assertions)
Duration: 112.50s
```

| Suite | Pass / Tổng | Số test |
|-------|-------------|---------|
| `Unit\ExampleTest` | 1/1 | 1 |
| `Unit\VietQrServiceTest` | 5/5 | 5 |
| `Feature\AuthFlowTest` | 7/7 | 7 |
| `Feature\PdfInvoiceParserTest` | 3/3 | 3 |
| `Feature\PosWorkflowTest` | 25/25 | 25 (+2 mới) |
| `Feature\StorefrontHomeTest` | 6/6 | 6 |
| `Feature\ThanhToanChuyenKhoanTest` | 18/18 | 18 (+2 mới) |
| `Feature\ExampleTest` | 1/1 | 1 |
| **Tổng** | **66/66** | **66** |

Chạy riêng 4 test mới (lọc theo tên): **4 passed, 43 assertions, 5.70s** — mỗi test độc lập, dùng `RefreshDatabase`, không phụ thuộc thứ tự.

**4 test mới:**

| Test | File:dòng |
|------|-----------|
| `test_return_rejected_after_return_deadline` | `tests/Feature/PosWorkflowTest.php:512` |
| `test_return_of_discounted_invoice_refunds_proportionally_and_caps_point_clawback` | `tests/Feature/PosWorkflowTest.php:553` |
| `test_T_CK17_webhook_toi_don_da_huy_bi_bo_qua` | `tests/Feature/ThanhToanChuyenKhoanTest.php:561` |
| `test_T_CK18_webhook_toi_don_qua_han_chua_huy_van_hoan_tat` | `tests/Feature/ThanhToanChuyenKhoanTest.php:609` |

Cả 4 test đều **PASS ngay lần đầu** ⇒ **không lộ lỗi logic cần sửa**. Hai hành vi được *ghi nhận bằng test* thay vì thay đổi (theo đúng yêu cầu "không sửa logic nghiệp vụ"):
- T-CK18: webhook vẫn hoàn tất đơn quá hạn chưa bị hủy.
- Test giảm giá: `diem_su_dung` (điểm khách đã tiêu) không được hoàn khi trả hàng.

### `demo:kiemtra-du-lieu` — đã kiểm tra và CHẠY trên DB thật

Đọc toàn bộ `app/Console/Commands/KiemTraDuLieu.php` (62 dòng). Chỉ có toán tử **đọc**:
`DB::table($table)->count()` (`:19`), `HoaDonNhap::firstOrFail()` (`:26`), `sum()` (`:27-29`),
`DB::select(...)` (`:34`, `:41`, `:47`, `:53`), `$this->info(...)` (`:59`).
**Không có** `insert`/`update`/`delete`/`create`/`save`/`truncate` nào ⇒ an toàn chạy trên DB thật.

Kết quả chạy ngày **2026-11-10** trên DB `doan_cnw` (DB cấu hình hiện tại):
```
categories: 12   products: 300   suppliers: 81   customers: 100
purchase_invoices: 1   purchase_invoice_items: 300
Dữ liệu hợp lệ.   (exit code 0)
```
Khớp đúng bảng kỳ vọng trong `KiemTraDuLieu.php:17`. DB thật **không bị thay đổi** (lệnh chỉ đọc).

> **Lưu ý về lượt chạy nền trước đó:** Một output nền (`PosWorkflowTest`) từng báo fail `admin can filter invoices by date` và `admin can view activity log...`, cùng `ExampleTest`/`AuthFlowTest`/`StorefrontHomeTest` fail do thiếu Vite manifest. Đó là trạng thái **trước khi build asset** (tương đương lượt 1). Các fail Pos/Example đã tự hết sau khi có manifest.

**Đối chiếu bản cũ:** Bản 2026-11-09 ghi "49 passed" là **stale** — commit `ecc0d6d` đã thêm storefront + registration (+13 test → 62), cộng 4 test đợt này → **66**. `route:list` = **100 routes** (bản cũ 87), không đổi sau khi xóa `Api/PaymentWebhookController.php`. `schtasks /Query` không có task Laravel. **Không** chạy `migrate:fresh`/`seed`/`demo:reset` trên DB thật, **không** đọc/ghi `.env`.

---

## BƯỚC 5 — DANH SÁCH "CHƯA CÓ TEST" (tổng hợp, cập nhật 2026-11-10)

### 5A. Đã có test sau đợt bổ sung này (7/17 mục)

| # | Mục cũ | Test chứng minh |
|---|--------|-----------------|
| 1 | Hạn trả hàng 7 ngày (`TraHangController.php:47`) | `test_return_rejected_after_return_deadline` (`PosWorkflowTest.php:512`) |
| 2 | Ngưỡng tự duyệt / luồng duyệt phiếu trả (`:71`, `:94-96`, `:120-131`) | `test_return_of_discounted_...` (`PosWorkflowTest.php:553`) — 850.000 > 500.000 → `cho_duyet`; thu ngân 403; admin duyệt → `hoan_thanh` |
| 3 | Hoàn tiền hóa đơn **có giảm giá / dùng điểm** (`:64-65`) | cùng test trên — `tong_hoan` 850.000 đúng tỷ lệ 1.700.000/2.000.000 |
| 4 | Thu hồi điểm + capped (`:70`, `:158`) | cùng test trên — `diem_thu_hoi` 8, số dư 2 → 0 (không âm) |
| 5 | `diem_su_dung` không hoàn (`:133-162`) | cùng test trên — số dư cuối 0, không phải 94 (**ghi nhận hành vi**, vẫn cần chủ dự án xác nhận) |
| 7 | Webhook cho đơn `da_huy` (`WebhookController.php:87-97`) | `test_T_CK17_webhook_toi_don_da_huy_bi_bo_qua` (`ThanhToanChuyenKhoanTest.php:561`) |
| 8 | Webhook cho đơn quá hạn chưa hủy (`:104-131`) | `test_T_CK18_webhook_toi_don_qua_han_chua_huy_van_hoan_tat` (`ThanhToanChuyenKhoanTest.php:609`) — **ghi nhận hành vi**, không đổi logic |

Mục 6 cũ (legacy HMAC controller) không còn là khoảng trống test: **file đã bị xóa**.

### 5B. Vẫn chưa có test (10 mục còn lại)

1. Cảnh báo lệch giá > 10% / giá bán < giá nhập (`HoaDonNhapService.php:35-39`).
2. Nhánh API key webhook rỗng → fail-closed (`SepayWebhook.php:17-19`) — T-CK setUp luôn set key.
3. Validate biên giảm giá 0–100 (`DonHangController.php:197`).
4. Assert giới hạn `limit(10)` khi tìm sản phẩm (`SanPhamController.php:102`).
5. Command `hold:clean` (`XoaGioTamHetHan.php:15`).
6. Đăng ký lịch `routes/console.php:7-8`.
7. Xác nhận nhập kho + khóa 1 lần (`HoaDonNhapService.php:24-28`) — hiện chỉ seeder gọi.
8. Nhánh Telegram gửi **thành công** (`ThongBaoService.php`) — mới test nhánh tắt.
9. Test concurrency thực sự cho `lockForUpdate` (webhook vs hủy đồng thời).
10. Assert doanh thu T-CK16 còn yếu (`assertIsArray` tại `:545`, không kiểm giá trị).

---

## BƯỚC 6 — CÁC THAY ĐỔI README ĐÃ THỰC HIỆN

Đã sửa `README.md` (các thay đổi ngoài README — test, `config/pos.php`, `phpunit.xml`, `.env.example`, xóa controller legacy — liệt kê ở **Bước 6B**):

1. §3.4: xóa câu sai "không có webhook".
2. Bảng env: dòng `POS_WEBHOOK_SECRET` → chú thích dead-code + fail-open; thêm ghi chú fail-closed SePay.
3. §4.3: lazy expiration chỉ qua poll + ghi chú scheduler chưa cấu hình trên máy.
4. §7: bullet webhook (ghi cả hai cột; hành vi `da_huy`/quá hạn kèm tên test T-CK17/T-CK18).
5. §8: mở rộng bảng URL (storefront, `/dang-ky`, `/trahang`, `/khachhang`, `/hold`, `/kiemke`, `/nhatky`, `/thongke`, `/baocao`, throttle).
6. §9: viết lại danh sách test (**66 test**/8 suite; yêu cầu build asset; danh sách 10 mục còn thiếu test).
7. §11: sửa cây thư mục (Storefront controllers, 2 command, views `kiemke`/`storefront`; sửa tên migration `add_ma_giao_dich`; 2 migration mới `2026_11_09_*`); **gỡ nhánh `Api/`** vì controller legacy đã xóa.
8. §12: DB (role `khachhang`, `customers.user_id` unique, hai cột unique song song).
9. §14: thêm storefront + đăng ký khách hàng.
10. §15 Pha15A: xóa mã test T-HDN không tồn tại; đổi dải mã test thành `T-CK01..T-CK18`.
11. §15 Pha18: công thức hoàn tỷ lệ + khoảng trống `diem_su_dung`, nay kèm test chứng minh.
12. §17: xóa bullet sai "Draft-Review UI chưa triển khai" (thực tế có tại `resources/views/hoadonnhap/form.blade.php:27`, `:412-484`); xóa bullet "2 test fail" (đã sửa); thêm 6 bullet nợ kỹ thuật (scheduler chưa cấu hình trên Windows, webhook không kiểm tra hạn theo T-CK18, `diem_su_dung` không hoàn, 2 cột mã giao dịch song song, route webhook khai báo trùng).
13. §18: thay bằng kết quả thật 2026-11-10 (4 lượt chạy, **66 passed / 0 failed**, bảng 4 test mới, minh chứng `demo:kiemtra-du-lieu` chỉ đọc, ghi chú controller legacy đã xóa, 100 routes, link báo cáo).
14. **Mục lục README:** thêm dòng `18. Kết quả kiểm tra`; bỏ ngày khỏi tiêu đề §18 (đưa vào thân bài) để anchor `#18-kết-quả-kiểm-tra` khớp; đã đối chiếu toàn bộ anchor còn lại theo quy tắc sinh anchor GitHub (thường hóa, bỏ dấu câu, khoảng trắng → gạch nối).
15. **§5:** thêm tiểu mục "Chạy lịch hẹn khi demo" hướng dẫn `php artisan schedule:work`, liệt kê task trong `routes/console.php`, phương án Windows Task Scheduler, hệ quả của hủy lười và ghi chú webhook không kiểm tra hạn.
16. **Bảng biến môi trường:** xóa dòng `POS_WEBHOOK_SECRET`, thay bằng ghi chú webhook chỉ xác thực bằng API Key và biến HMAC đã được gỡ khỏi `.env.example`, `config/pos.php`, `phpunit.xml` cùng controller legacy.

---

## BƯỚC 6B — THAY ĐỔI NGOÀI README (đợt 2026-11-10)

| File | Thay đổi | Lý do |
|------|----------|-------|
| `tests/Feature/AuthFlowTest.php:29` | `assertSee('Điện thoại nổi bật')` → `'Phụ kiện nổi bật'` | View là nguồn đúng (`home.blade.php:18`) |
| `tests/Feature/StorefrontHomeTest.php:21` | như trên | như trên |
| `tests/Feature/PosWorkflowTest.php` | +2 test (`:512`, `:553`), thêm `use App\Models\PhieuTra;` | Mục 5(a), 5(b) của yêu cầu |
| `tests/Feature/ThanhToanChuyenKhoanTest.php` | +2 test (`:561`, `:609`) | Mục 5(c), 5(d) của yêu cầu |
| `app/Http/Controllers/Api/PaymentWebhookController.php` | **xóa** (`git rm`) | Dead code, không route, fail-open ở `:34` |
| `config/pos.php` | xóa dòng `webhook_secret` | Không còn nơi nào đọc |
| `phpunit.xml` | xóa `<env name="POS_WEBHOOK_SECRET" .../>` | Đồng bộ |
| `.env.example` | xóa `POS_WEBHOOK_SECRET=` | Đồng bộ (`.env` **không** bị đọc/ghi) |

Không sửa bất kỳ dòng logic nghiệp vụ nào trong `app/`.

---

## BƯỚC 7 — KHUYẾN NGHỊ

### Đã thực hiện trong đợt này
1. ✅ **Sửa 2 test fail theo view** (view là nguồn đúng) — suite nay xanh 66/66.
2. ✅ **Bổ sung 4 test ưu tiên cao nhất** ở Bước 5: hạn trả, hoàn tiền giảm giá + thu hồi điểm capped + ngưỡng duyệt, webhook `da_huy`, webhook quá hạn chưa hủy.
3. ✅ **Xóa `Api/PaymentWebhookController`** (dead, fail-open) và gỡ sạch `POS_WEBHOOK_SECRET`.

### Còn lại, chưa tự thực hiện
4. **Quyết định nghiệp vụ cần chủ dự án chốt:** (a) có hoàn `diem_su_dung` khi trả hàng? (b) webhook có nên từ chối đơn quá hạn chưa hủy? Cả hai hiện đã có test *ghi nhận hành vi*, nếu đổi chính sách thì phải sửa test tương ứng (T-CK18, test giảm giá).
5. **Bổ sung test cho 10 mục ở Bước 5B** (ưu tiên: lệch giá nhập > 10%, nhánh API key rỗng fail-closed, `hold:clean`, xác nhận nhập kho chống bấm 2 lần).
6. **Dọn nợ kỹ thuật:** bỏ route webhook trùng trong `routes/api.php:5-6`; gộp hai cột `ma_giaodich`/`ma_giao_dich` về một.
7. **Cấu hình Windows Task Scheduler** gọi `php artisan schedule:run` mỗi phút (hoặc chạy `php artisan schedule:work` khi demo) để `donhang:huy-qua-han` và `hold:clean` thực chạy.
8. **README gốc workspace** `D:\Study\xampp\www\README.md:240,259` còn mô tả cũ về phạm vi webhook — chỉ flagged, **không sửa** (ngoài phạm vi nhánh `DA`).

---

*Báo cáo này thay thế bản 2026-11-09. Mọi khẳng định đều kèm `file:dòng`; mục không có test được đánh dấu rõ ràng. Không chạy `migrate:fresh`/`seed`/`demo:reset` trên DB thật, không đọc/ghi `.env`. Commit theo nhóm trên nhánh `docs/ra-soat-readme`, không merge vào `main`.*