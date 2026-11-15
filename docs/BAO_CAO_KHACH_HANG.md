# Báo cáo: Rà soát luồng khách hàng & chuẩn hóa số điện thoại

- **Ngày:** 2026-11-11
- **Nhánh:** `fix/khach-hang-so-dien-thoai` (tạo từ `main` @ `2bf1829`; **không merge, không push**)
- **Phạm vi:** model KHÁCH HÀNG (`customers`) — mọi điểm tạo/sửa/tìm/xóa khách và
  số điện thoại; chuẩn hóa + validate SĐT phía server; vá lỗi rõ ràng kèm test.
- **Ngoài phạm vi:** nghiệp vụ điểm tích lũy / hóa đơn / trả hàng; SĐT nhà cung cấp;
  thay đổi schema DB thật; tính năng mới ngoài khách hàng.
- **Ràng buộc đã tuân thủ:** không đọc/ghi/in `.env`; không migrate/seed/reset trên
  DB thật; test dùng SQLite `:memory:` + `RefreshDatabase`; kiểm tra dữ liệu thật
  chỉ ĐỌC.

## 1. Kết luận nhanh

- Lỗi gốc: ô SĐT ở form thêm/sửa khách và POS thêm nhanh chỉ validate
  `required|string|max:15|unique`, **không kiểm tra định dạng** nên nhận cả chữ
  (ví dụ dữ liệu thật có bản ghi SĐT = "Lê Văn Định").
- Đã có sẵn `unique` index trên `customers.so_dienthoai`
  (`database/migrations/2026_01_01_000006_create_customers_table.php:14`) →
  **không cần migration mới** cho chống trùng.
- Khắc phục: một Rule dùng chung `App\Rules\SoDienThoaiHopLe` (chuẩn hóa + regex
  di động VN 10 số), áp ở mọi điểm nhập; view thêm `type=tel/inputmode/pattern/
  maxlength` + JS chặn chữ; giữ `old()` và lỗi tiếng Việt.
- Dữ liệu thật: 101 khách, 0 SĐT rỗng, 0 trùng; **1 bản ghi sai** (SĐT là tên người).
  100/101 bản ghi khớp quy tắc chặt → áp dụng quy tắc chặt, không phải nới.
- Kiểm chứng: test mới viết trước khi sửa (đỏ 35/49), sau sửa xanh 49/49; full
  suite **224 pass, 1 skip (1208 assertions), 214.82s** — chi tiết mục 6.

## 2. Bảng khảo sát điểm nhập (file:dòng)

Ghi chú: cột vị trí và validate dưới đây là trạng thái **TRƯỚC khi sửa** (khảo sát
ban đầu); vị trí sau sửa ở mục 5.1.

| # | Điểm nhập | Vị trí | Validate SĐT (trước sửa) | Vấn đề |
|---|---|---|---|---|
| 1 | Thêm khách (form) | `app/Http/Controllers/KhachHangController.php:24-29,80-91` | `required,string,max:15,unique(ignore)` | Không định dạng → nhận chữ |
| 2 | Sửa khách (form) | `app/Http/Controllers/KhachHangController.php:36-41,80-91` | như trên | Không định dạng |
| 3 | POS thêm khách nhanh (AJAX) | `app/Http/Controllers/KhachHangController.php:66-78` | `required,string,max:15,unique` | Không định dạng; hiện không có UI gọi |
| 4 | POS tìm khách theo SĐT | `app/Http/Controllers/KhachHangController.php:54-64` | `LIKE 'sdt%'` | Không chuẩn hóa (`+84` không tìm ra); không escape `%`/`_` |
| 5 | Đăng ký storefront | `app/Http/Controllers/AuthController.php:77-135` | `regex:/^0\d{9,10}$/` | Cho 11 số & mọi đầu 0; không chuẩn hóa; link khách vãng lai theo SĐT thô |
| 6 | Import khách từ xlsx | `app/Console/Commands/NhapDuLieuTaiLieu.php:44-74` | `trim` | Không định dạng/chuẩn hóa |
| 7 | Seeder khách mẫu | `database/seeders/DuLieuMau300Seeder.php:73-77` | `(string)` cast | Không chuẩn hóa |
| 8 | Model | `app/Models/KhachHang.php:15-20` | fillable gồm `diem_tichluy`,`user_id`; không mutator | Không chuẩn hóa khi lưu |
| 9 | Migration bảng | `database/migrations/2026_01_01_000006_create_customers_table.php:11-18` | `string(15)->unique()` | Đã có unique; `dia_chi` nullable; `diem_tichluy` unsigned default 0 |
| 10 | View form khách | `resources/views/khachhang/form.blade.php:16` | input text thường | Thiếu `tel/inputmode/pattern/maxlength` |
| 11 | View đăng ký | `resources/views/auth/dangky.blade.php:48-54` | `type=tel`, có `old()` | Thiếu `inputmode/pattern/maxlength` |
| 12 | POS view (ô tìm khách) | `resources/views/pos/index.blade.php:79,565-597` | `type=search` | Không chặn chữ khi gõ |

**Khóa ngoại liên quan khi xóa khách:**

- `invoices.customer_id` → `nullOnDelete` (`...000007_add_customer_loyalty_to_invoices_table.php:12`).
- `returns.customer_id` → `nullOnDelete` (`...000014_create_returns_tables.php:17`).
- `customers.user_id` → `nullable, unique, nullOnDelete` (`...2026_11_09_000002_add_user_id_to_customers_table.php:11-18`).
- Controller đã chặn xóa khách có hóa đơn (`KhachHangController.php:43-52`); không có nguy cơ 500.

**Ngoài phạm vi (SĐT nhà cung cấp, không sửa):** `NhapHangController.php:188`,
`SyncTaiLieuDataCommand.php:72`, `NhapDuLieuTaiLieu.php:77-110`.

**Điểm tích lũy / mass assignment:** form chỉ nhận `ho_ten, so_dienthoai, dia_chi`
(`validated()`), POS nhanh chỉ nhận `ho_ten, so_dienthoai`; `diem_tichluy` không
đặt được từ form khách. Đăng ký gán cứng `diem_tichluy = 0`. Cột `unsignedInteger`
nên không âm. Không có lỗ mass assignment ở các điểm nhập hiện tại.

**XSS:** toàn bộ view khách dùng `{{ }}`; POS dựng nút khách bằng `textContent`
(`pos/index.blade.php:588`) → an toàn.

**Phân quyền:** mọi route khách hàng nằm trong nhóm `role:admin,thungan`
(`routes/web.php:46-91`); `thukho`/`khachhang` bị 403 hoặc chuyển hướng.

## 3. Kiểm tra dữ liệu hiện có (CHỈ ĐỌC, MySQL `doan_cnw`)

Truy vấn chỉ đọc qua script tạm boot Laravel (đã xóa sau khi chạy):

| Chỉ số | Giá trị |
|---|---|
| Tổng khách | 101 |
| SĐT rỗng/null | 0 |
| Không khớp quy tắc chặt `/^0(3\|5\|7\|8\|9)\d{8}$/` | 1 |
| Không khớp quy tắc nới `/^0\d{9,10}$/` | 1 |
| Cặp trùng SĐT thô (GROUP BY) | 0 |
| Cặp trùng sau chuẩn hóa | 0 |

- Bản ghi sai duy nhất: `so_dienthoai = "Lê Văn Định"` (nhập tên vào ô SĐT).
- 100 bản ghi còn lại đều là di động 10 số đầu `03/05/07/08/09`; không có số cố định
  `02x`, không có biến thể `+84`/khoảng trắng.
- **Kết luận:** áp dụng quy tắc chặt (khớp 100/101). Bản ghi lỗi giữ nguyên, không
  tự sửa; đề xuất lệnh chuẩn hóa dry-run ở mục "Cần người dùng xác nhận".

## 4. Quy tắc SĐT áp dụng

- Chuẩn hóa trước validate: `trim`; bỏ khoảng trắng, `.`, `-`, `(`, `)`;
  `+84`/`84` đầu → `0`.
- Hợp lệ: `/^0(3|5|7|8|9)\d{8}$/` (di động VN 10 chữ số). Từ chối chữ, ký tự đặc
  biệt, sai độ dài, mảng.
- Thông báo: "Số điện thoại không hợp lệ. Nhập 10 chữ số, bắt đầu bằng 0."
- Một nơi dùng chung: `App\Rules\SoDienThoaiHopLe` (Rule + `chuanHoa()` tĩnh).
- Lưu DB dạng đã chuẩn hóa; trùng kiểm ở tầng ứng dụng (`unique` bỏ qua bản ghi
  đang sửa) + unique index sẵn có.

## 5. Danh sách lỗi & khắc phục (trước/sau)

### 5.1. Lỗi đã sửa (kèm test chứng minh)

| # | Lỗi | Trước (file:dòng cũ) | Sau (file:dòng mới) | Test chứng minh |
|---|---|---|---|---|
| 1 | **Nhận chữ vào SĐT** khi thêm/sửa khách: chỉ `required,string,max:15,unique`, không kiểm tra định dạng. Bằng chứng: bản ghi thật SĐT = "Lê Văn Định" | `KhachHangController.php:80-91` (`validated()`), `:66-78` (`postTaoNhanh`) | Chuẩn hóa + Rule chung: `KhachHangController.php:69-102`; `app/Rules/SoDienThoaiHopLe.php:14-44` | `test_them_khach_tu_choi_sdt_khong_hop_le` (9 ca, gồm mảng), `test_pos_tao_nhanh_tu_choi_sdt_chu`, `test_them_khach_chuan_hoa_sdt_hop_le` (6 biến thể) |
| 2 | **Đăng ký storefront nhận SĐT 11 số / mọi đầu 0** (`regex:/^0\d{9,10}$/`), không chuẩn hóa; link khách vãng lai theo SĐT thô nên `+84…` không khớp bản ghi `0…` | `AuthController.php:77-135` (regex cũ + tra cứu thô) | `AuthController.php:80-87` (merge chuẩn hóa + Rule chung), `:113` (tra cứu bằng SĐT đã chuẩn hóa) | `test_dang_ky_chuan_hoa_sdt`, `test_dang_ky_tu_choi_sdt_chu`, `test_dang_ky_link_khach_vang_lai_sau_chuan_hoa` |
| 3 | **POS tìm khách không chuẩn hóa** (`+84…` không ra kết quả) và **không escape `%`/`_`** trong LIKE (`sdt=09%` khớp mọi khách đầu 09) | `KhachHangController.php:54-64` (`LIKE 'sdt%'` thô) | `KhachHangController.php:55-67` (chuẩn hóa + escape `\`, `%`, `_`) | `test_pos_tim_kiem_chuan_hoa_sdt`, `test_pos_tim_kiem_escape_ky_tu_like` |
| 4 | **Import xlsx / seeder không chuẩn hóa SĐT** (chỉ `trim` / cast chuỗi) | `NhapDuLieuTaiLieu.php:44-74`, `DuLieuMau300Seeder.php:73-77` | `NhapDuLieuTaiLieu.php:53-58` (chuẩn hóa + bỏ qua dòng sai, đếm `skipped`), `DuLieuMau300Seeder.php:76` | Unit `SoDienThoaiHopLeTest` (hàm dùng chung); chưa có test riêng cho import (cần file xlsx) |
| 5 | **UI cho gõ chữ, thiếu ràng buộc phía trình duyệt**: ô SĐT là text thường, không `tel/inputmode/pattern/maxlength`, không hiện lỗi từng trường | `khachhang/form.blade.php:16`, `auth/dangky.blade.php:48-54`, `pos/index.blade.php:79` | `form.blade.php:16-21,36-45` (tel + pattern + JS lọc ký tự + `@error`), `dangky.blade.php` (inputmode/pattern + JS), `pos/index.blade.php` (inputmode/maxlength + JS lọc) | `test_form_giu_old_va_hien_loi_tieng_viet` (giữ `old()`, lỗi tiếng Việt, `is-invalid`) |

Ghi chú chung: server là nguồn đúng — mọi điểm nhập đều `merge(chuanHoa(...))`
trước rồi mới validate bằng cùng một Rule; JS chỉ là tiện ích, tắt JS vẫn chặn
được chữ. Bỏ `max:15` vì Rule đã giới hạn đúng 10 chữ số sau chuẩn hóa.

### 5.2. Đã rà soát và ĐÚNG, không sửa (trạng thái test)

| Hạng mục | Kết luận | Test |
|---|---|---|
| Trùng SĐT khi thêm (kể cả trùng sau chuẩn hóa, vd `+84912…` vs `0912…`) | Đúng — `unique` tầng ứng dụng + unique index sẵn có | Mới: `test_them_trung_sdt_bi_tu_choi`, `test_them_trung_sdt_sau_chuan_hoa_bi_tu_choi`, `test_pos_tao_nhanh_trung_sdt` |
| Sửa khách giữ nguyên SĐT của chính nó (unique bỏ qua self) | Đúng — `Rule::unique()->ignore($khachHang)` | Mới: `test_sua_giu_nguyen_sdt_hop_le`, `test_sua_sdt_trung_khach_khac_bi_tu_choi` |
| Xóa khách đã có hóa đơn | Đúng — guard `donHangs()->exists()` chặn, không 500 (`KhachHangController.php:44-53`) | Mới: `test_xoa_khach_co_hoa_don_khong_gay_500`, `test_xoa_khach_chua_co_hoa_don_thanh_cong` |
| Phân quyền khu khách hàng (`role:admin,thungan`) | Đúng — thukho 403, khachhang chuyển hướng | Mới: `test_thu_kho_khong_duoc_them_khach`, `test_thu_ngan_duoc_them_khach`, `test_khach_hang_khong_vao_duoc_khu_khach_hang` |
| Mass assignment điểm/tài khoản | Đúng — form chỉ nhận `ho_ten, so_dienthoai, dia_chi`; đăng ký gán cứng `role=khachhang`, `diem_tichluy=0` | Đã có: `AuthFlowTest` (role admin gửi kèm bị bỏ qua); chưa có test riêng cho việc gán `diem_tichluy` qua form khách — xác minh bằng đọc code |
| XSS danh sách/tìm kiếm khách | Đúng — `{{ }}` và `textContent` | Chưa có test riêng (đã rà bằng đọc code) |
| Chống leo thang quyền khi đăng ký | Đúng — `unset($data['role'])` | Đã có: `AuthFlowTest::test_registration_creates_khachhang_and_links_customer` |

## 6. Test chứng minh

Test mới (viết TRƯỚC khi sửa, thấy đỏ trước):

- `tests/Unit/SoDienThoaiHopLeTest.php` — 16 ca: 7 biến thể chuẩn hóa, giữ nguyên
  giá trị không phải chuỗi, rule chấp nhận/từ chối + đúng thông báo tiếng Việt.
- `tests/Feature/KhachHangSoDienThoaiTest.php` — 33 ca: thêm/sửa/tìm/xóa khách,
  POS thêm nhanh + tìm kiếm, đăng ký storefront, phân quyền, `old()` + lỗi tiếng Việt.

| Lượt chạy | Lệnh | Kết quả |
|---|---|---|
| Đỏ trước khi sửa | `php artisan test tests/Unit/SoDienThoaiHopLeTest.php tests/Feature/KhachHangSoDienThoaiTest.php` | **35 failed, 14 passed** (89 assertions), 6.94s |
| Xanh sau khi sửa | như trên | **49 passed** (140 assertions), 1.96s |
| Full suite lượt 1 | `XDEBUG_MODE=off php artisan test` | 1 failed, 1 skipped, 223 passed (1207 assertions), 257.36s — fail là flake có sẵn `returns.ma_phieu` (xem dưới) |
| Chạy lại lớp flake | `php artisan test tests/Feature/PosWorkflowTest.php` | **32 passed** (195 assertions), 58.26s — xác nhận không liên quan thay đổi khách hàng |
| Full suite lượt 2 (sạch) | `XDEBUG_MODE=off php artisan test` | **224 passed, 1 skipped (1208 assertions), 214.82s**, exit 0 — 225 ca: 27 Unit + 198 Feature; skip duy nhất `KhoaDongMysqlTest` (cần MySQL bản sao) |
| Biên dịch view | `php artisan view:cache` | ✅ Thành công |

Flake lượt 1: `SQLSTATE[23000] UNIQUE constraint failed: returns.ma_phieu`
(`PosWorkflowTest.php:480`) — mã phiếu trả sinh bằng `YmdHis` + 2 chữ số ngẫu nhiên
nên trùng khi hai phiếu tạo cùng giây. Đây là lỗi có sẵn từ trước (đã ghi nhận
trong bộ nhớ dự án), nằm ngoài phạm vi khách hàng; không sửa trong đợt này.

Toàn bộ test dùng SQLite `:memory:` + `RefreshDatabase`; không mạng, không DB thật.

## 7. Rủi ro còn lại

- **1 bản ghi thật sai quy tắc** (SĐT = "Lê Văn Định"): giữ nguyên, không tự sửa.
  Khi sửa khách này bằng form, bắt buộc phải nhập SĐT hợp lệ (Rule chặn giá trị cũ)
  — đây là hành vi mong muốn, nhưng người vận hành cần biết để nhập số thật.
- **Endpoint POS thêm khách nhanh chưa có UI gọi** (`postTaoNhanh`): đã validate
  đúng nhưng là điểm nhập "chết"; giữ lại vì là API dự phòng, không xóa.
- **Danh sách khách tải toàn bộ** (`KhachHang::latest()->get()`,
  `KhachHangController.php:17`), phân trang chỉ phía client (DataTables). Với 101
  khách hiện tại không vấn đề; dữ liệu lớn sẽ chậm. Chưa sửa vì ngoài phạm vi lỗi.
- **Khách chỉ có phiếu trả (không hóa đơn) vẫn xóa được**: guard chỉ kiểm
  `donHangs`; `returns.customer_id` là `nullOnDelete` nên phiếu trả mất tham chiếu
  khách. Hành vi schema có sẵn, chưa đổi nghiệp vụ (xem mục 8).
- **Flake `returns.ma_phieu`** vẫn hiện hữu khi chạy full suite (ngoài phạm vi).
- **Chưa có test feature cho import xlsx/seeder** (cần fixture xlsx); logic chuẩn
  hóa dùng chung đã phủ bằng unit test.
- **Chưa kiểm tra trình duyệt thật** (E2E): test gọi HTTP nội bộ; JS lọc ký tự là
  tiện ích, không phải nguồn đúng.

## 8. Cần người dùng xác nhận

1. **Sửa bản ghi SĐT sai duy nhất** ("Lê Văn Định"). Chuẩn hóa không khôi phục được
   chữ số từ tên người ⇒ cần người thật cung cấp SĐT đúng. Lệnh dry-run CHỈ ĐỌC để
   liệt kê bản ghi không khớp quy tắc (không chạy trong đợt này):

   ```powershell
   php artisan tinker --execute='$rows = App\Models\KhachHang::all()->filter(fn ($k) => ! preg_match(App\Rules\SoDienThoaiHopLe::REGEX, App\Rules\SoDienThoaiHopLe::chuanHoa($k->so_dienthoai))); foreach ($rows as $k) { echo $k->id." | ".$k->ho_ten." | ".var_export($k->so_dienthoai, true).PHP_EOL; } echo "Tong: ".$rows->count().PHP_EOL;'
   ```

   (Ngoặc đơn bên ngoài để PowerShell không nội suy `$rows`/`$k`; lệnh chỉ đọc,
   không ghi.)

   Sau khi xác nhận, sửa trực tiếp trên form Sửa khách (đã enforce quy tắc) hoặc
   UPDATE có chủ đích từng bản ghi — không chạy hàng loạt tự động.
2. **Gia hạn guard xóa khách**: có nên chặn xóa khách đã có phiếu trả (dù không có
   hóa đơn) thay vì để `nullOnDelete`? Đây là đổi nghiệp vụ ⇒ chờ xác nhận.
3. **Phân trang server cho danh sách khách**: cải thiện hiệu năng khi dữ liệu lớn;
   ngoài phạm vi đợt này ⇒ chờ xác nhận.
4. **Flake mã phiếu trả `returns.ma_phieu`** (trùng `YmdHis` + 2 số ngẫu nhiên):
   cần sửa riêng (vd. thêm hậu tố ngẫu nhiên dài hơn hoặc sequence) — ngoài phạm
   vi khách hàng, chờ xác nhận mở đợt riêng.
5. **Không cần migration mới**: unique index trên `customers.so_dienthoai` đã có
   sẵn; không thêm/sửa schema DB thật trong đợt này.
