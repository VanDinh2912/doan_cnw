# Thiết kế đặt hàng online tối thiểu cho Web POS

- Nhánh: `docs/thiet-ke-dat-hang`. Tài liệu **thiết kế, chưa triển khai**: không code, không migration trong đợt này.
- Hiện trạng + bằng chứng chạy thật: [BAO_CAO_LUONG_DAT_HANG.md](BAO_CAO_LUONG_DAT_HANG.md) (14 test probe, 196 assertions, OK).
- Phạm vi: khách **vãng lai** và khách **đã đăng nhập** đặt hàng từ storefront; thanh toán VietQR/SePay hoặc **trả tiền khi nhận tại cửa hàng**.
- Ngoài phạm vi (không thiết kế ở đây): giao hàng/hãng vận chuyển, phí ship, Zalo, sàn TMĐT, hóa đơn điện tử, hoàn tiền tự động, mã khuyến mãi.

## 1. Nguyên tắc thiết kế

1. **Tái sử dụng tối đa** hạ tầng đơn hàng đang chạy đúng (giữ kho, webhook, hủy quá hạn, đối soát, in, trả hàng). Chỉ thêm bề mặt storefront + vài cột phân biệt kênh.
2. **Không thêm trạng thái mới vào enum `invoices.trang_thai`** trên DB thật — dùng lại đúng 3 trạng thái `cho_thanh_toan / da_thanh_toan / da_huy` cho cả hai hướng thanh toán. Lý do: tránh `ALTER ENUM` trên MySQL production (mục 6.3), và mọi báo cáo/webhook/scheduler hiện hành tiếp tục đúng không cần sửa.
3. Server là nguồn sự thật duy nhất về **giá, tồn kho, trạng thái** — giỏ session chỉ để hiển thị; checkout luôn tính lại từ DB trong transaction có khóa dòng.
4. Khách chỉ thấy đơn của mình (mã tra cứu ngẫu nhiên + SĐT, hoặc `customer_id` khi đăng nhập). Không dùng điểm online cho tới khi liên kết SĐT được xác minh (quyết định D6).
5. Mỗi pha triển khai trên branch riêng, có test đỏ trước, full suite xanh trước khi merge (đúng quy tắc README mục 15).

## 2. Thành phần tái sử dụng (file:dòng)

| # | Thành phần | Vị trí | Cách dùng lại | Sửa cần thiết |
|---|---|---|---|---|
| R1 | Tạo đơn chờ + **giữ kho ngay** (trừ tồn + movement `ban`), khóa SP theo id tăng dần, giá lấy từ DB | [ThanhToanChuyenKhoanService.php:22-125](../app/Services/ThanhToanChuyenKhoanService.php#L22-L125) (khóa+giá :38-44, tạo đơn :77-93, trừ kho :96-116, trừ điểm dùng :119-121) | Gọi từ controller checkout online với giỏ `storefront_cart` | Nhận `user_id = null` cho đơn guest (cột phải nullable — M1); thêm tham số `kenh`, `han_giu`, thông tin khách dat |
| R2 | Hoàn tất thanh toán **idempotent** (đúng 1 lần), cộng điểm nhận, ghi cả 2 cột mã GD | [Service:135-218](../app/Services/ThanhToanChuyenKhoanService.php#L135-L218) (pre-check :150-157, khóa SP→khách→đơn :162-183, cộng điểm :207-209) | Webhook + nút xác nhận của thu ngân dùng chung | Không sửa lõi; truyền `ghiChu` tên thu ngân |
| R3 | Hủy đơn chờ: hoàn kho (movement `dieuchinh` dương) + hoàn điểm đã dùng | [Service:228-304](../app/Services/ThanhToanChuyenKhoanService.php#L228-L304) (hoàn kho :271-285, hoàn điểm :288-290) | Scheduler + khách tự hủy + thu ngân hủy | Không sửa |
| R4 | Sinh mã `HD{Ymd}-{XXXX}` khóa chống trùng | [Service:309-320](../app/Services/ThanhToanChuyenKhoanService.php#L309-L320); [DonHangController.php:322-333](../app/Http/Controllers/DonHangController.php#L322-L333) | Giữ nguyên — webhook parse theo mẫu này ([SepayWebhook.php:71-80](../app/Services/Payment/SepayWebhook.php#L71-L80)) | Không sửa |
| R5 | QR VietQR động, nội dung ≤25 ký tự | [VietQrService.php:46-74](../app/Services/VietQrService.php#L46-L74), làm sạch :33-40 | `url(tong_thanh_toan, ma_hoadon)` — mã HD 14 ký tự vừa | Không sửa |
| R6 | Webhook SePay: API-key `hash_equals`, idempotent theo mã GD, thiếu tiền giữ nguyên, dư/trễ hạn vẫn hoàn tất + ghi chú, hàng chờ đối soát | [WebhookController.php:31-35,54-56,112-125,127-141,184-200](../app/Http/Controllers/WebhookController.php#L31-L35); [SepayWebhook.php:13-36](../app/Services/Payment/SepayWebhook.php#L13-L36) | Đơn online chung bảng `invoices` nên webhook khớp `ma_hoadon` như thường | Không sửa |
| R7 | Hủy quá hạn tự động **mỗi phút** + hủy lười khi polling | [HuyHoaDonQuaHan.php:16-44](../app/Console/Commands/HuyHoaDonQuaHan.php#L16-L44), [console.php:9](../routes/console.php#L9); [DonHangController.php:97-109](../app/Http/Controllers/DonHangController.php#L97-L109) | Giữ nguyên cơ chế | Đọc hạn theo đơn: `han_giu ?? created_at + config` (sửa nhỏ 2 chỗ) |
| R8 | Thông báo Telegram (tự tắt khi chưa cấu hình, timeout 5s, che token) | [ThongBaoService.php:16-59](../app/Services/ThongBaoService.php#L16-L59) | Thêm mẫu tin "Đơn online mới/chờ xác nhận" | **Escape ghi chú khách** trước khi ghép vào tin HTML (:64-74) — lỗ hổng L9 báo cáo |
| R9 | Nhật ký hoạt động | [NhatKyService.php:10-25](../app/Services/NhatKyService.php#L10-L25) | Ghi `dat_hang_online`, `xac_nhan_don_online`, `huy_don_online` | Không log SĐT đầy đủ (che `090****567`) |
| R10 | In hóa đơn (chỉ `da_thanh_toan`, khổ k58/k80) | [DonHangController.php:178-192](../app/Http/Controllers/DonHangController.php#L178-L192) | Thu ngân in sau khi hoàn tất | Xử lý `user_id null` → in "Đơn online" thay tên nhân viên |
| R11 | Trả hàng theo `invoice_id` | [migration returns:15](../database/migrations/2026_01_01_000014_create_returns_tables.php#L15), TraHangController | Đơn online là `invoices` nên trả hàng chạy nguyên vẹn | Không sửa |
| R12 | Báo cáo doanh thu/lợi nhuận/top/export đều lọc `da_thanh_toan` | [ThongKeController.php:48,90](../app/Http/Controllers/ThongKeController.php#L48), [HoaDonExport.php:30](../app/Exports/HoaDonExport.php#L30) | Đơn chờ/hủy không làm lệch | Dashboard `so_hoa_don` đếm mọi trạng thái ([TongQuanKhoService.php:36](../app/Services/TongQuanKhoService.php#L36)) → thêm lọc `kenh='pos'` hoặc `trang_thai='da_thanh_toan'` (quyết định D9) |
| R13 | Cấu hình POS | [config/pos.php](../config/pos.php): hạn chờ :12, điểm :6-7, key SePay :13, Telegram :14-15 | Giữ nguyên | Thêm khóa online mới (mục 6.2) |
| R14 | Đăng ký/đăng nhập khách, role `khachhang`, chặn leo thang | [AuthController.php:77-143](../app/Http/Controllers/AuthController.php#L77-L143) | Khách đăng nhập đặt hàng gắn `customer_id` | Liên kết SĐT phải xác minh trước khi cho dùng điểm (D6, L1) |
| R15 | Giỏ storefront session `storefront_cart` | [StorefrontCartController.php:17-103](../app/Http/Controllers/StorefrontCartController.php#L17-L103) | Nguồn hàng cho checkout | Checkout **không tin giá session**; đổi add/remove GET→POST (P5) |

## 3. Luồng đặt hàng

### 3.1 Trạng thái đơn (dùng lại enum hiện có, không thêm giá trị)

```
                    đặt hàng (guest / khachhang)
                              |
                              v
                    +------------------+   giữ kho NGAY (trừ tồn + movement 'ban')
                    |  cho_thanh_toan  |   QR: hạn 15-30 phút | Pickup: hạn 4 giờ (cấu hình)
                    +------------------+
                     |        |        |
   webhook đủ tiền   |        |        |  quá hạn (scheduler/lazy)
   HOẶC thu ngân     |        |        |  khách tự hủy / thu ngân hủy
   xác nhận đã thu   |        |        v
                     v        |    +---------+  hoàn kho (movement 'dieuchinh')
            +---------------+ |    | da_huy  |  hoàn điểm đã dùng (nếu có)
            | da_thanh_toan | |    +---------+
            +---------------+ |
                     |        +-> tiền về đơn đã hủy: hàng chờ đối soát (R6, giữ nguyên)
                     v
             cộng điểm nhận 1 lần (nếu có customer_id)
             in hóa đơn / trả hàng dùng luồng cũ (R10, R11)
```

- **QR online**: `phuong_thuc='chuyenkhoan'`, `kenh='online'`. Hoàn tất bằng webhook (tự động) hoặc thu ngân xác nhận thủ công (dự phòng) — đúng R2/R6.
- **Pickup (trả tại quầy)**: `phuong_thuc='tienmat'`, `kenh='online'`, không sinh QR. Khách tới quầy đọc mã HD/mã tra cứu, trả tiền mặt; thu ngân bấm "Đã thu tiền" → R2 với `ghiChu='Thu tiền mặt pickup — {tên thu ngân}'`. Tiền mặt không qua webhook.
- `da_huy` hoàn kho/điểm đúng R3; tiền chuyển khoản về đơn đã hủy vẫn vào hàng chờ đối soát (R6) — không hoàn tiền tự động (ngoài phạm vi).

### 3.2 Khách vãng lai (guest)

| Bước | Khách làm gì | Màn hình | Server làm gì |
|---|---|---|---|
| 1 | Duyệt SP, thêm vào giỏ | Trang chủ/danh mục/chi tiết (có sẵn) | `storefront_cart` session (R15) |
| 2 | Mở giỏ, bấm **"Đặt hàng"** (thay nút disabled) | `/gio-hang` (sửa nút) | — |
| 3 | Nhập họ tên, SĐT, ghi chú nhận hàng (tùy chọn); chọn **QR** hoặc **Trả tại quầy**; bấm xác nhận | `/dat-hang` (mới) | Validate; chống trùng token; throttle |
| 4 | — | — | Transaction: khóa SP theo id, **tính lại giá/tồn từ DB**, tạo `invoices` `kenh='online'`, `user_id=null`, `customer_id=null`, snapshot `ten_khach_dat/so_dienthoai_dat`, `ma_tra_cuu` ngẫu nhiên, `han_giu`; trừ kho + movement (R1); xóa giỏ + token |
| 5 | Nhận mã đơn + **mã tra cứu** + SĐT đã dùng; nếu QR: quét mã | `/dat-hang/thanh-cong/{ma_tra_cuu}` (mới) | Sinh QR bằng R5 với nội dung `ma_hoadon` |
| 6 | Chờ / chuyển khoản / tới quầy | `/don-hang/tra-cuu` → `/don-hang/{ma_tra_cuu}` (mới, polling 5s) | Trả trạng thái; hủy lười nếu quá hạn (R7) |
| 7 | (QR) chuyển khoản đúng số tiền + nội dung mã HD | — | Webhook R6 hoàn tất, Telegram báo nhân viên |
| 8 | (Pickup) tới quầy, đọc mã, trả tiền mặt | — | Thu ngân xác nhận (mục 3.4) |
| 9 | Xem trạng thái `da_thanh_toan`, nhận hàng | Trang tra cứu | Không in hóa đơn công khai; hóa đơn chỉ thu ngân in (R10) |

Khách guest **không có điểm** (không `customer_id`). Muốn tích điểm → đăng ký bằng đúng SĐT đã đặt (liên kết hồ sơ, D6) — đơn đã hoàn tất trước đó có thể được gắn hồ sơ ở pha sau (quyết định D8).

### 3.3 Khách đã đăng nhập (`khachhang`)

Giống guest, khác:

| Điểm khác | Chi tiết |
|---|---|
| Bước 3 | Form điền sẵn `ho_ten`, `so_dienthoai` từ `customers` (quan hệ `users.id → customers.user_id`); không cho sửa SĐT tùy tiện (SĐT là khóa tra cứu) |
| Bước 4 | Đơn gắn `customer_id` hồ sơ đã liên kết; `diem_tichluy_nhan` tính theo `tong_thanh_toan` (R1); **không cho dùng điểm online ở v1** (D6) |
| Bước 5-6 | Ngoài tra cứu công khai, có `/tai-khoan/don-hang` (mới): lịch sử đơn **chỉ của chính mình** (`customer_id = hồ sơ của user`), lọc theo trạng thái |
| Điểm | Cộng khi `da_thanh_toan` qua R2 (đúng 1 lần); khách thấy số dư điểm ở trang tài khoản (mới, chỉ đọc) |
| Quyền | Mọi route `/tai-khoan/*` yêu cầu `auth` + role `khachhang`; truy vấn luôn ràng buộc `customer_id` của user đăng nhập — không nhận `customer_id` từ request |

### 3.4 Ai làm gì

| Chủ thể | Việc |
|---|---|
| Khách | Đặt hàng, chuyển khoản (QR) hoặc tới quầy trả tiền (pickup), tra cứu bằng mã + SĐT, tự hủy khi còn `cho_thanh_toan` |
| Thu ngân | Thấy đơn online mới (Telegram + danh sách `/donhang?kenh=online`), xác nhận đã thu tiền pickup, hủy đơn theo yêu cầu, in hóa đơn sau khi hoàn tất, xử lý hàng chờ đối soát khi tiền về đơn hủy |
| Webhook SePay | Tự hoàn tất đơn QR đủ tiền; thiếu tiền → cảnh báo + đối soát; trùng mã GD → bỏ qua (R6) |
| Scheduler | Mỗi phút hủy đơn quá `han_giu`, hoàn kho/điểm (R7) |
| Admin | Cấu hình hạn giữ/giới hạn đơn mở (config), xem báo cáo (đã lọc đúng trạng thái) |

## 4. Hai hướng thanh toán

### 4.a VietQR/SePay (tái sử dụng gần như toàn bộ)

1. Checkout chọn QR → R1 tạo đơn `chuyenkhoan` + giữ kho, `han_giu = now + pos.online_qr_phut` (đề xuất 30 phút, D4).
2. Trả `url_qr` từ R5 (nội dung = `ma_hoadon`, đã làm sạch ≤25 ký tự) + `het_han_luc`.
3. Trang trạng thái polling 5s (mẫu POS [pos/index.blade.php:398-440](../resources/views/pos/index.blade.php#L398-L440)); endpoint công khai mới trả `{trang_thai}` — **không** lộ tiền/tên/SĐT.
4. Khách chuyển khoản → SePay webhook → R6: đủ tiền hoàn tất (R2), thiếu tiền giữ chờ + cảnh báo, trễ hạn vẫn hoàn tất + ghi chú, đơn đã hủy → đối soát.
5. Quá hạn chưa trả → scheduler/lazy hủy (R7), hoàn kho. Tiền về sau hủy → hàng chờ đối soát, thu ngân xử lý thủ công (không hoàn tự động).

Khác biệt duy nhất so với POS QR: nguồn giỏ là `storefront_cart`, đơn `kenh='online'`, `user_id=null`, trang trạng thái công khai có tra cứu mã+SĐT thay vì session nhân viên.

### 4.b Trả tiền khi nhận tại cửa hàng (pickup)

1. Checkout chọn pickup → R1 tạo đơn `tienmat` + **giữ kho**, `han_giu = now + pos.online_pickup_gio` (đề xuất 4 giờ, D4). Không QR.
2. Trang xác nhận hiện mã HD + mã tra cứu + địa chỉ cửa hàng (config `pos.bank_name`/thông tin cửa hàng sẵn có) + hạn giữ.
3. Khách tới quầy trong hạn → thu ngân mở `/donhang?kenh=online`, đối chiếu mã + SĐT, thu tiền mặt, bấm **"Đã thu tiền"** → R2 (idempotent) với ghi chú thu ngân.
4. Khách không tới trong hạn → scheduler hủy, hoàn kho (R7). Khách tự hủy được qua trang tra cứu khi còn chờ.
5. Chống giữ kho ảo: hạn ngắn + giới hạn đơn mở theo SĐT/IP (mục 5) + thu ngân hủy tay.

Vì sao không thêm trạng thái `cho_xac_nhan`: pickup bản chất là "chờ thanh toán tại quầy" — `cho_thanh_toan` + `phuong_thuc='tienmat'` + `kenh='online'` đã đủ phân biệt; thêm giá trị enum là rủi ro ALTER production không cần thiết (mục 6.3). Màn hình thu ngân phân loại bằng `kenh` + `phuong_thuc`.

## 5. Mô hình dữ liệu — so sánh 2 phương án

### Phương án 1: Dùng lại `invoices` + thêm cột (KHUYẾN NGHỊ)

Thêm cột (mô tả ở mục 6): `kenh`, `ma_tra_cuu`, `ten_khach_dat`, `so_dienthoai_dat`, `ghi_chu_khach`, `han_giu`; `user_id` → nullable. Không thêm bảng, không đổi enum.

| Khía cạnh | Ưu | Nhược |
|---|---|---|
| Báo cáo doanh thu/lợi nhuận/top/export | **Tự đúng** — đã lọc `da_thanh_toan` (R12), đơn online hoàn tất được tính ngay | Dashboard `so_hoa_don` đếm cả chờ/hủy → phải thêm lọc (1 dòng) |
| Tồn kho | **Tự đúng** — R1/R3 trừ/hoàn kho + movement sẵn; kiểm kê/nhập kho không đổi | Hàng giữ cho đơn chờ làm tồn hiển thị giảm (đúng bản chất, cần chú thích) |
| Điểm | **Tự đúng** — R1 tính điểm nhận, R2 cộng 1 lần, R3 hoàn điểm dùng | Phải chặn dùng điểm online tới khi xác minh SĐT (D6) |
| In hóa đơn | **Tự đúng** — R10 chạy khi `da_thanh_toan` | `user_id null` → view in phải hiện "Đơn online" (sửa nhỏ) |
| Trả hàng | **Tự đúng** — `returns.invoice_id` trỏ thẳng đơn online (R11) | Không |
| Webhook/đối soát | **Tự đúng** — tìm `ma_hoadon` chung bảng (R6) | Không |
| Scheduler hủy quá hạn | Dùng lại R7, chỉ đọc thêm `han_giu` | Sửa 2 chỗ nhỏ (command + lazy expiry) |
| Danh sách đơn POS | Thêm lọc/badge `kenh` | Đơn online lẫn vào POS nếu không lọc (sửa nhỏ) |
| Rủi ro schema | Thêm cột nullable + 1 cột nullable hóa, **không đụng enum**, bảng 5 dòng | `user_id` nullable là thay đổi ngữ nghĩa: code hiển thị phải chịu null |
| Khối lượng code mới | Controller checkout + tra cứu + trang khách + vài view | Ít hơn hẳn phương án 2 |

### Phương án 2: Bảng riêng `online_orders` + `online_order_items`

| Khía cạnh | Ưu | Nhược |
|---|---|---|
| Cách ly POS | POS không thấy đơn online; không sửa `invoices` | **Phải nhân đôi** logic giữ kho/hoàn kho/điểm hoặc tách service chung — dễ lệch |
| Báo cáo | Không lệch dashboard POS | Doanh thu/top/export phải UNION hoặc **convert sang invoices khi hoàn tất** — mọi báo cáo sửa |
| Webhook | — | Phải tìm thêm bảng online, rồi convert — R6 sửa nhiều |
| Trả hàng | — | `returns.invoice_id` không trỏ được đơn online → bắt buộc convert trước khi trả |
| In hóa đơn | — | Chỉ in sau convert |
| Scheduler | — | Command mới riêng |
| Schema | Không đụng bảng cũ | 2 bảng mới + FK + migration convert phức tạp; rủi ro mồ côi cao hơn (DB thật **0 FK** — L7) |
| Khối lượng code | — | Lớn hơn phương án 1 nhiều lần, đi ngược "tối thiểu" |

**Khuyến nghị: Phương án 1.** Toàn bộ chuỗi tiền–kho–điểm–in–trả–đối soát đang khóa vào `invoices`; dùng lại thì 6 hệ thống con tự đúng, chỉ thêm bề mặt storefront và vài cột. Phương án 2 chỉ đáng khi đơn online cần vòng đời rất khác (giao hàng, nhiều kiện, thanh toán một phần) — đều ngoài phạm vi đợt này.

Xử lý `user_id NOT NULL` (trở ngại chính của PA1): chọn **nullable hóa `user_id`** + hiển thị "Đơn online" khi null; thu ngân xác nhận có thể ghi `user_id` người xác nhận vào lúc hoàn tất (tùy chọn D7). Không chọn "user hệ thống giả" vì tạo tài khoản nhân viên ảo dễ gây nhầm phân quyền/audit.

## 6. Migration cần có (CHỈ MÔ TẢ — không tạo file trong đợt này)

### 6.1 M1 — mở rộng `invoices`

```
Schema::table('invoices'):
  kenh            string(10)  default 'pos'   index        -- 'pos' | 'online'
  ma_tra_cuu      string(20)  nullable unique              -- ngẫu nhiên, không đoán được
  ten_khach_dat   string(100) nullable                     -- snapshot họ tên khách đặt
  so_dienthoai_dat string(15) nullable  index              -- snapshot SĐT (khóa tra cứu + giới hạn đơn mở)
  ghi_chu_khach   string(500) nullable                     -- ghi chú nhận hàng, escape khi render
  han_giu         timestamp   nullable                     -- hạn giữ riêng từng đơn; null = POS cũ dùng config
  user_id         ->nullable()->change()                   -- đơn guest không có nhân viên tạo
```

- Không đổi `trang_thai`, không đổi `phuong_thuc`, không bảng mới. `payment_webhooks`, `customers`, `invoice_details` giữ nguyên.
- Model `DonHang`: thêm các cột mới vào `$fillable`; `user` quan hệ đã chịu null ( Blade hiển thị cần fallback).
- `down()`: drop cột mới; `user_id` về NOT NULL — **sẽ thất bại nếu còn dòng null** → tài liệu vận hành phải yêu cầu xử lý đơn online trước khi rollback (ghi rõ trong migration).

### 6.2 Cấu hình mới (config/pos.php, không phải migration)

| Khóa | Đề xuất | Ý nghĩa |
|---|---|---|
| `online_qr_phut` | 30 | Hạn giữ đơn QR online |
| `online_pickup_gio` | 4 | Hạn giữ đơn pickup (giờ) |
| `online_max_don_mo` | 3 | Số đơn `cho_thanh_toan` tối đa mỗi SĐT |
| `online_max_soluong` | 10 | Số lượng tối đa mỗi dòng hàng online |
| `online_giu_toi_da_phan_tram` | 50 | Trần tổng SL mỗi SP bị giữ bởi đơn pickup chưa thanh toán (% tồn gốc) |
| `online_throttle_dat` | 5,1 | Rate limit checkout mỗi IP |

### 6.3 Rủi ro khi áp lên MySQL thật (`doan_cnw`, MySQL 8.4.7)

1. **Backup trước**: dùng lệnh sẵn `sao-luu:csdl` ([SaoLuuCsdl.php](../app/Console/Commands/SaoLuuCsdl.php), chạy daily 02:00 — [console.php:10](../routes/console.php#L10)) hoặc mysqldump thủ công; xác nhận file backup đọc được.
2. **Thêm cột nullable/default**: MySQL 8 hỗ trợ `ALGORITHM=INSTANT` cho ADD COLUMN có default — gần như tức thì, không rebuild. Bảng đang 5 dòng nên mọi lựa chọn đều nhanh.
3. **`user_id` NOT NULL → NULL**: rebuild bảng (INPLACE) — tích tắc với 5 dòng, nhưng vẫn là thay đổi cấu trúc cột khóa; chạy lúc vắng khách.
4. **KHÔNG đụng enum** (`trang_thai`, `phuong_thuc`, `users.role`, `stock_movements.loai`): thiết kế này cố tình tránh `ALTER ... MODIFY ENUM` — thao tác dễ khóa bảng và sai thứ tự giá trị trên production. Nếu sau này bắt buộc thêm trạng thái: backup + thử trên `doan_cnw_copy` + thêm giá trị vào CUỐI enum + giờ thấp điểm.
5. **Metadata lock**: scheduler chạy mỗi phút (R7) và webhook có thể giữ lock ngắn; ALTER chờ MDL có thể chặn dây chuyền vài giây → chạy lúc thấp điểm, theo dõi `SHOW PROCESSLIST`.
6. **DB thật 0 FK** (L7): migration thêm cột không tạo FK mới cho bảng cũ; nếu muốn FK thật phải tạo riêng và dọn mồ côi trước — ngoài phạm vi tối thiểu, ghi nhận là nợ kỹ thuật.
7. **Lệch migration/DB thật** đã có tiền lệ (`hinh_anh` 150 vs 255, `dia_chi` 150 vs 255 — L8): sau khi chạy, đối chiếu lại `information_schema` như đợt này; thử trước trên `doan_cnw_copy` bằng biến môi trường tiến trình (không sửa `.env`).
8. **Rollback**: `down()` có điều kiện (mục 6.1); kịch bản xấu nhất là restore backup — phải diễn tập trên copy trước.

### 6.4 Trần giữ hàng pickup (PA1 — đã triển khai ở P0)

Quy tắc miền chống ôm hàng riêng cho pickup, bổ sung cho hạn giữ ngắn (D4):

- **Đơn pickup đang giữ** = `invoices.kenh='online'` + `phuong_thuc='tienmat'` (trả tại quầy) + `trang_thai='cho_thanh_toan'`. Không đếm QR (`chuyenkhoan`), đơn POS (`kenh='pos'`), `da_thanh_toan`, `da_huy`.
- `dangGiuPickup(sp)` = SUM(`invoice_details.soluong`) của các đơn trên theo `product_id`.
- `tonGoc(sp)` = `products.soluong` hiện tại + `dangGiuPickup` (kho trừ ngay khi tạo đơn chờ — R1 — nên cộng lại để trần không co lại theo mỗi đơn).
- `tran(sp)` = `max(1, floor(online_giu_toi_da_phan_tram/100 × tonGoc))` (mặc định 50%).
- `kiemTra(sp, soLuongMoi)`: cho phép nếu `dangGiuPickup + soLuongMoi ≤ tran`; từ chối (lỗi tiếng Việt, kèm số còn có thể giữ) nếu vượt hoặc `soLuongMoi ≤ 0`.

Hiện thực: [GiuHangOnlineService.php](../app/Services/GiuHangOnlineService.php) — **chỉ đọc + tính, không ghi**, không route/UI/guest input ở P0. Test: `GiuHangOnlineServiceTest` (SQLite `:memory:`, 8 ca, tạo dữ liệu bằng model).

**Rủi ro race còn lại (P1 phải xử lý):** service không tự khóa. P1 BẮT BUỘC gọi `kiemTra()` trong cùng `DB::transaction` với `lockForUpdate` sản phẩm (khóa theo id tăng dần, đúng mẫu R1/`ThanhToanChuyenKhoanService`) trước khi trừ kho; nếu không, hai khách đặt đồng thời có thể cùng vượt trần. Chưa có test đồng thời thật.

## 7. Mô hình đe dọa + giảm thiểu

| # | Đe dọa | Giảm thiểu | Vị trí thiết kế |
|---|---|---|---|
| T1 | **Tamper giá/tồn/trạng thái** từ client (sửa giá giỏ, gửi `tong_thanh_toan`, `customer_id` người khác) | Checkout bỏ qua mọi trường tiền từ request; tính lại từ DB trong `DB::transaction(...,2)` với `lockForUpdate` SP theo id tăng dần (đúng mẫu R1 :38-44); `customer_id` chỉ lấy từ user đăng nhập, không từ form guest | R1, mục 3.2 bước 4 |
| T2 | **Double-submit / đặt trùng** (double click, replay, hết giỏ rồi gửi lại) | Token checkout một lần trong session (`session()->pull`), xóa giỏ ngay sau tạo đơn thành công; nút disable phía client; `ma_tra_cuu` unique; transaction đảm bảo không tạo 2 đơn từ 1 token | P1, test T-DH05 |
| T3 | **Giữ kho ảo / ôm hàng** (đặt hàng loạt không trả) | Hạn giữ ngắn (QR 30p, pickup 4h, cấu hình); trần giữ pickup 50% tồn gốc mỗi SP (mục 6.4); scheduler mỗi phút + hủy lười; tối đa 3 đơn mở/SĐT; tối đa 10 SP/dòng; throttle checkout 5/phút/IP; thu ngân hủy tay; (tùy chọn D10: honeypot/captcha) | R7, mục 6.2/6.4 |
| T4 | **Dò mã đơn** (enumeration mã HD tuần tự, đoán mã tra cứu) | Tra cứu công khai dùng `ma_tra_cuu` ngẫu nhiên ≥10 ký tự (bảng chữ cái bỏ ký tự dễ nhầm) **+ đúng SĐT**; không dùng `ma_hoadon` tuần tự làm khóa tra cứu; throttle 10/phút/IP; thông báo lỗi chung "Không tìm thấy đơn hàng" (không phân biệt sai mã / sai SĐT); endpoint trạng thái chỉ trả `{trang_thai}` | P1/P2, test T-DH12 |
| T5 | **Lộ PII** (SĐT, tên, địa chỉ trong log/URL/response) | Thu thập tối thiểu: tên + SĐT + ghi chú (pickup không cần địa chỉ); che SĐT trong log/nhật ký (`090****567`); không log payload đầy đủ; Blade `{{ }}` escape mọi đầu ra; trang tra cứu không trả tên/SĐT/ngoài trạng thái; đơn đăng nhập chỉ truy vấn theo `customer_id` của chính user | R9, P4 |
| T6 | **XSS ghi chú/địa chỉ** (khách nhập `<script>` vào ghi chú) | Validate `ghi_chu_khach max:500`, lưu thô nhưng **escape khi render** (Blade mặc định); **escape trước khi ghép tin Telegram HTML** (R8 — L9); không dùng `{!! !!}` cho dữ liệu khách | R8, P1 |
| T7 | **Spam đơn giả / phá hoại** | Throttle checkout + giới hạn đơn mở/SĐT (T3); Telegram báo nhân viên mỗi đơn mới để phát hiện sớm; thu ngân hủy hàng loạt đơn ảo (R3 hoàn kho); honeypot field ẩn (D10) | P1/P3 |
| T8 | **IDOR — khách xem/hủy đơn người khác** | Tra cứu: mã ngẫu nhiên + SĐT khớp (T4); khách đăng nhập: mọi truy vấn ràng buộc `customer_id` hồ sơ của user, không nhận id đơn tùy ý rồi mới kiểm tra — kiểm tra quyền trước khi trả dữ liệu; hủy chỉ khi `cho_thanh_toan` và đúng chủ | P4, test T-DH19/20 |
| T9 | **Lạm dụng điểm** (chiếm hồ sơ khách lẻ qua SĐT chưa xác minh rồi tiêu điểm — L1) | v1: **không cho dùng điểm online**; chỉ cộng điểm nhận khi đơn `da_thanh_toan` có `customer_id` (R2 đúng 1 lần); muốn dùng điểm phải xác minh liên kết SĐT (OTP/email — D6, pha sau); không cho đổi SĐT hồ sơ tùy tiện | R14, D6 |
| T10 | **CSRF** (đặt hàng/hủy đơn hộ nạn nhân; nhét giỏ qua GET — L4) | Checkout/hủy là POST có `@csrf`; đổi add/remove giỏ GET→POST (P5); webhook giữ miễn CSRF nhưng chắn API-key (R6) | P1/P5, test T-DH23 |
| T11 | **Giả mạo webhook / replay** | Giữ nguyên R6: `hash_equals` API-key, idempotent theo `ma_giao_dich_ngoai` unique, so số tiền, thiếu tiền không hoàn tất; key rỗng = từ chối tất cả | R6 |
| T12 | **Race webhook ↔ scheduler hủy** (tiền về đúng lúc hết hạn) | Giữ nguyên thứ tự khóa SP→khách→đơn + kiểm tra lại trạng thái trong khóa (R2 :162-183); tiền về đơn đã hủy → hàng chờ đối soát + Telegram (R6 :89-100), không hoàn tự động | R2/R6 |
| T13 | **Leo thang quyền** (guest gọi route nhân viên, khách sửa role) | Route mới công khai chỉ tạo/tra cứu/hủy đơn của mình; route quản trị nằm trong nhóm `role:admin,thungan` sẵn có; đăng ký đã chặn role (R14); probe đã chứng minh guest/khachhang không chạm route POS | Mục 2 báo cáo |

## 8. Pha triển khai (mỗi pha: branch riêng + test + tiêu chí hoàn thành)

| Pha | Nội dung | Test chính | Tiêu chí hoàn thành |
|---|---|---|---|
| **P0** | Migration M1 + fillable model + config mới + fallback `user_id null` ở view in/danh sách | T-DH01, T-DH02 | Migration up/down sạch trên SQLite **và** `doan_cnw_copy`; full suite POS cũ xanh không đổi |
| **P1** | Checkout guest **pickup**: controller + service mở rộng R1 + form + trang xác nhận + tra cứu mã/SĐT + khách tự hủy | T-DH03..T-DH08, T-DH12 | Guest đặt được đơn pickup, kho giữ đúng, giá tính từ DB, token chống trùng, tra cứu sai không lộ thông tin |
| **P2** | Checkout guest **QR** + trang trạng thái polling + hạn giữ theo đơn (sửa R7 đọc `han_giu`) + webhook đầu-cuối | T-DH09..T-DH11, T-DH13, T-DH14 | Đơn QR hoàn tất bằng webhook thật (giả lập payload) trên copy; quá hạn tự hủy hoàn kho; tiền về đơn hủy vào đối soát |
| **P3** | Quản lý đơn online cho thu ngân: danh sách lọc `kenh`, nút "Đã thu tiền" (R2), hủy (R3), Telegram đơn mới (escape ghi chú) | T-DH15..T-DH18 | Thu ngân xác nhận pickup idempotent; thukho/khachhang bị chặn; ghi chú khách không chèn được HTML vào Telegram |
| **P4** | Khách đăng nhập: prefill hồ sơ, gắn `customer_id`, lịch sử đơn riêng, trang điểm chỉ đọc; chặn IDOR | T-DH19..T-DH22 | Khách chỉ thấy đơn mình; điểm cộng đúng 1 lần khi hoàn tất; không dùng được điểm online |
| **P5** | Cứng hóa: add/remove giỏ GET→POST, throttle checkout, giới hạn đơn mở/SĐT, lọc `kenh` cho dashboard/danh sách POS, README + docs + FE handoff | T-DH23..T-DH26 | Full suite xanh; CSRF phủ toàn bộ giỏ; dashboard không đếm đơn chờ online (theo D9); tài liệu cập nhật |

Thứ tự phụ thuộc: P0 → P1 → {P2, P3} → P4 → P5. Mỗi pha merge khi test pha đó + full suite xanh; không import dữ liệu mẫu, không reset DB production (quy tắc README).

## 9. Danh sách test dự kiến (viết mới ở pha triển khai, không phải đợt này)

| Mã | Kịch bản | Pha |
|---|---|---|
| T-DH01 | Migration thêm cột đúng kiểu/nullable/index trên SQLite; `user_id` nhận null | P0 |
| T-DH02 | Migration chạy + rollback trên MySQL copy; POS cũ không đổi hành vi | P0 |
| T-DH03 | Guest checkout pickup tạo đơn `cho_thanh_toan`, `kenh=online`, `user_id=null`, trừ kho + movement `ban` | P1 |
| T-DH04 | Giá/tồn tính lại từ DB: đổi giá DB sau khi thêm giỏ → đơn dùng giá mới; vượt tồn → 422 tiếng Việt, không tạo đơn | P1 |
| T-DH05 | Double-submit cùng token → 1 đơn; token cũ hết hiệu lực; giỏ xóa sau đặt | P1 |
| T-DH06 | Validate: thiếu tên/SĐT sai định dạng/ghi chú >500 → lỗi tiếng Việt, không tạo đơn | P1 |
| T-DH07 | SP ẩn/hết hàng/xóa trong giỏ → checkout từ chối toàn bộ, kho không đổi | P1 |
| T-DH08 | Khách tự hủy đơn chờ qua tra cứu → `da_huy`, hoàn kho; hủy đơn đã hoàn tất → từ chối | P1 |
| T-DH09 | Checkout QR sinh `url_qr` nội dung mã HD + `het_han_luc` đúng config | P2 |
| T-DH10 | Webhook đủ tiền hoàn tất đơn online QR, cộng điểm nếu có hồ sơ, idempotent khi gửi lại | P2 |
| T-DH11 | Webhook thiếu tiền → giữ chờ + bản ghi đối soát; đơn hủy → đối soát | P2 |
| T-DH12 | Tra cứu sai mã / sai SĐT → cùng thông báo chung, throttle; đúng → chỉ trả trạng thái | P1/P2 |
| T-DH13 | Quá `han_giu`: scheduler hủy hoàn kho; polling hủy lười; QR 30p vs pickup 4h độc lập | P2 |
| T-DH14 | Tiền về đơn đã hủy/trễ hạn: hoàn tất trễ hạn có ghi chú hoặc vào đối soát (đúng R6) | P2 |
| T-DH15 | Thu ngân xác nhận pickup → `da_thanh_toan` đúng 1 lần, ghi chú tên thu ngân | P3 |
| T-DH16 | `khachhang`/thukho/guest gọi route quản trị đơn online → 302/403 | P3 |
| T-DH17 | Ghi chú khách chứa HTML → tin Telegram đã escape, trang render đã escape | P3 |
| T-DH18 | Telegram đơn mới gửi sau commit, không chặn luồng khi lỗi mạng (mock Http) | P3 |
| T-DH19 | Khách đăng nhập chỉ thấy đơn `customer_id` mình; đoán id đơn khác → 403/404 | P4 |
| T-DH20 | Hủy đơn khách khác → từ chối kể cả khi biết mã tra cứu nhưng sai SĐT | P4 |
| T-DH21 | Điểm nhận cộng đúng 1 lần khi hoàn tất; không cho `diem_su_dung` online | P4 |
| T-DH22 | Prefill hồ sơ; đơn guest cùng SĐT không tự gắn hồ sơ khi chưa xác minh (D6/D8) | P4 |
| T-DH23 | Add/remove giỏ bằng POST + CSRF; GET cũ bị bỏ/405; PUT/DELETE vẫn 419 khi thiếu token | P5 |
| T-DH24 | Throttle checkout 5/phút/IP → 429; vượt 3 đơn mở/SĐT → từ chối | P5 |
| T-DH25 | Dashboard `so_hoa_don` không đếm đơn chờ/hủy online (theo D9); danh sách POS lọc `kenh` | P5 |
| T-DH26 | Full suite cũ + mới xanh trên SQLite; smoke MySQL copy không chạm DB thật | P5 |

## 10. Handoff cho team FE (tự chứa — BE không code FE)

BE sẽ cung cấp các endpoint sau (hợp đồng JSON dự kiến, chốt ở P1):

| Endpoint | Method | Auth | Request | Response |
|---|---|---|---|---|
| `/dat-hang` | GET | không | — | HTML form (tên, SĐT, ghi chú, chọn QR/pickup) |
| `/dat-hang` | POST | không + CSRF | `ten, so_dienthoai, ghi_chu?, phuong_thuc (qr\|pickup), checkout_token` | 302 → trang thành công; lỗi 422 + message tiếng Việt |
| `/dat-hang/thanh-cong/{ma_tra_cuu}` | GET | không | — | HTML: mã HD, mã tra cứu, QR (nếu qr), hạn giữ |
| `/don-hang/tra-cuu` | GET/POST | không + CSRF (POST) | `ma_tra_cuu, so_dienthoai` | 302 → trang trạng thái; lỗi chung |
| `/don-hang/{ma_tra_cuu}/trang-thai` | GET | không (đã tra cứu) | `sdt` | JSON `{trang_thai}` — polling 5s, dừng khi `da_thanh_toan/da_huy` |
| `/don-hang/{ma_tra_cuu}/huy` | POST | không + CSRF | `so_dienthoai` | 302 + flash; chỉ khi `cho_thanh_toan` |
| `/tai-khoan/don-hang` | GET | `khachhang` | — | HTML lịch sử đơn của chính mình |
| `/donhang?kenh=online` | GET | `admin,thungan` | query `kenh` | HTML danh sách (mở rộng trang có sẵn) |

Việc FE: thay nút "Thanh toán" disabled ([cart.blade.php:74-77](../resources/views/storefront/cart.blade.php#L74-L77)) bằng link `/dat-hang`; dựng form + trang thành công + tra cứu + trạng thái (polling mẫu POS [pos/index.blade.php:398-440](../resources/views/pos/index.blade.php#L398-L440)); disable nút submit khi đang gửi; hiện lỗi validation tiếng Việt từ session `errors`; mọi dữ liệu khách render bằng escape mặc định Blade; không tự tính tổng tiền phía client để gửi lên (server tính lại). Accessibility: `aria-live` cho vùng trạng thái polling, label tiếng Việt cho input.

## 11. Cần người dùng quyết định

| Mã | Câu hỏi | Đề xuất của tài liệu |
|---|---|---|
| D1 | Chỉ **pickup tại cửa hàng**, có làm giao hàng không? | Đợt này chỉ pickup (giao hàng ngoài phạm vi đã chốt) |
| D2 | Chấp nhận **cả hai** hướng thanh toán QR + trả tại quầy? | Có — QR tái dùng webhook, pickup tái dùng xác nhận thủ công |
| D3 | **Cho guest đặt hàng** hay bắt đăng nhập? | Cho guest đặt (tên + SĐT), đăng nhập là tùy chọn để tích điểm/xem lịch sử |
| D4 | **Hạn giữ kho**: QR bao nhiêu phút, pickup bao nhiêu giờ? | QR 30 phút, pickup 4 giờ (config được). Trần giữ pickup: tổng SL mỗi SP bị giữ bởi đơn chưa thanh toán ≤ 50% tồn gốc (config `online_giu_toi_da_phan_tram`, mục 6.4) |
| D5 | **Ai xác nhận** đơn pickup / xử lý đối soát? | Thu ngân xác nhận; admin xử lý đối soát (đúng phân quyền hiện tại) |
| D6 | **Điểm online**: cho dùng điểm khi liên kết SĐT chưa xác minh? | v1 chỉ cộng điểm nhận, KHÔNG cho dùng điểm; xác minh SĐT (OTP/email) làm pha sau — chặn L1 |
| D7 | Đơn online hoàn tất có ghi `user_id` thu ngân xác nhận? | Có (tùy chọn) — giúp audit; lúc tạo vẫn null |
| D8 | Đơn guest đã hoàn tất có **gắn ngược hồ sơ** khi khách đăng ký đúng SĐT? | Không tự gắn ở v1 (tránh chiếm hồ sơ — L1); làm cùng D6 |
| D9 | Dashboard `so_hoa_don` đếm gì khi có đơn online? | Đếm `da_thanh_toan` (hoặc `kenh='pos'`) — tránh đội số vì đơn chờ/hủy |
| D10 | Có **captcha/honeypot** checkout? | Honeypot field ẩn (rẻ); captcha chỉ khi bị spam thật |
| D11 | **Email xác nhận đơn** cho khách? | Chưa — không có hạ tầng mail (README mục 15 "Email thông báo"); thay bằng trang tra cứu + Telegram nội bộ. Làm khi có `MAIL_*` |
| D12 | Giới hạn **3 đơn mở/SĐT, 10 SP/dòng, 5 đơn/phút/IP**? | Chấp nhận các ngưỡng đề xuất, chỉnh qua config |

## 12. Ghi chú phạm vi

- Tài liệu này không tạo migration, không sửa code/test; mọi mô tả schema là **đề xuất** chờ duyệt.
- Không thiết kế: giao hàng/hãng vận chuyển, phí ship, Zalo, sàn TMĐT, hóa đơn điện tử, hoàn tiền tự động, mã khuyến mãi.
- Bằng chứng hiện trạng (route, probe, schema thật, lỗ hổng L1-L9): [BAO_CAO_LUONG_DAT_HANG.md](BAO_CAO_LUONG_DAT_HANG.md).