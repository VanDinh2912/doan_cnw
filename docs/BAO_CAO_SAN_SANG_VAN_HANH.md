# Báo cáo nâng cấp sẵn sàng vận hành

- **Dự án**: Web POS & Quản lý kho (Laravel 12, PHP 8.4, MySQL 8.4)
- **Nhánh**: `feat/san-sang-van-hanh` (tách từ `docs/ra-soat-readme`)
- **Ngày hoàn tất**: 2026-11-10
- **Phạm vi**: nghiệp vụ lõi, vận hành, bảo mật cấu hình, hiệu năng, test, tài liệu.
  Không thêm voucher/khuyến mãi, ROP/ABC, phân quyền động, REST API, tích hợp ngoài,
  webcam, in tự động, mobile.
- **Trạng thái**: hoàn thành Giai đoạn 1–4. **Chưa merge, chưa push.**
  CSDL thật `doan_cnw` **không bị migrate** trong đợt này.

## 1. Tóm tắt điều hành

Đợt này xử lý các lỗi khiến hệ thống "chạy demo được nhưng chưa chạy thật được":

1. **Trả hàng không hoàn điểm khách đã dùng** — đã sửa, có test.
2. **Giao dịch chuyển khoản không khớp hóa đơn bị bỏ rơi** — nay vào hàng chờ
   đối soát cho admin.
3. **CSDL thật dùng MyISAM** nên `DB::transaction`, `lockForUpdate` và rollback
   **không có tác dụng** ở mọi luồng tiền/tồn kho — phát hiện trọng yếu của đợt này;
   đã có migration chuyển InnoDB + kiểm tra tự động.
4. **Không có lịch chạy nền, không có sao lưu, không có kiểm tra sức khỏe** — đã bổ
   sung cả ba, có tài liệu cài đặt.
5. **Lệnh phá hủy dữ liệu chưa bị chặn ở production** — đã chặn cứng.
6. **Đăng nhập không throttle, khoá API webhook có thể để trống, log có thể lộ token
   Telegram** — đã xử lý.
7. **POS mất giỏ hàng khi AJAX lỗi, trang lỗi mặc định tiếng Anh** — đã sửa.
8. **N+1 và thiếu index vận hành** — đã thêm index và bật cảnh báo lazy-load.

Suite test tăng từ **66** lên **145 test** ở đợt nâng cấp ban đầu (144 pass,
1 skip, 627 assertions), rồi **165 test** sau đợt bổ sung: **164 pass, 1 skip,
792 assertions**, 230.67s với `XDEBUG_MODE=off`.

## 2. Bảng trước / sau

| Hạng mục | Trước | Sau |
|---|---|---|
| Transaction & khóa dòng | MyISAM: transaction/khóa là no-op | InnoDB cho 27/27 bảng (bản sao), engine mặc định InnoDB, health check `FAIL` nếu còn MyISAM |
| Trả hàng + điểm | Không hoàn `diem_su_dung` | Hoàn theo tỷ lệ giá trị hàng trả cộng dồn, làm tròn xuống, không vượt điểm gốc; có nhật ký, kể cả hóa đơn thực trả 0 |
| Webhook không khớp | Chỉ cảnh báo Telegram | Lưu hàng chờ `/doisoat`, admin đánh dấu xử lý + nhật ký |
| Lịch chạy nền | Chỉ `schedule:work` thủ công | `scripts/windows/chay-schedule.bat` + Task Scheduler mỗi phút + heartbeat |
| Kiểm tra sức khỏe | Không có | `php artisan he-thong:kiem-tra` (13 mục, chỉ đọc) |
| Sao lưu | Không có | `php artisan sao-luu:csdl`, chạy hằng ngày 02:00, giữ 7 bản |
| Lệnh phá hủy | Dặn trong tài liệu | Chặn cứng ở production: `--force-dangerous` + nhập lại tên DB |
| Đăng nhập | Không giới hạn | Throttle 10 lần/phút |
| Mật khẩu mặc định | Không cảnh báo | Cảnh báo ở trang đăng nhập + dashboard admin, kèm lệnh đổi mật khẩu |
| Log | Kênh theo `.env` (đang `single`), token Telegram lộ trong lỗi | Mặc định `daily` giữ 14 ngày, che token |
| POS khi AJAX lỗi | Tải lại trang, mất giỏ | Giữ giỏ, báo lỗi rõ, polling tự dừng sau 3 lỗi |
| Trang lỗi | Mặc định Laravel/tiếng Anh | 403/404/419/500 tiếng Việt, 500 không lộ chi tiết |
| N+1 | Không kiểm soát | `Model::preventLazyLoading()` ngoài production + sửa N+1 trang chủ |
| Index vận hành | Thiếu index ngày tạo | 4 index cho invoices, stock_movements, activity_logs |
| Test | 66 test | 165 test (164 pass, 1 skip, 792 assertions); đợt ban đầu 145 test |
| Tài liệu | Chưa có hướng dẫn triển khai | README mục 5/5A/7/8/9/10/12/14/16/17/18 + báo cáo này |

### Bổ sung cùng ngày — đối chiếu với HEAD `8835b52`

| Hạng mục | Trước đợt bổ sung này | Sau |
|---|---|---|
| Đường dẫn tài liệu | Có đường dẫn máy cá nhân trong README/báo cáo | Placeholder theo thư mục dự án và từng binary; kiểm tra cả README, `docs/`, `scripts/` |
| Webhook | Hai khai báo cùng URI; route API bị che | Chỉ giữ POST `/api/webhook/thanhtoan` trong `web.php`, có `throttle:60,1`; thêm test route duy nhất |
| `db:wipe` | Chưa có chốt chặn riêng | Dùng cùng chốt chặn production với `migrate:fresh`: `--force-dangerous` + đúng tên DB đích; `--force` đơn lẻ không đủ |
| Điểm trả hàng | Đã có `diem_hoan` nhưng tính theo tiền hoàn đã làm tròn; hóa đơn thực trả 0 không hoàn điểm; chưa giữ số lượng phiếu chờ duyệt | Tính theo giá trị hàng trả gốc cộng dồn, floor rồi trừ điểm đã cam kết, capped điểm gốc; giữ số lượng/điểm phiếu chờ duyệt, ghi nhật ký khi hoàn thành |
| Múi giờ | UTC | **Vẫn UTC**; chỉ có phương án, kiểm tra và hoàn tác ở mục 6.2, chưa đổi cấu hình/dữ liệu |

Đợt bổ sung không đọc/ghi `.env`, không chạy migration hoặc lệnh phá hủy trên CSDL thật, không merge/push. Các kết quả MySQL/bản sao ở những mục lịch sử phía dưới được giữ nguyên, **không phải các phép đo mới của đợt bổ sung**.

## 3. Giai đoạn 1 — Toàn vẹn tiền và kho

### 3.1. Hoàn lại điểm khách đã dùng khi trả hàng (`e21537a`)

- **Lỗi**: phiếu trả chỉ hoàn tiền và thu hồi điểm tích lũy; điểm khách đã tiêu để
  giảm giá (`invoices.diem_su_dung`) bị mất.
- **Sửa ban đầu**: thêm cột `returns.diem_hoan`
  (`database/migrations/2026_11_10_000001_add_diem_hoan_to_returns_table.php`).
- **Code sau đợt bổ sung**: [`TraHangController.php:69-83`](../app/Http/Controllers/TraHangController.php#L69-L83)
  lấy giá trị hàng gốc và lưu điểm hoàn; [`:139-164`](../app/Http/Controllers/TraHangController.php#L139-L164)
  tính `floor(diem_su_dung × giá_trị_hàng_trả_lũy_kế / tong_tien)` bằng phép chia
  số nguyên chính xác, trừ điểm đã cam kết, không vượt điểm gốc. Phiếu
  `hoan_thanh` và `cho_duyet` đều giữ hạn mức điểm/số lượng
  ([`:59-64`](../app/Http/Controllers/TraHangController.php#L59-L64)). Hóa đơn thực
  trả 0 vẫn hoàn điểm theo giá trị hàng, không theo số lượng hay tiền hoàn.
- **Cộng điểm và nhật ký**: [`:188-207`](../app/Http/Controllers/TraHangController.php#L188-L207)
  khóa khách, thu hồi không quá số dư rồi cộng điểm hoàn, ghi
  `hoan_diem_su_dung`. Chỉ thực hiện khi phiếu hoàn thành; duyệt lại bị chặn cả
  sau khi khóa phiếu ([`:125-131`](../app/Http/Controllers/TraHangController.php#L125-L131)).
- **Test**: `tests/Feature/PosWorkflowTest.php` — giảm giá + dùng điểm
  ([`:549`](../tests/Feature/PosWorkflowTest.php#L549)), nhiều lần 30/30/40 điểm
  ([`:645`](../tests/Feature/PosWorkflowTest.php#L645)), floor cộng dồn 6/7/7 điểm
  ([`:712`](../tests/Feature/PosWorkflowTest.php#L712)), tiền hoàn 0 với giá trị hàng
  khác nhau ([`:746`](../tests/Feature/PosWorkflowTest.php#L746)), hai phiếu chờ duyệt
  và duyệt ngược thứ tự ([`:778`](../tests/Feature/PosWorkflowTest.php#L778)), dòng
  hàng trùng ([`:819`](../tests/Feature/PosWorkflowTest.php#L819)), hàng lỗi
  ([`:844`](../tests/Feature/PosWorkflowTest.php#L844)) và hoàn hết điểm dù tiền làm
  tròn ([`:904`](../tests/Feature/PosWorkflowTest.php#L904)).
- **Ảnh hưởng dữ liệu**: migration thêm `diem_hoan` là số nguyên không âm, **NOT NULL, mặc định 0**, không phải nullable. Migration này đã có trước đợt bổ sung; đợt bổ sung không tạo migration mới hay áp dụng migration trên CSDL thật. Không ghi bù vào phiếu lịch sử trong đợt này. Hoàn tác code nhóm cũ: `git revert e21537a` (migration down xóa cột, chỉ thực hiện theo quy trình được duyệt).

### 3.2. Hàng chờ đối soát chuyển khoản (`8777efa`)

- **Lỗi**: webhook SePay không khớp hóa đơn/số tiền chỉ gửi Telegram rồi bỏ qua —
  không có nơi nào để admin xử lý tiếp.
- **Sửa**:
  - `app/Http/Controllers/WebhookController.php:186-199` ghi
    `payment_webhooks.ket_qua = 'can_doi_soat'`, `trang_thai_xu_ly = 'chua_xu_ly'`.
  - `app/Http/Controllers/DoiSoatController.php` — danh sách, chi tiết, đánh dấu đã
    xử lý trong transaction + `lockForUpdate` (`:52-62`), chặn xử lý lặp (`:54`),
    ghi nhật ký `doi_soat_danh_dau` (`:64-71`).
  - Migration `2026_11_10_000002_add_doi_soat_fields_to_payment_webhooks_table.php`.
  - View `resources/views/doisoat/index.blade.php`, `show.blade.php`; route
    `routes/web.php:153-157` (chỉ admin).
- **Test**: `tests/Feature/DoiSoatTest.php` (8 test).
- **Ảnh hưởng dữ liệu**: thêm cột vào `payment_webhooks`, không đổi dữ liệu cũ.
  Hoàn tác: `git revert 8777efa`.

### 3.3. Test chứng minh tính nguyên tử (`7b8b0bf`)

- `tests/Feature/ToanVenGiaoDichTest.php` (6 test): hủy đơn, nhập kho, thanh toán
  gặp lỗi giữa chừng phải rollback toàn bộ (tồn kho, điểm, trạng thái hóa đơn).
- **Lưu ý**: trên SQLite các test này chứng minh logic rollback của code; còn việc
  MySQL **có thật sự rollback được** chỉ đúng sau khi chuyển InnoDB (Giai đoạn 3.2).

## 4. Giai đoạn 2 — Vận hành thật

### 4.1. Scheduler + kiểm tra sức khỏe (`c88345f`, `91feb18`)

- `routes/console.php`: thêm heartbeat mỗi phút (`Cache::put('he_thong:scheduler_last_run')`)
  và `sao-luu:csdl` hằng ngày 02:00; giữ `donhang:huy-qua-han` mỗi phút, `hold:clean`
  hằng ngày.
- `scripts/windows/chay-schedule.bat` (CRLF): tự `cd` về gốc dự án, gọi
  `php artisan schedule:run --no-interaction`, ghi `storage/logs/schedule-YYYY-MM-DD.log`,
  tự xóa log quá 14 ngày, hỗ trợ biến `PHP_BINARY`.
- `.gitattributes`: `*.bat`/`*.cmd` checkout bằng CRLF (`91feb18`) — trước đó Git
  chuẩn hóa sang LF làm `cmd.exe` chạy sai.
- `app/Console/Commands/KiemTraHeThong.php` (chỉ đọc, không in giá trị bí mật):
  scheduler (`:42-61`), đơn quá hạn (`:63-78`), cấu hình ngân hàng/SePay/Telegram
  (`:83-106`), `APP_ENV`/`APP_DEBUG`/`APP_URL`/config cache (`:108-132`), quyền ghi
  (`:134-158`), log (`:160-175`), engine (`:181-204`), migration (`:206-226`).
  Exit code `1` nếu có `FAIL`.
- **Test**: `tests/Feature/KiemTraHeThongTest.php` (13 test).

### 4.2. Sao lưu CSDL (`00f7489`)

- `config/backup.php`: `disk`, `giu_ban`, `mysqldump_binary`, `timeout`.
- `config/filesystems.php:50-54`: đĩa `backups` → `storage/app/backups`.
- `app/Console/Commands/SaoLuuCsdl.php`:
  - MySQL: `mysqldump --single-transaction --routines --triggers --events
    --default-character-set=utf8mb4`, mật khẩu qua `MYSQL_PWD` (`:114-127`), che mật
    khẩu trong thông báo lỗi (`:137-140`).
  - SQLite: `VACUUM INTO` (`:94-97`).
  - Giữ N bản, chỉ xóa tệp do chính lệnh sinh ra (`:146-172`).
  - Thiếu `mysqldump` → lỗi rõ ràng kèm ví dụ `DB_DUMP_BINARY` (`:104-108`).
- **Test**: `tests/Feature/SaoLuuCsdlTest.php` (4 test, nhánh SQLite + giữ N bản).

### 4.3. Chốt chặn production (`0bec00e`)

- [`ChanLenhPhaHuy.php:14-44`](../app/Console/Commands/Concerns/ChanLenhPhaHuy.php#L14-L44):
  ở `production` bắt buộc `--force-dangerous` **và** nhập lại đúng tên database;
  không tương tác phải kèm `--ten-db-xac-nhan`. `--force` đơn lẻ không đủ.
- Áp dụng cho `migrate:fresh`, `demo:reset`, `documents:import`; đợt bổ sung thêm
  [`DbWipe.php:17-32`](../app/Console/Commands/DbWipe.php#L17-L32). Với
  `--database=<tên-kết-nối>`, chốt chặn lấy tên database thực của kết nối đã chọn,
  không dùng tên kết nối hoặc database mặc định
  ([`ChanLenhPhaHuy.php:47-54`](../app/Console/Commands/Concerns/ChanLenhPhaHuy.php#L47-L54)).
- [`MigrateFresh.php:23-47`](../app/Console/Commands/MigrateFresh.php#L23-L47) chuyển
  tiếp xác nhận sang `db:wipe`; lệnh con vẫn tự kiểm tra, không bỏ qua chốt chặn.
  Xác nhận được xóa sau mỗi lần chạy, không chuyển sang các lệnh con khác.
- **Test sau bổ sung**: `LenhNguyHiemTest` có 19 ca, dùng mock DB/schema/migrator,
  không xóa bảng hoặc chạy migration thật; kiểm tra đăng ký command/option
  ([`:63`](../tests/Feature/LenhNguyHiemTest.php#L63)), thiếu cờ/tên và sai DB
  ([`:77-109`](../tests/Feature/LenhNguyHiemTest.php#L77-L109)), kết nối đích
  ([`:123`](../tests/Feature/LenhNguyHiemTest.php#L123)), tương tác
  ([`:139`](../tests/Feature/LenhNguyHiemTest.php#L139)), ngoài production
  ([`:153`](../tests/Feature/LenhNguyHiemTest.php#L153)) và chuyển tiếp xác nhận
  ([`:165-188`](../tests/Feature/LenhNguyHiemTest.php#L165-L188)). Giữ 5 unit test
  `tests/Unit/ChanLenhPhaHuyTest.php`.

### 4.4. Bảo mật đăng nhập + quản lý tài khoản (`7b603ce`)

- `routes/web.php:24`: `throttle:10,1` cho `POST /dangnhap`.
- `app/Services/CanhBaoMatKhauMacDinh.php`: so hash mật khẩu với mật khẩu seeder,
  cache kết quả; cảnh báo ở trang đăng nhập (`AuthController.php:41`) và dashboard
  admin (`ThongKeController.php:23`) — **chỉ cảnh báo**, không khóa tài khoản.
- `php artisan nguoidung:doi-matkhau {email}`, `php artisan nguoidung:tao {email}
  {vai_tro}` — mật khẩu nhập ẩn, không nhận qua tham số dòng lệnh.
- **Test**: `tests/Feature/BaoMatDangNhapTest.php` (6), `NguoiDungCommandTest.php` (9).
- **Thay đổi test kèm lý do**: `tests/Feature/ExampleTest.php` được thêm
  `RefreshDatabase` vì trang đăng nhập giờ truy vấn bảng `users` để cảnh báo mật khẩu
  mặc định; thiếu migrate thì test cũ fail "no such table: users". Không đổi hành vi
  nghiệp vụ.

### 4.5. Log an toàn (`505237a`)

- `config/logging.php:57` — `LOG_STACK` mặc định `daily`; `:72` — `LOG_DAILY_DAYS`
  mặc định 14.
- `app/Services/ThongBaoService.php:47,54` — `cheToken()` thay bot token trong thông
  báo lỗi Guzzle trước khi ghi log.
- `.env.example`: `LOG_STACK=daily`, `LOG_DAILY_DAYS=14`, `BACKUP_*`, `DB_DUMP_BINARY`.
- **Test**: `tests/Feature/LogBiMatTest.php` (5).

## 5. Giai đoạn 3 — Độ tin cậy và hiệu năng

### 5.1. Phủ 10 mục Bước 5B (`27329a5`)

Bổ sung/siết test cho các mục trước đây chưa có test (không đổi logic):

| Mục | Test chứng minh |
|---|---|
| Cảnh báo lệch giá nhập >10% / giá bán thấp hơn giá nhập | `NhapKhoCanhBaoTest` (3) |
| Webhook fail-closed khi thiếu API key | `PosBaoVeTest::test_webhook_tu_choi_khi_api_key_chua_cau_hinh` |
| Biên giảm giá 0–100 | `PosBaoVeTest` (3 test giảm giá) |
| `limit(10)` tìm sản phẩm | `PosBaoVeTest::test_tim_san_pham_chi_tra_toi_da_10_ket_qua` |
| `hold:clean` quá 24h | `TacVuNenTest` |
| Telegram thành công | `TacVuNenTest` |
| Đăng ký lịch trong `routes/console.php` | `KiemTraHeThongTest` (schedule:list) |
| Xác nhận nhập kho chống bấm 2 lần | `ToanVenGiaoDichTest` (T-ID04) |
| Doanh thu theo ngày trước/sau webhook | `ThanhToanChuyenKhoanTest` T-CK16 (siết assertion) |

### 5.2. InnoDB — phát hiện trọng yếu (`02aa5b2`)

- **Hiện trạng**: `doan_cnw` (và bản sao) có **27 bảng MyISAM**. Trên MyISAM,
  `DB::transaction`, `lockForUpdate`, rollback **không có tác dụng** — mọi bảo đảm
  "mọi thao tác tiền/tồn kho trong transaction" trước đây chỉ đúng trên giấy.
- **Sửa**:
  - `database/migrations/2026_11_10_000003_chuyen_bang_mysql_sang_innodb.php`:
    no-op trên SQLite; trên MySQL/MariaDB duyệt `Schema::getTables()` và
    `ALTER TABLE ... ENGINE=InnoDB` cho bảng chưa phải InnoDB.
  - `config/database.php:59`: engine mặc định `InnoDB` cho bảng mới.
  - `KiemTraHeThong::kiemTraEngine()` (`:181-204`): `FAIL` nếu còn bảng khác InnoDB.
  - `tests/Feature/KhoaDongMysqlTest.php`: mở kết nối thứ hai, giữ
    `SELECT ... FOR UPDATE`, chứng minh kết nối kia bị chặn (timeout 1s), hóa đơn
    không đổi trạng thái; sau rollback thì thanh toán lại thành công.
- **Đã kiểm trên bản sao** `doan_cnw_copy`: 32 migration, 27/27 bảng InnoDB,
  `KhoaDongMysqlTest` pass 3.28s.
- **CSDL thật không bị chạm**: vẫn 26 migration, 27 bảng MyISAM (kiểm ngày 2026-11-10).
- **Hoàn tác**: `down()` của migration 000003 **cố ý để trống** — chuyển ngược về
  MyISAM sẽ làm mất tính nguyên tử; muốn quay lại phải khôi phục bản sao lưu.

### 5.3. Index và N+1 (`0c68a7d`)

- `database/migrations/2026_11_10_000004_them_index_van_hanh.php`: thêm
  `invoices(trang_thai, created_at)`, `invoices(created_at)`,
  `stock_movements(created_at)`, `activity_logs(created_at)`; `down()` xóa các index.
- EXPLAIN trước/sau trên bản sao: truy vấn danh sách hóa đơn theo trạng thái + ngày
  dùng composite index (`type: range`).
- `app/Providers/AppServiceProvider.php:28-30`: `Model::preventLazyLoading()` khi
  không phải production — phát hiện N+1 ngay trong test.
- N+1 thật được phát hiện và sửa: khối flash sale trang chủ
  (`app/Http/Controllers/StorefrontController.php:34` thêm `with('loaiSanPham')`).

### 5.4. Lỗi POS và trang lỗi (`c597d47`)

- `resources/views/pos/index.blade.php`: helper `docPhanHoi()` (`:220`) xử lý 419,
  500, JSON hỏng/mất kết nối; thông báo cố định "Giỏ hàng vẫn được giữ"; polling QR
  dừng sau 3 lỗi liên tiếp (`loiPollingLienTiep`, `:213`); khối
  `#qr-polling-status` (`:141`).
- Trang lỗi tiếng Việt: `resources/views/errors/{403,404,419,500}.blade.php`;
  trang 500 không hiển thị exception/SQLSTATE.
- **Test**: `tests/Feature/ThongBaoLoiTest.php` (5).

## 6. Giai đoạn 4 — Nợ kỹ thuật có điều kiện

### 6.1. Phân tích hợp nhất `ma_giaodich` / `ma_giao_dich` (chỉ phân tích, chưa sửa)

**Bảng đọc/ghi toàn bộ (đã quét `app/`, `resources/`, `routes/`, `database/`, `tests/`):**

| Vị trí | Vai trò | Cột |
|---|---|---|
| `app/Services/ThanhToanChuyenKhoanService.php:194` | Ghi | `ma_giaodich` |
| `app/Services/ThanhToanChuyenKhoanService.php:195` | Ghi | `ma_giao_dich` |
| `app/Http/Controllers/WebhookController.php:54` | Đọc chống trùng | Cả hai (`orWhere`) |
| `app/Models/DonHang.php:19` | `$fillable` | Cả hai |
| `app/Console/Commands/KiemTraDuLieu.php:41-43` | Đọc kiểm trùng | `ma_giaodich` |
| `database/migrations/2026_01_01_000007_...:17` | Schema | `ma_giaodich` varchar(100) unique |
| `database/migrations/2026_11_02_033147_...:15` | Schema | `ma_giao_dich` varchar(40) unique |
| `tests/Feature/ThanhToanChuyenKhoanTest.php:253,361,649-650,714-715` | Assert | `ma_giaodich` / `ma_giao_dich` |
| `tests/Feature/ToanVenGiaoDichTest.php:182,212` | Assert | `ma_giaodich` |
| `tests/Feature/KhoaDongMysqlTest.php:65,72` | Assert | `ma_giaodich` |

**Kết luận:** `ma_giaodich` (100 ký tự) là **cột chuẩn**: được service ghi, được
webhook dùng để chống trùng, được lệnh kiểm tra dữ liệu kiểm trùng, được hầu hết
test assert. `ma_giao_dich` (40 ký tự) là bản sao thêm ngày 2026-11-02, chỉ được ghi
song song và đọc trong một điều kiện `orWhere`.

**Độ lệch dữ liệu (đo ngày 2026-11-10):**

```sql
SELECT COUNT(*),
       SUM(ma_giaodich IS NOT NULL),
       SUM(ma_giao_dich IS NOT NULL),
       SUM(NOT (ma_giaodich <=> ma_giao_dich))
FROM invoices;
```

| CSDL | Tổng hóa đơn | Có `ma_giaodich` | Có `ma_giao_dich` | Số dòng lệch |
|---|---|---|---|---|
| `doan_cnw` (thật) | 3 | 0 | 0 | 0 |
| `doan_cnw_copy` | 3 | 0 | 0 | 0 |

Ngoài ra: không có trigger/view/procedure nào trong cả hai CSDL (0/0/0).

**Kế hoạch migration (chưa thực hiện):**

1. **Tiền kiểm** trên CSDL đích: câu SQL trên phải trả `SUM(NOT (...)) = 0`
   (hiện 0).
2. **Backfill phòng thủ**:
   `UPDATE invoices SET ma_giaodich = ma_giao_dich WHERE ma_giaodich IS NULL AND ma_giao_dich IS NOT NULL;`
3. **Sửa code**: bỏ `orWhere('ma_giao_dich', ...)` ở `WebhookController.php:54`;
   bỏ dòng ghi `ma_giao_dich` ở `ThanhToanChuyenKhoanService.php:195`; bỏ khỏi
   `$fillable`; giữ nguyên `KiemTraDuLieu` (đã dùng cột chuẩn).
4. **Migration mới**: `dropUnique('invoices_ma_giao_dich_unique')` rồi
   `dropColumn('ma_giao_dich')`; `down()` thêm lại cột + unique.
5. **Kiểm chứng**: chạy toàn bộ suite trên SQLite; chạy migration trên bản sao
   MySQL (migration mới chạy sau 033147 nên thứ tự an toàn); chạy lại
   `KhoaDongMysqlTest`.
6. **Triển khai**: sao lưu trước, chạy ngoài giờ bán hàng.

**Trạng thái: CHƯA LÀM — cần người dùng xác nhận** (xóa cột là thao tác phá hủy;
chưa rõ có báo cáo/Excel/Power BI bên ngoài nào đọc trực tiếp `ma_giao_dich` hay
không). Rủi ro dữ liệu hiện tại bằng 0 vì cả hai cột đều rỗng.

### 6.2. Phương án chuyển sang `Asia/Ho_Chi_Minh` — chỉ đề xuất, chưa thực hiện

`config/app.php:68` vẫn là `UTC`; đợt này **không sửa cấu hình, không chuyển dữ liệu**. Chưa xác minh múi giờ thực tế của MySQL/session hay nguồn gốc mọi timestamp cũ; không mặc định rằng chỉ đổi cấu hình hoặc cộng 7 giờ là an toàn.

**Tác động cần đánh giá:**

- Đổi timezone ứng dụng thay đổi cách Carbon/PHP diễn giải timestamp không có múi giờ và cách ghi thời gian mới; một giá trị cũ có thể bị hiểu thành giờ Việt Nam thay vì UTC. MySQL `TIMESTAMP` chịu chuyển đổi timezone session, còn `DATETIME` không tự chuyển đổi; phải kiểm tra kiểu cột thực tế.
- Mốc ngày trong lọc doanh thu, mã hóa đơn theo ngày, hạn trả 7 ngày, thời hạn thanh toán, heartbeat, lịch 02:00 sao lưu và lịch dọn giỏ có thể đổi. Không để dữ liệu cũ UTC và dữ liệu mới giờ Việt Nam lẫn trong cùng cột mà không có quy ước.
- Log cũ, log mới và thời gian từ webhook/API bên ngoài có thể khác múi giờ. Payload có offset phải được chuẩn hóa theo quy ước đã chọn, không cộng 7 giờ thêm lần nữa.

**Các bước kiểm tra trước khi người dùng quyết định:**

1. Sao lưu CSDL, code và trạng thái scheduler; thử trên bản sao cô lập, không chạy SQL chuyển đổi trên CSDL thật trong đợt này. Ghi timezone OS/PHP/MySQL và `@@global.time_zone`, `@@session.time_zone`, kiểu cột, min/max timestamp và các đơn/phiếu đang mở.
2. Chọn quy ước: **ưu tiên lưu UTC, chỉ đổi cách hiển thị/lọc/lịch sang giờ Việt Nam** để tránh viết lại dữ liệu cũ. Nếu bắt buộc đổi timezone ứng dụng sang `Asia/Ho_Chi_Minh`, chốt rõ cách đọc UTC cũ và lưu dữ liệu mới trước khi sửa; không chỉ đổi một dòng cấu hình rồi đưa lên thật.
3. Với phương án chuyển dữ liệu, lập danh sách cột và mốc cutover; script riêng phải idempotent, có bảng đối chiếu trước/sau và xử lý `DATETIME`/`TIMESTAMP` đúng timezone session. Không áp dụng quy tắc cộng 7 giờ hàng loạt; giữ nguyên offset của dữ liệu nguồn đã chuẩn hóa.
4. Trên bản sao, kiểm tra mẫu hóa đơn cũ và mới ở 16:59/17:00 UTC (23:59/00:00 Việt Nam), lọc doanh thu theo ngày, hạn trả trước/đúng/sau 7 ngày, đơn chờ thanh toán, webhook tới trễ và mã hóa đơn không trùng.
5. Kiểm tra scheduler chạy hủy đơn mỗi phút, sao lưu đúng 02:00 Việt Nam, heartbeat không âm; chạy suite test và kiểm tra đối chiếu số lượng bản ghi, tồn kho, tiền, điểm, timestamp/log. Chỉ triển khai ngoài giờ bán hàng sau khi người dùng duyệt tiêu chí và kết quả.

**Hoàn tác:** nếu chưa chuyển dữ liệu, đưa cấu hình/code về UTC, làm mới config cache theo quy trình vận hành và khởi động lại worker/scheduler; đối chiếu thời gian mẫu. Nếu đã chuyển dữ liệu, dừng ghi trước, phục hồi bản sao lưu hoặc chạy script đảo đã kiểm chứng dựa trên mốc cutover/bảng đối chiếu; phải có phương án giữ giao dịch phát sinh sau cutover. Không trừ 7 giờ hàng loạt và không phục hồi dump đè lên giao dịch mới chưa được bảo toàn. Kiểm tra lại báo cáo ngày, hạn trả, đơn chờ và lịch sao lưu trước khi mở bán.

### 6.3. Đồng bộ tài liệu kế hoạch (`ce09f08`)

`TaiLieu/KeHoach/Ke hoach.md` còn nhắc các thành phần đã xóa/đổi tên:

| Dòng | Trước | Sau |
|---|---|---|
| 433 | "sửa `ma_giaodich` → `ma_giao_dich`" | Ghi rõ thêm `ma_giao_dich` song song, `ma_giaodich` là cột chuẩn |
| 434 | `PaymentWebhookController` + test T66–T68 | `WebhookController@postThanhToan`; test T-CK03–T-CK09, T-DS07 |
| 445 | `app/Http/Controllers/Api/PaymentWebhookController.php` | `app/Http/Controllers/WebhookController.php` |
| 450 | `tests/Feature/PaymentWebhookTest.php` | `tests/Feature/ThanhToanChuyenKhoanTest.php` |
| 455 | `webhook_secret` | `sepay_webhook_api_key` |
| 459 | "Sửa fillable `ma_giao_dich`" | Thêm `ma_giao_dich`, giữ `ma_giaodich` là cột chuẩn |

## 7. Kiểm chứng đã chạy

### Đợt nâng cấp ban đầu — số liệu lịch sử

| Lệnh | Kết quả |
|---|---|
| `php artisan test` | **144 passed, 1 skipped (627 assertions)**, 829.59s |
| `php artisan view:cache` | Blade cached successfully |
| `php artisan route:list --json` | 103 routes |
| `php artisan he-thong:kiem-tra` (DB thật) | 3 FAIL, 6 WARN — dự kiến trước triển khai |
| `php artisan migrate --force` trên `doan_cnw_copy` | 32 migration, 27/27 bảng InnoDB |
| `php artisan test --filter=KhoaDongMysqlTest` trên `doan_cnw_copy` | Pass, 3.28s |
| EXPLAIN trước/sau index | Composite index được dùng (`type: range`) |

`KhoaDongMysqlTest` skip trong suite mặc định vì SQLite không có InnoDB/khóa dòng.
Cách chạy (chỉ trỏ vào bản sao):

```powershell
$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='doan_cnw_copy'
$env:DB_USERNAME='root'; $env:DB_PASSWORD=''
php artisan test --filter=KhoaDongMysqlTest
```

> `RefreshDatabase` sẽ xóa và migrate lại CSDL đích. **Không bao giờ** trỏ vào
> `doan_cnw`.

### Đợt bổ sung cùng ngày — đối chiếu với HEAD `8835b52`

| Lệnh/kiểm tra | Kết quả thực chạy |
|---|---|
| `XDEBUG_MODE=off php artisan test` — lượt đầu | 157 passed, 7 failed, 1 skipped; 764 assertions, 244.99s |
| Filter chốt chặn + trả giảm giá + phiếu chờ, sau sửa kỳ vọng test | 16 passed, 5 failed; 138 assertions, 5.60s; 5 lỗi constructor partial mock, sau đó đã sửa |
| `XDEBUG_MODE=off php artisan test --filter=LenhNguyHiemTest` — sau sửa mock | 19 passed, 115 assertions, 0.75s |
| `XDEBUG_MODE=off php artisan test --compact` — cuối | **164 passed, 1 skipped, 792 assertions, 230.67s, exit 0** |
| `php artisan route:list --json` trước/sau | **103 routes** cả hai lần; 1 POST webhook, đúng tên/action, giữ `throttle:60,1` |
| Tìm đường dẫn cá nhân trong README/`docs/`/`scripts/` | **0 kết quả** |
| Mục lục README / `config/app.php` | 19 anchor hợp lệ; không còn 15.5; cấu hình timezone không đổi (UTC) |

165 ca hiện tại gồm **11 Unit + 154 Feature**; Feature gồm `LenhNguyHiemTest`
19 ca, `PosWorkflowTest` 32 và `ThanhToanChuyenKhoanTest` 19. Một skip vẫn là
`KhoaDongMysqlTest` vì suite dùng SQLite `:memory:`. Lượt bật Xdebug đã dừng trước
kết quả cuối, không tính là pass/fail. Không kiểm lại MySQL, không chạy migration
hoặc lệnh phá hủy trên CSDL thật, không đọc/ghi `.env`, không thử luồng trình duyệt
POS trong đợt bổ sung. Các số liệu MySQL/health check phía trên thuộc đợt trước.

## 8. Hướng dẫn migration an toàn (khi triển khai)

1. **Sao lưu**: `mysqldump -u root --single-transaction --routines --triggers
   --events doan_cnw > doan_cnw-backup-20261110.sql` (hoặc `php artisan sao-luu:csdl`).
2. **Tạo bản sao** để thử: `CREATE DATABASE doan_cnw_copy;` rồi import bản dump.
3. **Thử trên bản sao**:
   ```powershell
   $env:DB_CONNECTION='mysql'; $env:DB_DATABASE='doan_cnw_copy'
   php artisan migrate --force
   php artisan he-thong:kiem-tra   # Engine phải OK
   ```
4. **Chạy trên CSDL thật** ngoài giờ bán hàng: `php artisan migrate --force`.
   Sáu migration sẽ chạy, gồm chuyển 27 bảng sang InnoDB (thời gian tùy dung lượng).
5. **Xác nhận**: `php artisan he-thong:kiem-tra` — mục *Engine lưu trữ* = OK,
   *Migration* = OK; bán thử một đơn tiền mặt và một đơn chuyển khoản.
6. **Nếu cần quay lui**: dừng ứng dụng, khôi phục bản dump ở bước 1. Không dựa vào
   `migrate:rollback` cho migration InnoDB (down cố ý để trống).

## 9. Rủi ro còn lại

- **Task Scheduler** phải cài thủ công trên máy cửa hàng; chưa cài thì đơn quá hạn
  chỉ bị hủy "lười" và health check báo FAIL.
- **Webhook không kiểm tra hạn chờ**: tiền về cho đơn quá hạn nhưng chưa hủy vẫn
  hoàn tất (giữ nguyên hành vi, test T-CK18).
- **Trả hàng lỗi**: hàng hỏng tăng `soluong_hong`, không cộng tồn tốt (đúng thiết kế).
- **Đối soát là thủ công**: chỉ đánh dấu + ghi chú, chưa tự khớp tiền vào hóa đơn.
- **Hai cột mã giao dịch song song** vẫn tồn tại (mục 6.1).
- **Route webhook trùng đã được gỡ** trong đợt bổ sung: giữ duy nhất bản
  `routes/web.php:34-36`, có `throttle:60,1`; không còn là rủi ro chờ quyết định.
- **Múi giờ UTC**: `config/app.php` giữ mặc định; giờ hóa đơn/log lệch 7 giờ so với
  Việt Nam. Chưa đổi vì sẽ làm lệch dữ liệu cũ.
- **`db:wipe` đã được bảo vệ** trong đợt bổ sung; không chạy lệnh trên CSDL thật để kiểm chứng.
- **SQLite test không chứng minh được khóa dòng/rollback thật** — đã có test MySQL
  riêng nhưng bị skip mặc định.

## 10. Cần người dùng xác nhận

1. **Chạy migration trên CSDL thật** `doan_cnw` (6 migration, gồm InnoDB) — thời
   điểm, thời gian dừng bán hàng, và xác nhận đã có bản sao lưu.
2. **Hợp nhất `ma_giaodich`/`ma_giao_dich`** theo kế hoạch mục 6.1 (xóa cột
   `ma_giao_dich`) — có hệ thống báo cáo bên ngoài nào đọc cột này không?
3. **Chuyển múi giờ** `config/app.php` sang `Asia/Ho_Chi_Minh` — dữ liệu cũ sẽ hiển
   thị lệch nếu đổi; cần quyết định có chuyển đổi dữ liệu hay không.
4. **Cài Task Scheduler** trên máy cửa hàng (script đã có) và thêm/cấu hình
   `LOG_STACK=daily` trên máy thật; đợt bổ sung không đọc/ghi `.env`.
5. **Cấu hình `SEPAY_WEBHOOK_API_KEY`** và Telegram trên máy thật (thiếu theo đợt kiểm trước).
6. **Đổi mật khẩu toàn bộ tài khoản demo** trước khi cho nhân viên dùng.

Người dùng đã yêu cầu bảo vệ `db:wipe` và bỏ route webhook trùng trong đợt bổ sung; hai mục này không còn chờ quyết định.

## 11. Cách hoàn tác theo nhóm

Mỗi nhóm là một commit độc lập trên `feat/san-sang-van-hanh`:

| Commit | Nội dung | Hoàn tác |
|---|---|---|
| `e21537a` | Hoàn điểm khi trả hàng | `git revert e21537a` |
| `8777efa` | Đối soát chuyển khoản | `git revert 8777efa` |
| `7b8b0bf` | Test toàn vẹn giao dịch | `git revert 7b8b0bf` |
| `c88345f` | Heartbeat + `he-thong:kiem-tra` | `git revert c88345f` |
| `91feb18` | CRLF cho file batch | `git revert 91feb18` |
| `00f7489` | `sao-luu:csdl` | `git revert 00f7489` |
| `0bec00e` | Chốt chặn production | `git revert 0bec00e` |
| `7b603ce` | Throttle + cảnh báo mật khẩu + CLI tài khoản | `git revert 7b603ce` |
| `505237a` | Log daily + che token | `git revert 505237a` |
| `27329a5` | Test Bước 5B | `git revert 27329a5` |
| `02aa5b2` | InnoDB + kiểm tra engine | `git revert 02aa5b2` (migration down rỗng — cần khôi phục backup nếu đã chạy trên CSDL thật) |
| `0c68a7d` | Index + N+1 | `git revert 0c68a7d` (hoặc chỉ `migrate:rollback` migration 000004) |
| `c597d47` | Lỗi POS + trang lỗi | `git revert c597d47` |
| `ce09f08` | Đồng bộ tài liệu kế hoạch | `git revert ce09f08` |
| Tài liệu | README + báo cáo này | `git checkout <commit> -- README.md docs/` |

## 12. Phụ lục — thay đổi test và lý do

- `tests/Feature/ExampleTest.php`: thêm `RefreshDatabase` (trang đăng nhập truy vấn
  `users` để cảnh báo mật khẩu mặc định).
- `tests/Feature/ThanhToanChuyenKhoanTest.php` (T-CK16): siết assertion doanh thu
  theo ngày trước/sau webhook; hành vi nghiệp vụ không đổi.
- `tests/Feature/KiemTraHeThongTest.php`: thêm test engine không áp dụng cho SQLite.
- Ba thay đổi trên thuộc đợt nâng cấp ban đầu (145 ca). Các thay đổi trong đợt
  bổ sung đối chiếu với HEAD `8835b52` được liệt kê riêng bên dưới, không hồi tố
  nội dung hoặc số liệu của đợt trước.

### Đợt bổ sung — route, chốt chặn và điểm trả hàng

- `ThanhToanChuyenKhoanTest.php:42`: thêm test đếm ngay lúc đăng ký POST webhook,
  trước khi Laravel ghi đè route trùng; kiểm tra một route, action/name đúng và
  `throttle:60,1`. 18 test T-CK cũ vẫn giữ.
- `LenhNguyHiemTest.php`: mở rộng từ 4 lên 19 ca; thay `RefreshDatabase` bằng mock
  DB/schema/migrator để kiểm tra cả nhánh cho phép mà không phá hủy dữ liệu.
  Partial mock chỉ chọn `call,runCommand` qua lớp con không có attribute
  `AsCommand`, tránh lỗi Mockery sao chép attribute Symfony thiếu tham số.
- `PosWorkflowTest.php`: siết test giảm giá + dùng điểm với nhật ký, không cộng
  trước duyệt và không hoàn lần hai; giữ test cộng dồn nhiều lần, hàng lỗi và
  làm tròn. Thêm 4 test: floor lũy kế, tiền hoàn 0 theo giá trị hàng, phiếu chờ
  duyệt giữ hạn mức, dòng hàng trùng bị từ chối.
- Lượt toàn bộ đầu tiên có 7 lỗi kiểm thử: 5 do partial mock/attribute Symfony;
  2 do kỳ vọng mới sai về dấu câu nhật ký và đếm movement toàn bảng có dữ liệu
  seeder. Sửa đúng chuỗi thực tế và giới hạn assertion movement theo hóa đơn,
  không đổi nghiệp vụ hoặc bỏ assertion để che lỗi. Kết quả các lượt chạy được
  ghi ở mục 7.
