# Báo cáo: Đối soát chủ động với SePay API

- **Ngày:** 2026-11-11
- **Nhánh:** `feat/sepay-doi-soat` (tách từ `main`; **chưa merge, chưa push**)
- **Phạm vi:** đọc giao dịch tiền vào từ SePay API v2 để phát hiện webhook bị mất,
  đưa vào hàng chờ `/doisoat`. Không tự hoàn tất hóa đơn.
- **Ngoài phạm vi:** Bank Hub, voucher, hoàn tiền tự động, Zalo, webhook mới,
  thay đổi giao diện POS, quản lý tài khoản ngân hàng.

## 1. Kết luận nhanh

Tính năng đã được bật ở **chế độ BÁO CÁO** (mặc định và duy nhất). Lệnh
`sepay:doi-soat` chỉ ghi thêm bản ghi `can_doi_soat` nguồn `sepay_api` vào
`payment_webhooks`; **không** đổi trạng thái hóa đơn, tồn kho hay điểm khách.

`--ap-dung` **bị từ chối có chủ đích** trước khi gọi mạng. Lý do: tài liệu SePay
không chứng minh được một khóa duy nhất chung giữa API v2 và webhook (mục 3).

Đợt siết tiếp theo: webhook **chỉ chống trùng theo id giao dịch**, không dùng
`referenceCode` để bỏ qua giao dịch (mã rỗng/trùng không còn chặn tiền thật); mã
tham chiếu chỉ gộp bản ghi báo cáo API cùng số tiền. `sepay:doi-soat` chỉ gửi
Telegram khi tìm được hóa đơn, tối đa 5 tin chi tiết mỗi lần chạy (cấu hình được),
phần vượt gộp thành một tin tóm tắt.

## 2. Căn cứ tài liệu

Đã đọc tài liệu chính thức (không gọi mạng thật trong test):

- v2 danh sách giao dịch: `https://developer.sepay.vn/en/sepay-api/v2/giao-dich/danh-sach`
  - `GET https://userapi.sepay.vn/v2/transactions`
  - Header `Authorization: Bearer <SEPAY_API_TOKEN>`
  - Tham số: `transaction_date_from`, `transaction_date_to` (`Y-m-d H:i:s`),
    `transfer_type=in`, `page`, `per_page` (tối đa 100)
  - Phản hồi: `{ status, data[], meta.pagination }`; `id` là **UUID**;
    `reference_number` là mã tham chiếu; có `code`, `transaction_content`,
    `amount_in`, `account_number`, `bank_brand_name`.
- Hướng dẫn đối soát: `https://developer.sepay.vn/vi/sepay-webhooks/doi-soat-giao-dich`
  - Gợi ý dùng `id` hoặc `reference_number` của API để tìm giao dịch thiếu.
  - **Không** khẳng định `reference_number` (API) bằng `referenceCode` (webhook).
- v1 (deprecated): `https://developer.sepay.vn/vi/sepay-api/v1/api-giao-dich`
  - Endpoint `GET https://my.sepay.vn/userapi/transactions/list`, tham số
    `transaction_date_min`/`transaction_date_max`; tài liệu ghi v1 không còn
    phát triển. Vì vậy client **từ chối** mọi `SEPAY_API_VERSION` khác `v2`.

Webhook hiện có (theo tài liệu webhook SePay): `id` là **số**, `referenceCode`,
`content`, `transferAmount`, `transferType`, `transactionDate`, `accountNumber`.

## 3. Khóa chống trùng — quyết định và lý do

| Nguồn | Trường định danh | Giá trị |
|---|---|---|
| Webhook SePay | `id` (số) | Dùng làm `ma_giao_dich_ngoai`, đã unique |
| API v2 | `id` (UUID) | Chuẩn hóa thành `ma_giao_dich_ngoai = api:<uuid>` |
| Cả hai | mã tham chiếu | Webhook `referenceCode`, API `reference_number` → cột `ma_tham_chieu` |

- **Không thể dùng `id` làm khóa chung**: kiểu dữ liệu và không gian giá trị khác
  nhau (số vs UUID), tài liệu không nói chúng bằng nhau.
- **`reference_number` vs `referenceCode`**: tài liệu đối soát chỉ khuyến nghị
  dùng mã tham chiếu để tra cứu, **không** cam kết hai trường này bằng nhau.
  Vì vậy chỉ dùng `ma_tham_chieu` để **gộp bản ghi trong nội bộ** khi trùng mã,
  không dùng để chứng minh "cùng một giao dịch" và càng không dùng để tự hoàn tất.
- Hệ quả: `--ap-dung` bị khóa. Muốn mở cần người dùng xác nhận bằng giao dịch
  thật rằng `referenceCode` của webhook trùng `reference_number` của API (mục 8).

Bản ghi API dùng tiền tố `api:` nên không bao giờ đụng unique index với `id` số
của webhook. Khi webhook đến sau, `WebhookController::ghiNhanWebhook` tìm bản ghi
theo `ma_giao_dich_ngoai` trước; nếu chưa có, chỉ gộp vào bản ghi báo cáo
`nha_cung_cap = sepay_api`, `ket_qua = can_doi_soat`, **cùng `ma_tham_chieu` và
cùng `so_tien`**, rồi cập nhật thay vì tạo trùng. Mã tham chiếu không bao giờ được
dùng để bỏ qua một webhook, và không bao giờ gộp/ghi đè bản ghi webhook của giao
dịch khác — kể cả khi `referenceCode` rỗng, chỉ khoảng trắng hoặc bị dùng lại.

## 4. Thay đổi theo tệp

### Cấu hình

- `config/pos.php:14-22` — `sepay_api_token`, `sepay_api_base_url`,
  `sepay_api_version`, `sepay_api_timeout`, `sepay_api_retries`,
  `sepay_api_per_page`, `sepay_api_min_interval_ms`, `sepay_api_max_retry_after`,
  `sepay_doi_soat_telegram_toi_da` (mặc định 5).
- `.env.example:12-19` — placeholder `SEPAY_API_TOKEN`, 4 biến phụ và
  `SEPAY_DOI_SOAT_TELEGRAM_TOI_DA=5`; **không có token thật**.
- `README.md` — bảng biến môi trường, bảng "Hai khóa SePay khác nhau", mục 4.3
  (người dùng tự nhập token + `sepay:kiem-tra-ket-noi`), mục 5 (scheduler), mục 6
  (health check), mục 7 (đối soát chủ động), mục 9/17/18 (test & giới hạn).

### Dữ liệu

- `database/migrations/2026_11_11_000001_add_ma_tham_chieu_to_payment_webhooks_table.php:15-18`
  — thêm cột nullable `ma_tham_chieu` (100) + index.
  **Chưa áp lên CSDL thật**; chỉ chạy trong SQLite `:memory:` của test.
- `app/Models/PaymentWebhook.php:17-18` — nhãn lý do `api_doi_soat`,
  `hoadon_da_thanh_toan`; `:26` thêm `ma_tham_chieu` vào `fillable`;
  `:54-70` `khoaThamChieu()` đọc cột hoặc dự phòng từ `payload`.

### Client chỉ đọc

- `app/Data/SepayTransaction.php` — DTO bất biến; `tuApi()` (`:26`) nhận cả
  snake_case của API và camelCase của webhook; `laTienVao()` (`:50`);
  `maNgoai()` (`:64`) trả `api:<uuid>`; `payloadLuuTru()` (`:81`) không lưu số
  tài khoản thô; `cheSoTaiKhoan()` (`:98`) chỉ giữ 4 số cuối.
- `app/Exceptions/SepayApiException.php` — thông điệp cố định, không chứa token;
  riêng lỗi kết nối nhận chi tiết **đã che**.
- `app/Services/SepayApiClient.php` — `layGiaoDichVao()` (`:33`) phân trang,
  chuyển cửa sổ thời gian UTC → `Asia/Ho_Chi_Minh`; `kiemTraKetNoi()` (`:93`)
  gọi `per_page=1`; `goiApi()` (`:101`) 401 dừng ngay, 429 đọc `Retry-After`
  (tối đa `SEPAY_API_MAX_RETRY_AFTER`) rồi thử lại, 5xx thử lại có giới hạn,
  lỗi mạng được che (`cheBiMat()` `:226`); giữ khoảng cách tối thiểu giữa các
  request (`:181`) để tôn trọng ~3 req/giây; chỉ dùng `GET`.

### Lệnh và lịch chạy

- `app/Console/Commands/SepayDoiSoat.php` — `--ap-dung` từ chối (`:45-51`);
  thiếu token thoát (`:53-57`); giới hạn Telegram mỗi lần chạy (`:65-69`);
  vòng lặp giao dịch (`:72`); phân loại (`:176-201`): `khong_tim_thay_ma_hoadon`,
  `hoadon_khong_ton_tai`, `don_da_huy`, `hoadon_da_thanh_toan`, `thieu_tien`, còn
  lại `api_doi_soat`; tạo bản ghi `can_doi_soat` (`:84-96`); chỉ gửi Telegram khi
  tìm được hóa đơn, tối đa N tin chi tiết, phần vượt cộng dồn (`:107-125`); gửi
  một tin tóm tắt phần vượt (`:137-139`); lưu mốc lần chạy vào cache (`:141`); lỗi
  in thông điệp an toàn (`:127-135`).
- `app/Console/Commands/SepayKiemTraKetNoi.php:20-33` — in OK/401/lỗi mạng,
  không in token hay dữ liệu giao dịch.
- `routes/console.php:13-16` — `sepay:doi-soat --gio=6` mỗi 10 phút,
  `withoutOverlapping()`, chỉ đăng ký khi có `SEPAY_API_TOKEN`.
- `app/Services/ThongBaoService.php:114-124` — tin tóm tắt "Còn N giao dịch thiếu
  webhook..." cho phần vượt giới hạn; `:130-138` — Telegram "GIAO DỊCH THIẾU
  WEBHOOK" gồm số tiền, hóa đơn, mã tham chiếu, lý do; không có nội dung chuyển
  khoản hay số tài khoản.

### Webhook và màn đối soát

- `app/Http/Controllers/WebhookController.php:53-58` chống trùng **chỉ** theo id
  giao dịch webhook (`ma_giaodich`/`ma_giao_dich`); không còn kiểm tra bản ghi
  `success` cùng `ma_tham_chieu`; `:151` ghi nhận thành công qua
  `ghiNhanWebhook`; `:186-230` gộp theo `ma_giao_dich_ngoai`, hoặc chỉ vào bản
  ghi báo cáo `sepay_api`/`can_doi_soat` cùng `ma_tham_chieu` **và** cùng
  `so_tien`; `:232-238` cắt mã 100 ký tự. Hành vi webhook cũ (xác thực, chống
  trùng theo `ma_giaodich`, hoàn tất đơn) không đổi.
- `resources/views/doisoat/index.blade.php:37,52` — cột **Nguồn**, nhãn
  `api_doi_soat`; `show.blade.php:14-26` — nhãn nguồn và mã tham chiếu.
  Không thêm nút tự hoàn tất.
- `app/Console/Commands/KiemTraHeThong.php:31,113-140` — `SEPAY_API_TOKEN`
  đủ/thiếu ở mức **WARN**; tuổi lần chạy `sepay:doi-soat` gần nhất, WARN nếu
  quá 30 phút.

## 5. Bằng chứng kiểm thử

Toàn bộ dùng `Http::fake()` + `Http::preventStrayRequests()`, SQLite `:memory:`,
`RefreshDatabase`; **không gọi mạng thật**.

| Yêu cầu | Test |
|---|---|
| DTO đúng, chỉ tiền vào, che số tài khoản | `SepayApiClientTest::test_chuan_hoa_dto_chi_lay_tien_vao_va_che_so_tai_khoan` |
| 401 không lộ token | `test_401_tra_ngoai_le_khong_lo_token` |
| 429 đọc `Retry-After` rồi thành công | `test_429_doc_retry_after_roi_thanh_cong` |
| Phân trang | `test_phan_trang_lay_du_cac_trang` |
| 5xx / lỗi kết nối | `test_5xx_tra_ngoai_le_sau_khi_thu_lai`, `test_loi_ket_noi_khong_lo_token` |
| v1 bị từ chối, thiếu token không gọi mạng | `test_v1_khong_duoc_ho_tro`, `test_thieu_token_khong_goi_mang` |
| Báo cáo-only không đổi hóa đơn/kho/điểm | `SepayDoiSoatTest::test_T_SDS01_...` |
| Chạy lại không trùng, Telegram một lần | `test_T_SDS02_...` |
| Webhook trước → API không coi là thiếu, không xử lý lần hai | `test_T_SDS03_...` |
| API trước → webhook sau gộp một bản ghi, hoàn tất một lần | `test_T_SDS04b_...` |
| Sai mã / không tồn tại / đã hủy / đã thanh toán / thiếu tiền chỉ báo | `test_T_SDS04_...` |
| `--ap-dung` từ chối, không mạng, không ghi | `test_T_SDS05_...` |
| Thiếu token, 401 không lộ token | `test_T_SDS06_...`, `test_T_SDS07_...` |
| Lịch đã đăng ký | `test_T_SDS08_...` (và `KiemTraHeThongTest::test_schedule_da_dang_ky_cac_lenh_van_hanh`) |
| `/doisoat` nhãn nguồn, chỉ admin | `test_T_SDS09_...` (quyền admin cũng có trong `DoiSoatTest`) |
| Kiểm tra kết nối + log không chứa token | `SepayKiemTraKetNoiTest` (4 test, assert cả output và Monolog) |
| Webhook thành công lưu `ma_tham_chieu` | `ThanhToanChuyenKhoanTest::test_T_CK03b_...` |
| Hai giao dịch khác id cùng `referenceCode` đều hoàn tất, giữ bản ghi riêng | `test_T_CK03c_...` |
| `referenceCode` rỗng/null/chỉ khoảng trắng không kích hoạt chống trùng | `test_T_CK03d_...` |
| Webhook gửi lại đúng id cũ vẫn idempotent, chỉ một bản ghi | `test_T_CK05_...` |
| Không tìm thấy hóa đơn/không tồn tại: ghi `/doisoat`, **không** Telegram | `SepayDoiSoatTest::test_T_SDS11_...` |
| Giới hạn tin Telegram + một tin tóm tắt phần vượt | `test_T_SDS12_...` |
| Health check token/lần đối soát | `KiemTraHeThongTest` (16 test) |

Kết quả chạy thật (đợt siết chống trùng + giảm nhiễu):

- `XDEBUG_MODE=off php artisan test --filter='test_T_CK03c|test_T_CK03d|test_T_CK05'`:
  **3 passed (34 assertions), 4.48s**.
- `XDEBUG_MODE=off php artisan test --filter=SepayDoiSoatTest`:
  **12 passed (91 assertions), 23.25s**.
- `XDEBUG_MODE=off php artisan test`: **205 passed, 1 skipped (1249 assertions),
  259.95s**, exit 0. 206 ca: 11 Unit + 195 Feature; skip duy nhất là
  `KhoaDongMysqlTest` (cần MySQL), không liên quan đợt này.
- Hai lượt chạy full trước đó đều gặp flake có sẵn tại `PosWorkflowTest` (trùng
  `ma_phieu` trả hàng do `random_int(10,99)` trong cùng giây,
  `TraHangController.php:77`); chạy riêng test đó 10/10 lần và cả lớp
  `PosWorkflowTest` (32 passed) đều xanh. Không sửa vì ngoài phạm vi đợt này.

Trước đợt siết, kết quả của đợt tính năng ban đầu: `--filter='Sepay'` **30 passed
(151 assertions), 36.54s**; full suite **201 passed, 1 skipped (1208 assertions),
280.21s**.

**Khác biệt so với yêu cầu ban đầu:** yêu cầu có mục "`--ap-dung` hoàn tất đúng
một lần và chạy lại không đổi". Do điều kiện an toàn số 4 (không chứng minh được
khóa chung ⇒ chỉ báo cáo), test tương ứng khẳng định **từ chối, không gọi mạng,
không ghi dữ liệu** thay vì hoàn tất. Đây là chủ ý, không phải thiếu sót.

## 6. Giới hạn

- **Chỉ báo cáo**: không tự khớp lại tiền vào hóa đơn; admin vẫn xử lý thủ công
  tại `/doisoat`.
- **Không có khóa chung được tài liệu chứng minh**; `--ap-dung` bị khóa.
- **Mã tham chiếu chỉ để gộp hiển thị**: webhook không bị bỏ qua khi
  `referenceCode` rỗng/trùng, và không ghi đè bản ghi webhook khác. Khi gộp vào
  báo cáo API phải cùng mã **và** cùng số tiền.
- **Nhiễu Telegram**: giao dịch lạ (không mã hóa đơn, hóa đơn không tồn tại) chỉ
  vào `/doisoat`, không gửi Telegram. Mỗi lần chạy tối đa
  `SEPAY_DOI_SOAT_TELEGRAM_TOI_DA` (mặc định 5) tin chi tiết, phần vượt gộp một
  tin tóm tắt. Lần đầu nên chạy tay `--gio=1` và dùng tài khoản ngân hàng riêng.
- **v1 deprecated, không hỗ trợ**; chỉ `SEPAY_API_VERSION=v2`.
- **Rate limit ~3 req/giây**: client giữ 350 ms giữa các request và xử lý 429;
  cửa sổ lớn có thể mất nhiều thời gian/phân trang (giới hạn 1000 trang).
- **Webhook cũ trước migration**: nếu `payload` không có `referenceCode` thì
  không gộp được với API; có thể bị báo lại một lần trong `/doisoat` (vô hại vì
  chỉ báo cáo).
- **Migration chưa áp lên CSDL thật**: phải sao lưu và `migrate --force` theo
  mục 5A trước khi dùng trên máy cửa hàng.
- **Múi giờ**: app vẫn UTC; client tự chuyển cửa sổ truy vấn sang
  `Asia/Ho_Chi_Minh` để khớp giờ SePay.

## 7. Việc đã không làm (đúng ràng buộc)

- Không đọc/ghi/in `.env`; không có token thật trong mã, test, tài liệu, log.
- Không gọi mạng thật tới SePay; không chạy migrate/seed/demo:reset trên CSDL thật.
- Chỉ dùng `GET`; không gọi endpoint ghi, không hoàn tiền, không quản lý tài khoản.
- Không merge, không push.

## 8. Cần người dùng xác nhận

1. **Token**: tự tạo API Token đọc giao dịch trên SePay và thêm dòng mới
   `SEPAY_API_TOKEN=` trong `.env` (không dùng chung với `SEPAY_WEBHOOK_API_KEY`),
   rồi chạy `php artisan config:clear` và `php artisan sepay:kiem-tra-ket-noi`.
2. **Khóa chung**: xác nhận bằng ít nhất một giao dịch thật rằng `referenceCode`
   của webhook trùng `reference_number` của API (hoặc cung cấp tài liệu SePay
   khẳng định điều này). Chỉ khi đó mới bàn đến việc mở `--ap-dung`.
3. **Migration và thứ tự bật**: đồng ý thời điểm sao lưu và chạy `migrate --force`
   cho cột `ma_tham_chieu` trên CSDL vận hành. Thứ tự bắt buộc: **sao lưu →
   `migrate --force` → `he-thong:kiem-tra` (không còn migration chờ) → mới bật
   webhook và lịch đối soát**. Chạy code mới trước khi migrate sẽ làm webhook lỗi
   500 do thiếu cột.
4. **Lịch chạy**: xác nhận Task Scheduler đã gọi `schedule:run` mỗi phút để
   `sepay:doi-soat --gio=6` thực sự chạy; nếu không, health check sẽ WARN sau
   30 phút.
5. **Chính sách dữ liệu**: xác nhận việc lưu nội dung chuyển khoản (cắt 255 ký
   tự) trong `payment_webhooks` và gửi Telegram khi phát hiện giao dịch thiếu là
   phù hợp.
