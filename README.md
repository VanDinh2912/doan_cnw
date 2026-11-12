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
| `TELEGRAM_BOT_TOKEN` | Không | (rỗng) | Token Telegram Bot | Thông báo |
| `TELEGRAM_CHAT_ID` | Không | (rỗng) | Chat ID nhận thông báo | Thông báo |
| `POS_DIEM_MOI_DONG` | Không | 100000 | Doanh thu để được 1 điểm | Tích điểm |
| `POS_GIA_TRI_DIEM` | Không | 1000 | Giá trị 1 điểm (VNĐ) | Tích điểm |
| `POS_HAN_TRA_NGAY` | Không | 7 | Hạn trả hàng (ngày) | Trả hàng |
| `POS_NGUONG_TRA_KHONG_DUYET` | Không | 500000 | Ngưỡng tự duyệt trả hàng (VNĐ) | Trả hàng |
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

1. **Sao lưu CSDL hiện tại** bằng `mysqldump` (hoặc `php artisan sao-luu:csdl`) và
   giữ bản sao ở ổ khác.
2. **Cấu hình `.env`** (chỉ sửa `.env` trên máy chủ, không commit):
   `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` đúng tên miền/IP,
   `DB_*` trỏ CSDL thật, `LOG_STACK=daily`, `LOG_DAILY_DAYS=14`.
   Thêm các dòng còn thiếu: `POS_BANK_CODE`, `POS_BANK_ACCOUNT`, `POS_BANK_NAME`,
   `SEPAY_WEBHOOK_API_KEY`, `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID`,
   `BACKUP_DISK`, `BACKUP_GIU_BAN`, `BACKUP_TIMEOUT`, `DB_DUMP_BINARY` (xem bảng biến
   môi trường ở mục 3.4).
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
- Quản lý khách hàng theo số điện thoại và tích điểm: 100.000 đ được 1 điểm, 1 điểm giảm 1.000 đ.
- Không cho bán vượt tồn kho.

Thanh toán sử dụng một transaction duy nhất:

1. Khóa sản phẩm bằng `lockForUpdate()`.
2. Kiểm tra tồn kho lần cuối.
3. Tạo hóa đơn và chi tiết hóa đơn.
4. Trừ tồn kho.
5. Ghi biến động kho loại `ban`.
6. Rollback toàn bộ nếu có lỗi.

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
  (idempotent) bằng cả hai cột `ma_giaodich` và `ma_giao_dich` (đều có unique
  index trong migration).
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
- Cấu hình trong `.env`: `SEPAY_WEBHOOK_API_KEY`, `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID`, `POS_CHO_THANH_TOAN_PHUT`.

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

Các test nghiệp vụ hiện có (**376 test**: 338 pass, 38 skip; 1714 assertions — xem
kết quả chạy thực tế ở mục 18):

**Unit (44 test)**

- `AnhSanPhamTest` (33): rule URL ảnh (6 hợp lệ gồm CDN không đuôi/đúng 150 ký tự, 13
  lỗi gồm javascript/data/http/userinfo/khoảng trắng/mảng/quá 150), accessor `anh_url`
  (https/tệp tải lên/legacy/rỗng), xóa tệp an toàn (chỉ thư mục ảnh, chặn
  traversal/backslash/URL, legacy `upload/` với tệp thật tạm).
- `ChanLenhPhaHuyTest` (5): chốt chặn production cho lệnh phá hủy dữ liệu.
- `VietQrServiceTest` (5, t61..t65): URL VietQR, chưa cấu hình, số tiền âm, làm
  sạch nội dung, template dự phòng.
- `ExampleTest` Unit (1).

**Feature (332 test, trong đó 38 skip)**

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
- `DoiSoatTest` (8): hàng chờ đối soát, phân quyền admin, đánh dấu đã xử lý, chặn
  xử lý lặp, webhook thành công không vào hàng chờ.
- `ExampleTest` Feature (1).
- `KhoaDongMysqlTest` (1, **skip trên SQLite**): khóa dòng `SELECT ... FOR UPDATE`
  thật trên MySQL/InnoDB — chỉ chạy khi trỏ vào bản sao MySQL (xem bên dưới).
- `KiemTraHeThongTest` (13): từng mục của `he-thong:kiem-tra`, gồm scheduler,
  cấu hình thiếu/đủ, log, engine, migration, mã thoát.
- `LenhNguyHiemTest` (19 ca): chốt chặn `migrate:fresh`, `db:wipe`, `demo:reset`,
  `documents:import`; thiếu cờ/tên, sai DB, kết nối đích, xác nhận tương tác,
  chuyển tiếp lệnh con và không tái sử dụng xác nhận. Dùng mock DB/schema/migrator,
  không xóa bảng hay chạy migration thật.
- `LogBiMatTest` (5): log daily giữ 14 ngày, che bot token Telegram khi lỗi.
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
- `StorefrontHomeTest` (6): trang chủ storefront, sản phẩm thật, sắp xếp loại theo
  số sản phẩm, trạng thái rỗng, header theo vai trò.
- `StorefrontProductTest` (5): chi tiết sản phẩm/danh mục trả 200 và hiển thị tên,
  id không tồn tại trả 404 trang lỗi tiếng Việt, link danh mục trên trang chủ trỏ
  đúng route; tái hiện lỗi lazy-load `loaiSanPham` ở danh sách sản phẩm liên quan.
- `TacVuNenTest` (2): `hold:clean` chỉ xóa giỏ quá 24h, Telegram thành công.
- `ThanhToanChuyenKhoanTest` (21: T-CK01..T-CK18 + 1 test route duy nhất + 2 test
  CSRF thật): tạo QR, webhook hợp lệ/sai key/trùng lặp/dư/thiếu/out, xác nhận/hủy
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

Lưu ý khi chạy test:

- Test dùng SQLite `:memory:` qua `RefreshDatabase` (`phpunit.xml`), không đụng
  database MySQL thật.
- Phải build asset trước (`npm ci` và `npm run build`) để có
  `public/build/manifest.json`; nếu không, các test render Blade dùng `@vite`
  sẽ lỗi 500.
- `KhoaDongMysqlTest` tự skip khi không phải MySQL. Muốn chạy, trỏ vào **bản sao**
  MySQL (không bao giờ là `doan_cnw`), vì `RefreshDatabase` sẽ xóa và migrate lại
  CSDL đó:

  ```powershell
  $env:DB_CONNECTION='mysql'; $env:DB_DATABASE='doan_cnw_copy'
  $env:DB_USERNAME='root'; $env:DB_PASSWORD=''
  php artisan test --filter=KhoaDongMysqlTest
  Remove-Item Env:DB_CONNECTION, Env:DB_DATABASE, Env:DB_USERNAME, Env:DB_PASSWORD
  ```

  `AnhUrlMysqlCopyTest` cũng chạy bằng cách trỏ bản sao như trên (đổi `--filter`);
  riêng lớp này **không** `RefreshDatabase` — nó cần cột `hinh_anh` VARCHAR(150)
  thật của bản sao để tái hiện lỗi 1406, và tự dọn bản ghi nó tạo.

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
- **Kiểm thử hiện tại: 338 pass, 38 skip, 1714 assertions** (250.41s,
  `XDEBUG_MODE=off`, nhánh `fix/anh-url-validate`). 38 skip = `KhoaDongMysqlTest`
  (1) + `AnhUrlMysqlCopyTest` (37) — SQLite không có khóa dòng/InnoDB và không
  cưỡng chế độ dài VARCHAR; cần trỏ vào bản sao MySQL để chạy (mục 9). Số pass
  không thay thế kiểm tra đó.
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
- **URL ảnh đã hết lỗi 500 do quá dài (sửa 2026-11-12)**: cột thật `products.hinh_anh`
  là VARCHAR(150) utf8mb4 (migration khai 255 nhưng chưa áp lên DB thật), rule cũ
  cho lọt URL 151–255 ký tự → `SQLSTATE[22001] 1406` → 500. `AnhSanPhamUrl` nay
  giới hạn **150 ký tự** với 2 thông báo tiếng Việt riêng (không hợp lệ / quá dài);
  có tệp tải lên thì URL hỏng không chặn lưu tệp. JS form kiểm tra ảnh phía trình
  duyệt (debounce 500ms + blur, `Image()` no-referrer, timeout 8s, chặn nút Lưu)
  **chỉ là UX, không phải bảo mật** và chưa có test tự động; server vẫn là nguồn sự
  thật. URL legacy >150 ký tự vẫn hiển thị nhưng khi sửa phải xóa trắng ô URL/chọn
  tệp/xóa ảnh mới lưu được; chưa dọn dữ liệu legacy. Chi tiết + phương án
  Content-Type phía server (kèm chống SSRF) chờ xác nhận:
  [docs/BAO_CAO_ANH_URL_VALIDATE.md](docs/BAO_CAO_ANH_URL_VALIDATE.md).
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

Cập nhật lần cuối: **2026-11-12**, trên nhánh `fix/anh-url-validate` (từ
`fix/ma-phieu-tra-hang`). Số liệu của các đợt trước được giữ lại để đối chiếu.

| Kiểm tra | Kết quả | Ghi chú |
|----------|---------|---------|
| `php artisan test` (chưa build asset) | 52 passed / 10 failed (206 assertions, 133s) | 10 fail do thiếu `public/build/manifest.json` (Vite) |
| `npm ci` + `npm run build` | ✅ Thành công | Tạo `public/build/manifest.json` |
| `php artisan test` (sau build, trước khi sửa) | 60 passed / 2 failed (228 assertions, 92s) | 2 fail do lệch chữ ở test storefront, xem bên dưới |
| `php artisan test` (sau khi sửa + thêm test) | **66 passed / 0 failed** (274 assertions, 112.5s) | Toàn bộ suite xanh (đợt rà soát README) |
| `php artisan route:list` | ✅ Hoạt động | 100 routes (không đổi sau khi xóa controller legacy) |
| `php artisan demo:kiemtra-du-lieu` | ✅ Đã chạy trên DB thật, **"Dữ liệu hợp lệ."** (exit 0) | Lệnh chỉ đọc — xem minh chứng bên dưới |
| `php artisan demo:reset` | Không chạy | Xóa dữ liệu thật |

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
| `test_T_CK17_webhook_toi_don_da_huy_bi_bo_qua` | `tests/Feature/ThanhToanChuyenKhoanTest.php` | Webhook tới đơn `da_huy` → 200 + "Hóa đơn đã bị hủy trước đó." (`WebhookController.php:87-97`), không đổi trạng thái/kho/điểm, không ghi `payment_webhooks` |
| `test_T_CK18_webhook_toi_don_qua_han_chua_huy_van_hoan_tat` | `tests/Feature/ThanhToanChuyenKhoanTest.php` | **Ghi nhận hành vi hiện tại**: webhook tới đơn quá hạn nhưng chưa bị hủy vẫn hoàn tất (`WebhookController.php:104-131` không kiểm tra thời hạn) |

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