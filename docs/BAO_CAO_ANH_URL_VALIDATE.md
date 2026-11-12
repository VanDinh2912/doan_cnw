# Báo cáo: validate URL ảnh sản phẩm — hết 500, lỗi tiếng Việt ngay trên form

- **Ngày**: 2026-11-12
- **Nhánh**: `fix/anh-url-validate` (từ `fix/ma-phieu-tra-hang` @ `bed34a6`)
- **Phạm vi**: thêm/sửa sản phẩm bằng URL ảnh; không đổi schema, không merge/push.
- **Commit**:
  - `defb39a` — test: quét 35 URL lạ qua thêm/sửa sản phẩm, không bao giờ 500
  - `81b6354` — test: gửi cả tệp lẫn URL hỏng thì tệp phải thắng, không bị chặn validate
  - `30fbad9` — fix: giới hạn URL ảnh 150 ký tự theo cột thật, báo lỗi tiếng Việt thay vì 500
  - `45e7485` — test: exception thật phải render trang 500 tiếng Việt khi tắt debug
  - `9bf69d0` — feat: kiểm tra ảnh phía trình duyệt, chặn nút Lưu khi URL hỏng
  - (docs) — báo cáo này + README mục 9/17/18

## 1. Hiện tượng và tái hiện

Người dùng dán URL ảnh dài (link quảng cáo Google aclk 255 ký tự) vào form sửa sản
phẩm → hệ thống văng **500** thay vì báo lỗi validate.

Bằng chứng từ `storage/logs/laravel.log` (chỉ đọc):

- Dòng 120689 — `[2026-11-12 14:53:14] local.ERROR`: `SQLSTATE[22001]: Data too long
  for column 'hinh_anh'` — `update products ... where id = 68`, userId=1.
- Dòng 120827 — `[2026-11-12 14:55:16] local.ERROR`: cùng lỗi, cùng sản phẩm.
- URL trong log đo được **đúng 255 ký tự** (link `https://www.google.com/aclk?...`).

Tái hiện có kiểm soát (test đỏ, mục 4): URL 151/200/255 ký tự lọt rule cũ, tới MySQL
strict mode thì `SQLSTATE[22001] 1406 Data too long` → 500. SQLite `:memory:` không
cưỡng chế độ dài VARCHAR nên **không tái hiện được lỗi này một mình** — phải chạy
thêm trên bản sao MySQL.

## 2. Nguyên nhân gốc (file:dòng)

**Phân loại: (a) khoảng trống rule + (b) ghi DB.** Không phải (c) hiển thị/xóa hay
(d) view/JS.

| Mắt xích | Vị trí | Sự thật |
|---|---|---|
| Rule cho phép tối đa 255 | `app/Rules/AnhSanPhamUrl.php:11` (trước sửa) | `MAX_DO_DAI = 255`, comment ghi "cột VARCHAR(255)" |
| Khai báo migration | `database/migrations/2026_01_01_000002_create_products_table.php:22` | `string('hinh_anh')` = VARCHAR(255) — **chưa áp lên DB thật** |
| Cột thật trên `doan_cnw` và `doan_cnw_copy` | `information_schema.COLUMNS` (chỉ đọc) | `hinh_anh` **VARCHAR(150)** `utf8mb4_unicode_ci`; `sql_mode` có `STRICT_TRANS_TABLES` |
| Hậu quả | `SanPhamController::postThem/postSua` → `update/insert` | URL 151–255 ký tự lọt validate → MySQL strict từ chối ghi → 1406 → 500 |

DB thật vẫn ở 26 migration (MyISAM, xem README mục 17) nên migration khai 255 chưa
có hiệu lực; rule lại viết theo migration thay vì cột thật.

Các mắt xích khác đã kiểm tra và **an toàn, không sửa**:

- (c) Accessor `SanPham::getAnhUrlAttribute` (`app/Models/SanPham.php:47-62`): chỉ
  `str_starts_with`, không `parse_url`/`realpath` trên giá trị bẩn → không ném lỗi.
- (c) `App\Services\TepAnhSanPham::xoaNeuLaTepCucBo`: chặn `://`, `..`, backslash,
  chỉ xóa trong thư mục ảnh, `realpath` có kiểm tra `false` → URL không bao giờ bị
  coi là tệp.
- (d) View: mọi chỗ hiển thị dùng `{{ }}` (escape) + `anh_url`; nháy kép/`<>` trong
  URL được escape đúng (có test). JS cũ chỉ gán `.src`, không `innerHTML`.

## 3. Sửa tối thiểu tại gốc (commit `30fbad9`)

| Tệp | Thay đổi |
|---|---|
| `app/Rules/AnhSanPhamUrl.php` | `MAX_DO_DAI = 150` (:12) theo cột thật, comment giải thích vì sao lệch migration. Kiểm tra độ dài **trước tiên** (:34-37) với thông báo riêng `THONG_BAO_QUA_DAI` (:16); các lỗi còn lại dùng `THONG_BAO` (:14). Không try/catch nuốt lỗi. |
| `app/Http/Controllers/SanPhamController.php` | `rules(?SanPham, bool $coTepTaiLen)` (:158): có tệp tải lên thì `hinh_anh_url` chỉ `['nullable']` (:172) — URL hỏng không chặn việc lưu tệp. Hai call site truyền `$request->hasFile('hinh_anh')` (:36, :67). Đọc URL bằng guard `is_string` (:37, :68) thay `(string)` để không ném Array-to-string khi rule bị bỏ qua; giữ thông báo "ưu tiên tệp" khi URL hợp lệ + tệp. |
| `resources/views/sanpham/form.blade.php` | **Bỏ `maxlength="255"`** ở ô URL: maxlength cắt cụt URL dán vào, biến URL dài thành URL ngắn hợp lệ nhưng hỏng, giấu mất lỗi "quá dài" của server. Không giới hạn phía client, server là nguồn sự thật. Help text đổi thành "tối đa 150 ký tự". `old()` + `@error('hinh_anh_url')` giữ nguyên dưới ô nhập. |
| `tests/Unit/AnhSanPhamTest.php` | Biên 255→150 (đúng 150 hợp lệ, 151 bị từ chối), assertion thông báo tách 2 nhánh theo `MAX_DO_DAI`. |

Thông báo tiếng Việt mới (nguyên văn):

- `THONG_BAO`: "Liên kết ảnh không hợp lệ. Hãy dán liên kết trực tiếp tới tệp ảnh
  bắt đầu bằng https://, không chứa khoảng trắng hay thông tin đăng nhập."
- `THONG_BAO_QUA_DAI`: "Liên kết ảnh quá dài (tối đa 150 ký tự). Hãy dùng liên kết
  trực tiếp tới tệp ảnh, không dùng liên kết tìm kiếm hay quảng cáo."

Quy tắc nghiệp vụ giữ nguyên: chỉ `https://`, không khoảng trắng/userinfo, trim hai
đầu, không yêu cầu đuôi ảnh, server **không tải/gọi HTTP** tới URL người dùng nhập
(chống SSRF — `Http::preventStrayRequests()` bật trong test).

## 4. Test đỏ → xanh

### Đỏ (trước sửa)

| Lượt | Kết quả | Ghi chú |
|---|---|---|
| Sweep 35 chuỗi × (thêm + sửa) + 11 ca hiển thị, SQLite | **6 failed, 75 passed (360 assertions), 3.02s** | 6 ca đỏ = 151/200/255 ký tự × thêm/sửa: lọt rule, SQLite lưu được nhưng spec yêu cầu từ chối theo cột thật |
| Sweep trên bản sao MySQL strict (`AnhUrlMysqlCopyTest`) | **4 failed, 33 passed (220 assertions), 5.67s** | Lỗi thật `SQLSTATE[22001] 1406 Data too long` → 500, đúng như log production |
| Tệp + URL hỏng (thêm/sửa) | **2 failed (4 assertions), 0.73s** | URL hỏng chặn cả khi có tệp — sai spec "tệp thắng" |

Test MySQL copy **không dùng `RefreshDatabase`** (migrate lại sẽ tạo cột VARCHAR(255)
và giấu bug); tự skip khi không phải MySQL `*_copy`; tự dọn bản ghi tạo ra (kể cả
nhật ký). Đã xác nhận sau chạy: `SWEEP-%` = 0 bản ghi trên copy.

### Xanh (sau sửa)

| Lượt | Kết quả |
|---|---|
| `XDEBUG_MODE=off php artisan test --filter='AnhUrlSweepTest\|AnhSanPhamTest\|SanPhamAnhUrlTest'` (SQLite) | **142 passed (542 assertions), 4.52s** |
| Thêm `ThongBaoLoiTest` (gồm test 500 luồng thật mới) | **148 passed (560 assertions), 15.58s** |
| `DB_CONNECTION=mysql DB_DATABASE=doan_cnw_copy ... --filter=AnhUrlMysqlCopyTest` | **37 passed (243 assertions), 2.33s** — URL 150 ký tự lưu được, 151 bị từ chối bằng validate, chuỗi multibyte 150 ký tự/286 byte legacy vẫn hiển thị |
| Full suite `XDEBUG_MODE=off php artisan test` | **338 passed, 38 skipped (1714 assertions), 250.41s**, exit 0 — 376 ca: 44 Unit + 332 Feature; 38 skip = `KhoaDongMysqlTest` (1) + `AnhUrlMysqlCopyTest` (37) |

Hợp đồng sweep: mọi ca đều **302** (không bao giờ 500) — hoặc thành công + dữ liệu
đúng, hoặc lỗi validate `hinh_anh_url` tiếng Việt + dữ liệu không đổi. Ca hiển thị:
11 giá trị bẩn/legacy (aclk 255, nháy, `<>`, Unicode, null byte, `upload/`,
`storage/`...) ép thẳng vào DB, mọi trang admin/storefront/giỏ/POS vẫn 200.

## 5. Kiểm tra ảnh phía trình duyệt (commit `9bf69d0`)

`resources/views/sanpham/form.blade.php` (JS inline, không qua Vite):

- Debounce **500ms** khi gõ + kiểm tra ngay khi **blur** (:113, :196-207).
- `new Image()` với `referrerPolicy = 'no-referrer'` (:184), timeout **8s** (:114, :187).
- 3 trạng thái qua vùng `aria-live="polite"` (:54): "Đang kiểm tra liên kết ảnh…" →
  "Liên kết ảnh tải được." (preview hiện ảnh) hoặc thông báo hỏng nguyên văn:
  "Liên kết này không phải ảnh hoặc không tải được. Hãy chọn 'Sao chép địa chỉ hình
  ảnh' rồi dán lại."
- Nút Lưu (:93) bị chặn khi URL hỏng/đang kiểm tra, **trừ khi** đã chọn tệp hoặc
  tick "Xóa ảnh hiện tại"; xóa trắng URL cũng mở lại nút.
- Chỉ `.src`/`.textContent`/`.className`/`.disabled` — không `innerHTML`.
- URL prefill khi mở form sửa **không tự động kiểm tra** (không khóa oan sản phẩm
  legacy); chỉ kiểm tra khi người dùng chạm vào ô URL.
- JS tắt vẫn đúng: server validate là nguồn sự thật; nút không bị chặn, lỗi tiếng
  Việt hiện dưới ô nhập kèm `old()`.

### Đã kiểm chứng bằng trình duyệt thật (in-app browser, `artisan serve` trỏ bản sao MySQL copy)

| Ca | Kết quả quan sát |
|---|---|
| Mở form thêm | Nút Lưu enabled, vùng trạng thái rỗng, ô URL không còn maxlength |
| Gõ URL hỏng (`https://127.0.0.1:8099/khong-co-anh.png`) | Ngay lập tức "Đang kiểm tra liên kết ảnh…" + Lưu disabled; sau debounce → đúng thông báo hỏng, `text-danger`, preview về ảnh mặc định |
| URL hợp lệ (twemoji PNG trên jsDelivr) | "Liên kết ảnh tải được." `text-success`, Lưu enabled, preview là ảnh thật (`naturalWidth > 0`) |
| URL hỏng + chọn tệp | Lưu enabled lại, preview là blob của tệp |
| Submit URL 200 ký tự (gọi `form.submit()` bypass nút disabled) | Server 302 về form, lỗi "Liên kết ảnh quá dài (tối đa 150 ký tự)…" hiện dưới ô URL, `old()` giữ nguyên URL 200 ký tự và tên sản phẩm — không 500 |
| Golden path: URL hợp lệ → Lưu | Redirect `/sanpham`, "Đã thêm sản phẩm.", DB copy lưu đúng URL (đã dọn sau kiểm chứng) |
| Form sửa: prefill | Không tự kiểm tra, Lưu enabled |
| Form sửa: blur với URL hỏng | Kiểm tra ngay không chờ debounce → Lưu disabled |
| Form sửa: tick "Xóa ảnh" / bỏ tick / xóa trắng URL | Lưu enabled / disabled / enabled đúng như thiết kế |
| Trang 500 | Test mới `test_exception_that_su_render_trang_500_tieng_viet_khi_debug_tat`: ném exception thật qua route, `app.debug=false` → 500 + "Máy chủ gặp sự cố", không lộ SQLSTATE/Exception |

Ghi chú: URL `https://www.google.com/images/branding/googlelogo_1x.png` không tải
được trong browser kiểm chứng (đã đổi/404) — dùng làm ca "URL hỏng" thực tế; ảnh
twemoji trên jsDelivr tải bình thường. Dữ liệu phiên kiểm chứng (user tạm, sản phẩm
tạm, nhật ký) đã xóa khỏi copy, xác nhận còn 0.

### JS không có test tự động — checklist thủ công

Dự án chưa có hạ tầng test JS (không Jest/Vitest); phần JS chỉ được kiểm chứng thủ
công như bảng trên. Checklist khi nghiệm thu:

- [ ] Gõ URL hỏng → sau ~0,5s hiện thông báo + nút Lưu mờ.
- [ ] Gõ xong click ra ngoài ngay (blur) → kiểm tra chạy liền, không chờ debounce.
- [ ] URL ảnh thật → preview hiện, nút Lưu mở.
- [ ] URL treo quá 8s → timeout, thông báo hỏng (chưa kiểm chứng thực tế ca này —
      cần một URL phản hồi chậm; code path đã rà).
- [ ] Chọn tệp khi URL hỏng → nút Lưu mở.
- [ ] Tick "Xóa ảnh hiện tại" → nút Lưu mở.
- [ ] Tắt JS (DevTools) → submit vẫn được, server trả lỗi tiếng Việt dưới ô URL.
- [ ] Trình đọc màn hình đọc được vùng trạng thái (aria-live).

## 6. Giới hạn

- **JS chỉ là UX, không phải bảo mật**: bypass được (curl, `form.submit()`); validate
  server mới là chốt chặn. Kiểm tra `Image()` không phân biệt "không phải ảnh" với
  403/404/mạng/chặn hotlink — một số CDN chặn hotlink sẽ bị báo hỏng ở trình duyệt
  dù URL đúng; server vẫn chấp nhận và lưu (không gọi HTTP).
- **Giới hạn 150 ký tự theo cột thật**, không theo migration (255). URL CDN dài kèm
  query có thể vượt 150 → bị từ chối có kiểm soát. Muốn nới: áp migration lên DB
  thật rồi sửa `MAX_DO_DAI` + help text + test biên (xem mục 7).
- **Dữ liệu legacy >150 ký tự vẫn hiển thị bình thường** (accessor/view an toàn, có
  test), nhưng khi sửa sản phẩm đó phải xóa trắng ô URL (giữ ảnh cũ), chọn tệp hoặc
  tick xóa ảnh mới lưu được — vì URL cũ sẽ bị validate từ chối. Chưa dọn hàng loạt
  dữ liệu legacy (chỉ đọc, không sửa DB thật).
- **Timeout 8s chưa kiểm chứng thực tế** (không có URL chậm sẵn); các trạng thái
  khác đã kiểm chứng bằng trình duyệt.
- Không thêm kiểm tra Content-Type phía server trong đợt này (giữ quyết định chống
  SSRF: server không gọi HTTP tới URL người dùng).

## 7. Cần người dùng xác nhận

1. **Có muốn kiểm tra Content-Type phía server không?** Hiện server chỉ lưu chuỗi.
   Nếu muốn chắc chắn URL trỏ tới ảnh thật, server phải GET URL → phát sinh rủi ro
   SSRF. Nếu làm, bắt buộc kèm: chỉ https, chặn IP nội bộ/private/link-local sau
   phân giải DNS (chống DNS rebinding), cấm redirect vào nội bộ, timeout + giới hạn
   dung lượng tải, chạy ở tầng mạng cách ly. Khuyến nghị: **không làm** ở đợt này;
   kiểm tra phía trình duyệt + validate hình thức là đủ cho nghiệp vụ hiện tại.
2. **Schema**: giữ VARCHAR(150) (hiện trạng, rule đã khớp) hay áp migration nâng
   `hinh_anh` lên 255 trên DB thật (cần chạy migrate — ngoài phạm vi đợt này)? Nếu
   nâng 255, sửa `MAX_DO_DAI` + help text + test biên là đủ.
3. **Dữ liệu legacy** (URL >150 ký tự đã tồn tại, ví dụ sản phẩm id=68 trong log):
   giữ nguyên (vẫn hiển thị) hay dọn hàng loạt về ảnh mặc định? Đợt này không sửa.
4. **Bỏ `maxlength` ô URL** (để không cắt cụt URL dán vào và giấu lỗi "quá dài") —
   xác nhận đây là UX mong muốn; nếu muốn giới hạn gõ trực tiếp thì đặt lại
   `maxlength="150"` nhưng khi đó URL dài bị cắt thầm lặng thay vì báo lỗi.

## 8. Tuân thủ ràng buộc an toàn

- Không đọc/ghi/in `.env`; không migrate/seed/demo:reset trên DB thật.
- Test SQLite `:memory:` + `RefreshDatabase`; MySQL chỉ qua bản sao `doan_cnw_copy`
  bằng biến môi trường tiến trình (không sửa `.env`), tự dọn dữ liệu test.
- Server không tải/gọi HTTP tới URL người dùng nhập (`Http::preventStrayRequests()`).
- Không đổi schema, không thêm migration; không merge, không push.
- Phiên kiểm chứng trình duyệt chạy trên copy; user/sản phẩm tạm đã xóa (xác nhận 0).