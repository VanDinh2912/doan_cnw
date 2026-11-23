# Hướng xử lý chi tiết sau khi áp migration — 2026-11-16

Bối cảnh: 5 migration đã áp lên `doan_cnw`, app đang live. Tài liệu này liệt kê
từng mục còn lại từ `he-thong:kiem-tra` + việc vận hành, kèm các bước xử lý cụ
thể. **Không chứa giá trị bí mật** — mọi token/key bạn tự điền vào `.env`.

Thứ tự ưu tiên: mục 1 (ảnh hưởng thanh toán) → mục 2-4 (vận hành) → mục 5-7 (dọn dẹp).

---

## 1. FAIL — `SEPAY_WEBHOOK_API_KEY` thiếu (ưu tiên cao nhất)

**Hiện trạng**: `he-thong:kiem-tra` báo FAIL — thiếu `SEPAY_WEBHOOK_API_KEY`
trong `.env`. Mọi webhook SePay gửi về sẽ bị từ chối **401**, tức tiền về tài
khoản ngân hàng sẽ KHÔNG tự đối soát/hoàn tất đơn online.

**Ảnh hưởng**: chỉ đơn `kenh=online` thanh toán chuyển khoản qua SePay. Đơn
POS/thường không ảnh hưởng. Nếu bạn chưa bật `POS_DAT_HANG_ONLINE_BAT` thì
chưa có đơn online mới — rủi ro hiện tại thấp, nhưng phải xử lý TRƯỚC khi
bật cờ đặt hàng online.

**Cách xử lý**:
1. Đăng nhập SePay (sepay.vn) → webhook/cài đặt tích hợp → lấy **API Key**
   của webhook (dạng chuỗi dài, KHÔNG phải API token đối soát).
2. Mở `.env` (tự tay, không gửi cho ai), thêm/sửa dòng:
   ```
   SEPAY_WEBHOOK_API_KEY=<api-key-cua-ban>
   ```
   Phân biệt với `SEPAY_API_TOKEN` (token đối soát chủ động — đã có, dùng cho
   `sepay:doi-soat`, khác mục đích).
3. Kiểm tra định dạng key trong code để chắc không thừa khoảng trắng:
   webhook so khớp header `Authorization: Apikey <key>`.
4. Chạy `php artisan config:clear` (hoặc `config:cache` nếu đã cache — xem mục 4).
5. Kiểm chứng: `php artisan he-thong:kiem-tra` → dòng SePay webhook phải `[OK]`.
6. Test thật (an toàn): dùng tính năng "gửi webhook thử" của SePay dashboard
   hoặc chuyển khoản nhỏ 1.000đ rồi xem `payment_webhooks` có bản ghi + đơn
   hoàn tất. Kiểm tra `storage/logs/laravel.log` không có 401.

**Nếu KHÔNG dùng SePay webhook** (ví dụ chỉ dùng VietQR tĩnh + xác nhận thủ
công): có thể bỏ qua FAIL này, nhưng nên ghi chú trong sổ tay rằng webhook
SePay đang tắt — cân nhắc vô hiệu hóa hẳn để he-thong:kiem-tra không báo FAIL
(việc này cần sửa code/kiểm tra thêm, báo tôi nếu muốn).

---

## 2. WARN — Telegram thiếu `TELEGRAM_BOT_TOKEN` / `TELEGRAM_CHAT_ID`

**Hiện trạng**: thông báo đơn mới, cảnh báo thiếu tiền, báo hủy đơn cần hoàn
tiền… đều **bị tắt** — bạn sẽ không nhận cảnh báo realtime trên điện thoại.

**Cách xử lý**:
1. Telegram: nhắn cho **@BotFather** → `/newbot` → đặt tên → nhận
   `TELEGRAM_BOT_TOKEN` (dạng `123456:ABC-DEF...`).
2. Tạo group/kênh riêng cho thông báo cửa hàng, thêm bot vào group.
3. Lấy `chat_id`: thêm bot **@userinfobot** vào group, gửi tin bất kỳ, bot trả
   `chat_id` (số âm, ví dụ `-100xxxxxxxxxx`).
4. Thêm vào `.env`:
   ```
   TELEGRAM_BOT_TOKEN=123456:ABC-DEF...
   TELEGRAM_CHAT_ID=-100xxxxxxxxxx
   ```
5. `php artisan config:clear` → `php artisan he-thong:kiem-tra` → phải `[OK]`.
6. Test: đặt một đơn test nhỏ hoặc chạy thử lệnh thông báo nếu có; kiểm tra
   group nhận tin. Thông báo đã escape HTML + che SĐT — không lo lộ dữ liệu.

**Nếu không muốn Telegram**: chấp nhận WARN này (chỉ là cảnh báo, hệ thống vẫn
chạy); có thể tắt hẳn để hết WARN — cần sửa code, báo tôi.

---

## 3. WARN — `sepay:doi-soat` chưa chạy lần nào

**Hiện trạng**: đối soát chủ động SePay (chế độ báo cáo, chỉ GET) chưa từng
chạy → không có dữ liệu đối chiếu giao dịch.

**Cách xử lý**:
1. Chỉ cần `SEPAY_API_TOKEN` (đã có sẵn — he-thong báo `[OK]`).
2. Chạy thử thủ công (chỉ đọc, an toàn):
   ```powershell
   php artisan sepay:doi-soat --gio=6
   ```
   Xem kết quả: khớp/lệch bao nhiêu giao dịch trong 6 giờ qua.
3. Nếu ổn, đăng ký lịch chạy (Windows Task Scheduler hoặc `schedule:run` mỗi
   phút nếu app đã có scheduler): lệnh nên chạy định kỳ, ví dụ mỗi giờ:
   `php artisan sepay:doi-soat` (không `--ap-dung` — cờ đó vẫn bị khóa).
4. Kiểm tra trang `/doisoat` trên admin để xem báo cáo đối soát.

**Lưu ý**: `--ap-dung` (tự động hoàn tất đơn từ đối soát) **vẫn bị khóa cố ý** —
không mở trừ khi có quyết định riêng.

---

## 4. WARN — `APP_ENV=local`, `APP_DEBUG` bật, chưa cache

**Hiện trạng**: app đang chạy môi trường dev trên chính DB thật. Rủi ro:
trang lỗi 500 sẽ **lộ cấu hình, truy vấn SQL, đường dẫn file** cho người xem;
`local` cũng ảnh hưởng một số hành vi (VD: một số guard chặn lệnh phá hủy chỉ
siết ở production).

**Cách xử lý khi cửa hàng chạy thật** (làm giờ thấp điểm, sau giờ đóng cửa):
1. Mở `.env`, sửa:
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://ten-mien-that-cua-ban   # hoặc http://IP:port nếu nội bộ
   ```
2. Cache lại toàn bộ (bắt buộc SAU KHI sửa .env):
   ```powershell
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan event:cache   # nếu có
   ```
3. Kiểm chứng: `php artisan he-thong:kiem-tra` → các dòng APP_ENV/APP_DEBUG/
   "Cache cấu hình" phải `[OK]`.
4. Mở lại trình duyệt kiểm tra vài trang + đăng nhập thử → không lỗi.

**CẢNH BÁO**: `config:cache` nướng giá trị `.env` vào cache — sau này sửa `.env`
phải chạy lại `config:cache` mới có hiệu lực. Nếu quên, triệu chứng là "sửa
.env không ăn".

**Nếu chỉ là máy dev nội bộ** (không public): có thể để nguyên, chấp nhận WARN.

---

## 5. WARN — `LOG_STACK` chưa phải `daily`

**Hiện trạng**: log ghi kênh `single` — `storage/logs/laravel.log` sẽ **phình vô
hạn** (hiện đã ~292k dòng), không tự xoay.

**Cách xử lý**:
1. Thêm/sửa `.env`:
   ```
   LOG_STACK=daily
   LOG_LEVEL=info        # production nên info; debug quá ồn
   LOG_DAILY_DAYS=14     # giữ 14 ngày rồi tự xóa
   ```
2. `php artisan config:cache` (nếu đã cache) hoặc `config:clear`.
3. Kiểm tra `he-thong:kiem-tra` → dòng Log phải `[OK] ghi theo ngày, giữ 14 ngày`.
4. File cũ `laravel.log` có thể đổi tên thành `laravel-single-cu.log` để giải
   phóng (không bắt buộc; hệ thống sẽ tự ghi file mới theo ngày).

---

## 6. Việc vận hành còn lại

### 6a. Sao lưu database định kỳ
- Lệnh có sẵn: `php artisan sao-luu:csdl` — cần `mysqldump` trong PATH hoặc
  `DB_DUMP_BINARY` trỏ đúng (máy này:
  `DB_DUMP_BINARY=<thư-mục-xampp>\bin\mysql\mysql8.4.7\bin\mysqldump.exe`).
- Nên chạy **mỗi ngày** qua Windows Task Scheduler (ví dụ 22:00), chép bản ra
  USB/ổ ngoài định kỳ. Hiện giữ tối đa 7 bản trong `storage/app/backups`.
- Đợt này đã tạo 3 tệp backup ngoài repo — **bạn tự chép ra USB**:
  - `<thư-mục-www>\doan_cnw_truoc_migrate_20261116_2216.sql`
  - `<thư-mục-www>\doan_cnw_truoc_migrate_that_20261116_2235.sql`
  - `storage/app/backups/doan_cnw-20261116-152541.sql` (trong repo, gitignored)

### 6b. `demo:kiemtra-du-lieu` báo "customers không đúng"
- Đây là check của **bộ dữ liệu mẫu** (kỳ vọng đúng 100 customers) — DB thật có
  101 khách (1 khách thật đăng ký thêm). **Không phải lỗi**, không cần sửa.
- Nếu muốn check toàn vẹn thật: dùng các truy vấn trong
  `docs/BAO_CAO_AP_MIGRATION.md` mục 3 (đã chạy: 0 lệch).

### 6c. DB `doan_cnw_dev`
- Đang giữ bản sao đã migrate — dùng để thử nghiệm an toàn trước khi đụng DB
  thật. Xóa khi không cần:
  ```powershell
  & "<thư-mục-xampp>\bin\mysql\mysql8.4.7\bin\mysql.exe" -u root -e "DROP DATABASE doan_cnw_dev;"
  ```
- Muốn tạo lại bản mới nhất từ doan_cnw: lặp lại quy trình mysqldump → tạo DB →
  nạp (xem `docs/SO_TAY_TRIEN_KHAI.md` mục 2).

### 6d. Bật đặt hàng online (khi sẵn sàng)
- Điều kiện tiên quyết: mục 1 (webhook) xong, Telegram nên có, đã kiểm tra tay.
- Quy trình 8 bước kiểm tra tay + bật cờ: `docs/SO_TAY_TRIEN_KHAI.md` mục 5.
- Gợi ý giá trị test: `POS_PHI_GIAO_HANG=30000`,
  `POS_MIEN_PHI_GIAO_TU=300000` (miễn ship đơn ≥300k).

### 6e. Gỡ guard schema (sau ≥ 1 tuần ổn định)
- Các guard `Schema::hasColumn`/`KiemTraSchema::coCot` hiện là "code đi trước
  migration". Sau khi DB chạy ổn ≥ 1 tuần không lỗi thiếu cột, có thể gỡ từng
  guard (danh sách file:dòng trong `docs/SO_TAY_TRIEN_KHAI.md` mục 4) — **không
  gỡ vội**, gỡ sai sẽ 500 trên DB chưa migrate.

---

## 7. Checklist nhanh cho lần tới

- [ ] `SEPAY_WEBHOOK_API_KEY` đã điền → he-thong:kiem-tra hết FAIL
- [ ] `TELEGRAM_*` đã điền (hoặc chấp nhận WARN)
- [ ] `sepay:doi-soat` đã chạy thử + lên lịch
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` đúng
- [ ] `config:cache` + `route:cache` + `view:cache` đã chạy SAU khi sửa .env
- [ ] `LOG_STACK=daily`, `LOG_DAILY_DAYS=14`
- [ ] Backup đã chép ra USB; Task Scheduler chạy `sao-luu:csdl` hằng ngày
- [ ] (Sau ≥1 tuần) rà soát gỡ guard schema từng phần nhỏ, test lại sau mỗi phần
