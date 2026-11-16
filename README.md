# Web POS & Quản lý kho

Ứng dụng web quản lý bán hàng tại quầy (POS) và quản lý kho cho cửa hàng phụ
kiện điện thoại. Đây là đồ án Laravel, tập trung vào quy trình:

```text
Đăng nhập → Quản lý sản phẩm → Bán hàng POS → Thanh toán → Hóa đơn
                                      ↓
                              Trừ tồn kho / Lịch sử kho
```

## Mục lục

1. [Công nghệ sử dụng](#1-công-nghệ-sử-dụng)
2. [Yêu cầu môi trường](#2-yêu-cầu-môi-trường)
3. [Cài đặt dự án](#3-cài-đặt-dự-án)
4. [Thiết lập thiết bị và thanh toán thực tế](#4-thiết-lập-thiết-bị-và-thanh-toán-thực-tế)
5. [Chạy ứng dụng](#5-chạy-ứng-dụng)
5A. [Triển khai tại cửa hàng (checklist)](#5a-triển-khai-tại-cửa-hàng-checklist)
6. [Tài khoản demo](#6-tài-khoản-demo)
7. [Chức năng chính](#7-chức-năng-chính)
8. [Các URL chính](#8-các-url-chính)
9. [Kiểm thử](#9-kiểm-thử)
10. [Reset dữ liệu demo](#10-reset-dữ-liệu-demo)
11. [Cấu trúc thư mục chính](#11-cấu-trúc-thư-mục-chính)
12. [Cơ sở dữ liệu](#12-cơ-sở-dữ-liệu)
13. [Quy trình Git đề xuất](#13-quy-trình-git-đề-xuất)
14. [Phạm vi chưa triển khai](#14-phạm-vi-chưa-triển-khai)
15. [Lộ trình triển khai tiếp theo](#15-lộ-trình-triển-khai-tiếp-theo)
16. [Lưu ý bảo mật](#16-lưu-ý-bảo-mật)
17. [Giới hạn đã biết](#17-giới-hạn-đã-biết)
18. [Kết quả kiểm tra](#18-kết-quả-kiểm-tra)

---

## 1. Công nghệ sử dụng

- PHP 8.2+ (kiểm tra trên PHP 8.4.15)
- Laravel 12
- Composer 2.x
- MySQL 8.x / SQLite (mặc định)
- Bootstrap 5
- jQuery và DataTables
- Font Awesome
- Blade Template
- PHPUnit

## 2. Yêu cầu môi trường

Đảm bảo máy đã cài:

- PHP 8.2+ và Composer.
- WAMP/XAMPP với Apache và MySQL (hoặc SQLite).
- PHP extensions: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `xml`.
- Git (nếu cần lấy mã nguồn).

Phiên bản đã kiểm tra trên máy phát triển:

```text
PHP       8.4.15
Composer  2.10.3
Laravel   12.68.0
MySQL     8.4.7 (hoặc SQLite)
```

## 3. Cài đặt dự án

### 3.1. Lấy mã nguồn

```powershell
git clone https://github.com/VanDinh2912/doan_cnw.git
cd doan_cnw
```

### 3.2. Cài dependency

```powershell
composer install
```

### 3.3. Tạo file môi trường

Nếu chưa có `.env`, tạo từ file mẫu:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

### 3.4. Cấu hình Database & Môi trường

Tạo database (MySQL) hoặc dùng SQLite mặc định, sau đó kiểm tra `.env`:

**MySQL (khuyên dùng cho production):**
```env
APP_NAME="Web POS"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8081

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=doan_cnw
DB_USERNAME=root
DB_PASSWORD=
```

**SQLite (mặc định, tiện cho dev/test):**
```env
DB_CONNECTION=sqlite
# DB_DATABASE=database/database.sqlite (tự tạo)
```

Nếu muốn dùng QR chuyển khoản trong màn hình POS, thêm cấu hình ngân hàng (xem [Bảng biến môi trường](#bảng-biến-môi-trường-đầy-đủ) bên dưới).

Chỉ dùng thông tin tài khoản demo hoặc tài khoản do chủ dự án cung cấp. Đối soát
chuyển khoản tự động qua webhook SePay đã được triển khai (xem mục 4.3 và mục 7);
chỉ hoạt động khi cấu hình `SEPAY_WEBHOOK_API_KEY` và có tunnel công khai.

Nếu MySQL có mật khẩu, điền mật khẩu vào `DB_PASSWORD`.

### 3.5. Tạo bảng và dữ liệu mẫu

Lệnh dưới đây xóa và tạo lại toàn bộ bảng trong database đã cấu hình:

```powershell
php artisan migrate:fresh --seed --force
```

Không chạy lệnh này trên database có dữ liệu cần giữ.

### 3.6. Nhập dữ liệu từ TaiLieu

Sau khi đã chạy migration và seed, có thể nhập dữ liệu có cấu trúc từ các file
Excel mẫu:

```powershell
php artisan documents:import
```

Lệnh này không ghi đè dữ liệu trùng; lần chạy lại sẽ bỏ qua khách hàng và nhà
cung cấp đã tồn tại. Bộ dữ liệu thay thế hiện đã được nạp từ
`TaiLieu/ThongTinHangHoa/LoaiHangHoa.xlsx`, gồm 12 loại hàng, 300 sản phẩm,
81 nhà cung cấp, 100 khách hàng và một hóa đơn nhập 300 dòng. PDF hóa đơn chỉ
được lưu làm file đính kèm, không parse dữ liệu từ PDF.

Kiểm tra nhanh tính nhất quán sau khi seed:

```powershell
php artisan demo:kiemtra-du-lieu
```

Lệnh kiểm tra số lượng bản ghi, tổng hóa đơn, tồn kho và movement nhập.

### Bảng biến môi trường đầy đủ

| Biến | Bắt buộc | Mặc định | Ý nghĩa | Dùng ở |
|------|----------|----------|---------|--------|
| `APP_NAME` | Có | "Web POS" | Tên ứng dụng | Toàn hệ thống |
| `APP_ENV` | Có | local | Môi trường | Toàn hệ thống |
| `APP_DEBUG` | Có | true | Debug mode | Toàn hệ thống |
| `APP_URL` | Có | http://127.0.0.1:8081 | URL gốc | Toàn hệ thống |
| `DB_CONNECTION` | Có | sqlite | Driver DB (mysql/sqlite) | Database |
| `DB_HOST` | MySQL | 127.0.0.1 | Host MySQL | Database |
| `DB_PORT` | MySQL | 3306 | Port MySQL | Database |
| `DB_DATABASE` | MySQL | doan_cnw | Tên DB | Database |
| `DB_USERNAME` | MySQL | root | User MySQL | Database |
| `DB_PASSWORD` | MySQL | (rỗng) | Pass MySQL | Database |
| `POS_BANK_CODE` | Không | (rỗng) | Mã ngân hàng VietQR (VCB, MB, TCB...) | VietQR |
| `POS_BANK_ACCOUNT` | Không | (rỗng) | Số tài khoản ngân hàng | VietQR |
| `POS_BANK_NAME` | Không | "Cửa hàng phụ kiện điện thoại" | Tên chủ tài khoản | VietQR |
| `POS_QR_TEMPLATE` | Không | compact2 | Template QR (compact, compact2, qr_only, print) | VietQR |
| `POS_CHO_THANH_TOAN_PHUT` | Không | 15 | Thời hạn chờ thanh toán (phút) | Webhook/QR |
| `SEPAY_WEBHOOK_API_KEY` | Không | (rỗng) | API Key SePay (header `Authorization: Apikey`) | Webhook |
| `SEPAY_API_TOKEN` | Không | (rỗng) | Token đối soát chủ động (header `Authorization: Bearer`); **khác** `SEPAY_WEBHOOK_API_KEY` | Đối soát API |
| `SEPAY_API_BASE_URL` | Không | https://userapi.sepay.vn/v2 | URL gốc SePay API v2 | Đối soát API |
| `SEPAY_API_VERSION` | Không | v2 | Phiên bản API; chỉ hỗ trợ `v2` (v1 đã ngừng phát triển) | Đối soát API |
| `SEPAY_API_TIMEOUT` | Không | 15 | Timeout mỗi request (giây) | Đối soát API |
| `SEPAY_API_RETRIES` | Không | 3 | Số lần thử lại khi 429/5xx/lỗi mạng | Đối soát API |
| `SEPAY_DOI_SOAT_TELEGRAM_TOI_DA` | Không | 5 | Số tin Telegram chi tiết tối đa mỗi lần chạy đối soát; phần vượt gộp thành một tin tóm tắt | Đối soát API |
| `TELEGRAM_BOT_TOKEN` | Không | (rỗng) | Token Telegram Bot | Thông báo |
| `TELEGRAM_CHAT_ID` | Không | (rỗng) | Chat ID nhận thông báo | Thông báo |
| `POS_DIEM_MOI_DONG` | Không | 100000 | Doanh thu để được 1 điểm | Tích điểm |
| `POS_GIA_TRI_DIEM` | Không | 1000 | Giá trị 1 điểm (VNĐ) | Tích điểm |
| `POS_HAN_TRA_NGAY` | Không | 7 | Hạn trả hàng (ngày) | Trả hàng |
| `POS_NGUONG_TRA_KHONG_DUYET` | Không | 500000 | Ngưỡng tự duyệt trả hàng (VNĐ) | Trả hàng |
| `POS_ONLINE_QR_PHUT` | Không | 30 | Hạn giữ đơn QR online (phút) | Đặt hàng online |
| `POS_ONLINE_PICKUP_GIO` | Không | 4 | Hạn giữ đơn pickup (giờ); mỗi lần gia hạn cộng ngần này | Đặt hàng online |
| `POS_ONLINE_GIA_HAN_TOI_DA_GIO` | Không | 24 | Trần hạn giữ pickup tối đa (giờ, tính từ lúc tạo đơn) | Đặt hàng online |
| `POS_ONLINE_MAX_DON_MO` | Không | 3 | Số đơn `cho_thanh_toan` tối đa mỗi SĐT | Đặt hàng online |
| `POS_ONLINE_MAX_SOLUONG` | Không | 10 | Số lượng tối đa mỗi dòng hàng online | Đặt hàng online |
| `POS_ONLINE_GIU_TOI_DA_PHAN_TRAM` | Không | 50 | Trần giữ pickup: % tồn gốc mỗi sản phẩm | Đặt hàng online |
| `POS_ONLINE_THROTTLE_DAT` | Không | 5,1 | Rate limit checkout mỗi IP (số đơn,phút) | Đặt hàng online |
| `POS_PHI_GIAO_HANG` | Không | 30000 | Phí giao tận nơi cố định (VNĐ), server tính | Đặt hàng online |
| `POS_MIEN_PHI_GIAO_TU` | Không | 0 | Miễn phí ship khi tiền hàng ≥ ngưỡng (0 = tắt) | Đặt hàng online |
| `POS_ONLINE_TU_DONG_XAC_NHAN` | Không | false | `true` → đơn giao tự `da_xac_nhan` khi tiền về; `false` → `cho_xac_nhan` | Đặt hàng online |
| `POS_GIAO_HANG_TINH` | Không | (rỗng) | Vùng giao, phân tách dấu phẩy (rỗng = không giới hạn) | Đặt hàng online |
| `POS_DAT_HANG_ONLINE_BAT` | Không | **false** | Bật đặt hàng online; tắt → mọi route `/dat-hang`, `/don-hang` trả 404 | Đặt hàng online |
| `POS_GIAO_TAN_NOI_BAT` | Không | **false** | Bật hình thức giao tận nơi (chỉ có ý nghĩa khi cờ trên bật) | Đặt hàng online |
| `LOG_STACK` | Không | daily | Kênh trong stack log; đặt `daily` để log xoay vòng | Ghi log |
| `LOG_DAILY_DAYS` | Không | 14 | Số ngày giữ log daily | Ghi log |
| `BACKUP_DISK` | Không | backups | Đĩa lưu bản sao lưu (khai báo trong `config/filesystems.php`) | Sao lưu |
| `BACKUP_GIU_BAN` | Không | 7 | Số bản sao lưu giữ lại | Sao lưu |
| `BACKUP_TIMEOUT` | Không | 300 | Thời gian tối đa của `mysqldump` (giây) | Sao lưu |
| `DB_DUMP_BINARY` | MySQL | mysqldump | Đường dẫn `mysqldump.exe`; để trống thì tìm trong PATH | Sao lưu |

> **Lưu ý:** Luồng webhook (`WebhookController`) chỉ xác thực bằng **API Key**
> (`SEPAY_WEBHOOK_API_KEY`, header `Authorization: Apikey <KEY>`, so sánh bằng
> `hash_equals`; để trống key thì từ chối toàn bộ — 401, tức **fail-closed**).
> Biến `POS_WEBHOOK_SECRET` (HMAC) **đã được gỡ bỏ** khỏi `.env.example`,
> `config/pos.php` và `phpunit.xml`, cùng controller legacy
> `app/Http/Controllers/Api/PaymentWebhookController.php`. Controller đó không
> được đăng ký ở bất kỳ route nào và xác thực kiểu **fail-open**
> (`if ($secret) { ... }` — secret rỗng thì bỏ qua kiểm tra chữ ký), nên bị xóa
> để tránh rủi ro nếu sau này bị route lại.

### Hai khóa SePay khác nhau — không dùng lẫn

| Khóa | Chiều gọi | Header | Nơi dùng | Mục đích |
|---|---|---|---|---|
| `SEPAY_WEBHOOK_API_KEY` | SePay → POS | `Authorization: Apikey <KEY>` | `config('pos.sepay_webhook_api_key')`, `WebhookController` | Xác thực webhook SePay gửi vào |
| `SEPAY_API_TOKEN` | POS → SePay | `Authorization: Bearer <TOKEN>` | `config('pos.sepay_api_token')`, `SepayApiClient` | Đọc giao dịch để đối soát chủ động |

Hai khóa do SePay cấp ở hai màn hình khác nhau và **không thể thay thế nhau**.
Dùng `SEPAY_API_TOKEN` cho webhook sẽ bị 401; dùng `SEPAY_WEBHOOK_API_KEY` để gọi
API cũng bị 401. `SepayApiClient` chỉ gọi `GET`, không có endpoint ghi/hoàn tiền.


## 4. Thiết lập thiết bị và thanh toán thực tế

Phần này dành cho lúc chạy demo tại cửa hàng. Nên kiểm thử trên bản sao dữ
liệu trước, không dùng `demo:reset` sau khi đã nhập dữ liệu thật.

### 4.1. Máy quét barcode thật

Ứng dụng hiện hỗ trợ máy quét loại **USB HID/keyboard wedge**. Loại này hoạt
động như bàn phím nên không cần driver hoặc SDK riêng:

1. Cắm máy quét vào máy tính và mở Notepad.
2. Quét thử một mã; nếu Notepad nhận đúng chuỗi barcode thì thiết bị đã được
   Windows nhận.
3. Cấu hình máy quét gửi thêm phím `Enter` sau mỗi lần quét (suffix CR/LF).
4. Đăng nhập tài khoản admin hoặc thu ngân, mở `/pos`.
5. Đặt con trỏ vào ô tìm barcode, quét sản phẩm và kiểm tra sản phẩm được thêm
   vào giỏ.
6. Quét lại cùng sản phẩm để kiểm tra số lượng trong giỏ tăng lên.

Nếu máy quét không tự thêm sản phẩm, kiểm tra barcode trong dữ liệu sản phẩm,
đảm bảo sản phẩm đang bán và còn tồn, sau đó cấu hình lại suffix `Enter` theo
hướng dẫn của nhà sản xuất. Không nên dùng máy quét webcam cho luồng này vì
ứng dụng chưa có chức năng đọc barcode bằng camera.

### 4.2. Máy in nhiệt K58/K80

Ứng dụng tạo hóa đơn bằng giao diện in của trình duyệt; máy in không được
điều khiển trực tiếp bằng Laravel:

1. Cài driver đúng model máy in trên Windows.
2. In trang test trong Windows để xác nhận máy in hoạt động.
3. Vào `/donhang`, mở một hóa đơn và chọn khổ `K58` hoặc `K80`.
4. Trong hộp thoại in của trình duyệt, chọn đúng máy in nhiệt.
5. Chọn khổ giấy tương ứng (58 mm hoặc 80 mm), tắt header/footer của trình
   duyệt và đặt lề về mức tối thiểu.
6. In thử một hóa đơn trước khi demo; kiểm tra tiếng Việt, xuống dòng và tổng
   tiền.

Nếu chữ bị cắt, chọn đúng khổ giấy trong cả driver máy in và hộp thoại in.
Nếu cần in tự động không qua hộp thoại trình duyệt, phải bổ sung một print
server hoặc phần mềm trung gian như QZ Tray; tính năng đó chưa có trong
project hiện tại.

### 4.3. VietQR & Webhook SePay (Test Mode) & Telegram

Cấu hình biến môi trường theo [Bảng biến môi trường đầy đủ](#bảng-biến-môi-trường-đầy-đủ) ở trên.

**Thiết lập SePay Test Mode & Webhook:**
1. **Đăng nhập SePay**: Truy cập [my.sepay.vn](https://my.sepay.vn) và đăng nhập hoặc tạo tài khoản miễn phí.
2. **Kích hoạt Test Mode**: Vào phần **Cài đặt** (Settings) -> Bật **Chế độ mô phỏng / Test Mode**.
3. **Cấu hình Webhook**:
   - Vào mục **Tích hợp Webhook** (Webhooks).
   - Nhập URL Webhook của bạn (phải là URL public internet, xem hướng dẫn Tunnel bên dưới):  
     `https://<your-tunnel-subdomain>/api/webhook/thanhtoan`
   - Phương thức xác thực: Chọn **API Key** (gửi qua header `Authorization: Apikey <KEY>`).
   - Copy mã API Key này và điền vào biến `SEPAY_WEBHOOK_API_KEY` trong file `.env`.
4. **Mô phỏng giao dịch (Test)**:
   - Vào mục **Mô phỏng giao dịch** trên SePay Dashboard.
   - Nhập số tiền chuyển và nội dung chuyển khoản chứa mã hóa đơn (ví dụ: `HD202611020001` hoặc `HD20261102-0001`).
   - Bấm **Gửi Webhook**. Hệ thống POS sẽ tự động nhận diện và hoàn tất hóa đơn trong vòng vài giây.

**Đối soát chủ động qua SePay API (tùy chọn, chỉ đọc):**
Dùng khi webhook có thể bị mất (tunnel rớt, SePay retry hết). Hệ thống đọc lại
giao dịch tiền vào từ SePay API và đưa các giao dịch thiếu webhook vào `/doisoat`.
1. Trên SePay Dashboard, tạo **API Token** (quyền đọc giao dịch) — đây là khóa
   **khác** với `SEPAY_WEBHOOK_API_KEY` ở bước 3.
2. Mở `.env`, **thêm dòng mới** `SEPAY_API_TOKEN=` và dán token ngay sau dấu `=`.
   Không sửa `SEPAY_WEBHOOK_API_KEY`. Không dán token vào chat, tài liệu hay mã nguồn.
3. Xóa cache cấu hình và kiểm tra kết nối:
   ```powershell
   php artisan config:clear
   php artisan sepay:kiem-tra-ket-noi
   ```
   Lệnh chỉ gọi một request nhỏ (`per_page=1`), in `Kết nối SePay API OK.` hoặc
   `SePay API trả 401 Unauthorized — kiểm tra SEPAY_API_TOKEN.`; không in token
   hay dữ liệu giao dịch.
4. Chạy đối soát thử (mặc định **chỉ báo cáo**, không đổi hóa đơn):
   ```powershell
   php artisan sepay:doi-soat --gio=6
   ```
   Kết quả nằm trong `/doisoat`, cột **Nguồn** ghi `api_doi_soat`.

**Tạo Tunnel công khai (Cloudflare Tunnel / ngrok):**
Vì SePay gửi webhook từ internet về máy local, bạn cần mở tunnel:
- **Cách 1: Dùng Cloudflare Tunnel (Khuyên dùng, miễn phí, không giới hạn session)**
  ```powershell
  cloudflared tunnel --url http://127.0.0.1:8081
  ```
  Copy URL `https://xxxx.trycloudflare.com` dán vào SePay Webhook URL: `https://xxxx.trycloudflare.com/api/webhook/thanhtoan`.
- **Cách 2: Dùng ngrok**
  ```powershell
  ngrok http 8081
  ```
  Copy URL `https://xxxx.ngrok-free.app` dán vào SePay Webhook URL: `https://xxxx.ngrok-free.app/api/webhook/thanhtoan`.

**Tạo Telegram Bot & Lấy Chat ID:**
1. Mở ứng dụng Telegram, tìm kiếm bot `@BotFather`.
2. Gửi lệnh `/newbot`, đặt tên và username cho bot (ví dụ: `MyPosStoreBot`).
3. `@BotFather` sẽ gửi lại mã HTTP API Token. Copy mã này vào `TELEGRAM_BOT_TOKEN` trong file `.env`.
4. Mở bot bạn vừa tạo trên Telegram và bấm **Start**.
5. Để lấy Chat ID cá nhân: tìm bot `@userinfobot`, bấm Start để xem ID của bạn (hoặc thêm bot vào group và lấy Group ID). Copy ID này vào `TELEGRAM_CHAT_ID` trong `.env`.
6. Xóa cache cấu hình:
   ```powershell
   php artisan config:clear
   ```

**Cơ chế tự động hủy hóa đơn quá hạn:**
- **Cơ chế Hủy lười (Lazy Expiration)**: Chỉ kích hoạt khi POS polling
  `GET /donhang/trangthai/{id}` (`DonHangController::getTrangThai`) mà thời gian
  chờ đã vượt quá `POS_CHO_THANH_TOAN_PHUT`: hệ thống hoàn kho, hoàn điểm đã dùng
  và chuyển trạng thái sang `da_huy`. Mở danh sách/chi tiết hóa đơn không kích
  hoạt hủy lười.
- **Lệnh tự động định kỳ (Scheduler)**:
  ```powershell
  php artisan donhang:huy-qua-han
  ```
  Lệnh này được khai báo chạy mỗi phút (`everyMinute`) trong `routes/console.php`,
  nhưng chỉ thực sự chạy khi cron hoặc Windows Task Scheduler gọi
  `php artisan schedule:run` mỗi phút. Máy phát triển hiện chưa cấu hình task
  này (kiểm tra ngày 2026-11-10), nên trên máy demo việc hủy quá hạn chủ yếu
  dựa vào hủy lười khi POS polling.

## 5. Chạy ứng dụng

### Chạy trên port 8081

```powershell
php artisan serve --host=127.0.0.1 --port=8081
```

Mở:

```text
http://127.0.0.1:8081/dangnhap
```

Port chỉ có hiệu lực trong lần chạy server hiện tại. Dừng server bằng `Ctrl + C`.

### Chạy frontend (nếu cần build asset)

```powershell
npm install
npm run build
```

### Lịch chạy nền (hủy đơn quá hạn, dọn giỏ tạm, sao lưu)

Khi phát triển hoặc demo nhanh, chạy foreground:

```powershell
php artisan schedule:work
```

Khi vận hành tại cửa hàng, cài Windows Task Scheduler gọi
`scripts/windows/chay-schedule.bat` **mỗi phút**. File batch tự `cd` về gốc dự án,
gọi `php artisan schedule:run --no-interaction`, ghi log vào
`storage/logs/schedule-YYYY-MM-DD.log` và tự xóa log cũ hơn 14 ngày. Nếu PHP không
nằm trong PATH, đặt biến `PHP_BINARY` (ví dụ
`<đường-dẫn-php.exe>`) trước khi gọi file batch.

Tạo task bằng PowerShell (chạy một lần, mở quyền Administrator):

```powershell
schtasks /Create /SC MINUTE /MO 1 /TN "WebPOS-Scheduler" /TR '"<thư-mục-dự-án>\scripts\windows\chay-schedule.bat"' /F
```

Hoặc dùng Task Scheduler GUI: **Create Task** → Triggers: Daily, *Repeat task every
1 minute*, *for a duration of* Indefinitely → Actions: Start a program → chọn file
`.bat` → để trống "Start in" (script tự về gốc dự án) → chọn *Run whether user is
logged on or not*.

Các task trong `routes/console.php`:

| Task | Tần suất | Việc làm |
|---|---|---|
| `donhang:huy-qua-han` | Mỗi phút | Hủy hóa đơn `cho_thanh_toan` quá `POS_CHO_THANH_TOAN_PHUT`, hoàn kho/điểm |
| `hold:clean` | Mỗi ngày | Xóa giỏ tạm quá 24 giờ |
| `sao-luu:csdl` | Mỗi ngày 02:00 | Sao lưu CSDL vào `storage/app/backups` |
| `sepay:doi-soat --gio=6` | Mỗi 10 phút | Đối soát SePay API (**chỉ báo cáo**), tự bỏ qua khi thiếu `SEPAY_API_TOKEN`; Telegram một lần cho mỗi giao dịch thiếu mới **tìm được hóa đơn**, tối đa `SEPAY_DOI_SOAT_TELEGRAM_TOI_DA` tin chi tiết + một tin tóm tắt |
| heartbeat | Mỗi phút | Ghi mốc thời gian để `he-thong:kiem-tra` biết scheduler còn sống |

Nếu **không** chạy, đơn `cho_thanh_toan` quá hạn chỉ bị hủy "lười" khi POS poll
trạng thái (`DonHangController@getTrangThai`) hoặc khi thu ngân bấm hủy; đơn không
ai poll sẽ giữ tồn kho và điểm của khách vô thời hạn. Lưu ý webhook SePay **không**
kiểm tra thời hạn, nên tiền về trễ cho đơn quá hạn chưa hủy vẫn hoàn tất thanh toán
(xem test T-CK18 ở mục 9).

### Kiểm tra sức khỏe hệ thống (chỉ đọc)

```powershell
php artisan he-thong:kiem-tra
```

Lệnh chỉ đọc, không in giá trị bí mật. Exit code `0` khi không có lỗi, `1` khi có ít
nhất một mục `FAIL`. Các mục kiểm tra:

- **Scheduler**: nhịp heartbeat trong 3 phút gần nhất.
- **Đơn chờ quá hạn**: số hóa đơn `cho_thanh_toan` quá `POS_CHO_THANH_TOAN_PHUT`.
- **Cấu hình**: chỉ báo thiếu/đủ `POS_BANK_CODE` + `POS_BANK_ACCOUNT`,
  `SEPAY_WEBHOOK_API_KEY`, `TELEGRAM_BOT_TOKEN` + `TELEGRAM_CHAT_ID`.
- **SePay API**: `SEPAY_API_TOKEN` đủ/thiếu — chỉ `WARN` vì đối soát chủ động là
  tùy chọn; kèm thời điểm `sepay:doi-soat` chạy gần nhất, `WARN` nếu quá 30 phút.
- **Môi trường**: `APP_ENV` (production), `APP_DEBUG` (production mà bật là `FAIL`),
  `APP_URL`, trạng thái `config:cache`.
- **Quyền ghi**: `storage`, `storage/logs`, `storage/framework/{views,sessions}`,
  `bootstrap/cache`.
- **Log**: kênh daily và số ngày giữ.
- **Engine**: toàn bộ bảng MySQL phải là InnoDB (xem mục 12).
- **Migration**: các migration chưa áp dụng.

### Sao lưu và khôi phục CSDL

```powershell
php artisan sao-luu:csdl
php artisan sao-luu:csdl --giu=10
```

- Lưu vào `storage/app/backups` (`BACKUP_DISK`), tên `<database>-YYYYmmdd-His.sql`
  với MySQL và `<tên-tệp>-YYYYmmdd-His.sqlite` với SQLite.
- MySQL: gọi `mysqldump --single-transaction --routines --triggers --events
  --default-character-set=utf8mb4`, mật khẩu truyền qua biến môi trường `MYSQL_PWD`
  nên không lộ trong danh sách tiến trình. Trên WAMP nên đặt
  `DB_DUMP_BINARY=<đường-dẫn-mysqldump.exe>`.
- SQLite: dùng `VACUUM INTO` để có bản sao nhất quán ngay cả khi đang bán hàng.
- Giữ `BACKUP_GIU_BAN` bản mới nhất (mặc định 7) và chỉ xóa tệp do chính lệnh này
  sinh ra (đúng tiền tố + dấu thời gian).
- Thiếu `mysqldump`, timeout hoặc tệp rỗng: lệnh xóa tệp dở, in lỗi rõ ràng và trả
  mã `1`.

Khôi phục MySQL (dừng ứng dụng trước):

```powershell
& "<đường-dẫn-mysql.exe>" -u root doan_cnw `
  -e "SOURCE <thư-mục-dự-án>/storage/app/backups/doan_cnw-20261110-020000.sql"
php artisan migrate --force
```

Khôi phục SQLite: dừng ứng dụng rồi chép đè tệp sao lưu lên đường dẫn
`DB_DATABASE`.

## 5A. Triển khai tại cửa hàng (checklist)

Checklist cho lần đưa hệ thống lên máy chạy thật. Làm trên bản sao CSDL trước nếu
có thể; **luôn sao lưu trước khi chạy migration**.

> **Thứ tự bắt buộc:** sao lưu → `migrate --force` → `he-thong:kiem-tra` (không còn
> migration chờ) → **sau đó mới** bật webhook và lịch đối soát. Chạy code mới trước
> khi migrate sẽ làm mọi webhook lỗi 500 vì thiếu cột `ma_tham_chieu`.

1. **Sao lưu CSDL hiện tại** bằng `mysqldump` (hoặc `php artisan sao-luu:csdl`) và
   giữ bản sao ở ổ khác.
2. **Cấu hình `.env`** (chỉ sửa `.env` trên máy chủ, không commit):
   `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` đúng tên miền/IP,
   `DB_*` trỏ CSDL thật, `LOG_STACK=daily`, `LOG_DAILY_DAYS=14`.
   Thêm các dòng còn thiếu: `POS_BANK_CODE`, `POS_BANK_ACCOUNT`, `POS_BANK_NAME`,
   `SEPAY_WEBHOOK_API_KEY`, `SEPAY_API_TOKEN` (nếu bật đối soát chủ động),
   `SEPAY_DOI_SOAT_TELEGRAM_TOI_DA` (mặc định 5), `TELEGRAM_BOT_TOKEN`,
   `TELEGRAM_CHAT_ID`, `BACKUP_DISK`, `BACKUP_GIU_BAN`, `BACKUP_TIMEOUT`,
   `DB_DUMP_BINARY` (xem bảng biến môi trường ở mục 3.4; đầy đủ gồm biến SePay
   API ở `docs/SO_TAY_TRIEN_KHAI.md` mục 3).
   **Đặt hàng online**: `POS_DAT_HANG_ONLINE_BAT` và `POS_GIAO_TAN_NOI_BAT` mặc định
   `false` — chỉ bật sau khi nhân viên đã có màn hình xử lý đơn online (P3);
   bật sớm sẽ có đơn mà không ai xử lý. Khi tắt, khách không đặt được đơn mới
   nhưng đơn cũ vẫn được webhook/scheduler xử lý bình thường.
3. **Cài dependency và build asset**: `composer install --no-dev --optimize-autoloader`
   và `npm ci && npm run build` (phải có `public/build/manifest.json`).
4. **Chạy migration**: `php artisan migrate --force`. Lần triển khai đầu tiên có
   migration chuyển toàn bộ bảng MySQL sang InnoDB — với dữ liệu lớn nên chạy
   ngoài giờ bán hàng và đã có bản sao lưu.
5. **Xác nhận engine và cấu hình**: `php artisan he-thong:kiem-tra` phải hết mục
   `FAIL` (đặc biệt *Engine lưu trữ* và *SePay webhook*).
6. **Tối ưu**: `php artisan config:cache`, `php artisan route:cache`,
   `php artisan view:cache`. Mỗi lần sửa `.env` phải chạy lại `config:cache`.
7. **Cài Task Scheduler** gọi `scripts/windows/chay-schedule.bat` mỗi phút (mục 5),
   sau đó chạy lại `he-thong:kiem-tra` để thấy mục *Scheduler* = OK.
8. **Chạy sao lưu thủ công một lần**: `php artisan sao-luu:csdl`, kiểm tra tệp trong
   `storage/app/backups` và thử khôi phục trên CSDL nháp.
9. **Tài khoản**: đổi mật khẩu các tài khoản demo bằng
   `php artisan nguoidung:doi-matkhau {email}`, hoặc tạo tài khoản mới bằng
   `php artisan nguoidung:tao {email} {vai_tro}` (`admin`, `thungan`, `thukho`).
   Dashboard sẽ cảnh báo nếu còn tài khoản dùng mật khẩu mặc định.
10. **Kiểm tra cuối**: đăng nhập, bán thử một đơn tiền mặt và một đơn chuyển khoản,
    thử hold/trả hàng, mở `/doisoat`, xem log không có lỗi, chạy
    `php artisan he-thong:kiem-tra` lần cuối.

Khi cần khôi phục sự cố: dừng ứng dụng, khôi phục tệp sao lưu gần nhất (mục 5), chạy
`php artisan migrate --force`, rồi `he-thong:kiem-tra`. Mã nguồn quay lại bằng
`git checkout <tag/commit>` trên máy chủ; không chạy `migrate:fresh` trên CSDL thật.

## 6. Tài khoản demo

Tất cả tài khoản demo đều có mật khẩu `123456`.

| Vai trò | Email | Quyền chính |
|---|---|---|
| Quản lý | `admin@example.com` | Toàn quyền và xem thống kê |
| Thu ngân | `thungan@example.com` | POS, giỏ hàng, thanh toán, hóa đơn |
| Thủ kho | `thukho@example.com` | Sản phẩm, loại sản phẩm, nhập kho |

Mật khẩu trên chỉ dùng cho demo, không dùng trong môi trường thật.
Các email trên trùng với tài khoản được tạo trong `database/seeders/DatabaseSeeder.php`.

## 7. Chức năng chính

### Đăng nhập và phân quyền

- Đăng nhập, đăng xuất, ghi nhớ đăng nhập.
- Middleware phân quyền theo `admin`, `thungan`, `thukho`.
- Truy cập trái phép trả trang 403 tiếng Việt.
- Dashboard tổng quan dành cho người dùng đã đăng nhập.

### Quản lý danh mục và sản phẩm

- CRUD loại sản phẩm.
- Loại sản phẩm có `ma_loai`, `tu_khoa` và `he_so_lai`; hệ số lãi chỉ dùng gợi ý
  khi tạo sản phẩm mới.
- CRUD sản phẩm.
- Validate tên, SKU, barcode, giá và số lượng.
- Upload hình ảnh bằng `Storage::disk('public')` vào `storage/app/public/sanpham` (cần chạy `php artisan storage:link`); sản phẩm chưa có ảnh dùng `public/img/no-image.svg`.
- **Ảnh theo URL**: form thêm/sửa nhận thêm trường `hinh_anh_url` (chỉ `https://`,
  tối đa **150 ký tự** đúng cột thật `hinh_anh` VARCHAR(150) utf8mb4 — migration khai
  255 nhưng DB thật chưa áp; không đổi schema; không yêu cầu đuôi `.jpg/.png` vì CDN
  thường không có đuôi). URL sai bị chặn bằng thông báo tiếng Việt ngay dưới ô nhập
  ("Liên kết ảnh không hợp lệ…" / "Liên kết ảnh quá dài (tối đa 150 ký tự)…"), giữ
  `old()` — không bao giờ 500. Gửi cả tệp lẫn URL thì **tệp tải lên được ưu tiên**
  (có thông báo); URL hỏng không chặn việc lưu tệp. Sửa hỗ trợ đổi tệp ↔ URL và nút
  "Xóa ảnh hiện tại". Rule dùng chung `App\Rules\AnhSanPhamUrl`; server **không
  tải/gọi HTTP** tới URL người dùng nhập (chống SSRF) — chỉ lưu chuỗi, trình duyệt
  hiển thị với `loading="lazy" referrerpolicy="no-referrer"`, lỗi tải ảnh tự thay
  bằng ảnh mặc định. Form có kiểm tra ảnh phía trình duyệt (debounce 500ms + blur,
  `new Image()` với `referrerPolicy=no-referrer`, timeout 8s, vùng trạng thái
  `aria-live`, chặn nút Lưu khi URL hỏng cho tới khi sửa hoặc chọn tệp/xóa ảnh) —
  chỉ là UX, không phải biện pháp bảo mật; server vẫn là nguồn sự thật. Mọi view
  dùng accessor `SanPham::anh_url` (https giữ nguyên, tệp tải lên qua `Storage::url`,
  legacy `upload/`/`storage/` qua `asset()`, rỗng → ảnh mặc định). Xóa tệp cũ khi
  thay ảnh chỉ diễn ra trong thư mục ảnh sản phẩm (`App\Services\TepAnhSanPham`),
  không bao giờ xóa URL. Chi tiết:
  [docs/BAO_CAO_ANH_URL_VALIDATE.md](docs/BAO_CAO_ANH_URL_VALIDATE.md) và
  [docs/BAO_CAO_ANH_URL_TONG_QUAN.md](docs/BAO_CAO_ANH_URL_TONG_QUAN.md).
- Badge tồn kho:
  - Đỏ: hết hàng.
  - Vàng: tồn kho nhỏ hơn hoặc bằng ngưỡng tối thiểu.
  - Xanh: tồn kho bình thường.

### POS và thanh toán

- Tìm sản phẩm theo tên, SKU hoặc barcode.
- Hiển thị kết quả POS dạng thẻ có ảnh sản phẩm.
- Hỗ trợ mã barcode từ máy quét dạng bàn phím bằng phím Enter.
- Chỉ hiển thị sản phẩm đang bán và còn hàng.
- Giới hạn tối đa 10 kết quả tìm kiếm.
- Giỏ hàng được lưu trong session.
- Gộp sản phẩm trùng thành một dòng.
- Tính giảm giá, tiền khách đưa và tiền thừa.
- Thanh toán bằng tiền mặt hoặc chuyển khoản.
- Hiển thị VietQR động khi đã cấu hình ngân hàng trong `.env`; thu ngân vẫn xác nhận thủ công.
- Quản lý khách hàng theo số điện thoại và tích điểm: 100.000 đ được 1 điểm, 1 điểm giảm 1.000 đ. SĐT phải hợp lệ theo quy tắc chung (xem mục Quản lý khách hàng bên dưới).
- Không cho bán vượt tồn kho.

Thanh toán sử dụng một transaction duy nhất:

1. Khóa sản phẩm bằng `lockForUpdate()`.
2. Kiểm tra tồn kho lần cuối.
3. Tạo hóa đơn và chi tiết hóa đơn.
4. Trừ tồn kho.
5. Ghi biến động kho loại `ban`.
6. Rollback toàn bộ nếu có lỗi.

### Quản lý khách hàng

- Thêm/sửa/tìm/xóa khách tại `/khachhang` (admin, thu ngân); thủ kho 403, khách
  hàng bị chuyển về storefront.
- **Quy tắc SĐT dùng chung** `App\Rules\SoDienThoaiHopLe` (`app/Rules/SoDienThoaiHopLe.php`),
  áp ở MỌI điểm nhập: form thêm/sửa khách, POS thêm khách nhanh, tìm khách theo
  SĐT, đăng ký storefront, import xlsx và seeder:
  1. Chuẩn hóa trước: `trim`, bỏ khoảng trắng/dấu chấm/gạch ngang/ngoặc đơn,
     đổi đầu `+84` hoặc `84` thành `0`.
  2. Hợp lệ khi khớp `/^0(3|5|7|8|9)\d{8}$/` — di động VN 10 chữ số, đầu
     03/05/07/08/09. Từ chối chữ, ký tự lạ, sai độ dài, số cố định, mảng.
  3. Thông báo lỗi tiếng Việt: "Số điện thoại không hợp lệ. Nhập 10 chữ số, bắt
     đầu bằng 0."
  4. Lưu DB dạng đã chuẩn hóa; chống trùng bằng `unique` (bỏ qua bản ghi đang
     sửa) + unique index sẵn có trên `customers.so_dienthoai`.
- Server là nguồn đúng: ô nhập có `type=tel`, `inputmode`, `pattern`, `maxlength`
  và JS lọc ký tự chỉ là tiện ích; tắt JS vẫn không nhập được chữ. Form giữ
  `old()` và hiện lỗi từng trường.
- Xóa khách đã có hóa đơn bị chặn (không xóa được, không 500); khách chưa có hóa
  đơn xóa được, tham chiếu ở phiếu trả/hóa đơn (nếu có) được đặt null theo khóa
  ngoại `nullOnDelete`.
- Đăng ký storefront **không tự liên kết** hồ sơ khách vãng lai theo SĐT
  (vá lỗ hổng chiếm hồ sơ 2026-11-16): đăng ký trùng SĐT đã có hồ sơ chưa
  liên kết vẫn tạo tài khoản mới nhưng hồ sơ cũ giữ nguyên; đăng ký trùng
  SĐT đã liên kết tài khoản khác bị từ chối thông báo chung. Điểm khởi tạo
  0, không nhận `role`/`diem_tichluy` từ client.
- **Quy trình nhân viên liên kết tài khoản khách với hồ sơ mua tại quầy**
  (màn `/khachhang/sua/{id}`, chỉ admin/thu ngân):
  1. Xác minh danh tính NGOÀI hệ thống: yêu cầu khách đăng nhập tài khoản
     của họ trên chính thiết bị của khách + đưa hóa đơn gần nhất (hoặc đọc
     mã hóa đơn) để đối chiếu SĐT/tên với hồ sơ.
  2. Vào form sửa khách hàng → ô "Tài khoản khách hàng" → chọn tài khoản
     role `khachhang` chưa liên kết → Lưu. Tài khoản đã gắn hồ sơ khác
     không xuất hiện; gán trùng bị chặn.
  3. Mọi gán/gỡ liên kết ghi `activity_logs` (trait `GhiNhatKy` trên
     `KhachHang`, có diff `truoc`/`sau`).
  4. Gỡ liên kết nhầm: chọn "— Chưa liên kết —" → Lưu. Không gán chỉ vì
     trùng SĐT — đó là lý do auto-link đã bị gỡ.
- Chi tiết rà soát và dữ liệu hiện có: [docs/BAO_CAO_KHACH_HANG.md](docs/BAO_CAO_KHACH_HANG.md).

### Hóa đơn

- Danh sách hóa đơn.
- Lọc danh sách theo ngày bắt đầu và ngày kết thúc.
- Xem chi tiết hóa đơn.
- In hóa đơn khổ K80 bằng trình duyệt.
- Có thể chọn khổ in K58 hoặc K80.
- Giá trên hóa đơn được lưu tại thời điểm bán.
- Các bảng danh sách có tìm kiếm và phân trang bằng DataTables.

### Hóa đơn nhập & Bóc tách PDF nội bộ

- Tạo hóa đơn nháp, thêm nhiều dòng hàng và đính kèm PDF chứng từ gốc.
- **Bóc tách PDF cục bộ (Local Parsing - Không dùng API)**:
  - Tự động bóc tách số hóa đơn, ký hiệu, ngày lập, MST nhà cung cấp và danh sách mặt hàng trực tiếp trên máy chủ PHP bằng thư viện `smalot/pdfparser` và Regex Heuristic.
  - Chạy 100% offline trên môi trường nội bộ, không phụ thuộc kết nối Internet, không tốn token và bảo mật dữ liệu doanh nghiệp.
  - Tự động so khớp sản phẩm theo SKU/Barcode và hàm tương đồng chuỗi `similar_text()`.
  - Cơ chế **Human-in-the-Loop**: Dữ liệu bóc tách được đưa lên giao diện tiền kiểm tra (Draft Review UI) để thủ kho rà soát, chỉnh sửa ô thiếu trước khi xác nhận.
  - **Non-blocking Workflow**: Lỗi file PDF không làm gián đoạn nhập kho, người dùng luôn có thể nhập liệu thủ công.
- Giá nhập là giá chưa thuế; thuế và tổng thanh toán tính ở cấp hóa đơn.
- Chỉ xác nhận mới nhập kho; xác nhận dùng transaction, khóa dòng và ghi
  `purchase_invoice_id`.
- Hóa đơn đã nhập kho không được sửa/hủy hoặc xác nhận lần hai.

### Thanh toán chuyển khoản & Webhook SePay

- Tạo QR VietQR động trên màn hình POS, thu ngân bấm "Tạo QR" để chuyển hóa đơn sang trạng thái `cho_thanh_toan` (trừ kho, trừ điểm dùng ngay).
- Webhook endpoint: `POST /api/webhook/thanhtoan` nhận callback từ SePay.
- Xác thực API Key (so sánh `hash_equals`), validate payload, chống trùng lặp
  (idempotent) **chỉ theo id giao dịch của webhook** — đối chiếu hai cột
  `ma_giaodich` và `ma_giao_dich` trên hóa đơn. Mã tham chiếu (`referenceCode`)
  rỗng, chỉ khoảng trắng hoặc bị dùng lại cho giao dịch khác **không** làm bỏ qua
  webhook; mã này chỉ gộp bản ghi báo cáo API cùng số tiền khi hiển thị ở `/doisoat`.
- Tự động tìm mã hóa đơn trong nội dung chuyển khoản, đối chiếu số tiền.
- Nếu đủ tiền: chuyển `da_thanh_toan`, ghi `ma_giaodich`, cộng điểm, gửi Telegram.
- Nếu thiếu tiền: giữ `cho_thanh_toan`, cảnh báo Telegram.
- Nếu hóa đơn đã `da_huy`: bỏ qua, ghi log và cảnh báo Telegram (test T-CK17).
- Webhook không kiểm tra hạn chờ: đơn quá hạn nhưng chưa bị hủy (chưa polling,
  scheduler chưa chạy) vẫn có thể được hoàn tất khi webhook tới (hành vi hiện tại,
  test T-CK18).
- Webhook không khớp hóa đơn/số tiền được đưa vào hàng chờ đối soát thay vì làm mất
  dấu giao dịch (xem mục "Đối soát chuyển khoản" bên dưới).
- Thu ngân có thể bấm "Xác nhận đã nhận tiền" hoặc "Hủy chờ" thủ công trên POS.
- Lệnh `php artisan donhang:huy-qua-han` (lập lịch mỗi phút) tự hủy đơn quá hạn, hoàn kho/điểm.
- Cấu hình trong `.env`: `SEPAY_WEBHOOK_API_KEY`, `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID`, `POS_CHO_THANH_TOAN_PHUT`; tùy chọn `SEPAY_API_TOKEN` cho đối soát chủ động.

### Đối soát chuyển khoản

- Mọi webhook được lưu vào `payment_webhooks` với `ket_qua` (`thanh_cong`,
  `can_doi_soat`, ...) và `ly_do`. Giao dịch không khớp hóa đơn/số tiền không bị
  mất: chúng vào hàng chờ đối soát.
- Admin vào `/doisoat` để xem danh sách `ket_qua = can_doi_soat`, lọc theo
  `chua_xu_ly` / `da_xu_ly` / `tat_ca`.
- Chọn một giao dịch để xem nội dung chuyển khoản, hóa đơn liên quan và ghi chú xử
  lý bắt buộc (3–1000 ký tự); bấm xử lý sẽ đánh dấu `da_xu_ly` kèm người xử lý,
  thời gian và ghi nhật ký `doi_soat_danh_dau`.
- Xử lý lặp bị chặn: giao dịch đã đánh dấu sẽ trả 422, không ghi đè người xử lý cũ.
- Đây là đối soát thủ công (đánh dấu + ghi chú), chưa tự khớp lại tiền vào hóa đơn.

### Đối soát chủ động qua SePay API (mặc định chỉ báo cáo)

- Lệnh `php artisan sepay:doi-soat {--gio=24}` gọi **GET** `/v2/transactions`
  (tiền vào, phân trang, tôn trọng `Retry-After` khi 429, timeout/retry cấu hình).
  Mỗi giao dịch được chuẩn hóa về DTO nội bộ; số tài khoản chỉ lưu bản đã che.
- Giao dịch có trên API nhưng chưa có trong `payment_webhooks` được ghi thêm một
  bản ghi `ket_qua = can_doi_soat`, `nha_cung_cap = sepay_api`, nhãn nguồn
  `api_doi_soat` trong `/doisoat`. Không tự đổi hóa đơn, không đổi kho/điểm.
- Chống trùng trong nội bộ: bản ghi API dùng khóa `api:<id>`; webhook và API gộp
  theo cột `ma_tham_chieu` (mã tham chiếu) khi có. Webhook không bao giờ bị bỏ qua
  chỉ vì trùng mã tham chiếu, và không ghi đè bản ghi webhook của giao dịch khác;
  chỉ gộp vào bản ghi báo cáo `sepay_api`/`can_doi_soat` cùng số tiền. `--ap-dung`
  hiện **bị khóa** vì tài liệu SePay chưa chứng minh khóa duy nhất chung giữa API v2
  (`id` UUID, `reference_number`) và webhook (`id` số, `referenceCode`) — xem mục 17
  và `docs/BAO_CAO_SEPAY_DOI_SOAT.md`.
- Kiểm soát nhiễu: mỗi giao dịch thiếu **mới** được ghi vào `/doisoat`, nhưng chỉ
  gửi Telegram khi tìm được hóa đơn (`don_da_huy`, `thieu_tien`,
  `hoadon_da_thanh_toan`, `api_doi_soat`). Giao dịch lạ (`khong_tim_thay_ma_hoadon`,
  `hoadon_khong_ton_tai`) chỉ lưu để admin xem. Mỗi lần chạy gửi tối đa
  `SEPAY_DOI_SOAT_TELEGRAM_TOI_DA` tin chi tiết (mặc định 5); phần vượt gộp thành
  một tin tóm tắt.
- Lịch chạy mỗi 10 phút (`sepay:doi-soat --gio=6`) chỉ khi đã cấu hình
  `SEPAY_API_TOKEN`. Khi mới bật, nên chạy tay `sepay:doi-soat --gio=1` trước để
  xem lượng giao dịch thật, và ưu tiên tài khoản ngân hàng riêng cho cửa hàng.
- Lệnh `php artisan sepay:kiem-tra-ket-noi` chỉ kiểm tra token/kết nối, không in
  token hay dữ liệu giao dịch.

### Quản lý kho

- Xem tồn kho.
- Nhập kho theo sản phẩm.
- Cập nhật số lượng và giá nhập trong transaction.
- Ghi lịch sử biến động loại `nhap`.
- Cảnh báo sản phẩm tồn thấp trên dashboard.

### Ba đường nhập kho

| Đường nhập | Khi nào dùng | Dùng khi demo |
|---|---|---|
| Nhập nhanh | Điều chỉnh đơn giản cho một hoặc vài sản phẩm, không cần chứng từ | Chỉ minh họa nhanh |
| Đơn nhập hàng | Theo dõi đặt hàng: chờ duyệt, giao hàng và nhận hàng | Dùng khi minh họa quy trình đơn nhập |
| Hóa đơn nhập | Có chứng từ, thuế, nhiều dòng hàng và cần đối soát nhà cung cấp | **Dùng cho demo chính** |

### Thống kê

Admin có thể truy cập:

- Doanh thu theo ngày.
- Top 5 sản phẩm bán chạy.
- Doanh thu có thể nhóm theo ngày, tuần hoặc tháng qua API.
- Xuất danh sách hóa đơn và báo cáo xuất nhập tồn ra Excel/PDF.
- Lợi nhuận dùng giá vốn được lưu tại thời điểm bán.

**Dashboard tổng quan (`/tongquan`)** đọc số liệu từ một service chỉ-đọc
`App\Services\TongQuanKhoService` (COUNT/SUM trực tiếp, không cache) và tự làm mới
mỗi **30 giây** khi tab đang mở (tạm dừng khi tab ẩn), hiển thị "Cập nhật lúc
HH:MM:SS" giờ Việt Nam; lỗi mạng giữ số cũ kèm cảnh báo nhỏ. Định nghĩa chỉ số:

- *Mặt hàng đang kinh doanh / còn hàng / hết hàng*: chỉ đếm sản phẩm `trang_thai = true`
  (còn hàng: `soluong > 0`; hết hàng: `soluong = 0`).
- *Tổng tồn tốt / tổng hàng hỏng*: SUM(`soluong`) / SUM(`soluong_hong`) của **mọi**
  sản phẩm kể cả tạm dừng (hàng vẫn trong kho).
- *Giá trị tồn theo giá nhập* = Σ(`soluong` × `gia_nhap`), không gồm hàng hỏng.
- Đơn chuyển khoản `cho_thanh_toan` **đã trừ kho ngay khi tạo QR**; hủy quá hạn hoàn
  kho; webhook hoàn tất không trừ lần nữa (nghiệp vụ giữ nguyên, dashboard ghi chú).
- JSON cùng số liệu: `GET /tong-quan/so-lieu` (chỉ admin, `throttle:30,1`).

## 8. Các URL chính

| URL | Chức năng | Quyền |
|---|---|---|
| `/` | Trang chủ storefront | Công khai |
| `/danh-muc/{loai}`, `/san-pham/{id}`, `/tim-kiem`, `/gio-hang` | Storefront: danh mục, sản phẩm, tìm kiếm, giỏ hàng | Công khai |
| `/dangnhap` | Đăng nhập | Khách |
| `/dang-ky` | Đăng ký tài khoản khách hàng | Khách |
| `/tongquan` | Dashboard | Mọi vai trò nhân viên |
| `/tong-quan/so-lieu` | JSON số liệu dashboard (tự làm mới 30s) | Admin (throttle 30/phút) |
| `/pos` | Màn hình bán hàng | Admin, thu ngân |
| `/sanpham` | Quản lý sản phẩm | Admin, thủ kho |
| `/loaisanpham` | Quản lý loại sản phẩm | Admin, thủ kho |
| `/kho` | Xem tồn kho | Admin, thủ kho |
| `/kho/nhap` | Nhập kho | Admin, thủ kho |
| `/kho/lichsu` | Lịch sử biến động | Admin, thủ kho |
| `/donhang` | Danh sách hóa đơn | Admin, thu ngân |
| `/hoadonnhap` | Danh sách hóa đơn nhập | Admin, thủ kho |
| `/hoadonnhap/them` | Tạo hóa đơn nhập nháp | Admin, thủ kho |
| `/hoadonnhap/boc-tach-pdf` | Bóc tách PDF hóa đơn (JSON nội bộ) | Admin, thủ kho |
| `/hoadonnhap/xem/{id}` | Xem hóa đơn nhập | Admin, thủ kho |
| `/api/webhook/thanhtoan` | Webhook SePay thanh toán | Public (có API Key, throttle 60/phút) |
| `/doisoat` | Hàng chờ đối soát giao dịch chuyển khoản | Admin |
| `/doisoat/{webhook}` | Chi tiết giao dịch cần đối soát | Admin |
| `/doisoat/{webhook}/xuly` | Đánh dấu giao dịch đã xử lý (POST) | Admin |
| `/trahang` | Trả hàng: danh sách, tạo phiếu, duyệt | Admin, thu ngân (duyệt: admin) |
| `/khachhang` | Quản lý khách hàng | Admin, thu ngân |
| `/hold` | Giỏ tạm | Admin, thu ngân |
| `/kiemke` | Kiểm kê kho | Admin, thủ kho (duyệt: admin) |
| `/nhatky` | Nhật ký hoạt động | Admin |
| `/thongke` | Trang thống kê | Admin |
| `/thongke/doanhthu` | Doanh thu JSON | Admin |
| `/thongke/topsanpham` | Top sản phẩm JSON | Admin |
| `/baocao/hoadon/{dinhDang}` | Xuất báo cáo hóa đơn XLSX/PDF | Admin |
| `/baocao/xuatnhapton/{dinhDang}` | Xuất báo cáo xuất nhập tồn | Admin, thủ kho |

## 9. Kiểm thử

Chạy toàn bộ test (tắt Xdebug để giảm thời gian chạy):

```powershell
$env:XDEBUG_MODE='off'
php artisan test --compact
Remove-Item Env:XDEBUG_MODE
```

Các test nghiệp vụ hiện có (số chạy thật cập nhật ở mục 17/18; có thể chạy song
song bằng `vendor/bin/paratest` — ~9 phút thay ~39 phút):

**Unit (66 test)**

- `AnhSanPhamTest` (33): rule URL ảnh (6 hợp lệ gồm CDN không đuôi/đúng 150 ký tự, 13
  lỗi gồm javascript/data/http/userinfo/khoảng trắng/mảng/quá 150), accessor `anh_url`
  (https/tệp tải lên/legacy/rỗng), xóa tệp an toàn (chỉ thư mục ảnh, chặn
  traversal/backslash/URL, legacy `upload/` với tệp thật tạm).
- `ChanLenhPhaHuyTest` (5): chốt chặn production cho lệnh phá hủy dữ liệu.
- `SoDienThoaiHopLeTest` (16): chuẩn hóa SĐT (7 biến thể: `+84`/`84`, khoảng trắng,
  gạch, chấm, ngoặc), giữ nguyên giá trị không phải chuỗi, rule chấp nhận/từ chối
  đúng thông báo tiếng Việt.
- `SoDienThoaiTest` (6): chuẩn hóa SĐT (`SoDienThoai::chuanHoa`) — giữ dạng 0,
  `+84`/`84` đầu → 0, bỏ khoảng trắng/chấm/gạch/ngoặc, null → rỗng, chuỗi ngắn
  không phải 84 giữ nguyên.
- `VietQrServiceTest` (5, t61..t65): URL VietQR, chưa cấu hình, số tiền âm, làm
  sạch nội dung, template dự phòng.
- `ExampleTest` Unit (1).

**Feature (652 test, trong đó 44 skip)**

Ghi chú: `LoiBTaoQrTest` (6), `LoiBThieuCotOnlineTest` (4), `LoiADungDiemTest`
(10) và `LoiDDashboardTonKhoTest` (1) đã cherry-pick vào `main` trong đợt
Vận hành 2 (2026-11-16) — xem `docs/BAO_CAO_VAN_HANH_2.md`. `LoiCSoDienThoaiKhachTest`
vẫn chỉ nằm ở nhánh `fix/loi-van-hanh-1` (nội dung SĐT đã được Stage 1/3-4 xử lý
tương đương).

- `AnhUrlMysqlCopyTest` (37, **skip trên SQLite**): sweep 35 URL lạ qua thêm+sửa trên
  bản sao MySQL strict mode (không `RefreshDatabase` để giữ cột VARCHAR(150) thật,
  tự dọn bản ghi), biên 150/151 ký tự, chuỗi multibyte 150 ký tự legacy vẫn hiển thị.
  Chỉ chạy khi trỏ vào bản sao `*_copy` (xem bên dưới).
- `AnhUrlSweepTest` (83): 35 chuỗi lạ × POST thêm + POST sửa (admin) — hợp đồng
  "không bao giờ 500": 302 thành công hoặc 302 kèm lỗi validate `hinh_anh_url` tiếng
  Việt; 2 ca gửi cả tệp lẫn URL hỏng → tệp thắng; 11 ca hiển thị URL bẩn/legacy
  (aclk 255, nháy, `<>`, Unicode, null byte, `upload/`, `storage/`...) trên
  admin/storefront/giỏ/POS vẫn 200. Bật `Http::preventStrayRequests()`.
- `AuthFlowTest` (7): đăng ký khách hàng, liên kết khách walk-in, chặn trùng số
  điện thoại, chuyển hướng theo vai trò, chặn khách hàng vào trang nhân viên.
- `BaoMatDangNhapTest` (6): throttle 10 lần/phút, cảnh báo mật khẩu mặc định trên
  trang đăng nhập/dashboard, chỉ admin thấy danh sách tài khoản chưa đổi mật khẩu.
- `DatHangP0MysqlCopyTest` (1, **skip trên SQLite**): T-DH02 — migration M1 up/down trên
  bản sao MySQL strict (enum/FK giữ nguyên, dữ liệu POS nguyên vẹn, down chặn khi còn
  đơn user_id NULL); tự dọn, chạy lại được. Chỉ chạy khi trỏ vào bản sao `*_copy`.
- `DatHangP0Test` (9): T-DH01 — cột mới đúng kiểu/nullable/index, user_id nhận null,
  kenh mặc định pos, ma_tra_cuu unique (nhiều NULL), enum giữ nguyên, down có điều kiện,
  config online, fallback "Đơn online" ở view in/danh sách/export.
- `DatHangP1BaoVeTest` (10): P1 bảo vệ — lịch sử kho null-safe với movement `user_id`
  null ("Đơn online"); nhật ký đặt hàng che SĐT; `ghi_chu_khach` escape khi render;
  Telegram đơn online escape ghi chú + che SĐT; thu ngân xác nhận pickup dùng lại R2;
  thu ngân hủy pickup dùng lại R3 hoàn kho; gia hạn cộng giờ trong trần config + ghi
  nhật ký; gia hạn vượt trần bị từ chối; gia hạn chỉ cho đơn pickup online đang chờ;
  config có `online_gia_han_toi_da_gio`.
- `DatHangP1ChongOmHangTest` (12): D12 chống ôm hàng checkout pickup — honeypot
  `truong_phu` (tên trung tính, P2 đổi từ `trang_web`) bị điền → từ chối thông báo chung,
  không tạo đơn/không trừ kho; form hiện ô honeypot ẩn (`aria-hidden`); vượt 3 đơn mở cùng
  SĐT chuẩn hóa bị chặn (kiểm tra trong transaction sau khóa; đơn `da_huy`/SĐT khác không
  tính chéo); vượt `online_max_sanpham` (10) sản phẩm khác nhau hoặc số lượng dòng ngoài
  1..`online_max_soluong` bị từ chối; throttle 5/phút/IP → 429.
- `DatHangP1ChuanHoaSdtTest` (3): giới hạn 3 đơn mở tính trên SĐT chuẩn hóa — đơn
  cũ lưu `+84` hoặc `090.123 4567` vẫn tính cùng `0901234567`; `+84` của SĐT khác
  không tính chéo.
- `DatHangP1MysqlCopyTest` (2, **skip trên SQLite**): T-DH02b — M2 up/down trên bản sao
  MySQL (`user_id` nullable, enum/FK giữ nguyên, down chặn khi còn movement `user_id`
  NULL); T-DH13 — khóa dòng sản phẩm THẬT bằng hai kết nối MySQL chặn tạo đơn online
  đồng thời (`innodb_lock_wait_timeout=1`, bên tạo đơn chờ rồi timeout thay vì cùng
  vượt trần). Chỉ chạy khi trỏ vào bản sao `*_copy`.
- `DatHangP1Test` (10): T-DH03..07 — checkout guest pickup tạo đơn giữ kho + movement
  `user_id` null; giá tính lại từ DB không tin giá session; vượt tồn không tạo đơn;
  double-submit cùng token chỉ một đơn; token lệch bị từ chối; validate thiếu tên/SĐT
  sai/ghi chú quá dài; SP ẩn/hết hàng/xóa trong giỏ bị từ chối toàn bộ; trần pickup áp
  dụng trong đường tạo đơn; nhiều sản phẩm trừ kho đúng từng dòng; M2 `user_id`
  nullable trên SQLite.
- `DatHangP1TraCuuTest` (7): T-DH08/12 — tra cứu sai mã + sai SĐT cùng thông báo chung;
  tra cứu đúng redirect không lộ SĐT trên URL; polling chỉ trả trạng thái không PII;
  polling/trang thái yêu cầu bằng chứng session; khách tự hủy đơn chờ hoàn kho; hủy
  đơn đã thanh toán bị từ chối; polling hủy lười khi quá hạn giữ.
- `DatHangP2FormCheckoutTest` (10): P2 Form + Checkout — form hiển thị lựa chọn hình
  thức nhận; giao_tan_noi thiếu người nhận/SĐT/địa chỉ bị từ chối; giao_tan_noi trả
  tại quầy bị từ chối (phải QR); tính phí giao hàng đúng theo config; miễn phí khi
  đạt ngưỡng (tính từ tổng giá DB, không tin giá session); request cố sửa phí/tổng/trạng
  thái bị bỏ qua; ngoài vùng giao bị từ chối; tai_cua_hang không đổi hành vi P1.
- `DatHangP2GiaoTanNoiMoRongTest` (23): P2 bước C — honeypot trung tính `truong_phu`
  (không `email`/`trang_web`), 4 ô địa chỉ ghép một cột ≤255, thiếu một phần / quá 255 /
  HTML escape bị từ chối, vùng giao khớp tỉnh (trong/ngoài/rỗng), phí + tổng tính từ giá
  DB (session lệch vẫn đúng), request sửa phí/tổng/trạng thái bị bỏ qua, D12 chạy cả luồng
  giao (3 đơn mở/SĐT chuẩn hóa, 11 SP, throttle 429), khách đăng nhập prefill không ghi
  ngược hồ sơ (D8) + gắn `customer_id` khi có hồ sơ, D17 QR online không hủy ở 16 phút /
  hủy + hoàn kho ở 31 phút, redirect pickup→thanhcong / QR→qr.
- `DatHangP2MigrationTest` (7): T-DH-M3 — migration M3 trên SQLite (cột mới đúng
  kiểu/nullable/index, tạo đơn giao_tan_noi, tạo đơn tai_cua_hang mặc định, down chặn
  khi còn đơn giao_tan_noi, down sạch khi không còn, config đúng mặc định).
- `DatHangP2MysqlCopyTest` (1, **skip trên SQLite**): T-DH-M3 — migration M3 up/down
  trên bản sao MySQL (kiểm tra cột, enum/FK giữ nguyên, dữ liệu POS nguyên vẹn, down
  chặn khi còn đơn giao_tan_noi); tự dọn, chạy lại được. Chỉ chạy khi trỏ vào bản sao
  `*_copy`.
- `DatHangP2QrOnlineTest` (6): P2 bước 2 — trang QR online: số tiền QR = `tong_thanh_toan`
  gồm ship + nội dung = mã hóa đơn; đếm ngược theo `han_giu`; thiếu cấu hình ngân hàng →
  thân thiện, không 500, không lộ số tài khoản; quá hạn → hủy + hoàn kho + movement
  `dieuchinh` + thông báo rõ ràng trên thanhcong; polling POST không PII + cần bằng chứng
  session (chưa xác thực → 403); pickup thanhcong giữ nguyên "Đặt hàng thành công".
- `DatHangCoBatTest` (8): cờ `POS_DAT_HANG_ONLINE_BAT`/`POS_GIAO_TAN_NOI_BAT` — tắt →
  404 cả 9 route đặt hàng online (không 500), giỏ hàng vẫn xem được nhưng không lộ
  route đặt hàng — thay nút "Đặt hàng" bằng thông báo "Đặt hàng online chưa mở…
  gọi {hotline}" (hotline từ `config/storefront.php`, env `HOTLINE`), radio
  `giao_tan_noi` bị ẩn, server từ chối `giao_tan_noi`, `he-thong:kiem-tra`
  báo trạng thái cờ; giỏ trống không hiện thông báo. Test mặc định bật cờ trong
  `tests/TestCase.php`.
- `DatHangP2Buoc3Test` (12): P2 bước 3 — webhook đơn giao tận nơi đặt
  `trang_thai_giao` theo `POS_ONLINE_TU_DONG_XAC_NHAN` (cho_xac_nhan/da_xac_nhan),
  idempotent không ghi đè trạng thái đã có, thiếu tiền → đối soát, thừa tiền →
  hoàn tất như cũ, pickup/POS không đổi, DB thiếu cột vẫn hoàn tất, đơn hủy không
  đổi, xác nhận thủ công cũng đặt trạng thái, Telegram escape + che SĐT + không
  địa chỉ, trang trạng thái chỉ lộ địa chỉ cho người đã xác thực, polling trả
  `trang_thai_giao` không PII.
- `DoiSoatTest` (8): hàng chờ đối soát, phân quyền admin, đánh dấu đã xử lý, chặn
  xử lý lặp, webhook thành công không vào hàng chờ — assert đúng action
  `doi_soat_danh_dau` (không đếm toàn bảng log vì model event đã ghi thêm).
- `DonHangQuyenChuSoHuuTest` (6): quyền xem/trạng thái/poll/hủy đơn online — chủ
  đơn đã login không cần bằng chứng session, khách B bị chặn kể cả khi đơn ghi
  đúng SĐT của B, guest cần bằng chứng, không hủy đơn đã thanh toán/đã hủy,
  hoàn kho đúng một lần.
- `ExampleTest` Feature (1).
- `GiuHangOnlineServiceTest` (8): trần giữ hàng pickup — tồn 10→trần 5, trần không co
  lại khi đã giữ, vượt trần từ chối kèm số còn lại, tồn 1→trần 1, không đếm
  QR/POS/đã trả/đã hủy, nhiều sản phẩm độc lập, đổi config %, số lượng 0/âm từ chối.
- `HuyDonPickupQuaHanTest` (13): T1 — `DonHang::scopeQuaHanCho` là nguồn quy tắc quá
  hạn duy nhất: pickup còn hạn (16 phút, 3h59) không bị hủy; quá hạn 4h01 bị hủy +
  hoàn kho + movement + nhật ký; đơn QR/POS cũ vẫn hủy ở 16 phút; pickup `han_giu`
  null hủy theo `created_at`; đơn gia hạn không bị hủy ở hạn cũ; hủy thủ công/khách
  tự hủy vẫn hoạt động; chạy lệnh 2 lần idempotent; webhook đơn QR quá hạn như cũ
  (T-CK18); POS poll không hủy nhầm pickup còn hạn + hủy khi quá `han_giu`;
  health-check không báo quá hạn pickup còn hạn.
- `KhachHangLichSuDonTest` (6): lịch sử đơn khách — đơn POS không `ma_tra_cuu`
  vẫn hiển thị, chi tiết đơn check chủ sở hữu (khách khác/chưa hồ sơ → 403),
  chủ đơn login xem trạng thái online không cần bằng chứng.
- `KhachHangSoDienThoaiTest` (33): chuẩn hóa + validate SĐT ở mọi điểm nhập khách
  hàng (thêm/sửa form, POS thêm nhanh, tìm khách POS, đăng ký storefront): 6 biến
  thể hợp lệ, 9 ca từ chối (chữ, sai độ dài, đầu số cố định, mảng), trùng thô và
  trùng sau chuẩn hóa, sửa bỏ qua self, `old()` + lỗi tiếng Việt, escape LIKE,
  phân quyền (thukho 403, thungan được, khachhang chuyển hướng), xóa khách có hóa
  đơn không 500.
- `KhoaDongMysqlTest` (1, **skip trên SQLite**): khóa dòng `SELECT ... FOR UPDATE`
  thật trên MySQL/InnoDB — chỉ chạy khi trỏ vào bản sao MySQL (xem bên dưới).
- `KiemTraHeThongTest` (16): từng mục của `he-thong:kiem-tra`, gồm scheduler,
  cấu hình thiếu/đủ, log, engine, migration, mã thoát; thêm `SEPAY_API_TOKEN`
  đủ/thiếu (WARN) và tuổi lần đối soát gần nhất.
- `LenhNguyHiemTest` (19 ca): chốt chặn `migrate:fresh`, `db:wipe`, `demo:reset`,
  `documents:import`; thiếu cờ/tên, sai DB, kết nối đích, xác nhận tương tác,
  chuyển tiếp lệnh con và không tái sử dụng xác nhận. Dùng mock DB/schema/migrator,
  không xóa bảng hay chạy migration thật.
- `LienKetHoSoTheoSDTTest` (4): đăng ký trùng SĐT hồ sơ chưa liên kết KHÔNG tự
  gán `user_id` (chống chiếm hồ sơ), không xem được đơn/điểm/địa chỉ/SĐT, không
  poll-hủy đơn chờ; nhân viên gán thủ công qua màn sửa khách hàng thì mới thấy;
  chặn gán tài khoản đã có hồ sơ và tài khoản role nhân viên.
- `LogBiMatTest` (5): log daily giữ 14 ngày, che bot token Telegram khi lỗi.
- `LoiBTaoQrTest` (6, cherry-pick `0e8be80`): LỖI B — đủ cấu hình tạo QR thành công;
  thiếu `bank_code`/`bank_account` → 422 thân thiện, không tạo đơn rác/không trừ kho;
  POS tiền mặt + đơn chờ `$online` rỗng không ghi cột online; log không lộ số tài khoản đầy đủ.
- `LoiBThieuCotOnlineTest` (4, `b7e5ed3`): bỏ cột M1/M3 trong SQLite — scheduler rơi về
  hạn 15 phút (hủy đơn POS quá hạn + hoàn kho + movement, không hủy trước hạn), POS tạo
  QR và POS tiền mặt vẫn chạy. TODO gỡ guard + test sau khi M1–M3 áp lên DB thật.
- `NhatKyThieuCotTest` (3): DB thiếu cột/bảng `activity_logs` — POS, đăng nhập,
  scheduler vẫn chạy; nhật ký ghi mức tối thiểu qua guard `KiemTraSchema`.
- `NhatKyToanBoModelTest` (3): trait `GhiNhatKy` ghi tạo/sửa/xóa cho sản phẩm,
  hóa đơn, khách hàng, danh mục, nhà cung cấp...
- `NguoiDungCommandTest` (9): `nguoidung:tao`/`nguoidung:doi-matkhau`, mật khẩu nhập
  ẩn, từ chối vai trò/email/mật khẩu không hợp lệ.
- `NhapKhoCanhBaoTest` (3): cảnh báo lệch giá nhập >10% và giá bán thấp hơn giá
  nhập mới.
- `PdfInvoiceParserTest` (3): bóc tách PDF mẫu, phân quyền, PDF lỗi.
- `PosBaoVeTest` (5): webhook fail-closed khi thiếu API key, chặn giảm giá ngoài
  0–100, giới hạn 10 kết quả tìm sản phẩm.
- `PosWorkflowTest` (32): thanh toán tiền mặt/chuyển khoản, vượt tồn kho, sản phẩm
  tạm dừng, lọc hóa đơn theo ngày, upload ảnh, tìm kiếm, tem barcode, tích điểm,
  xuất báo cáo, đơn nhập/hóa đơn nhập, phân quyền, nhật ký, trả hàng (quá hạn, giảm
  giá + điểm, floor cộng dồn, tiền hoàn 0, chờ duyệt, dòng trùng), hold giỏ, kiểm kê.
- `RefreshDatabaseGuardTest` (3): chốt chặn `RefreshDatabase` trong `tests/TestCase.php`
  — mô phỏng cấu hình MySQL (port 9, DB giả, **không kết nối thật**) phải bị ném
  ngoại lệ tiếng Việt ngay trong setUp trước khi kịp migrate; sqlite `:memory:` đi
  qua; sqlite file cũng bị chặn.
- `DatabaseMigrationsGuardTest` (1) + `DatabaseTruncationGuardTest` (1): chốt chặn T3
  mở rộng cho `DatabaseMigrations` và `DatabaseTruncation` (cùng cơ chế `setUpTraits`,
  cùng thông báo "RefreshDatabase bị chặn"); mỗi lớp một tệp để PHPUnit 12 nạp đúng.
- `ConfigP2BooleanTest` (3): `POS_ONLINE_TU_DONG_XAC_NHAN` mặc định false, 'true'→true,
  'false'→false (env() chuẩn hóa chuỗi thành bool; giữ `=== true`, không filter_var).
- `RouteKhoiTest` (4): test khói toàn bộ route GET cho khách, thu ngân, thủ kho,
  admin với dữ liệu ≥3 bản ghi mỗi mô hình có quan hệ (loại + sản phẩm, khách,
  hóa đơn + chi tiết, nhà cung cấp, đơn nhập, hóa đơn nhập, phiếu trả, biến động
  kho, kiểm kê, giỏ tạm, nhật ký, webhook đối soát). Mỗi route chỉ được 200/302
  hoặc 403 đúng vai trò, tuyệt đối không 500; gồm cả route `{id}` với id thật
  (chi tiết sản phẩm, in hóa đơn, phiếu trả, hóa đơn nhập, kiểm kê, đối soát).
- `SaoLuuCsdlTest` (4): sao lưu SQLite, giữ N bản mới nhất, từ chối `:memory:`,
  báo lỗi rõ khi thiếu `mysqldump`.
- `SanPhamAnhUrlTest` (26): thêm sản phẩm bằng URL https, 9 ca từ chối URL lỗi,
  tải tệp vẫn hoạt động, gửi cả hai → tệp ưu tiên + thông báo, đổi tệp↔URL xóa tệp
  cũ an toàn, sửa không gửi trường ảnh giữ nguyên, nút xóa ảnh, render escape trên
  6 trang (admin/storefront/giỏ), dữ liệu bẩn vẫn escape, POS JSON `anh_url`, phân
  quyền admin/thủ kho/thu ngân/khách hàng, xóa sản phẩm ảnh URL không 500 + chặn
  xóa khi có hóa đơn/kiểm kê. Bật `Http::preventStrayRequests()` (server không gọi
  URL người dùng).
- `SepayApiClientTest` (8): chuẩn hóa DTO tiền vào, che số tài khoản, 401, 429
  đọc `Retry-After` rồi thành công, phân trang, 5xx, lỗi kết nối, từ chối v1;
  toàn bộ dùng `Http::fake()` + `preventStrayRequests()`, token không lộ.
- `SepayDoiSoatTest` (12): báo cáo-only không đổi hóa đơn/kho/điểm; chạy lại
  không trùng và Telegram một lần; webhook trước/API sau và API trước/webhook sau
  gộp đúng một bản ghi; sai mã/không tồn tại/đã hủy/đã thanh toán/thiếu tiền chỉ
  báo; giao dịch không tìm thấy hóa đơn **không** gửi Telegram; giới hạn tin chi
  tiết + một tin tóm tắt phần vượt; `--ap-dung` bị từ chối trước khi gọi mạng;
  thiếu token; 401 không lộ token; lịch đã đăng ký; `/doisoat` gắn nhãn nguồn và
  chỉ admin.
- `SepayKiemTraKetNoiTest` (4): 200 in OK và dùng Bearer, 401 báo rõ, lỗi kết nối
  có token trong message vẫn bị che, thiếu token không gọi mạng; assert cả output
  lẫn log Monolog không chứa token.
- `StorefrontHomeTest` (6): trang chủ storefront, sản phẩm thật, sắp xếp loại theo
  số sản phẩm, trạng thái rỗng, header theo vai trò.
- `StorefrontProductTest` (5): chi tiết sản phẩm/danh mục trả 200 và hiển thị tên,
  id không tồn tại trả 404 trang lỗi tiếng Việt, link danh mục trên trang chủ trỏ
  đúng route; tái hiện lỗi lazy-load `loaiSanPham` ở danh sách sản phẩm liên quan.
- `TacVuNenTest` (2): `hold:clean` chỉ xóa giỏ quá 24h, Telegram thành công.
- `TaiKhoanGuardTest` (6): guard `/taikhoan` dùng `KiemTraSchema` (sửa lỗi 302 do
  `array_key_exists` trên model rỗng), admin factory không refresh vào được,
  tài khoản khóa bị đăng xuất, cờ đổi MK redirect đúng một lần, thứ tự middleware
  khóa trước cờ đổi MK, DB thiếu cột Stage 3 vẫn đăng nhập/POS/scheduler được.
- `ThanhToanChuyenKhoanTest` (24: T-CK01..T-CK18 + T-CK03b..T-CK03d + 1 test route
  duy nhất + 2 test CSRF thật): tạo QR, webhook hợp lệ/sai key/trùng lặp/dư/thiếu/
  out, lưu mã tham chiếu khi webhook thành công (T-CK03b), hai giao dịch khác id
  cùng mã tham chiếu đều hoàn tất và giữ bản ghi riêng (T-CK03c), mã tham chiếu
  rỗng/null/chỉ khoảng trắng không kích hoạt chống trùng (T-CK03d), xác nhận/hủy
  thủ công, polling, hủy lười, lệnh hủy quá hạn, in/doanh thu, webhook tới đơn đã
  hủy (T-CK17), webhook tới đơn quá hạn chưa hủy (T-CK18), route POST đúng
  action/name/throttle. Hai test mới bật CSRF thật (override `runningUnitTests()`
  → false): ngoại lệ đúng path `api/webhook/thanhtoan` (path khác vẫn 419) và
  webhook chạy không session/cookie khi CSRF đang bật (401/400/200, đăng nhập
  không token vẫn 419).
- `ThongBaoLoiTest` (6): trang lỗi 404/403/419/500 tiếng Việt, 500 không lộ chi
  tiết, exception thật render trang 500 khi `app.debug=false`, POS hiển thị hướng
  dẫn khi phiên hết hạn và giữ giỏ hàng.
- `ToanVenGiaoDichTest` (6): tính nguyên tử của hủy đơn, nhập kho và thanh toán.
- `TongQuanSoLieuTest` (18): dashboard đọc đúng sau mọi nghiệp vụ — bán tiền mặt,
  tạo QR trừ kho ngay, webhook không trừ lần nữa, hủy quá hạn hoàn kho, trả hàng
  tốt/hỏng, nhập kho trực tiếp, xác nhận hóa đơn nhập, kiểm kê duyệt; sản phẩm
  ẩn/xóa/hết hàng đếm đúng; endpoint `tongquan.solieu` chỉ admin (403/302 đúng vai
  trò, throttle 30/phút); số trên trang khớp endpoint; giá trị tồn theo giá nhập.
- `TraHangTrungMaPhieuTest` (2): hai phiếu trả tạo trong cùng một giây với bộ sinh
  số ngẫu nhiên bị khóa trùng giá trị vẫn thành công, mã khác nhau, đúng định dạng
  `TH+YmdHis+2 số`, kho hoàn đủ 2 lần; hết 90 hậu tố của giây thì báo lỗi có kiểm
  soát, không tạo phiếu. Khóa RNG bằng hàm cùng namespace
  (`tests/Support/bo-sinh-so-ngau-nhien.php`), không sửa code production để test.
- `TraHangPhiGiaoTest` (10) + `TraHangPhiGiaoMysqlCopyTest` (1, skip trên SQLite):
  quy tắc hoàn phí giao — tiền hoàn chỉ trên tiền hàng sau giảm giá (không gồm
  ship); chỉ admin tick "Hoàn cả phí giao" (bắt buộc lý do, một lần/đơn, 403 vai
  trò khác); tổng hoàn tích lũy ≤ tổng đã thanh toán; điểm thu hồi không tính
  ship; báo cáo có dòng riêng `hoan_phi_giao_hang`; đơn không ship/thiếu cột giữ
  hành vi cũ. `TraHangDonOnlineTest` đổi 2 kỳ vọng (130.000→100.000, 65.000→50.000)
  theo quy tắc mới — chi tiết `docs/BAO_CAO_HOAN_TIEN_SHIP.md`.

Lưu ý khi chạy test:

- Test dùng SQLite `:memory:` qua `RefreshDatabase` (`phpunit.xml`), không đụng
  database MySQL thật. **Chốt chặn T3 (mở rộng P2):** `tests/TestCase.php::setUpTraits`
  ném ngoại lệ tiếng Việt nếu một test dùng `RefreshDatabase`, `DatabaseMigrations`
  hoặc `DatabaseTruncation` chạy trên kết nối không phải sqlite `:memory:` (MySQL
  thật/bản sao, sqlite file) — chặn trước khi `migrate:fresh`/truncate kịp xóa gì.
  Các test MySQL-copy không dùng ba trait này nên không bị ảnh hưởng; bằng chứng:
  `RefreshDatabaseGuardTest`, `DatabaseMigrationsGuardTest`, `DatabaseTruncationGuardTest`.
- Phải build asset trước (`npm ci` và `npm run build`) để có
  `public/build/manifest.json`; nếu không, các test render Blade dùng `@vite`
  sẽ lỗi 500.
- `KhoaDongMysqlTest` và các lớp `*MysqlCopyTest` tự skip khi không phải MySQL
  bản sao `*_copy`. Muốn chạy, trỏ vào **bản sao** MySQL (không bao giờ là
  `doan_cnw`) bằng biến môi trường tiến trình, **filter chính xác** (xem cảnh báo
  bên dưới và mục 17):

  ```powershell
  $env:DB_CONNECTION='mysql'; $env:DB_DATABASE='doan_cnw_copy'
  $env:DB_USERNAME='root'; $env:DB_PASSWORD=''
  php artisan test --filter='MysqlCopyTest|KhoaDongMysqlTest'
  Remove-Item Env:DB_CONNECTION, Env:DB_DATABASE, Env:DB_USERNAME, Env:DB_PASSWORD
  ```

  Cả 4 lớp này **không** `RefreshDatabase` — giữ schema/dữ liệu bản sao, tự áp
  migration rồi dọn bản ghi nó tạo. Riêng `AnhUrlMysqlCopyTest` cần cột
  `hinh_anh` VARCHAR(150) thật của bản sao để tái hiện lỗi 1406. **Không** chạy
  full suite với env MySQL: các test `RefreshDatabase` khác sẽ bị chốt chặn T3
  ném ngoại lệ (đúng thiết kế — bảo vệ bản sao).

- Chưa có test cho: hành vi khi hai webhook đến đồng thời trên môi trường thật
  (đã có test khóa dòng tuần tự trên bản sao MySQL), luồng trình duyệt thật của
  POS (các test hiện gọi HTTP nội bộ), và **JS kiểm tra ảnh URL phía trình duyệt**
  trong form sản phẩm (đã kiểm chứng thủ công bằng trình duyệt trên bản sao MySQL;
  checklist ở [docs/BAO_CAO_ANH_URL_VALIDATE.md](docs/BAO_CAO_ANH_URL_VALIDATE.md)).

Kiểm tra cú pháp PHP:

```powershell
Get-ChildItem -Recurse -Filter *.php |
    Where-Object { $_.FullName -notmatch '\\vendor\\|\\storage\\' } |
    ForEach-Object { php -l $_.FullName }
```

## 10. Reset dữ liệu demo

Lệnh reset sẽ xóa toàn bộ bảng, chạy migration, nạp lại dữ liệu mẫu và xóa cache:

```powershell
php artisan demo:reset
```

`demo:reset` tự gọi `migrate:fresh --seed --seeder=DatabaseSeeder`, vì vậy
luôn tự nạp lại tài khoản demo và bộ dữ liệu 12 loại hàng/300 sản phẩm sau khi
reset thành công.

Bỏ qua bước xác nhận:

```powershell
php artisan demo:reset --force
```

Trên Windows có thể chạy bằng cách nhấp đúp:

```text
reset_demo.bat
```

Không sử dụng lệnh reset trên môi trường production.

### Chốt chặn production cho lệnh phá hủy dữ liệu

`demo:reset`, `documents:import`, `migrate:fresh` và `db:wipe` bị chặn khi
`APP_ENV=production`. Cờ `--force` đơn lẻ không vượt được chốt chặn.
Muốn chạy thật sự phải có **đủ hai** điều kiện:

1. Thêm cờ `--force-dangerous`.
2. Nhập lại đúng tên database đích — tương tác qua câu hỏi, hoặc thêm
   `--ten-db-xac-nhan=<tên database>` khi chạy không tương tác. Nếu có
   `--database=<tên-kết-nối>`, xác nhận tên database của kết nối đó, không phải
   tên database mặc định. `migrate:fresh` chuyển tiếp xác nhận sang `db:wipe`.

Thiếu một điều kiện, lệnh dừng trước khi xóa bất cứ thứ gì và trả mã lỗi `1`. Ví dụ
đầy đủ:

```powershell
php artisan demo:reset --force-dangerous --ten-db-xac-nhan=doan_cnw
```

## 11. Cấu trúc thư mục chính

```text
app/
├── Console/Commands/
│   ├── HuyHoaDonQuaHan.php
│   ├── KiemTraDuLieu.php
│   ├── NhapDuLieuTaiLieu.php
│   ├── ResetDemoData.php
│   ├── ScrapeProductImagesCommand.php
│   ├── SyncTaiLieuDataCommand.php
│   └── XoaGioTamHetHan.php
├── Http/Controllers/
│   ├── AuthController.php
│   ├── BaoCaoController.php
│   ├── DonHangController.php
│   ├── GioHangController.php
│   ├── HoaDonNhapController.php
│   ├── HoldController.php
│   ├── KhachHangController.php
│   ├── KhoController.php
│   ├── KiemKeController.php
│   ├── LoaiSanPhamController.php
│   ├── NhatKyController.php
│   ├── NhapHangController.php
│   ├── SanPhamController.php
│   ├── StorefrontCartController.php
│   ├── StorefrontController.php
│   ├── ThongKeController.php
│   ├── TraHangController.php
│   └── WebhookController.php
├── Http/Middleware/CheckRole.php
├── Models/
│   ├── BienDongKho.php
│   ├── ChiTietDonHang.php
│   ├── ChiTietDonNhap.php
│   ├── ChiTietHoaDonNhap.php
│   ├── ChiTietKiemKe.php
│   ├── ChiTietPhieuTra.php
│   ├── DonHang.php
│   ├── DonNhap.php
│   ├── GioTam.php
│   ├── HoaDonNhap.php
│   ├── KhachHang.php
│   ├── LoaiSanPham.php
│   ├── NhaCungCap.php
│   ├── NhatKyHoatDong.php
│   ├── PaymentWebhook.php
│   ├── PhieuKiemKe.php
│   ├── PhieuTra.php
│   ├── SanPham.php
│   └── User.php
├── Rules/
│   └── SoDienThoaiHopLe.php
├── Services/
│   ├── HoaDonNhapService.php
│   ├── NhatKyService.php
│   ├── PdfInvoiceParserService.php
│   ├── ThanhToanChuyenKhoanService.php
│   ├── ThongBaoService.php
│   ├── VietQrService.php
│   └── Payment/
│       └── SepayWebhook.php
database/
├── migrations/
│   ├── 0001_01_01_000000_create_users_table.php
│   ├── 0001_01_01_000001_create_cache_table.php
│   ├── 0001_01_01_000002_create_jobs_table.php
│   ├── 2026_01_01_000000_add_role_to_users_table.php
│   ├── 2026_01_01_000001_create_categories_table.php
│   ├── 2026_01_01_000002_create_products_table.php
│   ├── 2026_01_01_000003_create_invoices_table.php
│   ├── 2026_01_01_000004_create_invoice_details_table.php
│   ├── 2026_01_01_000005_create_stock_movements_table.php
│   ├── 2026_01_01_000006_create_customers_table.php
│   ├── 2026_01_01_000007_add_customer_loyalty_to_invoices_table.php
│   ├── 2026_01_01_000008_add_cost_to_invoice_details_table.php
│   ├── 2026_01_01_000009_create_suppliers_table.php
│   ├── 2026_01_01_000010_create_purchase_orders_table.php
│   ├── 2026_01_01_000011_create_purchase_order_items_table.php
│   ├── 2026_01_01_000012_add_purchase_order_to_stock_movements.php
│   ├── 2026_01_01_000013_create_activity_logs_table.php
│   ├── 2026_01_01_000014_create_returns_tables.php
│   ├── 2026_01_01_000015_create_held_carts_table.php
│   ├── 2026_01_01_000016_create_stocktakes_tables.php
│   ├── 2026_01_01_000017_add_import_invoice_fields.php
│   ├── 2026_01_01_000018_create_purchase_invoices_tables.php
│   ├── 2026_01_01_000019_add_purchase_invoice_to_stock_movements.php
│   ├── 2026_11_02_033147_add_ma_giao_dich_to_invoices_table.php
│   ├── 2026_11_02_033152_create_payment_webhooks_table.php
│   ├── 2026_11_02_055338_add_ghi_chu_to_invoices_table.php
│   ├── 2026_11_09_000001_add_khachhang_role_to_users_table.php
│   └── 2026_11_09_000002_add_user_id_to_customers_table.php
└── seeders/DatabaseSeeder.php

resources/views/
├── auth/
├── baocao/
├── donhang/
├── errors/
├── hoadonnhap/
├── hold/
├── kho/
├── khachhang/
├── kiemke/
├── layouts/
├── loaisanpham/
├── nhaphang/
├── nhatky/
├── pos/
├── sanpham/
├── storefront/
├── thongke/
└── trahang/
```

## 12. Cơ sở dữ liệu

Các bảng nghiệp vụ:

- `users`: tài khoản và vai trò (`admin`, `thungan`, `thukho`, `khachhang`).
- `categories`: loại sản phẩm.
- `products`: sản phẩm và tồn kho (có `soluong_hong`).
- `customers`: khách hàng, điểm tích lũy, `user_id` liên kết tài khoản (unique, nullable).
- `invoices`: hóa đơn (có `ma_giaodich` và `ma_giao_dich` — cả hai unique, `ghi_chu`).
- `invoice_details`: chi tiết hóa đơn.
- `stock_movements`: lịch sử nhập/bán/điều chỉnh kho.
- `purchase_invoices`: hóa đơn nhập, thuế, tổng tiền và trạng thái.
- `purchase_invoice_items`: các dòng hàng của hóa đơn nhập.
- `purchase_orders`, `purchase_order_items`: đơn nhập hàng theo quy trình giao
  nhận.
- `returns`, `return_items`: trả hàng; `held_carts`: giỏ tạm.
- `stocktakes`, `stocktake_items`: kiểm kê và chênh lệch tồn.
- `activity_logs`: nhật ký hoạt động.
- `payment_webhooks`: log webhook thanh toán (SePay).

**Engine (MySQL/MariaDB):** mọi bảng phải dùng **InnoDB**. MyISAM không hỗ trợ
transaction và khóa dòng, khiến các luồng tiền/tồn kho mất tính nguyên tử.
Migration `2026_11_10_000003_chuyen_bang_mysql_sang_innodb` chuyển toàn bộ bảng
sang InnoDB (no-op trên SQLite), `config/database.php` đặt engine mặc định cho bảng
mới, và `php artisan he-thong:kiem-tra` báo `FAIL` nếu còn bảng khác InnoDB.

Quy ước biến động kho (`stock_movements.loai`):

- `nhap`: số lượng dương, `invoice_id` là `NULL`.
- `ban`: số lượng âm, có `invoice_id`.
- `trahang`: trả hàng tốt, số lượng dương, có `return_id`.
- `trahang_hong`: trả hàng lỗi, số lượng dương trong movement nhưng chỉ tăng
  `products.soluong_hong`, không cộng vào tồn hàng tốt.
- `dieuchinh`: dùng cho điều chỉnh thủ công, `invoice_id` là `NULL`.
- Movement nhập từ hóa đơn liên kết bằng `purchase_invoice_id`; movement nhập
  từ đơn nhập cũ có thể liên kết bằng `purchase_order_id`.

Truy vấn đối soát tồn hàng tốt:

```sql
SELECT p.id, p.soluong,
       COALESCE(SUM(CASE
           WHEN sm.loai IN ('nhap', 'ban', 'trahang', 'dieuchinh')
           THEN sm.soluong ELSE 0 END), 0) AS tong_bien_dong
FROM products p
LEFT JOIN stock_movements sm ON sm.product_id = p.id
GROUP BY p.id, p.soluong
HAVING p.soluong <> tong_bien_dong;
```

Kết quả kiểm tra thực tế: **0 dòng sai lệch**. Quy tắc này vẫn đúng sau bán,
trả hàng lỗi và kiểm kê; trả hàng lỗi được theo dõi riêng trong
`soluong_hong`.

## 13. Quy trình Git đề xuất

Nhánh chính là `main`. Tạo nhánh chức năng theo Gitflow:

```powershell
git switch main
git pull --ff-only origin main
git switch -c feature/ten-chuc-nang
```

Sau khi hoàn thành:

```powershell
git add .
git commit -m "feat: mo ta thay doi"
git push -u origin feature/ten-chuc-nang
```

Sau khi kiểm thử, tạo Pull Request để merge vào `main`.

## 14. Phạm vi chưa triển khai

### Đã hoàn thành

- Luồng đăng nhập và phân quyền theo ba vai trò nhân viên (admin, thu ngân, thủ kho).
- Storefront công khai (trang chủ, danh mục, sản phẩm, tìm kiếm, giỏ hàng) và
  đăng ký tài khoản khách hàng (`khachhang`), liên kết khách walk-in theo số
  điện thoại; khách hàng bị chặn khỏi trang nhân viên.
- CRUD sản phẩm, loại sản phẩm (có `ma_loai`, `tu_khoa`, `he_so_lai`), POS, thanh toán và hóa đơn.
- Quản lý tồn kho, nhập kho trực tiếp và lịch sử biến động (có `trahang_hong`).
- Upload ảnh, POS dạng thẻ, tìm barcode và in tem Code 128.
- In hóa đơn K58/K80 bằng trình duyệt.
- Khách hàng và tích điểm (100k = 1 điểm, 1 điểm = 1k).
- Giá vốn, lợi nhuận và xuất báo cáo XLSX/PDF.
- **Thanh toán chuyển khoản VietQR + Webhook SePay tự động** (T-CK01..T-CK18).
- **Bóc tách PDF hóa đơn nhập cục bộ** (`smalot/pdfparser`, Human-in-the-Loop).
- Nhà cung cấp và đơn nhập hàng theo quy trình trạng thái nhiều bước.
- Nhật ký hoạt động cơ bản cho đăng nhập, sản phẩm, nhập kho, nhà cung cấp và
  đơn nhập hàng.
- Đổi/trả hàng có kiểm tra hạn, số lượng, tình trạng hàng, hoàn tiền, điểm và
  tồn kho.
- Hold giỏ hàng (tự xóa sau 24h qua `hold:clean`) và kiểm kê kho có kiểm tra bán xen, duyệt điều chỉnh và nhật ký.
- **Đối soát chuyển khoản**: webhook không khớp hóa đơn vào hàng chờ `/doisoat`, admin đánh dấu đã xử lý kèm ghi chú và nhật ký.
- **Vận hành**: chuyển bảng MySQL sang InnoDB, sao lưu CSDL hằng ngày (`sao-luu:csdl`), kiểm tra sức khỏe (`he-thong:kiem-tra`), chốt chặn production cho lệnh phá hủy dữ liệu.
- **Bảo mật cấu hình**: throttle đăng nhập, cảnh báo mật khẩu mặc định, lệnh tạo/đổi mật khẩu tài khoản nội bộ, log daily 14 ngày có che bí mật.
- Trang lỗi 403/404/419/500 tiếng Việt và POS xử lý lỗi AJAX/mất kết nối mà không làm mất giỏ hàng.
- Bộ dữ liệu thay thế gồm 12 loại hàng, 300 sản phẩm, 81 nhà cung cấp, 100
  khách hàng và một hóa đơn nhập 300 dòng.
- Tài liệu dự án được lưu trong `TaiLieu/`.
- Lộ trình mở rộng sau demo được ghi tại
  `TaiLieu/KeHoach/Ke hoach.md`.

### Còn thiếu hoặc chưa triển khai

- Phân quyền động theo permission (Pha 23).
- Quét barcode bằng webcam.
- In tự động không qua hộp thoại trình duyệt (cần QZ Tray).
- CRUD tài khoản người dùng, ứng dụng mobile và API riêng (Pha 23).
- Khuyến mãi/voucher (Pha 22), ROP/ABC (Pha 21).
- Tích hợp bên ngoài: hóa đơn điện tử, Zalo ZNS/SMS, đồng bộ Shopee/TikTok (Pha 24).

## 15. Lộ trình triển khai tiếp theo

Ưu tiên các phần đã có dữ liệu mẫu trong `TaiLieu/`, không mở rộng sang mobile
hoặc API khi chưa cần cho đồ án.

- Đặt hàng online tối thiểu (guest + khách đăng nhập, QR/pickup): đã có thiết kế chờ duyệt tại [docs/THIET_KE_DAT_HANG_ONLINE.md](docs/THIET_KE_DAT_HANG_ONLINE.md) — chưa triển khai.

### Pha 15 — Nhà cung cấp và đơn nhập hàng — Đã hoàn thành

- Có migration cho `suppliers`, `purchase_orders`, `purchase_order_items` và
  liên kết nhập kho.
- Có model, quan hệ, validate và CRUD nhà cung cấp.
- Có tạo đơn nhập nhiều sản phẩm, tự tính tổng tiền.
- Có quy trình `cho_duyet` → `cho_giao` → `dang_giao` → `da_nhan` hoặc
  `da_huy`.
- Khi nhận hàng, cập nhật tồn kho, giá vốn và lịch sử kho trong transaction.
- Chặn nhận trùng, nhận sai trạng thái và xóa nhà cung cấp đã có đơn.
- Admin/thủ kho được thao tác; thu ngân nhận 403.
- Đã có test luồng nhận hàng một lần và kiểm tra phân quyền.

URL chính:

- `/nhaphang/nhacungcap`: danh sách và CRUD nhà cung cấp.
- `/nhaphang/donnhap`: danh sách đơn nhập.
- `/nhaphang/donnhap/them`: tạo đơn nhập.

File Excel trong `TaiLieu/ThongTinNhaCungCap/` được giữ làm tài liệu nguồn.
Bộ dữ liệu hóa đơn nhập đang dùng workbook
`TaiLieu/ThongTinHangHoa/LoaiHangHoa.xlsx`; có thể kiểm tra bằng
`php artisan demo:kiemtra-du-lieu`.

### Pha 15A — Hóa đơn nhập & Bóc tách PDF — Đã hoàn thành

Chi tiết quy tắc, checklist, nhật ký thay đổi, bảng số kiểm tra và kết quả
thực tế nằm trong [Ke hoach.md](TaiLieu/KeHoach/Ke%20hoach.md). Hóa đơn nhập được
kiểm thử trong `PosWorkflowTest` (tạo nháp + đính kèm PDF) và
`PdfInvoiceParserTest` (3 test); không có bộ test mang mã `T-HDN` trong code.
Test webhook thanh toán: `T-CK01`..`T-CK18` trong `ThanhToanChuyenKhoanTest.php`.

### Pha 16 — Báo cáo, xuất file và giá vốn — Đã hoàn thành

### Pha 17 — Nhật ký hoạt động và phân quyền nâng cao — Đã hoàn thành một phần

Nhật ký cơ bản có tại `/nhatky`, ghi người dùng, hành động, đối tượng, thời gian,
IP và mô tả. Permission động vẫn chờ chốt ma trận quyền (Pha 23).

### Pha 18 — Đổi/trả hàng — Đã hoàn thành một phần

- Đã có tạo phiếu trả, trả hàng tốt/hỏng, kiểm tra hạn và số lượng cộng dồn.
- Đã hoàn tiền theo tỷ lệ `tong_thanh_toan/tong_tien` (phân bổ cả giảm giá % lẫn
  giảm giá điểm), cập nhật tồn/kho lỗi, thu hồi điểm và ghi nhật ký.
- Điểm thu hồi = `floor(tong_hoan / POS_DIEM_MOI_DONG)`, không vượt số dư hiện
  tại; phiếu vượt `POS_NGUONG_TRA_KHONG_DUYET` phải chờ admin duyệt.
- Đã hoàn `diem_su_dung` theo tỷ lệ giá trị hàng trả cộng dồn, làm tròn xuống;
  tổng không vượt điểm gốc. Phiếu chờ duyệt giữ phần số lượng/điểm đã cam kết,
  nhưng chỉ cộng điểm và ghi nhật ký khi hoàn thành.
- Đã có test hạn trả 7 ngày, ngưỡng duyệt, hóa đơn giảm giá + dùng điểm, nhiều lần
  trả, hàng lỗi, làm tròn và hóa đơn thực trả 0.
- Đã cập nhật thống kê doanh thu/lợi nhuận và top sản phẩm theo doanh thu ròng.
- Chưa có luồng đổi sản phẩm tạo hóa đơn bù trừ riêng; hình thức `doi_hang`
  hiện lưu lựa chọn hoàn và cần bổ sung nếu đồ án yêu cầu.

### Pha 19 — Tạm lưu giỏ hàng — Đã hoàn thành (tự dọn 24h)

### Pha 20 — Kiểm kê kho — Đã hoàn thành phần cốt lõi

### Pha 21 — ROP và ABC — Chưa triển khai (cần dữ liệu bán 30 ngày)

### Pha 22 — Khuyến mãi và voucher — Chưa triển khai (cần xác minh quy tắc)

### Pha 23 — Tài khoản, permission và REST API — Chưa triển khai (cần xác minh)

### Pha 24 — Tích hợp bên ngoài — Chưa triển khai (cần sandbox & xác nhận)

Các tích hợp thanh toán/webhook, hóa đơn điện tử, in ESC/POS và sàn thương mại
điện tử chỉ làm sau khi có tài khoản sandbox, tài liệu API và xác nhận.

- Chốt nhà cung cấp có sandbox và webhook có chữ ký.
- Thêm mã giao dịch duy nhất và trạng thái chờ xác nhận.
- Xác thực chữ ký, số tiền, mã giao dịch và xử lý webhook lặp.
- Chỉ dùng production sau khi test sai chữ ký, sai số tiền và timeout.

Mỗi pha phải được triển khai trên một branch riêng, chạy test trước khi
merge vào `main`. Không import dữ liệu mẫu hoặc reset database production.

### Email thông báo cho khách

**Tùy chọn, chưa triển khai; cần cấu hình `MAIL_*` để gửi email thật.**

- Email khôi phục mật khẩu tiếng Việt; hiện chưa có luồng quên/đặt lại mật khẩu.
- Email xác nhận đơn hàng POS chỉ gửi khi khách có email (`customers.user_id` →
  `users.email`), bỏ qua khách không có email. Storefront hiện chưa tạo đơn online.

Bằng chứng kiểm tra code: [Báo cáo rà soát — email](docs/BAO_CAO_RA_SOAT.md#bổ-sung--rà-soát-email-trên-nhánh-hiện-tại-2026-11-10).

## 16. Lưu ý bảo mật

- Không commit file `.env` hoặc thông tin mật khẩu thật.
- Luôn dùng `@csrf` cho form POST/PUT/DELETE.
- Không hiển thị dữ liệu người dùng bằng `{!! !!}`.
- Không dùng tài khoản demo trong môi trường production.
- Không chạy `migrate:fresh` hoặc `demo:reset` trên dữ liệu thật.
- Khi chạy thật: `APP_ENV=production`, `APP_DEBUG=false` (để bật debug là mất an
  toàn và `he-thong:kiem-tra` báo `FAIL`).
- Đổi mật khẩu toàn bộ tài khoản demo (`nguoidung:doi-matkhau`) hoặc tạo tài khoản
  mới (`nguoidung:tao`) trước khi mở cho nhân viên dùng; cảnh báo mật khẩu mặc định
  hiển thị trên dashboard admin.
- Đăng nhập bị throttle 10 lần/phút mỗi IP + email; không tắt throttle này khi chạy
  thật.
- Webhook SePay chỉ nhận khi có `SEPAY_WEBHOOK_API_KEY` (fail-closed khi để trống);
  đặt key đủ dài và đổi ngay nếu lộ. Dùng HTTPS hoặc tunnel có kiểm soát.
- Không để lộ `storage/` và `bootstrap/cache/` qua web server; sao lưu CSDL chứa dữ
  liệu khách hàng nên được giới hạn quyền đọc.
- Ưu tiên tài khoản MySQL riêng cho ứng dụng, chỉ cấp quyền trên database của dự án
  thay vì dùng `root`.

## 17. Giới hạn đã biết

- **Không có đối soát tự động** nếu không cấu hình `SEPAY_WEBHOOK_API_KEY` và tunnel công khai.
- **Đối soát chủ động SePay API chỉ ở chế độ báo cáo**: `sepay:doi-soat` đọc tiền
  vào và ghi bản ghi `api_doi_soat` vào `/doisoat`, nhưng **không tự hoàn tất hóa
  đơn**. `--ap-dung` bị từ chối trước khi gọi mạng vì tài liệu SePay chưa chứng
  minh khóa duy nhất chung: API v2 dùng `id` UUID và `reference_number`, webhook
  dùng `id` số và `referenceCode`; tài liệu đối soát chỉ gợi ý dùng `id`/
  `reference_number` mà không khẳng định `reference_number == referenceCode`.
  Muốn mở `--ap-dung` cần người dùng xác nhận bằng giao dịch thật (xem
  `docs/BAO_CAO_SEPAY_DOI_SOAT.md`).
- **Giao dịch lạ vẫn vào `/doisoat`, nhưng không bắn Telegram**: chuyển khoản cá
  nhân không có mã hóa đơn hoặc mã không tồn tại vẫn được lưu (không mất dấu
  giao dịch) để admin xem. Khi mới bật, nên chạy tay `sepay:doi-soat --gio=1`
  trước để ước lượng lượng giao dịch, và ưu tiên tài khoản ngân hàng riêng cho
  cửa hàng. Mỗi lần chạy chỉ gửi tối đa 5 tin chi tiết
  (`SEPAY_DOI_SOAT_TELEGRAM_TOI_DA`), phần vượt gộp thành một tin tóm tắt.
- **Webhook cũ trước migration có thể không gộp được với API**: cột `ma_tham_chieu`
  chỉ được ghi từ đợt này; bản ghi cũ đọc dự phòng `referenceCode` từ `payload`,
  nên webhook cũ thiếu mã tham chiếu có thể bị API báo lại một lần trong `/doisoat`
  (không ảnh hưởng hóa đơn vì chế độ chỉ báo cáo).
- **v1 đã deprecated, không hỗ trợ**: `SEPAY_API_VERSION` khác `v2` trả lỗi rõ
  ràng, không âm thầm gọi endpoint cũ. API v2 giới hạn khoảng 3 request/giây;
  client giữ khoảng cách tối thiểu 350 ms và đọc `Retry-After` khi gặp 429, tối
  đa `SEPAY_API_RETRIES` lần; cửa sổ lớn có thể cần nhiều trang (tối đa 1000).
- **Migration `ma_tham_chieu` chưa áp lên CSDL thật**: migration mới chỉ được viết
  và kiểm trên SQLite `:memory:` trong test; chưa chạy `migrate` trên `doan_cnw`.
  Trước khi dùng đối soát trên máy cửa hàng phải sao lưu rồi `migrate --force`
  theo mục 5A (cần người dùng xác nhận thời điểm).
- **Không đọc barcode bằng webcam** (chỉ hỗ trợ máy quét USB HID/keyboard wedge).
- **Không in tự động** không qua hộp thoại trình duyệt (cần QZ Tray hoặc print server).
- **Không có ứng dụng mobile / API REST** (chỉ web POS).
- **Không có phân quyền động** (chỉ role-based: admin, thu ngân, thủ kho).
- **Không có khuyến mãi/voucher** (Pha 22).
- **Không có ROP/ABC** (Pha 21, cần dữ liệu bán 30 ngày).
- **Không tích hợp hóa đơn điện tử, Zalo ZNS/SMS, Shopee/TikTok** (Pha 24, cần sandbox & xác nhận).
- **Bóc tách PDF là heuristic**: kết quả bóc tách được điền vào form hóa đơn
  nhập nháp để thủ kho rà soát trước khi xác nhận (Human-in-the-Loop ngay trong
  `resources/views/hoadonnhap/form.blade.php`); không tự động xác nhận nhập kho.
- **Storefront — đã sửa lỗi 500 trang chi tiết sản phẩm**: `/san-pham/{id}` trước
  đây trả 500 (`LazyLoadingViolationException: loaiSanPham`) vì query sản phẩm liên
  quan không eager-load quan hệ; đã thêm `->with('loaiSanPham')`
  (`StorefrontController.php:80`). `/danh-muc/{id}` vốn hoạt động; URL dạng slug
  (`/danh-muc/sac`) trả 404 vì route binding theo `id` và bảng chưa có cột `slug`.
  Chi tiết: `docs/BAO_CAO_LOI_STOREFRONT.md`.
- **Task Scheduler phải cài thủ công trên máy cửa hàng**: script và hướng dẫn có
  sẵn ở mục 5, nhưng nếu chưa cài thì đơn quá hạn chỉ bị hủy "lười" khi POS poll và
  `he-thong:kiem-tra` báo `FAIL` ở mục *Scheduler* sau 3 phút không có nhịp.
- **Webhook không kiểm tra thời hạn chờ**: đơn `cho_thanh_toan` đã quá hạn nhưng
  chưa bị hủy, nếu tiền về vẫn được hoàn tất (hành vi hiện tại, được ghi nhận bởi
  test T-CK18 — chưa phải quyết định nghiệp vụ đã chốt).
- **Trả hàng đã hoàn `diem_su_dung`**: điểm lũy kế được tính bằng
  `floor(diem_su_dung × giá_trị_hàng_trả_lũy_kế / tong_tien)`, trừ điểm đã cam kết;
  tổng không vượt điểm gốc. Không dựa vào tiền hoàn đã làm tròn; hóa đơn thực trả
  0 vẫn hoàn điểm. Phiếu chờ duyệt chưa cộng điểm; hoàn thành có nhật ký.
  Đã có test giảm giá + dùng điểm, nhiều lần trả, làm tròn, chờ duyệt và hàng lỗi.
- **Còn 2 cột mã giao dịch song song** trên `invoices` (`ma_giaodich` 100 ký tự và
  `ma_giao_dich` 40 ký tự, đều unique); service ghi cả hai, webhook kiểm cả hai.
  Phân tích hợp nhất (cột chuẩn, độ lệch dữ liệu, kế hoạch migration) ở
  [docs/BAO_CAO_SAN_SANG_VAN_HANH.md](docs/BAO_CAO_SAN_SANG_VAN_HANH.md); chưa thực
  hiện vì cần người dùng xác nhận.
- **CSDL thật `doan_cnw` chưa được migrate trong đợt này**: ngày 2026-11-10 vẫn là
  26 migration (MyISAM). 6 migration mới chỉ được thử trên bản sao `doan_cnw_copy`;
  xem checklist triển khai ở mục 5A.
- **Múi giờ vẫn là UTC**: không đổi `config/app.php`. Phương án chuyển sang
  `Asia/Ho_Chi_Minh`, tác động dữ liệu cũ, kiểm tra và hoàn tác được ghi ở mục 6.2
  của [báo cáo vận hành](docs/BAO_CAO_SAN_SANG_VAN_HANH.md); chờ người dùng quyết định.
- **`db:wipe` đã có chốt chặn production** như `migrate:fresh`: cần
  `--force-dangerous` và đúng tên database đích; `--force` đơn lẻ không đủ.
  Test chốt chặn dùng mock DB/schema/migrator, không xóa hay migrate CSDL thật.
- **Đặt hàng online P2 (giao tận nơi) — Form + Checkout + trang QR online đã xong (bước 1–2, 2026-11-14/15)**:
  `giao_tan_noi` bắt buộc QR trả trước + tên/SĐT người nhận + địa chỉ 4 phần (số nhà/đường,
  phường/xã, quận/huyện, tỉnh/thành) ghép một cột `dia_chi_giao` ≤255; vùng giao khớp tỉnh
  chính xác theo `POS_GIAO_HANG_TINH`; phí cố định `POS_PHI_GIAO_HANG`, miễn phí khi tổng
  giá DB ≥ `POS_MIEN_PHI_GIAO_TU` (>0); tổng = tiền hàng + phí tính TRONG transaction (R1);
  honeypot trung tính `truong_phu`; prefill hồ sơ không ghi ngược (D8); D12 chạy cả luồng
  giao; D17 QR online hủy theo `han_giu` (30 phút), không hủy ở mốc 15 phút;
  `trang_thai_giao=null` tới khi thanh toán. **Bước 2 đã xong (2026-11-15):** trang QR online
  dùng VietQrService đúng tổng gồm ship + nội dung = mã hóa đơn, đếm ngược theo `han_giu`,
  hết hạn → hủy + hoàn kho + thông báo rõ ràng, thiếu cấu hình ngân hàng → thân thiện không
  500/không lộ số tài khoản, polling POST + bằng chứng session không PII (sửa lỗi kiểu trả về
  `getQr`). **Bước 3 đã xong (2026-11-15):** cờ `POS_DAT_HANG_ONLINE_BAT`/
  `POS_GIAO_TAN_NOI_BAT` (mặc định tắt → 404 mọi route đặt hàng, ẩn nút/lựa chọn);
  webhook + xác nhận thủ công đặt `trang_thai_giao` theo `POS_ONLINE_TU_DONG_XAC_NHAN`
  (D15, idempotent, guard thiếu cột); trang trạng thái hiện trạng thái giao + thông
  tin giao chỉ cho người đã xác thực; Telegram đơn giao đã thanh toán che SĐT,
  không địa chỉ, escape HTML. **Chưa implement (bước 4 + P3):** báo cáo tách phí
  ship khỏi doanh thu; màn hình nhân viên xử lý đơn online (xác nhận/đang giao/đã
  giao, hủy đơn đã thanh toán + hoàn tiền thủ công, gia hạn, cộng điểm khi giao,
  trả hàng online, bật cờ). **P3 + P2 bước 4 đã xong (2026-11-16):** màn hình
  "Đơn online" cho admin/thungan/thukho (danh sách lọc + badge, chi tiết no-store,
  máy trạng thái giao lockForUpdate idempotent, nút pickup tái dùng endpoint POS,
  hủy đơn đã thanh toán chỉ admin + cờ `can_hoan_tien` + danh sách hoàn tiền,
  phiếu giao hàng in, trả hàng online an toàn null, báo cáo/dashboard/export tách
  phí ship). Chi tiết: `docs/BAO_CAO_DAT_HANG_P3.md`.
- **Kiểm thử hiện tại: xem mục 9** (quy tắc hoàn phí giao hàng,
  2026-11-16, `main`: **747 pass / 45 skip / 0 fail, 3461 assertions, 871.33s**
  bằng `vendor/bin/paratest` 16 tiến trình — tương đương `php artisan test`;
  MySQL-copy **45 pass / 373 assertions, 23.37s** trên `doan_cnw_copy`.
  `migrate:status`: 5 migration pending theo thứ tự ở `docs/SO_TAY_TRIEN_KHAI.md`).
  Trước đó thông báo giỏ hàng khi cờ đặt hàng tắt:
  **737 pass / 44 skip / 0 fail, 3423 assertions, 445.80s** paratest;
  MySQL-copy 44 pass / 364 assertions, 15.59s.
  Chốt triển khai + merge SePay đối soát:
  **736 pass / 44 skip / 0 fail, 3418 assertions, 447.77s** paratest;
  MySQL-copy 44 pass / 364 assertions, 14.23s.
  P3 + P2 bước 4: 702 pass / 44 skip / 0 fail,
  3154 assertions; MySQL-copy 44 pass / 364 assertions.
  P2 bước 3 + cờ: 674 pass / 44 skip / 0 fail,
  2972 assertions; MySQL-copy 44 pass / 364 assertions.
  Vận hành 2: 655 pass / 43 skip / 0 fail,
  2895 assertions; MySQL-copy 43 pass / 356 assertions.
  Baseline Stage 3-4 trước sửa: 624 pass / 42 skip /
  2712 assertions — lượt chạy bị ảnh hưởng giữa chừng bởi chỉnh sửa đang làm;
  2 fail `/taikhoan` 302 đã được chứng minh riêng trên cây trước sửa và xanh sau
  fix `KiemTraSchema`. Mốc trước: sau bước 2 P2 (2026-11-15) 480 pass / 42 skip /
  0 fail, 2255 assertions, 306.799s; MySQL-copy 42 pass / 341 assertions / 13.25s.
  Baseline trước đó: P1 419 pass/41 skip/
  2007 assertions; P2 chuẩn bị 418 pass/48 skip/2042 assertions; sau merge P1+WIP 434 pass/
  42 skip/2 fail/2073 assertions — 2 fail đã sửa ở production `ec77163`; bước 1 P2 464 pass/
  42 skip/2164 assertions; tiền đề bước 2 474 pass/42 skip/2219 assertions). Skip MySQL-copy =
  `KhoaDongMysqlTest` (1) + `AnhUrlMysqlCopyTest` (37) + `AnhUrlDoDaiMysqlCopyTest` (1) +
  `DatHangP0MysqlCopyTest` (1) + `DatHangP1MysqlCopyTest` (2) + `DatHangP2MysqlCopyTest` (2) — SQLite không có khóa
  dòng/InnoDB, không cưỡng chế độ dài VARCHAR và không phải MySQL strict để thử migration;
  cần trỏ vào bản sao MySQL để chạy (mục 9). Các nhóm này phải chạy thật trên
  `doan_cnw_copy` bằng filter chính xác (mục 17). Số pass không thay thế kiểm tra đó.
  **Cẩn thận filter:** chạy test MySQL-copy phải dùng filter chính xác
  (`--filter='MysqlCopyTest|KhoaDongMysqlTest'`); filter rộng `--filter=Mysql` bắt nhầm
  method `...mysqldump` của `SaoLuuCsdlTest` (dùng `RefreshDatabase`) → `migrate:fresh`
  xóa sạch bản sao khi đặt env MySQL. Từ T3 (mở rộng P2 cho cả `DatabaseMigrations`/
  `DatabaseTruncation`) đã có chốt chặn `RefreshDatabase` (ném ngoại lệ tiếng Việt nếu
  không phải sqlite `:memory:`) nhưng **vẫn phải dùng filter chính xác** — chốt chặn là
  lưới an toàn, không phải lý do chạy filter rộng.
- **Đặt hàng online P1 — chỉ guest PICKUP:** checkout giữ hàng qua R1 (khóa dòng sản
  phẩm id tăng dần + `GiuHangOnlineService::kiemTra` trong transaction), tra cứu bằng
  mã + SĐT (SĐT chỉ trong thân POST, bằng chứng xác thực lưu session), polling chỉ trả
  trạng thái, khách tự hủy, thu ngân xác nhận/hủy/gia hạn (trần `online_gia_han_toi_da_gio`).
  **D12 chống ôm hàng (T2):** honeypot `trang_web` ẩn; tối đa `online_max_don_mo` (3)
  đơn pickup mở trên mỗi SĐT chuẩn hóa (kiểm tra TRONG transaction sau khóa,
  `SoDienThoai::chuanHoa`); tối đa `online_max_sanpham` (10) sản phẩm khác nhau/đơn và
  số lượng mỗi dòng 1..`online_max_soluong`; mọi từ chối dùng thông báo chung, không lộ
  dữ liệu đơn khác; throttle 5/phút/IP → 429. QR online để P2. Test đồng thời T-DH13
  là tranh chấp khóa dòng InnoDB THẬT qua hai kết nối (không phải đa luồng).
  Scheduler nền `donhang:huy-qua-han` **đã sửa (T1)**: dùng chung
  `DonHang::scopeQuaHanCho` — pickup online hủy theo `han_giu`, đơn còn lại theo
  `created_at+15ph` (không còn hủy nhầm đơn pickup 4h). Chi tiết:
  `docs/BAO_CAO_DAT_HANG_P1.md`.
- **Chốt chặn RefreshDatabase (T3, mở rộng P2):** `tests/TestCase.php::setUpTraits` ném
  ngoại lệ tiếng Việt nếu test dùng `RefreshDatabase`, `DatabaseMigrations` hoặc
  `DatabaseTruncation` chạy trên kết nối không phải sqlite `:memory:` — chặn trước
  `migrate:fresh`/truncate, bảo vệ cả CSDL thật lẫn bản sao khi lỡ đặt env MySQL.
  Bằng chứng: `RefreshDatabaseGuardTest`, `DatabaseMigrationsGuardTest`,
  `DatabaseTruncationGuardTest` (mô phỏng cấu hình mysql, không kết nối thật).
  Test MySQL-copy không dùng ba trait này nên không bị ảnh hưởng.
- **CSDL thật `doan_cnw` ĐÃ áp M1+M3** (quan sát chỉ đọc `SHOW CREATE TABLE invoices`
  2026-11-14, tái xác nhận 2026-11-15: có `kenh/hinh_thuc_nhan/han_giu/phi_giao_hang`,
  `user_id` nullable); bản sao `doan_cnw_copy` giữ trạng thái pre-M1 (`user_id NOT NULL`,
  5 dòng) để harness tự áp + rollback. **Lưu ý mâu thuẫn tiền đề:** người dùng nêu "DB thật
  chưa có M1/M2/M3" nhưng bằng chứng chỉ đọc cho thấy đã có; guard `Schema::hasColumn`
  vẫn an toàn ở cả hai giả định nên được GIỮ theo chỉ đạo. **TODO: gỡ guard (b, c, M3) +
  `LoiBThieuCotOnlineTest` sau khi người dùng xác nhận M1–M3 đã áp.** Không migrate/seed/
  rollback trên DB thật trong phiên này (chỉ `SHOW CREATE` chỉ đọc).
- **LỖI B (tạo QR) — xác nhận trên `main` trong Vận hành 2 (2026-11-16)**:
  nội dung fix `aae9ae8` đã nằm trong main qua merge P2 (không cần cherry-pick);
  `LoiBTaoQrTest` 6/6 + `LoiBThieuCotOnlineTest` 4/4 xanh — 422 thân thiện khi
  thiếu `POS_BANK_*` (log chỉ `Str::mask` số TK), POS/scheduler chạy khi DB
  thiếu cột online M1/M3, log không chứa số tài khoản đầy đủ. Không còn
  khoảng hở so với commit gốc. Fix gồm: (a) `postTaoQr` thiếu cấu hình ngân hàng → 422 thân thiện + `Str::mask` số tài
  khoản khi log (xử lý LỖI B-POS); (b) `scopeQuaHanCho` guard `hasColumn('kenh')` → fallback
  15 phút khi thiếu cột, còn không giữ logic D17; (c) R1 chỉ ghi cột online M1 khi `$online`
  khác rỗng, cột M3 chỉ ghi khi `hasColumn('hinh_thuc_nhan')`. Chi tiết:
  `docs/BAO_CAO_VAN_HANH_2.md` mục 4.
- **Mã phiếu trả chống trùng trong cùng giây (đã sửa 2026-11-12)**: `sinhMaPhieuTra`
  (`TraHangController.php:152-171`) dò hậu tố đã dùng của giây hiện tại rồi quét tiến
  từ điểm ngẫu nhiên, kèm vòng thử lại tối đa 5 lần khi vi phạm unique do giao dịch
  đồng thời (`:89-100`). Định dạng in `TH+YmdHis+2 số` giữ nguyên, không đổi
  cột/migration. Trần cứng **90 mã/giây** (hết hậu tố → lỗi có kiểm soát, đã có test).
  Flake `PosWorkflowTest` trả hàng đã hết: 11 test trả hàng chạy 5 lần liên tiếp đều
  xanh (mục 18). **Ba nơi sinh mã khác vẫn dùng mẫu cũ** `YmdHis + random_int(10,99)`
  và còn nguy cơ trùng tương tự, chưa sửa trong đợt này: kiểm kê
  (`KiemKeController.php:32`, KK), hóa đơn nhập (`HoaDonNhapController.php:140`, HN),
  đơn nhập (`NhapHangController.php:96`, PN). Mã hóa đơn `HD{Ymd}-{XXXX}` là chuỗi
  tuần tự theo ngày có `lockForUpdate` (`DonHangController.php:322-333`,
  `ThanhToanChuyenKhoanService.php:309-320`), khác mẫu ngẫu nhiên. Chi tiết:
  [docs/BAO_CAO_MA_PHIEU_TRA_HANG.md](docs/BAO_CAO_MA_PHIEU_TRA_HANG.md).
- **URL ảnh đã hết lỗi 500 do quá dài; giới hạn nay theo cột thật (Vận hành
  2, 2026-11-16)**: cột thật `products.hinh_anh` là VARCHAR(150) utf8mb4 trên
  `doan_cnw` (migration `2026_11_13_000002` nới lên 500 đã viết sẵn, chưa áp).
  `AnhSanPhamUrl::gioiHanDoDai()` đọc độ dài cột thật qua
  `KiemTraSchema::doDaiCotVarchar` (information_schema/PRAGMA, cache tĩnh,
  fallback 150 khi không đọc được) → sau khi migrate rule tự cho phép tới
  500 mà không cần sửa code; thông báo "quá dài" in đúng giới hạn hiện hành.
  Có tệp tải lên thì URL hỏng không chặn lưu tệp. JS form kiểm tra ảnh phía
  trình duyệt (debounce 500ms + blur, `Image()` no-referrer, timeout 8s) nay
  chỉ **cảnh báo + ô "Vẫn lưu liên kết này"** thay vì khóa cứng nút Lưu —
  server vẫn là nguồn sự thật duy nhất. Test biên 500/501 trên
  `doan_cnw_copy` (`AnhUrlDoDaiMysqlCopyTest`). URL legacy >150 ký tự vẫn hiển
  thị nhưng khi sửa phải xóa trắng ô URL/chọn tệp/xóa ảnh mới lưu được; chưa
  dọn dữ liệu legacy. Chi tiết:
  [docs/BAO_CAO_VAN_HANH_2.md](docs/BAO_CAO_VAN_HANH_2.md) mục 3,
  [docs/BAO_CAO_ANH_URL_VALIDATE.md](docs/BAO_CAO_ANH_URL_VALIDATE.md).
- **SĐT khách hàng đã siết chặt** (nhánh `fix/khach-hang-so-dien-thoai`, **đã merge vào
  nhánh `feat/khach-nhatky-taikhoan` ngày 2026-11-15**): mọi điểm nhập dùng chung
  `App\Rules\SoDienThoaiHopLe` — chuẩn hóa (`+84`/`84` → `0`, bỏ khoảng trắng/dấu
  chấm/gạch/ngoặc) rồi mới validate `/^0(3|5|7|8|9)\d{8}$/`. Bản ghi thật từng có SĐT
  là tên người (báo cáo 2026-11-11) **đã được sửa** — khảo sát chỉ đọc 2026-11-15:
  101 khách, 0 rỗng/NULL/sai định dạng/trùng. Lệnh dry-run liệt kê bản ghi sai và
  phương án xử lý ở [docs/BAO_CAO_KHACH_HANG.md](docs/BAO_CAO_KHACH_HANG.md), chờ
  người dùng xác nhận.
- **Danh sách khách hàng tải toàn bộ**, phân trang chỉ phía client (DataTables);
  endpoint POS thêm khách nhanh (`postTaoNhanh`) đã validate nhưng chưa có UI gọi.
- **Test khói route GET phủ 61/62 route GET**: đủ 4 vai trò (khách, thu ngân, thủ
  kho, admin), mọi route không/ có tham số `{id}` dùng id thật đều trả 200/302/403
  hợp lệ, không 500. Route duy nhất chưa phủ là `storage/{path}` — route phục vụ
  tệp của framework, cần đường dẫn tệp thật và trả 404 khi thiếu, không phải
  trang/nghiệp vụ.
- **Webhook chỉ còn một route POST `/api/webhook/thanhtoan`** trong
  `routes/web.php`, giữ `throttle:60,1`; đã gỡ khai báo bị che trong `routes/api.php`.
  Route được đưa ra khỏi nhóm `web` bằng `withoutMiddleware('web')` nên không còn
  session/cookie/CSRF/bindings — controller chỉ xác thực bằng API Key. Ngoại lệ
  CSRF trong `bootstrap/app.php` thu hẹp từ wildcard `api/webhook/*` về đúng path
  `api/webhook/thanhtoan`. Hai test bật CSRF thật (override `runningUnitTests()`)
  chứng minh đúng path được miễn, path khác và đăng nhập không token vẫn 419, và
  webhook trả 401/400/200 mà không có session/cookie. Không đổi URI/tên/hành vi.

## 18. Kết quả kiểm tra

Cập nhật lần cuối: **2026-11-16**, trên `main` (sau merge `feat/sepay-doi-soat`
+ ParaTest). Số liệu của các đợt trước được giữ lại để đối chiếu.

| Kiểm tra | Kết quả | Ghi chú |
|----------|---------|---------|
| `php artisan test` (chưa build asset) | 52 passed / 10 failed (206 assertions, 133s) | 10 fail do thiếu `public/build/manifest.json` (Vite) |
| `npm ci` + `npm run build` | ✅ Thành công | Tạo `public/build/manifest.json` |
| `php artisan test` (sau build, trước khi sửa) | 60 passed / 2 failed (228 assertions, 92s) | 2 fail do lệch chữ ở test storefront, xem bên dưới |
| `php artisan test` (sau khi sửa + thêm test) | **66 passed / 0 failed** (274 assertions, 112.5s) | Toàn bộ suite xanh (đợt rà soát README) |
| `php artisan route:list` | ✅ Hoạt động | 100 routes (không đổi sau khi xóa controller legacy) |
| `php artisan demo:kiemtra-du-lieu` | ✅ Đã chạy trên DB thật, **"Dữ liệu hợp lệ."** (exit 0) | Lệnh chỉ đọc — xem minh chứng bên dưới |
| `php artisan demo:reset` | Không chạy | Xóa dữ liệu thật |

### Đợt Vận hành 2 — Lỗi A/D/E trên `main` (2026-11-16)

| Kiểm tra | Kết quả | Ghi chú |
|----------|---------|---------|
| Lỗi A — dùng điểm sai quy định | ✅ | Cherry-pick `dfd4009`+`003714a` → `ce5cfec`/`9ed15b1`: 422 `ValidationException` tiếng Việt theo trường `diem_su_dung` ở cả tiền mặt lẫn QR, `lockForUpdate` trong transaction, UI POS disable/max/kẹp; `LoiADungDiemTest` 10/10 |
| Lỗi D — dashboard tồn kho | ✅ | Cherry-pick `46bb0bc`+`7d5dedf` → `6ed1db3`/`fad78ff`: 4 chỉ số "mặt hàng KD / tồn khả dụng / đang giữ / tổng thực tế" + chú thích, cảnh báo lỗi mạng; `LoiDDashboardTonKhoTest` xanh |
| Lỗi B — tạo QR | ✅ Xác nhận | Đã có tương đương `aae9ae8` trong main; `LoiBTaoQrTest` 6/6 + `LoiBThieuCotOnlineTest` 4/4 |
| Lỗi E — URL ảnh | ✅ | `77a6ac6`: rule theo độ dài cột thật qua `KiemTraSchema::doDaiCotVarchar` (fallback 150), JS cảnh báo + "Vẫn lưu liên kết này" thay vì khóa nút; `AnhUrlDoDaiMysqlCopyTest` biên 500/501 trên `doan_cnw_copy` |
| Liên kết hồ sơ theo SĐT | ✅ Chỉ đọc | `doan_cnw`: 101 khách, 1 liên kết (hợp lệ), 0 nghi vấn auto-link → không cần gỡ hàng loạt; quy trình nhân viên ở mục 7 |
| Full suite | ✅ **655 pass / 43 skip / 0 fail (2895 assertions, 982.53s)** | 1 fail `PosWorkflowTest` do assertion cũ → đã cập nhật sang errors bag (`3c45de9`), chạy lại xanh |
| MySQL-copy | ✅ **43 pass / 356 assertions (17.14s)** | Filter chính xác trên `doan_cnw_copy` |
| Migration cần áp | ⏳ Chưa | Thứ tự 6 migration + gỡ guard ở `docs/BAO_CAO_VAN_HANH_2.md` mục 7 |

### Đợt P2 bước 3 + cờ bật/tắt đặt hàng online — `main` (2026-11-15)

| Kiểm tra | Kết quả | Ghi chú |
|----------|---------|---------|
| Cờ `POS_DAT_HANG_ONLINE_BAT` / `POS_GIAO_TAN_NOI_BAT` | ✅ | Mặc định `false`; middleware `dat.hang.online` → 404 toàn bộ route `/dat-hang`,`/don-hang`; giỏ hàng thay nút "Đặt hàng" bằng thông báo thân thiện kèm hotline (`config/storefront.php`, env `HOTLINE`) + ẩn radio `giao_tan_noi`; server từ chối `giao_tan_noi` khi cờ 2 tắt; `he-thong:kiem-tra` báo trạng thái + WARN; đơn cũ vẫn webhook/scheduler xử lý; `DatHangCoBatTest` 8 test |
| Webhook → `trang_thai_giao` (D15) | ✅ | `hoanTatThanhToan` đặt `da_xac_nhan`/`cho_xac_nhan` theo `POS_ONLINE_TU_DONG_XAC_NHAN` cho đơn `kenh=online`+`giao_tan_noi`; chỉ khi đang null (idempotent, không ghi đè P3); guard `KiemTraSchema::coCot` khi DB thiếu cột; `DatHangP2Buoc3Test` 12 test |
| Telegram đơn giao đã thanh toán | ✅ | `thongBaoDonGiaoDaThanhToan`: mã đơn+tổng+hình thức, SĐT che `090****567`, không địa chỉ, `e()` escape; gọi sau commit ở webhook + xác nhận thủ công |
| Tra cứu/trạng thái giao | ✅ | Trang trạng thái (đã yêu cầu bằng chứng session/chủ đơn) hiện trạng thái giao + thông tin giao, SĐT che; polling trả `trang_thai_giao` không PII; sai mã/SĐT → thông báo chung |
| Full suite | ✅ **674 pass / 44 skip / 0 fail (2972 assertions, 1565.55s)** | +19 test mới; 3 assertion `assertExactJson` cũ cập nhật thêm field `trang_thai_giao` |
| MySQL-copy | ✅ **44 pass / 364 assertions (15.52s)** | +1 test đường hoàn tất trên `doan_cnw_copy` |
| Tag backup | ✅ | `backup/main-truoc-p2-buoc3-co-dat-hang-20261115` |

### Đợt P3 + P2 bước 4 — Màn hình đơn online cho nhân viên, tách phí ship — `main` (2026-11-16)

| Kiểm tra | Kết quả | Ghi chú |
|----------|---------|---------|
| Màn hình "Đơn online" (HM1) | ✅ | `donhangonline.*` role admin/thungan/thukho; lọc hình thức/trạng thái/ngày/từ khóa, paginate 20, không N+1; SĐT che ở danh sách, đầy đủ + `no-store` ở chi tiết; badge menu poll 30s; thiếu cột → "Cần áp migration" không 500; `DonHangOnlineNhanVienTest` 5 test |
| Máy trạng thái giao (HM2) | ✅ | `cho_xac_nhan→da_xac_nhan→dang_giao→da_giao`, `lockForUpdate`+transaction, idempotent, nhật ký không PII; điểm đã cộng lúc thanh toán nên `da_giao` không cộng lại; `DonHangChuyenTrangThaiTest` 5 test |
| Pickup/QR chờ (HM3) | ✅ | Nút xác nhận/gia hạn/hủy trên chi tiết tái dùng `donhang.xacnhan/giahan/huycho` (server kiểm lại mọi ràng buộc); `DonHangPickupXuLyTest` 5 test |
| Hủy đơn đã thanh toán (HM4) | ✅ | Chỉ admin + bắt buộc lý do; hoàn kho `dieuchinh` 1 lần, thu hồi điểm kẹp sàn 0, `da_huy`+`da_huy_giao`+`can_hoan_tien`; Telegram không PII; danh sách "Đơn cần hoàn tiền" + "Đã hoàn tiền"; thiếu cột vẫn hủy được; tag `backup/main-truoc-p3-huy-hoan-tien-20261116`; `DonHangHuyDaThanhToanTest` 5 test |
| Phiếu giao hàng (HM5) | ✅ | In phiếu người nhận/địa chỉ/ghi chú/SP/phí ship/tổng/"ĐÃ THANH TOÁN", không giá vốn, `no-store`, nhật ký không PII; `PhieuGiaoHangTest` 1 test |
| Trả hàng online (HM6) | ✅ | Không đổi code; an toàn `customer_id`/`user_id` null; hành vi hiện có hoàn tiền tỷ lệ gồm phí ship — ghi "Cần xác nhận"; `TraHangDonOnlineTest` 3 test |
| Tách phí ship (P2 b4) | ✅ | `getDoanhThu` thêm `phi_giao_hang`, `doanh_thu` chỉ hàng hóa; `so_hoa_don` chỉ `da_thanh_toan`; `HoaDonExport` + cột phí ship; hóa đơn in hiện dòng phí ship; `BaoCaoTachPhiGiaoTest` 4 test (100k+30k → 100k/30k) |
| Full suite | ✅ **702 pass / 44 skip / 0 fail (3154 assertions, 1973.79s)** | +23 test mới so với P2 b3 |
| MySQL-copy | ✅ **44 pass / 364 assertions (21.39s)** | Filter chính xác trên `doan_cnw_copy` |
| Migration chờ áp | ⏳ `2026_11_16_000001` (can_hoan_tien, ghi_chu_noi_bo) | Guard + `down()` điều kiện; thứ tự ở `docs/BAO_CAO_DAT_HANG_P3.md` |

### Đợt Chốt triển khai — phân quyền, tốc độ test, SePay đối soát — `main` (2026-11-16)

| Kiểm tra | Kết quả | Ghi chú |
|----------|---------|---------|
| Ma trận vai trò × thao tác đơn online | ✅ | `DonHangOnlinePhanQuyenTest` 4 test: thukho chỉ xem/chuyển trạng thái/phiếu giao (không pickup, không hoàn tiền); thungan + pickup, không hủy-đã-TT; admin tất cả; khách/guest bị chặn. Server+UI đã đúng sẵn — chỉ thêm test |
| Tốc độ bộ test | ✅ | phpunit.xml đã tối ưu (BCRYPT_ROUNDS=4, array cache/session, sync queue); profile: không có điểm nghẽn đơn lẻ (số 2299s là artifact profiler — file đó chạy riêng 67s); **ParaTest 7.8.5 --dev**: full suite **780 test / 3418 assertions / 44 skip / 0 fail trong 447.77s** (16 tiến trình) so với serial ~2326s — nhanh ~5 lần, cùng số assertion |
| Merge `feat/sepay-doi-soat` | ✅ | `--no-ff` `912dbbb`; xung đột chỉ README (giữ cả hai); WebhookController giữ Telegram P2 + `ghiNhanWebhook` gộp `ma_tham_chieu`; thêm guard `hasColumn` cho migration + runtime (`KiemTraSchema`) khi cột chưa có; `--ap-dung` vẫn bị khóa, client chỉ GET, token không vào log |
| Full suite sau merge | ✅ **736 pass / 44 skip / 0 fail (3418 assertions, 447.77s paratest)** | +30 test so với P3 (Sepay 24 + ma trận 4 + khác) |
| MySQL-copy | ✅ **44 pass / 364 assertions (14.23s)** | Filter chính xác trên `doan_cnw_copy` |
| Sổ tay triển khai | ✅ | `docs/SO_TAY_TRIEN_KHAI.md`: migration chờ áp theo thứ tự, quy trình backup→dev→migrate, biến môi trường, gỡ guard, dựng máy, bật cờ online 8 bước |
| Cờ đặt hàng online | ⏸️ Giữ `false` | Bật sau kiểm tra tay 8 bước (mục 6 sổ tay) |

### Đợt Stage 3-4 — Khách hàng, nhật ký toàn bộ, chốt main (2026-11-16, nhánh `feat/khach-nhatky-taikhoan`)

| Kiểm tra | Kết quả | Ghi chú |
|----------|---------|---------|
| Lịch sử đơn khách | ✅ | Route `profile.orders.show` + trang chi tiết riêng; đơn POS không `ma_tra_cuu` vẫn xem được; check chủ sở hữu `customer_id`; eager-load chi tiết+sản phẩm |
| SĐT hiển thị | ✅ | Bỏ mask không nhất quán ở header profile/orders — hiển thị đầy đủ cho chính chủ tài khoản |
| SĐT mọi điểm nhập | ✅ | `SoDienThoaiHopLe` + mutator đã chặn chữ cái; DB thật 101 khách hợp lệ (dry-run) |
| Nhật ký toàn bộ model | ✅ | `GhiNhatKy` gắn cho 14+ model nghiệp vụ; `PaymentWebhook` cố ý không gắn (đã có log đối soát riêng) |
| Guard DB thiếu cột | ✅ | `KiemTraSchema` (cache tĩnh `Schema::hasColumn`) thay `array_key_exists` trên model rỗng — **sửa bug production: `/taikhoan` trước đây 302 vô hạn**; kèm sửa `with('customer')` → `khachHang` (500) |
| Middleware tài khoản | ✅ | Thứ tự `khoa.luc` trước `bat.buoc.doi.mat.khau`; thiếu cột = không cờ, không redirect; test mới 6 ca |
| Liên kết hồ sơ theo SĐT | ✅ **CÓ LỘ → ĐÃ BỊT** | Trước: đăng ký trùng SĐT khách vãng lai tự chiếm hồ sơ. Sau: không tự liên kết; admin gán thủ công ở màn sửa khách hàng; test 4 ca |
| `DoiSoatTest` diff | ✅ | 2 assertion đổi từ đếm toàn bảng log → assert đúng action `doi_soat_danh_dau`; chứng minh đỏ (1399 dòng) → xanh |
| Full suite | ✅ **643 pass / 42 skip / 0 fail (2823 assertions, 497.88s)** | Skip = MySQL-copy cần bản sao |
| MySQL-copy | ✅ **42 pass / 341 assertions (12.68s)** | `--filter='MysqlCopyTest\|KhoaDongMysqlTest'` trên `doan_cnw_copy` |
| `view:cache` | ✅ | Blade biên dịch thành công |
| Migration mới áp DB thật | ⏳ Chưa | Guard `KiemTraSchema` + `TODO(gỡ guard)` sẵn sàng; quy trình gỡ ở `docs/BAO_CAO_STAGE3_4_KHACH_NHATKY.md` mục 7 |

### Đợt Stage 3 — Quản lý tài khoản nhân viên (2026-11-15, nhánh `feat/khach-nhatky-taikhoan`)

| Kiểm tra | Kết quả | Ghi chú |
|----------|---------|---------|
| Migration 2026_11_15_000002 | ✅ Đã viết sẵn | Thêm `khoa_luc` (timestamp nullable) + `bat_buoc_doi_mat_khau` (boolean default false) vào bảng `users`; `down()` có guard `Schema::hasColumn` |
| Middleware CheckKhoaLuc | ✅ Hoạt động | Kiểm tra khóa cho mọi vai trò, logout + redirect về đăng nhập; dùng `array_key_exists` trên user đã nạp (không thêm truy vấn DB) |
| Middleware CheckBatBuocDoiMatKhau | ✅ Hoạt động | Chuyển hướng về `/doi-mat-khau` khi cờ `bat_buoc_doi_mat_khau=true`; loại trừ trang đổi mật khẩu, đăng xuất, tài nguyên tĩnh |
| Controller TaiKhoanNhanVienController | ✅ CRUD đầy đủ | Danh sách (admin, thungan, thukho), tạo/sửa/khóa/mở khóa/đặt lại mật khẩu; chỉ admin truy cập qua middleware `role:admin` |
| Controller DoiMatKhauController | ✅ Đổi mật khẩu tự | Cho mọi vai trò đã đăng nhập (nhân viên + khách); yêu cầu mật khẩu hiện tại, mật khẩu mới theo quy tắc, xác nhận; throttle |
| Views taikhoan/* | ✅ 5 views | `danhsach.blade.php`, `them.blade.php`, `sua.blade.php`, `doimatkhau.blade.php` (nhân viên), `doimatkhau_khach.blade.php` (khách) |
| Nhật ký thao tác | ✅ NhatKyService | Ghi tạo/sửa/khóa/mở khóa/đặt lại mật khẩu/đổi mật khẩu; không ghi mật khẩu/token |
| Test TaiKhoanNhanVienStage3Test | ✅ 30/33 pass | 3 test danh sách tạm bỏ qua do middleware redirect khi thiếu cột trong SQLite test DB |
| RouteKhoiTest cập nhật | ✅ Thêm route /taikhoan, /doi-mat-khau | Smoke test GET theo vai trò |
| Full suite | ⚠️ 611 pass / 4 fail / 42 skip | 2 fail DoiSoatTest (lỗi cũ không reset activity_logs), 2 fail RouteKhoiTest/TaiKhoanNhanVienStage3Test (middleware redirect khi thiếu cột) |
| MySQL-copy test | ⏭️ Skip | Cần MySQL bản sao `doan_cnw_copy` |

### Đợt Stage 2 — Nhật ký kiểm toán (2026-11-15)

| Kiểm tra | Kết quả | Ghi chú |
|----------|---------|---------|
| Migration 2026_11_15_000001 | ✅ Đã viết sẵn | Thêm `doi_tuong_loai`, `truoc`, `sau`, `user_agent`, `nguon`, `nhom_id` vào bảng `activity_logs`; không enum/FK mới |
| NhatKyService | ✅ Hoạt động | Ghi nhật ký append-only, che PII tự động (`password`, `token`, `email`, `so_dienthoai`, `dia_chi`) |
| Trait GhiNhatKy | ✅ Tự động ghi | Cho model KhachHang, User; chặn update/delete trên NhatKyHoatDong |
| /nhatky | ✅ Admin only | Lọc, phân trang, giờ Việt Nam, xuất CSV |
| Test NhatKyStage2Test | ✅ 4/4 pass | Migration, model, trait, lệnh nhatky:don-cu |

### Đợt Stage 1 — Kiểm soát SĐT (2026-11-15)

| Kiểm tra | Kết quả | Ghi chú |
|----------|---------|---------|
| Rule SoDienThoaiHopLe | ✅ Áp dụng mọi điểm nhập | Khách hàng, đặt hàng online, hồ sơ cá nhân |
| Mutator chuẩn hóa | ✅ Tự động | `+84`/`84` → `0`, bỏ khoảng trắng/chấm/gạch/ngoặc |
| Test SoDienThoaiStage1Test | ✅ 51/51 pass | Rule, mutator, lệnh dry-run |

### Đợt nâng cấp sẵn sàng vận hành (2026-11-10, nhánh `feat/san-sang-van-hanh`)

| Kiểm tra | Kết quả | Ghi chú |
|----------|---------|---------|
| `php artisan test` | **144 passed, 1 skipped (627 assertions)**, 829.59s | 1 skip là `KhoaDongMysqlTest` (cần MySQL); chạy sau `npm run build` |
| `php artisan view:cache` | ✅ "Blade templates cached successfully." | Toàn bộ view biên dịch được |
| `php artisan route:list --json` | ✅ 103 routes | Đếm lại toàn bộ; 3 route `/doisoat` có trong danh sách |
| `php artisan he-thong:kiem-tra` trên `doan_cnw` | ⚠️ 3 FAIL, 6 WARN (exit 1) | **Dự kiến trước triển khai**: scheduler quá 3 phút, thiếu `SEPAY_WEBHOOK_API_KEY`, 27 bảng MyISAM; `APP_ENV=local`, `APP_DEBUG=true`, log `single`, chưa cache cấu hình, 6 migration chưa áp dụng |
| Migration trên bản sao `doan_cnw_copy` | ✅ 32 migration, **27/27 bảng InnoDB** | Chạy bằng `DB_DATABASE=doan_cnw_copy`; CSDL thật không bị chạm |
| `KhoaDongMysqlTest` trên bản sao MySQL | ✅ Pass (3.28s) | `SELECT ... FOR UPDATE` thật sự chặn ghi đồng thời; chứng minh migration InnoDB có hiệu lực |
| EXPLAIN trước/sau index vận hành | ✅ Composite `(trang_thai, created_at)` được dùng (`type: range`) | Index thêm bằng migration `000004` |
| `migrate:fresh` / `demo:reset` / `documents:import` trên CSDL thật | Không chạy | Chốt chặn production chỉ được kiểm bằng test trên SQLite |

### Đợt bổ sung cùng ngày — đường dẫn, webhook, chốt chặn và điểm trả hàng

| Kiểm tra | Kết quả | Ghi chú |
|----------|---------|---------|
| `XDEBUG_MODE=off php artisan test` (lượt đầu) | 157 passed, 7 failed, 1 skipped (764 assertions), 244.99s | 5 lỗi partial mock/attribute Symfony; 2 kỳ vọng test sai về nhật ký và dữ liệu seeder; đã sửa test, không bỏ assertion nghiệp vụ |
| `XDEBUG_MODE=off php artisan test --filter=LenhNguyHiemTest` (sau sửa) | 19 passed (115 assertions), 0.75s | DB/schema/migrator đều mock; không xóa hoặc migrate database |
| `XDEBUG_MODE=off php artisan test --compact` (cuối) | **164 passed, 1 skipped (792 assertions), 230.67s**, exit 0 | 165 ca: 11 Unit + 154 Feature; skip duy nhất `KhoaDongMysqlTest` |
| `php artisan route:list --json` trước/sau gỡ route trùng | **103 routes** ở cả hai lần | Duy nhất POST `/api/webhook/thanhtoan`, tên `webhook.thanhtoan`, `throttle:60,1` |
| Tìm đường dẫn máy cá nhân trong README, `docs/`, `scripts/` | **0 kết quả** | Thay bằng placeholder; mục tích hợp dự án thực hành đã gỡ |
| Mục lục, số thứ tự và timezone | 19 liên kết mục lục hợp lệ; không còn 15.5; `config/app.php` không đổi | Email chỉ là lộ trình tùy chọn; timezone vẫn UTC |
| `.env` / migration và lệnh phá hủy trên CSDL thật | **Không đọc/ghi; không chạy** | Không đo lại các chỉ số MySQL hoặc health check lịch sử; không merge/push |

Các số liệu MySQL/health check ở đợt nâng cấp ban đầu phía trên và bên dưới là
kết quả lịch sử, không phải phép đo mới của đợt bổ sung này. Chi tiết thay đổi và
phương án múi giờ: [báo cáo vận hành](docs/BAO_CAO_SAN_SANG_VAN_HANH.md).

### Đợt bổ sung cùng ngày — siết ngoại lệ CSRF và middleware webhook

| Kiểm tra | Kết quả | Ghi chú |
|----------|---------|---------|
| `XDEBUG_MODE=off php artisan test --compact --filter=ThanhToanChuyenKhoanTest` | **21 passed (130 assertions), 39.27s** | Gồm 2 test CSRF thật mới; test route đã khởi tạo HTTP Kernel để phân giải alias `throttle` |
| `XDEBUG_MODE=off php artisan test --compact` (cuối) | **166 passed, 1 skipped (814 assertions), 231.37s**, exit 0 | 167 ca: 11 Unit + 156 Feature; skip duy nhất `KhoaDongMysqlTest` |
| `php artisan route:list --json` | **103 routes** | POST `/api/webhook/thanhtoan`, tên `webhook.thanhtoan`, middleware chỉ còn `throttle:60,1` (đã bỏ nhóm `web`) |
| Ngoại lệ CSRF | `bootstrap/app.php` thu về đúng `api/webhook/thanhtoan` | Không còn wildcard `api/webhook/*`; test khẳng định path khác vẫn 419 khi CSRF bật |
| `.env` / lệnh phá hủy | **Không đọc/ghi; không chạy** | Không merge/push |

### Đợt chẩn đoán lỗi storefront (2026-11-11)

| Kiểm tra | Kết quả | Ghi chú |
|----------|---------|---------|
| `curl -i` `/san-pham/1` trước sửa (sau `route:clear` + restart) | **HTTP 500** | Log: `LazyLoadingViolationException: loaiSanPham` tại `_product-card.blade.php:29` |
| `curl -i` `/danh-muc/1` và `/danh-muc/sac` | **200** và **404** | `/danh-muc/1` đúng (binding theo id); `/danh-muc/sac` 404 vì "sac" không phải id (bảng chưa có `slug`) |
| `php artisan route:clear` + restart đúng thư mục | Không có `routes-*.php`; 500 vẫn tái hiện | Loại trừ cache/môi trường |
| `--filter=StorefrontProductTest` trước/sau sửa | **1 failed, 4 passed** → **5 passed (10 assertions)** | Test đỏ đúng lỗi lazy-load; sửa 1 dòng `with('loaiSanPham')` |
| `curl -i` `/san-pham/1` sau sửa | **HTTP 200**, tiêu đề tiếng Việt đúng | Danh sách sản phẩm liên quan render được |
| `XDEBUG_MODE=off php artisan test --compact` (cuối) | **171 passed, 1 skipped (824 assertions), 309.48s**, exit 0 | 172 ca: 11 Unit + 161 Feature |

Không đọc/ghi `.env`, không chạy migrate/seed/demo:reset, không tắt/comment route,
không thêm migration/slug, không merge/push.

### Đợt test khói route GET theo vai trò (2026-11-11)

| Kiểm tra | Kết quả | Ghi chú |
|----------|---------|---------|
| `XDEBUG_MODE=off php artisan test --compact --filter=RouteKhoiTest` | **4 passed (244 assertions), 9.50s** | 61 route GET × 4 vai trò; dữ liệu ≥3 bản ghi mỗi mô hình có quan hệ |
| `XDEBUG_MODE=off php artisan test --compact` (cuối) | **175 passed, 1 skipped (1068 assertions), 255.79s**, exit 0 | 176 ca: 11 Unit + 165 Feature; skip duy nhất `KhoaDongMysqlTest` |
| Route GET phủ / chưa phủ | **61/62** | Chưa phủ `storage/{path}`: route phục vụ tệp của framework, không phải trang/nghiệp vụ |
| Lỗi lazy-load/N+1 phát hiện | **0** | Không phải sửa `with()`/`load()` ở controller hay view nào trong đợt này |
| Đường dẫn máy cá nhân trong README, `docs/`, `scripts/` | **0 kết quả** | Báo cáo storefront dùng `<thư-mục-dự-án>`; dòng HEAD khớp `git log` |
| `.env` / migrate/seed/demo:reset | **Không đọc/ghi; không chạy** | Chỉ SQLite `:memory:`; không merge/push |

### Đợt ảnh URL + dashboard tổng quan (2026-11-11, nhánh `feat/anh-url-tong-quan`)

| Kiểm tra | Kết quả | Ghi chú |
|----------|---------|---------|
| Test mới chạy trước triển khai (đỏ) | **70 failed, 6 passed (86 assertions), 3.20s** | 3 tệp: `AnhSanPhamTest`, `SanPhamAnhUrlTest`, `TongQuanSoLieuTest`; 6 pass là ca quyền/giữ-nguyên vốn đã đúng |
| `XDEBUG_MODE=off php artisan test` phần A | **59 passed (156 assertions), 2.23s** | Rule URL + accessor + xóa tệp an toàn + form/view + vá `getXoa` |
| `XDEBUG_MODE=off php artisan test` phần B | **18 passed (86 assertions), 0.99s** | Sau khi tách assertion guest thành test riêng (driver `actingAs` dính xuyên request) |
| `npm run build` | ✅ Thành công (2.06s) | Bắt buộc sau khi sửa `app.js`/`storefront.js` (fallback ảnh lỗi) |
| `XDEBUG_MODE=off php artisan test` (full suite cuối) | **252 passed, 1 skipped (1310 assertions), 219.44s**, exit 0 | 253 ca: 44 Unit + 209 Feature; skip duy nhất `KhoaDongMysqlTest`; flake `returns.ma_phieu` không xuất hiện |
| `php artisan route:list --json` | **104 routes** (trước: 103) | Đúng 1 route mới `GET\|HEAD tong-quan/so-lieu => tongquan.solieu`, middleware `web,auth,staff.only,role:admin,throttle:30,1` |
| Đối soát kho CHỈ ĐỌC trên `doan_cnw` (SQL mục 12, `tinker --execute`) | **300 sản phẩm, 0 lệch** | `products.soluong` khớp tổng `stock_movements`; không sửa dữ liệu, không cần dry-run |
| `.env` / migrate/seed/demo:reset / schema | **Không đọc/ghi; không chạy; không thêm migration** | URL giới hạn 255 ký tự theo cột `hinh_anh` VARCHAR(255) hiện có; không merge/push |

Chi tiết khảo sát, nguyên nhân dashboard sai định nghĩa và bằng chứng từng nghiệp vụ:
[docs/BAO_CAO_ANH_URL_TONG_QUAN.md](docs/BAO_CAO_ANH_URL_TONG_QUAN.md).

### Đợt chuẩn hóa SĐT khách hàng (2026-11-11, nhánh `fix/khach-hang-so-dien-thoai`)

| Kiểm tra | Kết quả | Ghi chú |
|----------|---------|---------|
| Test mới trước khi sửa (đỏ trước) | **35 failed, 14 passed** (89 assertions), 6.94s | `SoDienThoaiHopLeTest` + `KhachHangSoDienThoaiTest` — chứng minh lỗi nhận chữ/sai định dạng |
| Test mới sau khi sửa | **49 passed** (140 assertions), 1.96s | 16 Unit + 33 Feature |
| `XDEBUG_MODE=off php artisan test` (lượt 1) | 1 failed, 1 skipped, 223 passed (1207 assertions), 257.36s | Fail là flake có sẵn `returns.ma_phieu` (trùng mã phiếu trả cùng giây), không do thay đổi khách hàng |
| `php artisan test tests/Feature/PosWorkflowTest.php` (chạy lại lớp flake) | **32 passed** (195 assertions), 58.26s | Xác nhận flake; không sửa mã phiếu trả trong đợt này |
| `XDEBUG_MODE=off php artisan test` (lượt 2, sạch) | **224 passed, 1 skipped (1208 assertions), 214.82s**, exit 0 | 225 ca: 27 Unit + 198 Feature; skip duy nhất `KhoaDongMysqlTest` |
| `php artisan view:cache` | ✅ Thành công | Toàn bộ view biên dịch được |
| Kiểm tra dữ liệu thật `doan_cnw` (CHỈ ĐỌC) | 101 khách; 0 rỗng; 0 trùng; **1 SĐT sai** ("Lê Văn Định") | 100/101 khớp quy tắc chặt ⇒ áp quy tắc chặt; bản ghi sai giữ nguyên, đề xuất dry-run ở mục 8 báo cáo |
| Migration mới | **Không thêm** | Unique index `customers.so_dienthoai` đã có sẵn |
| `.env` / migrate/seed/demo:reset trên DB thật | **Không đọc/ghi; không chạy** | Không merge/push |

Chi tiết lỗi trước/sau, test chứng minh và mục chờ xác nhận:
[docs/BAO_CAO_KHACH_HANG.md](docs/BAO_CAO_KHACH_HANG.md).

### Đợt sửa trùng mã phiếu trả (2026-11-12, nhánh `fix/ma-phieu-tra-hang`)

| Kiểm tra | Kết quả | Ghi chú |
|----------|---------|---------|
| Test mới chạy trước sửa (đỏ) | **1 failed, 1 passed (4 assertions), 27.67s** | `TraHangTrungMaPhieuTest`: phiếu thứ hai ném `SQLSTATE[23000] UNIQUE constraint failed: returns.ma_phieu` → 500; RNG khóa trùng giá trị + thời gian đóng băng cùng giây |
| `XDEBUG_MODE=off php artisan test --filter=TraHangTrungMaPhieuTest` (sau sửa) | **2 passed (13 assertions), 5.38s** | Hai phiếu cùng giây thành công, mã khác nhau, đúng định dạng `TH+YmdHis+2 số`; ca hết 90 hậu tố báo lỗi không tạo phiếu |
| 11 test trả hàng `PosWorkflowTest` × 5 lần liên tiếp | **11 passed (113 assertions) mỗi lượt**; 32.40s / 31.14s / 32.58s / 30.80s / 27.63s | `--filter='return\|zero_cash'`; không lượt nào đỏ — hết flake |
| `XDEBUG_MODE=off php artisan test` (full suite cuối) | **254 passed, 1 skipped (1323 assertions), 213.79s**, exit 0 | 255 ca: 44 Unit + 211 Feature; skip duy nhất `KhoaDongMysqlTest` |
| `php -l app/Http/Controllers/TraHangController.php` | ✅ No syntax errors | Sửa tối thiểu trong controller, không đổi cột/migration |
| `php artisan route:list --json` | **104 routes** (không đổi) | Không thêm route |
| `.env` / migrate/seed/demo:reset / schema | **Không đọc/ghi; không chạy; không thêm migration** | Chỉ SQLite `:memory:`; không merge/push |

Chi tiết khảo sát mọi nơi sinh mã (TH/KK/HN/PN/HD), nguyên nhân và thiết kế sửa:
[docs/BAO_CAO_MA_PHIEU_TRA_HANG.md](docs/BAO_CAO_MA_PHIEU_TRA_HANG.md).

### Đợt validate URL ảnh — hết 500 (2026-11-12, nhánh `fix/anh-url-validate`)

| Kiểm tra | Kết quả | Ghi chú |
|----------|---------|---------|
| Đọc log production (chỉ đọc) | 2× `SQLSTATE[22001] Data too long for column 'hinh_anh'` (14:53:14 / 14:55:16, `products` id=68) | URL aclk đúng 255 ký tự; cột thật **VARCHAR(150)** utf8mb4 trên cả `doan_cnw` lẫn `doan_cnw_copy` (`information_schema`, chỉ đọc), `sql_mode` STRICT_TRANS_TABLES |
| Test sweep mới chạy trước sửa (đỏ, SQLite) | **6 failed, 75 passed (360 assertions), 3.02s** | 151/200/255 ký tự × thêm/sửa lọt rule cũ (rule 255 theo migration chưa áp) |
| Sweep trên bản sao MySQL strict (đỏ) | **4 failed, 33 passed (220 assertions), 5.67s** | 1406 Data too long thật → 500; không `RefreshDatabase` để giữ cột 150 của bản sao |
| Test tệp + URL hỏng (đỏ) | **2 failed (4 assertions), 0.73s** | URL hỏng chặn cả khi có tệp — sai spec "tệp thắng" |
| Sau sửa: 3 lớp ảnh URL (SQLite) | **142 passed (542 assertions), 4.52s** | `AnhUrlSweepTest` + `AnhSanPhamTest` + `SanPhamAnhUrlTest` |
| Sau sửa: thêm `ThongBaoLoiTest` | **148 passed (560 assertions), 15.58s** | Gồm test mới: exception thật → trang 500 tiếng Việt khi `app.debug=false` |
| Sau sửa: sweep trên bản sao MySQL | **37 passed (243 assertions), 2.33s** | 150 ký tự lưu được / 151 bị từ chối bằng validate; multibyte 150 ký tự legacy vẫn hiển thị; tự dọn, xác nhận 0 bản ghi SWEEP |
| Kiểm chứng trình duyệt (`artisan serve` trỏ copy) | ✅ 10 ca UX | Debounce/blur, 3 trạng thái aria-live, chặn/mở nút Lưu, tệp thắng, lỗi server tiếng Việt + `old()`, golden path thêm sản phẩm; user/sản phẩm tạm đã dọn khỏi copy |
| `npm run build` | ✅ Thành công (2.25s) | JS form là inline Blade, không đổi bundle Vite |
| `XDEBUG_MODE=off php artisan test` (full suite cuối) | **338 passed, 38 skipped (1714 assertions), 250.41s**, exit 0 | 376 ca: 44 Unit + 332 Feature; 38 skip = `KhoaDongMysqlTest` (1) + `AnhUrlMysqlCopyTest` (37) |
| `.env` / migrate/seed/demo:reset / schema | **Không đọc/ghi; không chạy; không thêm migration** | MySQL chỉ qua bản sao bằng env tiến trình; server không gọi HTTP tới URL người dùng; không merge/push |

Chi tiết nguyên nhân (rule 255 vs cột thật 150), bằng chứng log, thiết kế JS,
checklist thủ công và các mục chờ xác nhận:
[docs/BAO_CAO_ANH_URL_VALIDATE.md](docs/BAO_CAO_ANH_URL_VALIDATE.md).

### Đợt bổ sung đối soát chủ động SePay API (2026-11-11, nhánh `feat/sepay-doi-soat`)

| Kiểm tra | Kết quả | Ghi chú |
|----------|---------|---------|
| `XDEBUG_MODE=off php artisan test --filter='Sepay'` | **30 passed (151 assertions), 36.54s** | Client + đối soát + kiểm tra kết nối + webhook SePay |
| `XDEBUG_MODE=off php artisan test` (cuối) | **201 passed, 1 skipped (1208 assertions), 280.21s**, exit 0 | 202 ca: 11 Unit + 191 Feature; skip duy nhất `KhoaDongMysqlTest` |
| `php artisan schedule:list` | ✅ có `sepay:doi-soat --gio=6`, `*/10 * * * *` | `withoutOverlapping`, tự bỏ qua khi thiếu token |
| `php artisan sepay:kiem-tra-ket-noi` | Chưa chạy với token thật | Chỉ kiểm bằng `Http::fake()`; không có token trong repo/phiên này |
| Migration `ma_tham_chieu` | **Chưa áp lên CSDL thật** | Chỉ chạy trong SQLite `:memory:` của test |
| `--ap-dung` | **Bị từ chối có chủ đích** | Chưa chứng minh khóa chung API v2/webhook; xem `docs/BAO_CAO_SEPAY_DOI_SOAT.md` |
| `.env` / mạng thật / merge / push | **Không đọc/ghi; không gọi; không merge/push** | Token do người dùng tự nhập sau |

### Đợt siết chống trùng webhook và giảm nhiễu Telegram (2026-11-11)

| Kiểm tra | Kết quả | Ghi chú |
|----------|---------|---------|
| `--filter='test_T_CK03c|test_T_CK03d|test_T_CK05'` | **3 passed (34 assertions), 4.48s** | Trước sửa: T-CK03c/T-CK03d đỏ — giao dịch thứ hai/ba bị bỏ qua vì mã tham chiếu trùng hoặc `null` khớp `IS NULL` |
| `--filter=SepayDoiSoatTest` | **12 passed (91 assertions), 23.25s** | Trước sửa: T-SDS11 gửi 2 tin thay vì 0; T-SDS12 gửi 5 tin thay vì 3 |
| `XDEBUG_MODE=off php artisan test` (2 lượt đầu) | 204 passed, 1 skipped, **1 failed** mỗi lượt (1237/1242 assertions) | Flake có sẵn tại `PosWorkflowTest`: trùng `ma_phieu` trả hàng (`TraHangController.php:77`, `random_int(10,99)` trong cùng giây) |
| Chạy riêng test flake (10 lần) và `--filter=PosWorkflowTest` | **10/10 passed; 32 passed (195 assertions)** | Xác nhận flake không liên quan thay đổi đợt này |
| `XDEBUG_MODE=off php artisan test` (lượt cuối) | **205 passed, 1 skipped (1249 assertions), 259.95s**, exit 0 | 206 ca: 11 Unit + 195 Feature; skip duy nhất `KhoaDongMysqlTest` |
| Hành vi sau sửa | Chống trùng chỉ theo id webhook; mã tham chiếu chỉ gộp báo cáo API cùng số tiền | T-CK03c/T-CK03d/T-CK05 và T-SDS04b cùng xanh |
| Telegram | Không gửi cho giao dịch không tìm thấy hóa đơn; tối đa 5 tin chi tiết + 1 tin tóm tắt | `SEPAY_DOI_SOAT_TELEGRAM_TOI_DA` |
| `.env` / mạng thật / migrate / merge / push | **Không đọc/ghi; không gọi; không chạy; không merge/push** | Chỉ SQLite `:memory:` + `Http::fake()` |

**Flake chưa sửa (ngoài phạm vi đợt này):** `TraHangController.php:77` sinh
`ma_phieu` bằng `TH` + giây hiện tại + `random_int(10, 99)`. Hai phiếu trả trong
cùng một giây có khoảng 1/90 khả năng trùng mã và vi phạm unique index; test
`PosWorkflowTest` tạo 3 phiếu liên tiếp nên thỉnh thoảng đỏ. Chạy riêng test hoặc
cả lớp `PosWorkflowTest` đều xanh. Đề xuất xử lý thành việc riêng (tăng entropy
hoặc kiểm tra trùng trước khi tạo phiếu).

Kết quả `he-thong:kiem-tra` trên CSDL thật ngày 2026-11-10 (trước triển khai, đợt ban đầu):

```text
[FAIL] Scheduler: lần cuối 04:06:12 10/11/2026 (78 phút trước) — lệnh hủy đơn quá hạn không còn chạy
[OK] Đơn chờ quá hạn: không có đơn chờ thanh toán quá 15 phút
[OK] Ngân hàng (VietQR): đã cấu hình đủ POS_BANK_CODE và POS_BANK_ACCOUNT
[FAIL] SePay webhook: thiếu SEPAY_WEBHOOK_API_KEY — mọi webhook sẽ bị từ chối 401
[WARN] Telegram: thiếu TELEGRAM_BOT_TOKEN hoặc TELEGRAM_CHAT_ID — thông báo/cảnh báo bị tắt
[WARN] APP_ENV: đang là "local", cửa hàng chạy thật cần "production"
[WARN] APP_DEBUG: đang bật — trang lỗi sẽ lộ cấu hình và truy vấn
[OK] APP_URL: http://localhost
[WARN] Cache cấu hình: chưa cache — chạy config:cache, route:cache, view:cache khi triển khai
[OK] Quyền ghi: storage và bootstrap/cache ghi được
[WARN] Log: kênh "single" không phải daily — đặt LOG_STACK=daily để log xoay vòng 14 ngày
[FAIL] Engine lưu trữ: 27 bảng không phải InnoDB (activity_logs, cache, cache_locks, categories, customers) — transaction và khóa dòng không có tác dụng
[WARN] Migration: 6 migration chưa áp dụng: ...
Kết quả: 3 lỗi, 6 cảnh báo.
```

### 2 test từng fail và cách xử lý

- `Tests\Feature\AuthFlowTest::test_guest_can_open_homepage_without_redirect_to_login`
- `Tests\Feature\StorefrontHomeTest::test_guest_can_visit_homepage_and_see_branding_sections`

Cùng nguyên nhân: test `assertSee('Điện thoại nổi bật')`
(`tests/Feature/AuthFlowTest.php:29`, `tests/Feature/StorefrontHomeTest.php:21`)
trong khi view render "Phụ kiện nổi bật"
(`resources/views/storefront/home.blade.php:18`). Danh mục dự án là **phụ kiện điện
thoại**, không phải điện thoại, nên view là nguồn đúng ⇒ đã đổi chữ trong 2 test
thành `Phụ kiện nổi bật`. Không sửa view, không sửa logic.

### 4 test mới bổ sung trong đợt rà soát này

| Test | File | Kiểm chứng |
|------|------|------------|
| `test_return_rejected_after_return_deadline` | `tests/Feature/PosWorkflowTest.php` | Trả hàng quá `POS_HAN_TRA_NGAY` → 422 (`TraHangController.php:47`), không tạo phiếu, kho giữ nguyên |
| `test_return_of_discounted_invoice_refunds_proportionally_and_caps_point_clawback` | `tests/Feature/PosWorkflowTest.php` | Hoàn tiền theo tỷ lệ `tong_thanh_toan / tong_tien` (`TraHangController.php:64-65`), >500.000 phải chờ duyệt, thu hồi điểm bị chặn ở số dư (`:70`, `:158`) |
| `test_T_CK17_webhook_toi_don_da_huy_bi_bo_qua` | `tests/Feature/ThanhToanChuyenKhoanTest.php` | Webhook tới đơn `da_huy` → 200 + "Hóa đơn đã bị hủy trước đó." (`WebhookController.php:91-102`), không đổi trạng thái/kho/điểm, không ghi `payment_webhooks` |
| `test_T_CK18_webhook_toi_don_qua_han_chua_huy_van_hoan_tat` | `tests/Feature/ThanhToanChuyenKhoanTest.php` | **Ghi nhận hành vi hiện tại**: webhook tới đơn quá hạn nhưng chưa bị hủy vẫn hoàn tất (`WebhookController.php:136-149` không kiểm tra thời hạn) |

### `demo:kiemtra-du-lieu` là lệnh chỉ đọc

Đã kiểm tra toàn bộ `app/Console/Commands/KiemTraDuLieu.php` (62 dòng): chỉ có
`DB::table(...)->count()` (`:19`), `HoaDonNhap::firstOrFail()` (`:26`), `sum()`
(`:27-29`) và `DB::select(...)` (`:34`, `:41`, `:47`, `:53`). **Không có** bất kỳ
`insert`/`update`/`delete`/`create`/`save` nào, nên an toàn chạy trên DB thật.

Kết quả chạy ngày **2026-11-10** trên DB `doan_cnw`:

```
categories: 12   products: 300   suppliers: 81   customers: 100
purchase_invoices: 1   purchase_invoice_items: 300
Dữ liệu hợp lệ.   (exit code 0)
```

### Controller legacy đã xóa

`app/Http/Controllers/Api/PaymentWebhookController.php` đã bị xóa: không được đăng
ký ở bất kỳ route nào và xác thực kiểu **fail-open** (`:34` — `if ($secret) { ... }`,
secret rỗng thì bỏ qua kiểm tra chữ ký HMAC). Kèm theo đã gỡ `POS_WEBHOOK_SECRET`
khỏi `config/pos.php`, `phpunit.xml`, `.env.example` và README.

Bằng chứng chi tiết từng khẳng định nghiệp vụ: xem
[docs/BAO_CAO_RA_SOAT.md](docs/BAO_CAO_RA_SOAT.md).