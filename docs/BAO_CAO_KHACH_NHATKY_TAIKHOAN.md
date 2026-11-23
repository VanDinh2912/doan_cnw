# Báo cáo: Hồ sơ khách · SĐT · Nhật ký kiểm toán · Quản lý tài khoản

- **Nhánh:** `feat/khach-nhatky-taikhoan` (tạo từ `feat/dat-hang-p2-giao-tan-noi` @ `14c8f54`,
  theo phương án 1 người dùng chọn ngày 2026-11-15; nền gồm toàn bộ P0/P1/P2 + LỖI B).
- **Ngày bắt đầu:** 2026-11-15.
- **Prompt:** HỒ SƠ KHÁCH · SĐT · NHẬT KÝ KIỂM TOÁN · QUẢN LÝ TÀI KHOẢN (6 stage 0→5).
- **Tiến độ:** xem `docs/TIEN_DO_KHACH_NHATKY_TAIKHOAN.md`.
- **Ràng buộc:** không đọc/ghi/in `.env`; không đụng DB thật `doan_cnw` (chỉ ĐỌC);
  migration chỉ viết sẵn, chứng minh trên SQLite `:memory:` + `doan_cnw_copy`;
  không enum/FK mới; không đổi nghiệp vụ POS/webhook/đặt hàng online hiện có.

## Stage 0 — Nền + khảo sát (chỉ đọc, không đổi mã ứng dụng)

### 0.1. Git + điều kiện nền

- Cây sạch khi bắt đầu; nhánh mới tạo từ `14c8f54` (HEAD của nhánh P2 sau bước 2).
- `main` (`2bf1829`) là tổ tiên của nền; nền = main + 49 commit (P0 schema, P1 checkout,
  P2 giao tận nơi + QR online, cherry-pick LỖI B, chốt chặn production).
- Nhánh liên quan phát hiện trước khi chọn nền: **`fix/khach-hang-so-dien-thoai`**
  (từ main, 3 commit `c589928` test / `d197937` fix / `e5b82cc` docs, +797 dòng) đã làm
  phần lớn Stage 1: Rule chung `App\Rules\SoDienThoaiHopLe` (chuẩn hóa + regex
  `/^0(3|5|7|8|9)\d{8}$/`), áp ở admin/POS nhanh/đăng ký, view tel/inputmode/pattern,
  422 dòng test, báo cáo `BAO_CAO_KHACH_HANG.md`. **Chưa nằm trong nền.**
  → Stage 1 sẽ merge nhánh này (phương án 1 đã duyệt), giữ test hai phía, bổ sung
  phần spec mới còn thiếu (chốt tầng model, thống kê + lệnh dry-run, hồ sơ khách…).
- Không đụng `wip/p2-giao-tan-noi`; không merge main; không push.

### 0.2. Baseline full suite (số thật)

- **Baseline (2026-11-15, `XDEBUG_MODE=off php artisan test`): 522 test —
  480 pass / 42 skip / 0 fail, 2255 assertions, 477.59s.** Skip = 42 test MySQL-copy
  (chỉ chạy trên `doan_cnw_copy` bằng filter chính xác). Không giảm test so với nền P2.
  Thời gian dài hơn lượt P2 (306.799s) do chạy song song với khảo sát chỉ đọc; số đo thật.
- Chạy trên SQLite `:memory:` theo `phpunit.xml`, `XDEBUG_MODE=off`, lệnh
  `php artisan test`; chốt chặn RefreshDatabase trong `tests/TestCase.php` giữ nguyên.
- Test tạm Stage 0 (`TmpStage0KhaoSatTest`, 9 test) chạy riêng để tái hiện triệu chứng,
  **không commit**, đã xóa trước khi commit stage.

### 0.3. migrate:status + he-thong:kiem-tra (chỉ đọc trên DB thật)

- **36/36 migration đã áp** trên `doan_cnw`, gồm cả M1
  (`2026_11_12_000001_add_online_order_fields_to_invoices_table`, batch 5) và M3
  (`2026_11_14_000003_add_delivery_fields_to_invoices_table`, batch 5), role khachhang
  + `customers.user_id` (batch 4), `activity_logs` (batch 1).
  **Trái tiền đề prompt** ("DB thật có thể CHƯA có M1–M3"): thực tế đã có đủ — tái xác
  nhận lần 2 ngày 2026-11-15. Guard `Schema::hasColumn` hiện có vẫn giữ tới khi người
  dùng xác nhận gỡ (TODO từ P2, xem `BAO_CAO_DAT_HANG_P2.md`).
- `he-thong:kiem-tra`: **1 lỗi, 5 cảnh báo** — FAIL thiếu `SEPAY_WEBHOOK_API_KEY`
  (webhook sẽ 401; người dùng chưa xoay token, đúng trạng thái đã ghi nhớ); WARN
  Telegram thiếu token/chat-id, `APP_ENV=local`, `APP_DEBUG=true`, chưa cache cấu hình,
  log kênh `single`. OK: scheduler chạy (0 phút trước), không đơn quá hạn tồn đọng,
  đủ cấu hình VietQR, quyền ghi, **toàn bộ bảng InnoDB**, migration đủ.
- Không in giá trị bí mật nào (lệnh chỉ báo thiếu/đủ).

### 0.4. Bảng độ lệch schema (mã dùng vs DB thật)

| Cột/bảng mã đang dùng | DB thật `doan_cnw` | Kết luận |
|---|---|---|
| `invoices.kenh, ma_tra_cuu, ten_khach_dat, so_dienthoai_dat, ghi_chu_khach, han_giu` (M1) | CÓ (information_schema 2026-11-15) | Không lệch |
| `invoices.hinh_thuc_nhan, ten_nguoi_nhan, sdt_nguoi_nhan, dia_chi_giao, phi_giao_hang, trang_thai_giao` (M3) | CÓ | Không lệch |
| `users.role` (giá trị `khachhang`) | CÓ, varchar (không enum) | Không lệch |
| `customers.user_id` (nullable, UNIQUE) | CÓ | Không lệch |
| `activity_logs` (9 cột hiện tại) | CÓ | Stage 2 sẽ thêm cột (chỉ viết sẵn) |
| `users.khoa_luc`, `users.bat_buoc_doi_mat_khau` | CHƯA (mã cũng chưa dùng) | Stage 3 thêm, kèm guard |
| FK trên giấy (migrations) vs DB thật | DB thật **0 FK** (MyISAM cũ, đã chuyển InnoDB nhưng không tạo FK) | Giữ nguyên trạng, không thêm FK |

- Phát hiện phụ (chỉ ghi, không sửa ở Stage 0): `customers.dia_chi` là **varchar(150)**
  trong khi `ThongTinCaNhanController::postProfile` và `KhachHangController::validated`
  cho `max:255` → địa chỉ 151–255 ký tự sẽ lỗi SQL trên MySQL thật (SQLite không chặn).
  Đưa vào "Cần người dùng xác nhận" (sửa validation về 150 hay nới cột — đổi cột cần migration).
- `customers.so_dienthoai` varchar(15) NOT NULL + **UNIQUE sẵn có**; `customers.user_id`
  UNIQUE (một hồ sơ tối đa một tài khoản — chống gán trùng đã có ở tầng DB).

### 0.5. Triệu chứng 1 — khách đăng nhập không xem được lịch sử đơn

**Đường đi hiện có:** header storefront `_header.blade.php:39` → `profile.show`
(`/thong-tin-ca-nhan`) → nav "Lịch sử đơn hàng" → `profile.orders`
(`/thong-tin-ca-nhan/don-hang`) → `ThongTinCaNhanController::getOrders`
(`app/Http/Controllers/ThongTinCaNhanController.php:59-68`) →
`$khachHang->donHangs()` = `invoices.customer_id` (quan hệ `KhachHang::donHangs`,
`app/Models/KhachHang.php:22-25`). Route + nút + view **đã tồn tại**; không phải 404.

**Hai nguyên nhân gốc (bằng chứng test tạm + dữ liệu thật):**

1. **500 khi lịch sử có đơn POS:** `resources/views/thongtincanhan/orders.blade.php:77`
   dựng link `route('don-hang.trangthai', $don->ma_tra_cuu)`. Đơn POS có
   `ma_tra_cuu = NULL` → `MissingRequiredParameterException` bọc trong `ViewException`
   → **500 toàn trang**. Bằng chứng: `[EVIDENCE-1] Illuminate\View\ViewException:
   Missing required parameter for [Route: don-hang.trangthai] [URI: don-hang/{ma_tra_cuu}]
   [Missing parameter: ma_tra_cuu]. (View: .../thongtincanhan/orders.blade.php)`.
   Dữ liệu thật: hồ sơ `customers.id=102` gắn `users.id=4` (role khachhang) có **2 đơn:
   invoice 9 (kenh=pos, ma_tra_cuu NULL) + invoice 14 (online)** → trang lịch sử của
   tài khoản khách duy nhất hiện đang 500 trên môi trường thật.
2. **Link "Xem chi tiết" không dùng được cho chủ đơn:** `getTrangThai`
   (`DatHangOnlineController.php:314-328`) bắt buộc bằng chứng session (`coBangChung`)
   → khách đăng nhập xem lịch sử ở phiên không có bằng chứng bị **302 về form tra cứu**
   (`[EVIDENCE-1b]`). Bằng chứng chỉ được ghi cho phiên đặt đơn (`postDat`) hoặc phiên
   tra cứu đúng mã+SĐT (`postTraCuu`).

**Đã loại trừ:** `postDat` **CÓ gắn `customer_id`** khi khách đăng nhập
(`DatHangOnlineController.php:225-227`: `auth()->user()?->khachHang?->id` truyền vào R1).
Đơn online guest (chưa đăng nhập) thì `customer_id=null` — 3/4 đơn online thật là guest.
Đơn quầy chỉ gắn `customer_id` khi thu ngân chọn khách; hồ sơ phải được liên kết
(`customers.user_id`) thì khách mới thấy — đây là câu chuyện liên kết ở Stage 4.

### 0.6. Triệu chứng 2 — hai định dạng che SĐT + form điền sẵn SĐT đầy đủ

| Nơi | Định dạng | Vị trí |
|---|---|---|
| Header trang hồ sơ + lịch sử | inline `substr(0,3).'****'.substr(-2)` → `091****78` (3 đầu + 4 sao + **2 cuối**) | `resources/views/thongtincanhan/profile.blade.php:20`, `orders.blade.php:20` |
| Log/Telegram/online | `CheThongTin::soDienThoai` = 3 đầu + `****` + **3 cuối** → `091****567`; chuỗi <7 ký tự che toàn bộ | `app/Support/CheThongTin.php:8-18`; dùng ở `DatHangOnlineController`, `ThongBaoService` |

- Người dùng mô tả "082***52"; mã thực tế là 4 sao (`082****52`) — vẫn lệch chuẩn
  `CheThongTin` (2 cuối vs 3 cuối). Stage 4 thống nhất một định dạng `CheThongTin`.
- **Form cập nhật điền sẵn SĐT đầy đủ:** `profile.blade.php:72`
  `value="{{ old('so_dienthoai', $khachHang?->so_dienthoai) }}"` → mở trang là thấy
  nguyên số trong ô nhập (và trong view-source). Bằng chứng: `[EVIDENCE-2] HTML chứa
  value="0912345678" + '091****78', không dùng CheThongTin`.
- `postProfile` (`ThongTinCaNhanController.php:22-57`) validate
  `regex:/^0\d{9,10}$/` (10–11 số, mọi đầu 0), **không chuẩn hóa, không kiểm trùng**,
  cho đổi SĐT tự do không cần mật khẩu — sẽ thay bằng luồng "Đổi số điện thoại" ở Stage 4.

### 0.7. Triệu chứng 3 — ô SĐT admin nhận chữ; giả thuyết "chữ → đã tồn tại"

**Bảng điểm nhập SĐT khách (nền hiện tại, thứ tự validate/chuẩn hóa):**

| # | Điểm nhập | Vị trí | Validate hiện tại | Chuẩn hóa |
|---|---|---|---|---|
| 1 | Admin thêm khách | `KhachHangController.php:24-29` + `validated():80-91` | `required,string,max:15,unique(ignore)` | KHÔNG |
| 2 | Admin sửa khách | `KhachHangController.php:36-41` + `validated()` | như trên | KHÔNG |
| 3 | POS thêm nhanh (AJAX) | `KhachHangController.php:66-78` | `required,string,max:15,unique` | KHÔNG |
| 4 | POS tìm khách | `KhachHangController.php:54-64` | `LIKE 'sdt%'`, không escape `%/_` | KHÔNG |
| 5 | Đăng ký storefront | `AuthController.php:82` | `regex:/^0\d{9,10}$/` | KHÔNG |
| 6 | Hồ sơ khách tự sửa | `ThongTinCaNhanController.php:26` | `regex:/^0\d{9,10}$/` | KHÔNG |
| 7 | Import xlsx | `NhapDuLieuTaiLieu.php:52-66` | `trim` + bỏ qua rỗng + exists | trim |
| 8 | Seeder mẫu | `DuLieuMau300Seeder.php:73-77` | cast `(string)` | KHÔNG |
| 9 | Model | `KhachHang.php` | không mutator/saving | KHÔNG |

**Bằng chứng tái hiện (test tạm, SQLite):**
- `[EVIDENCE-3]` POST `/khachhang/them` với `so_dienthoai='abcdef'` → **302 thành công**,
  DB có bản ghi `'abcdef'` (chữ được chấp nhận, không kiểm định dạng).
- `[EVIDENCE-3b]` POST `/khachhang/nhanh` `'abcdef'` → **201**, DB có `'abcdef'`.
- `[EVIDENCE-3c]` trùng số hợp lệ thật → lỗi "Số điện thoại đã tồn tại." (đúng nghiệp vụ).
- `[EVIDENCE-3d]` khách SĐT rỗng có sẵn trong DB + thêm số hợp lệ mới → **không** báo trùng giả.

**Kết luận giả thuyết "chuẩn hóa ra rỗng rồi so trùng với khách SĐT rỗng":**
**không tái hiện được trên nền hiện tại** vì nền không có bước chuẩn hóa nào — chữ
được lưu thẳng. Nhánh `fix/khach-hang-so-dien-thoai` chuẩn hóa bằng
`SoDienThoaiHopLe::chuanHoa` (trim, bỏ `[\s.\-()]`, +84/84→0) nhưng **không strip chữ**,
và Rule định dạng chạy TRƯỚC unique → chữ nhận thông báo "không hợp lệ", không phải
"đã tồn tại". Triệu chứng "nhập chữ báo đã tồn tại" người dùng nhớ có thể từ một bản
trung gian khác; ghi nhận **chưa kiểm chứng được**, không kết luận bừa. Dù nguồn gốc
là gì, spec Stage 1 (bail định dạng trước, trùng chỉ với số hợp lệ đã chuẩn hóa, bỏ
qua rỗng/NULL và bản ghi đang sửa, chốt model) triệt tiêu cả hai lớp lỗi.
Báo cáo cũ 2026-11-11 của nhánh fix ghi nhận dữ liệu thật từng có 1 bản ghi SĐT là
tên người ("<tên-người>") — xem mục 0.8, nay đã hết.

### 0.8. Số liệu DB thật (CHỈ ĐỌC, 2026-11-15, không in PII)

| Chỉ số | Giá trị |
|---|---|
| Tổng khách (`customers`) | 101 |
| SĐT NULL / rỗng `''` | 0 / 0 |
| Sai định dạng chặt `/^0(3\|5\|7\|8\|9)\d{8}$/` (REGEXP MySQL) | **0** |
| Trùng SĐT (GROUP BY HAVING >1) | 0 |
| Users theo vai trò | admin 1, thungan 1, thukho 1, khachhang 1 |
| `invoices` online | 4 đơn — 3 đơn `customer_id NULL` (guest), 1 gắn hồ sơ |
| `invoices` pos | 7 đơn — 6 đơn `customer_id NULL`, **cả 7 `ma_tra_cuu NULL`** |
| Hồ sơ đã liên kết tài khoản | 1 (customers 102 ↔ users 4, 2 đơn: 1 pos + 1 online) |
| `activity_logs` | 40 dòng, 2026-11-02 01:23:39 → 2026-11-15 05:35:34 (UTC) |

- Khác báo cáo 2026-11-11 (101 khách, 1 sai định dạng): bản ghi sai **đã được sửa**
  trong khoảng 11→15/11 (quan sát chỉ đọc; không can thiệp). Nay 100% khớp quy tắc chặt.
- Vẫn đề xuất lệnh chuẩn hóa **dry-run mặc định** ở Stage 1 theo spec (không tự chạy),
  dù hiện tại không có gì để chuẩn hóa — lệnh hữu ích cho dữ liệu tương lai.

### 0.9. Nhật ký hiện có (khảo sát cho Stage 2)

- **Bảng `activity_logs`** (`2026_01_01_000013_create_activity_logs_table.php`):
  `id, user_id (nullable, FK giấy), hanh_dong(50), doi_tuong(100), doi_tuong_id (nullable),
  mo_ta (text), ip (nullable), timestamps`; index `(doi_tuong, doi_tuong_id)` + `hanh_dong`.
  Chưa có: truoc/sau JSON, user_agent, nguon, nhom_id, doi_tuong_loai.
- **Model** `NhatKyHoatDong` (`app/Models/NhatKyHoatDong.php`): fillable 6 cột,
  `user()` belongsTo; **chưa chặn update/delete** (append-only sẽ làm ở Stage 2).
- **Service** `NhatKyService::ghi(Request, hanhDong, doiTuong, doiTuongId, moTa)` static
  (`app/Services/NhatKyService.php:10-25`): user_id lấy từ request, ip từ request;
  không transaction-bound, không diff.
- **24 điểm gọi** (grep `NhatKyService::ghi|NhatKyHoatDong::create`):
  - Auth: đăng nhập/đăng ký/đăng xuất (`AuthController.php:63,139,147`).
  - Sản phẩm: thêm/sửa/xóa (`SanPhamController.php:52,84,103`).
  - NCC: thêm/sửa/xóa (`NhapHangController.php:37,50,62`); đơn nhập: tạo/nhận hàng (:119,:178).
  - Kho: nhập trực tiếp (`KhoController.php:58`); giữ giỏ (`HoldController.php:29`).
  - Kiểm kê: duyệt (`KiemKeController.php:87`); trả hàng: tạo/duyet/… (`TraHangController.php:119,147,239`).
  - Online: đặt hàng (`DatHangOnlineController.php:238`, SĐT đã che qua CheThongTin);
    gia hạn đơn (`DonHangController.php:212`); đối soát (`DoiSoatController.php:64`).
  - Lệnh: `nguoidung:tao` (`TaoNguoiDung.php:65`), `nguoidung:doi-matkhau`
    (`DoiMatKhauNguoiDung.php:43`) — user_id null, mo_ta chứa **email đầy đủ**
    (Stage 2 sẽ che theo danh sách nhạy cảm; nếu phải sửa test cũ sẽ nêu lý do từng test).
- **Chưa được ghi (điểm lọt sơ bộ, bảng chi tiết ở Stage 2):** CRUD khách hàng
  (`KhachHangController` không gọi nhật ký), thanh toán POS tiền mặt/QR
  (`postThanhToan`), webhook hoàn tất đơn, hủy quá hạn (scheduler), trả hàng ở các
  nhánh khác, increment/decrement điểm, `DB::table`/query-builder updates, import/seeder.
- **UI `/nhatky`** (`NhatKyController.php`, admin): lọc duy nhất `hanh_dong`,
  `with('user')` chống N+1, paginate 30; view `nhatky/index.blade.php` hiển thị
  `user?->name ?? 'Hệ thống'` (null-safe), giờ `created_at->format('d/m/Y H:i:s')`
  đang theo **UTC** (app chưa đổi múi giờ — Stage 2 hiển thị giờ Việt Nam tại view,
  không đổi config). Chưa lọc người dùng/đối tượng/nguồn/khoảng ngày, chưa CSV,
  chưa chi tiết diff.

### 0.10. Users / role / middleware / lệnh nguoidung:*

- `User` (`app/Models/User.php`): fillable `name,email,password,role`; hidden
  `password,remember_token`; cast `password=hashed`; quan hệ `donHangs`, `bienDongKhos`,
  `khachHang` (hasOne). **Chưa có** `khoa_luc`, `bat_buoc_doi_mat_khau`,
  `last_login_at` (Stage 3 thêm cột tối thiểu theo spec: khoa_luc + bat_buoc_doi_mat_khau;
  "lần đăng nhập gần nhất nếu có" → hiện chưa có cột, sẽ ghi nhận hoặc thêm nullable).
- Vai trò: chuỗi `admin|thungan|thukho|khachhang` (không enum). Middleware:
  `role` = `CheckRole` (403 nếu sai vai trò), `staff.only` = `RedirectKhachhangFromStaff`
  (khachhang → storefront kèm thông báo), guest → `auth.dangnhap`
  (`bootstrap/app.php:19-24`). CSRF trừ webhook. **Chưa có middleware khóa tài khoản.**
- Đăng nhập: `AuthController::postDangNhap` throttle 10,1; **không kiểm tra khóa**
  (chưa có cột); đăng xuất GET `/dangxuat` (auth). Đăng ký: **không throttle, không
  honeypot** (Stage 4 thêm); mật khẩu `min:8,confirmed` — quy tắc mật khẩu sẵn có duy nhất.
- `nguoidung:tao {email} {vai_tro} {--ten=}`: chỉ 3 vai trò nhân viên, mật khẩu nhập ẩn
  (`secret()`), kiểm email hợp lệ/trùng, ghi nhật ký user_id=null.
  `nguoidung:doi-matkhau {email}`: mọi tài khoản, mật khẩu ẩn, min 8, ghi nhật ký.
  Cả hai giữ nguyên ở Stage 3 (spec yêu cầu), bổ sung nhật ký chuẩn Stage 2.
- Scheduler (`routes/console.php`): `hold:clean` daily, `donhang:huy-qua-han` mỗi phút,
  `sao-luu:csdl` 02:00, heartbeat cache. Stage 2 **không** đăng ký lịch tự động cho
  `nhatky:don-cu`.
- `CanhBaoMatKhauMacDinh`: cảnh báo mật khẩu mặc định còn hiệu lực (dùng ở trang đăng
  nhập); `DoiMatKhauNguoiDung` gọi `quenCache()` sau đổi.

### 0.11. Rủi ro liên kết SĐT khi đăng ký (tiền đề Stage 4)

- `AuthController::postDangKy:109-132`: tìm hồ sơ theo SĐT **khớp chuỗi thô**; nếu hồ
  sơ chưa có `user_id` → **tự liên kết** (gán user_id + đổi ho_ten) trong transaction;
  nếu đã liên kết → lỗi "Số điện thoại đã được liên kết với tài khoản khác."
- **Không yêu cầu chứng minh sở hữu số** (không OTP/SMS — ngoài phạm vi). Bằng chứng
  `[EVIDENCE-4]`: đăng ký bằng SĐT của hồ sơ lạ → tài khoản mới tự sở hữu hồ sơ, trang
  hồ sơ hiện **điểm 50 + tổng đơn/chi tiêu của chủ thật**; lịch sử đơn quầy của chủ thật
  cũng sẽ hiện (khi trang lịch sử hết lỗi 500). `[EVIDENCE-4b]`: thông báo trùng **lộ
  việc SĐT đã tồn tại và đã liên kết**.
- **Kết luận: CÓ lộ dữ liệu nhạy cảm** → theo spec Stage 4 mục 1, mặc định an toàn:
  đăng ký KHÔNG tự liên kết; liên kết chỉ khi admin/thu ngân xác nhận thủ công;
  thông báo trùng dùng câu chung; cập nhật `AuthFlowTest` kèm ghi trước/sau.
- Chủ thật của số vẫn đăng ký được sau (hồ sơ chưa liên kết → tạo hồ sơ mới trùng SĐT?
  không — `customers.so_dienthoai` UNIQUE nên đăng ký số đã có hồ sơ sẽ phải đi nhánh
  liên kết; sau thay đổi: tạo tài khoản KHÔNG hồ sơ hoặc hồ sơ mới? → phương án cụ thể
  trình ở Stage 4, ghi "Cần người dùng xác nhận").

### 0.12. Test tạm Stage 0 (không commit)

`tests/Feature/TmpStage0KhaoSatTest.php` — 9 test, 28 assertions, chạy
`--filter=TmpStage0KhaoSatTest` (Xdebug off): **9/9 pass** sau khi chỉnh đúng lớp
ngoại lệ thật (ViewException bọc MissingRequiredParameterException). Các bằng chứng
`[EVIDENCE-1..4b]` trích ở mục 0.5–0.7, 0.11. File đã xóa trước commit Stage 0;
không còn trong cây.

### 0.13. Checklist Stage 0 (§5)

- [x] Không đổi mã ứng dụng; không migration; không enum/FK; không áp gì lên DB thật.
- [x] Mọi truy vấn DB thật chỉ ĐỌC (migrate:status, he-thong:kiem-tra, SELECT/SHOW aggregate).
- [x] Không đọc/ghi/in `.env`; không in PII (chỉ số liệu tổng hợp).
- [x] Test tạm không commit; cây sạch cuối stage (sau khi xóa test tạm).
- [x] Baseline full suite số thật (mục 0.2); không giảm test.
- [x] Báo cáo + file tiến độ cập nhật; commit docs tách bạch.

### 0.14. Cần người dùng xác nhận (gộp tới Stage 5)

1. **Gỡ guard `Schema::hasColumn`** (M1/M3 + cột online): DB thật đã áp đủ 36 migration
   (xác nhận 2 lần). Giữ guard tới khi có lệnh gỡ (TODO từ P2).
2. **`customers.dia_chi` varchar(150) vs validate max:255**: chọn (a) hạ validation về
   150 (không migration) hay (b) nới cột lên 255 (migration viết sẵn). Đề xuất (a)
   cho Stage 4 nếu không có nhu cầu địa chỉ dài; chờ xác nhận.
3. **Hành vi đăng ký–liên kết** sẽ đổi theo mặc định an toàn của spec Stage 4 (không
   tự liên kết). Ghi trước/sau trong báo cáo Stage 4; người dùng có thể yêu cầu khác.
4. **SEPAY_WEBHOOK_API_KEY** đang thiếu (FAIL he-thong:kiem-tra) — người dùng tự xoay
   token rồi bật webhook (đã ghi nhớ từ đợt đối soát); không thuộc phạm vi prompt này.

## Stage 1 — Kiểm soát SĐT (merge nhánh cũ + bổ sung spec mới)

### 1.1. Tóm tắt

- Nhánh `feat/khach-nhatky-taikhoan`. Merge `fix/khach-hang-so-dien-thoai`
  (`2ead5af`, giữ nguyên test hai phía, chỉ xung đột README đã giải quyết) rồi bổ
  sung phần spec mới còn thiếu: **test `8957c9b` → feat `a220ca6` → docs (commit này)**.
- Một Rule/hàm chuẩn hóa dùng chung MỌI điểm nhập; **bail định dạng trước unique**;
  mutator model làm chốt cuối; lệnh chuẩn hóa **dry-run mặc định**. Không migration
  mới; không đổi nghiệp vụ POS/webhook/đặt hàng online.

### 1.2. Kiểm thử (số thật, Xdebug off)

| Mốc | Tests | Pass | Skip | Assertions | Thời gian |
|---|---|---|---|---|---|
| Baseline nền (Stage 0) | 522 | 480 | 42 | 2255 | 477.59s |
| Sau merge nhánh SĐT | 571 | 529 | 42 | 2395 | 320.79s |
| **Sau Stage 1 (full suite)** | **622** | **580** | **42** | **2584** | **341.19s** |

- Test mới `SoDienThoaiStage1Test` **51 ca**; chạy trước triển khai **đỏ 19 failed /
  32 passed (148 assertions), 2.24s** (đúng lý do: hồ sơ chưa dùng Rule chung, chưa
  bail, chưa mutator, form thiếu `tel`); sau triển khai **51/51 xanh**.
- Bộ SĐT gộp (`SoDienThoaiStage1Test|SoDienThoaiHopLeTest|KhachHangSoDienThoaiTest|SoDienThoaiTest`):
  **106 passed (336 assertions), 2.46s**.
- **MySQL-copy chạy thật** trên `doan_cnw_copy`, filter chính xác
  `--filter='MysqlCopyTest|KhoaDongMysqlTest'`: **42 passed (341 assertions), 9.92s,
  0 skip**. Đối chiếu `SHOW CREATE TABLE customers` (CHỈ ĐỌC) thật vs copy: **giống
  hệt** (`so_dienthoai` varchar(15) NOT NULL UNIQUE, `dia_chi` varchar(150),
  `user_id` nullable UNIQUE). Copy tự hoàn nguyên sau test: cột `kenh` đã gỡ (về
  trước M1), 0 đơn `P2-%`, 0 sản phẩm `SWEEP%`.
- **42 skip phân loại**: toàn bộ MySQL-copy — `AnhUrlMysqlCopyTest` (37) +
  `DatHangP0MysqlCopyTest` (1) + `DatHangP1MysqlCopyTest` (2) + `DatHangP2MysqlCopyTest`
  (1) + `KhoaDongMysqlTest` (1); chỉ chạy khi trỏ bản sao `*_copy`.
- `php artisan view:cache` ✅; `php -l` 7 tệp PHP thay đổi ✅. **Không giảm số test;
  không sửa test cũ** (522 nền + 49 merge giữ nguyên trạng).

### 1.3. Thay đổi theo file

| File | Thay đổi |
|---|---|
| `app/Rules/SoDienThoaiHopLe.php` | `chuanHoa()` ủy quyền cho `App\Support\SoDienThoai::chuanHoa` (giữ guard không-phải-chuỗi) — một nguồn chuẩn hóa |
| `app/Models/KhachHang.php` | Thêm mutator `soDienThoai()` (set → `SoDienThoai::chuanHoa`) — chốt chuẩn hóa mọi đường ghi |
| `app/Http/Controllers/KhachHangController.php` | `bail` + thông báo `string` tiếng Việt ở `validated()` (thêm/sửa) và `postTaoNhanh()` |
| `app/Http/Controllers/AuthController.php` | `bail` + thông báo `string` ở `postDangKy()` |
| `app/Http/Controllers/ThongTinCaNhanController.php` | `postProfile()` bỏ regex cũ `/^0\d{9,10}$/`, dùng chuẩn hóa + Rule chung + `unique()->ignore(hồ sơ)`; thông báo trùng chung |
| `resources/views/thongtincanhan/profile.blade.php` | Ô SĐT `type=tel/inputmode=numeric/maxlength=20/pattern` + JS nhẹ chặn chữ + hint; giữ `old()` |
| `app/Console/Commands/ChuanHoaSdtKhachHang.php` | **Mới** — `khachhang:chuan-hoa-sdt` dry-run mặc định, `--ap-dung` mới ghi |
| `tests/Feature/SoDienThoaiStage1Test.php` | **Mới** — 51 ca |

`app/Support/SoDienThoai.php` giữ nguyên (nguồn chuẩn hóa duy nhất, luồng online D12
đang dùng). Import `NhapDuLieuTaiLieu` đã chuẩn hóa từ nhánh merge + nay thêm mutator
model; seeder được mutator bao phủ.

### 1.4. Điểm nhập đã phủ

| Điểm nhập | Chuẩn hóa | Định dạng (bail) | Trùng (bỏ qua self/rỗng) |
|---|---|---|---|
| Admin thêm/sửa khách | ✅ merge + mutator | ✅ bail | ✅ `unique()->ignore` |
| POS thêm nhanh (`postTaoNhanh`) | ✅ merge + mutator | ✅ bail | ✅ unique (tạo mới) |
| POS tìm khách (`getTimKiem`) | ✅ merge (escape LIKE) | — (tìm kiếm) | — |
| Đăng ký storefront | ✅ merge + mutator | ✅ bail | (tự liên kết — Stage 4 đổi) |
| Hồ sơ khách (`postProfile`) | ✅ **mới** + mutator | ✅ **mới** | ✅ **mới** `ignore` + câu chung |
| Import xlsx / seeder | ✅ merge + mutator | (mutator chuẩn hóa) | — |
| Online checkout / webhook | ✅ `Support` (không đổi nghiệp vụ) | — | — |

Kịch bản **khách SĐT rỗng legacy** (`''`): đăng ký số hợp lệ không đụng độ; sửa khách
rỗng thành số hợp lệ được; thêm/POS nhanh/hồ sơ với `''` báo `required` (không báo
trùng). Chữ/ký tự lạ (`abcdef`, `09123abc78`, `+84 abc`, mảng...) → đúng
`Số điện thoại không hợp lệ. Nhập 10 chữ số, bắt đầu bằng 0.`, **không bao giờ**
"đã tồn tại" (test bail với bản ghi legacy `abcdef` tồn tại sẵn).

### 1.5. Migration

**Không thêm migration.** `customers.so_dienthoai` varchar(15) NOT NULL UNIQUE đã có
sẵn; đối chiếu `SHOW CREATE` thật vs copy giống hệt (mục 1.2). Không enum/FK mới.

### 1.6. Sai lệch spec / điểm dừng (ghi trung thực)

1. **Giả thuyết "chữ → đã tồn tại"** không tái hiện trên nền (Stage 0). Sau merge,
   Laravel **đã tự bỏ qua rule `unique` khi định dạng fail** — các test bail xanh
   ngay cả trước khi thêm `bail`. Vẫn thêm `bail` tường minh theo spec để bảo đảm
   thứ tự định-dạng-trước-trùng không phụ thuộc hành vi ngầm.
2. **Thông báo trùng ở hồ sơ khách** dùng câu chung `Số điện thoại này không thể sử
   dụng.` (khác câu `Số điện thoại đã tồn tại.` của admin) để không lộ việc SĐT tồn
   tại — đúng ràng buộc tuyệt đối. Stage 4 sẽ chuyển sang flow đổi SĐT có mật khẩu
   hiện tại + throttle.
3. **`customers.dia_chi` varchar(150) vs validate max:255**: ngoài phạm vi SĐT, giữ
   nguyên, chờ xác nhận (mục 0.14).
4. **Lệnh dry-run**: viết sẵn + chứng minh trên SQLite, **KHÔNG chạy trên DB thật**
   (kể cả dry-run) theo ràng buộc; chỉ đề xuất để người dùng tự chạy sau xác nhận.

### 1.7. Số liệu dữ liệu cũ (CHỈ ĐỌC) + đề xuất lệnh chuẩn hóa

- Stage 0 đã đếm trên `doan_cnw` (chỉ đọc): **101 khách, 0 rỗng/NULL/sai định
  dạng/trùng** (bản ghi sai "<tên-người>" trong báo cáo 11-11 đã được sửa). Stage 1
  **không sửa/xóa dữ liệu khách hiện có**.
- Đề xuất quy trình (người dùng tự chạy, agent không chạy):
  1. `php artisan khachhang:chuan-hoa-sdt` — **DRY-RUN**, chỉ liệt kê (che SĐT),
     phân loại "cần chuẩn hóa" / "sai định dạng" / "hợp lệ", không ghi.
  2. Rà kết quả; nếu đồng ý: `php artisan khachhang:chuan-hoa-sdt --ap-dung` — chỉ
     chuẩn hóa bản ghi hợp lệ hóa được (vd `+84…` → `0…`), **giữ nguyên** bản ghi sai
     định dạng (cần người thật cung cấp số đúng), **bỏ qua** bản ghi xung đột unique.

### 1.8. Checklist §5 (từng dòng)

- [x] Không enum mới; không migration; không FK mới; không áp lên DB thật; không cột
  mới nên không cần guard; POS + scheduler chạy bình thường (full suite xanh).
- [x] Hành vi POS/webhook/trả hàng/đặt hàng online/báo cáo không đổi; test hiện có
  xanh nguyên trạng (522 nền + 49 merge không sửa).
- [x] Không mật khẩu/token/SĐT-email-địa chỉ đầy đủ/`ma_tra_cuu`/số tài khoản trong
  log/JSON/HTML; lệnh che SĐT (`CheThongTin`); thông báo trùng hồ sơ dùng câu chung.
- [x] Escape đầu ra (Blade `{{ }}`); đăng ký giữ CSRF (throttle thêm ở Stage 4);
  route quản trị trong `auth + staff.only + role`; hồ sơ khách truy vấn theo chủ sở
  hữu (`Auth::user()->khachHang`).
- [x] Server kiểm mọi ràng buộc (chuẩn hóa + Rule + bail + unique + mutator); JS chỉ
  hỗ trợ, không phụ thuộc cho tính đúng đắn.
- [x] Test mới đỏ trước (19 failed) rồi xanh; full suite xanh 622; không giảm test;
  MySQL-copy chạy thật bằng filter chính xác (42 pass).
- [x] Không file tạm/secret/.env lọt commit; không xóa nhánh `wip/*`; cây sạch cuối
  stage; file tiến độ cập nhật.

### 1.9. Việc dở / rủi ro / câu hỏi

- **Stage 4 sẽ làm**: bỏ prefill SĐT đầy đủ ở form hồ sơ (Stage 1 mới siết validate,
  chưa đổi hiển thị che), flow "Đổi số điện thoại" (mật khẩu hiện tại + throttle),
  nhất quán một định dạng che `CheThongTin`, đổi mặc định đăng ký không tự liên kết.
- **Rủi ro còn lại**: đăng ký vẫn tự liên kết hồ sơ theo SĐT (lộ điểm/lịch sử — Stage 4
  xử lý); bản ghi SĐT sai định dạng legacy (nếu phát sinh) cần người thật sửa.
- **Câu hỏi chờ xác nhận**: giữ ở mục 0.14 (gỡ guard, `dia_chi` 150/255, hành vi đăng
  ký, SEPAY key) — không chặn Stage 2–3.

### 1.10. Git cuối Stage 1

Cây sạch. Chuỗi commit: `2ead5af` (merge) → `8957c9b` (test) → `a220ca6` (feat) →
docs (commit này). Không merge `main`, không push, không đụng `wip/*`.