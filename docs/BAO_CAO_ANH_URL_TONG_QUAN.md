# Báo cáo: Ảnh sản phẩm theo URL + Dashboard tổng quan đúng số liệu

- Nhánh: `feat/anh-url-tong-quan` (từ `main` @ `2bf1829`). Không merge, không push.
- Ngày thực hiện: 2026-11-11.
- Phạm vi: (A) thêm/sửa ảnh sản phẩm bằng URL https bên cạnh tải tệp; (B) dashboard
  admin đọc/hiển thị đúng số lượng sản phẩm – tồn kho sau mọi nghiệp vụ và tự làm mới.

## 0. Ràng buộc an toàn đã tuân thủ

| Ràng buộc | Thực hiện |
|---|---|
| Không đọc/ghi/in `.env` | Không chạm `.env`; cấu hình test đặt bằng `Config::set()` trong test |
| Không migrate/seed/demo:reset trên DB thật | Không chạy; test dùng SQLite `:memory:` + `RefreshDatabase` |
| Truy vấn DB thật chỉ ĐỌC | Đúng 1 lệnh `tinker --execute` chứa `SELECT` (đối soát mục 3.1e), không ghi |
| Không đổi schema | Không thêm migration; URL bị giới hạn 255 ký tự theo cột `hinh_anh` VARCHAR(255) hiện có |
| Không đổi nghiệp vụ trừ/cộng kho, điểm, hóa đơn | Phần B chỉ thêm service ĐỌC + endpoint JSON + view; không sửa chỗ ghi kho. Không phát hiện lỗi ghi kho nên không có đề xuất thay đổi |
| Server không tải/gọi HTTP tới URL người dùng nhập (SSRF) | Server chỉ lưu chuỗi và nối URL hiển thị; test bật `Http::preventStrayRequests()` — mọi request HTTP ra ngoài trong test sẽ fail |

## 1. Phần A — Ảnh sản phẩm bằng URL

### 1.1. Khảo sát trước sửa (A1)

| Điểm | Vị trí (trước sửa) | Hiện trạng |
|---|---|---|
| Cột lưu ảnh | `database/migrations/2026_01_01_000002_create_products_table.php` | `hinh_anh` VARCHAR(255) nullable → URL tối đa 255 ký tự, **không đổi schema** |
| Model | `app/Models/SanPham.php` | Chưa có accessor ảnh |
| Validate | `app/Http/Controllers/SanPhamController.php:141` | Chỉ nhận tệp `image/mimes/max:2048`, chưa có trường URL |
| Lưu tệp | `SanPhamController.php:164-171` (`luuHinhAnh`) | `store('sanpham','public')` — giữ nguyên |
| Xóa tệp | `SanPhamController.php:173-180` (`xoaHinhAnh`) | **Không an toàn**: `Storage::delete()` với mọi giá trị không bắt đầu `upload/` — kể cả URL; `@unlink(public_path())` không chặn `..` |
| Xóa sản phẩm | `SanPhamController.php:68-79` (`getXoa`) | **Lỗi P1**: dùng `$request` không khai báo (dòng 76) → 500 sau khi xóa; guard thiếu 3 bảng FK `stocktake_items`, `purchase_invoice_items`, `return_items` (đều `restrictOnDelete`) |
| Form | `resources/views/sanpham/form.blade.php:38-41` | Chỉ có input tệp |
| View admin | `resources/views/sanpham/danhsach.blade.php:40` | Ternary `upload/` → `asset()`, còn lại `asset('storage/'.value)` — URL https bị nối sai thành `asset('storage/https://...')` |
| Storefront card | `resources/views/storefront/partials/_product-card.blade.php:5,28,34` | `asset($hinh_anh)` — **lỗi có sẵn**: tệp tải lên `sanpham/xxx.jpg` bị nối thành `asset('sanpham/xxx.jpg')` (sai, phải qua `storage/`); URL cũng sai |
| Storefront chi tiết | `resources/views/storefront/product.blade.php:7,26` | Như trên |
| Giỏ hàng storefront | `resources/views/storefront/cart.blade.php:30` + `StorefrontCartController.php:45` | Session lưu `hinh_anh` thô, view `asset()` — sai với cả tệp tải lên lẫn URL |
| POS | `resources/views/pos/index.blade.php:248-259` | JS tự nối `upload/`/`storage/` từ JSON `hinh_anh` — không biết URL |
| Quick view | `_quick-view-modal.blade.php:13` + `storefront.js:168-171` | Lấy `data-image` từ card (kế thừa lỗi card) |
| Seeder/import | `ScrapeProductImagesCommand` | Ghi `storage/products/<slug>.svg` (tệp thật ở `storage/app/public/products/`) — dạng legacy, accessor phải hỗ trợ |
| CSP | Toàn dự án | **Không có** header CSP → theo yêu cầu, không thêm mới |
| Quyền | `routes/web.php:93-150` | CRUD sản phẩm trong nhóm `role:admin,thukho` — giữ nguyên |

### 1.2. Thiết kế và thay đổi (A2)

**Rule dùng chung `app/Rules/AnhSanPhamUrl.php` (mới)**
- `null`/rỗng sau trim → bỏ qua (nullable); không phải chuỗi → từ chối.
- Giới hạn **255 ký tự** đúng cột `hinh_anh` (ghi nhận trong báo cáo, không đổi schema).
- Chỉ nhận `https://` (lý do: trang chạy HTTPS nếu có sẽ chặn mixed-content; http
  thuần cũng bị từ chối theo yêu cầu). Không yêu cầu đuôi `.jpg/.png` — CDN thường
  không có đuôi.
- Từ chối: khoảng trắng bất kỳ, `javascript:`, `data:`, `file:`, `ftp:`,
  `chrome-extension:`, protocol-relative `//`, userinfo `user:pass@`, host rỗng,
  đường dẫn tương đối. Kiểm bằng `filter_var(FILTER_VALIDATE_URL)` + `parse_url`.
- Trim hai đầu trước khi kiểm và trước khi lưu.
- Thông báo lỗi tiếng Việt cố định (test khẳng định đúng từng chữ).

**Accessor `SanPham::getAnhUrlAttribute()` — `app/Models/SanPham.php:47` (mới)**
- Rỗng → `asset('img/no-image.svg')`; `https://` → giữ nguyên; `upload/` hoặc
  `storage/` (legacy) → `asset($value)`; còn lại (tệp tải lên `sanpham/...`) →
  `Storage::disk('public')->url($value)`.
- **Mọi** view dùng chung accessor này; không còn chỗ nào tự nối `asset()` từ `hinh_anh`.

**Service xóa tệp an toàn `app/Services/TepAnhSanPham.php` (mới)**
- Chỉ xóa khi đường dẫn thuộc `sanpham/`, `products/`, `storage/products/` (disk
  public) hoặc `upload/` (legacy trong `public/upload`, kiểm `realpath` nằm trong
  thư mục rồi mới `@unlink`).
- Không bao giờ xóa URL (chứa `://`), chặn `..` và backslash; thư mục lạ → bỏ qua.
- Thay thế hoàn toàn `xoaHinhAnh` cũ (đã xóa method cũ).

**Controller `app/Http/Controllers/SanPhamController.php`**
- `rules()` (dòng 171-172): thêm `hinh_anh_url` (nullable + Rule chung) và `xoa_anh`
  (nullable boolean). Giữ nguyên validate tệp cũ.
- `postThem` (34-62): gửi cả tệp + URL → **tệp thắng**, thông báo success kèm
  "Đã ưu tiên tệp tải lên thay vì URL."; chỉ URL → lưu chuỗi đã trim; không gửi gì
  → `hinh_anh` null.
- `postSua` (65-87): thứ tự ưu tiên tệp > `xoa_anh` > URL; mỗi lần thay ảnh đều gọi
  xóa an toàn ảnh cũ (URL cũ → không xóa gì trên disk); không gửi trường ảnh → giữ nguyên.
- `getXoa` (89-106): **vá P1** — thêm tham số `Request $request` (trước đây thiếu →
  500 sau khi xóa); mở rộng guard sang `stocktake_items`, `purchase_invoice_items`,
  `return_items` để không đâm khóa ngoại `restrictOnDelete`; xóa ảnh qua service an toàn.
- `getTimKiem` (132): JSON trả thêm `anh_url` (accessor) cho POS; giữ các khóa cũ.

**Form `resources/views/sanpham/form.blade.php`**
- Input tệp (39-44) + input URL `type=url maxlength=255` (45-53) + `@error` riêng
  từng trường + dòng chú thích ưu tiên tệp.
- Ảnh xem trước (56-60) + checkbox "Xóa ảnh hiện tại" chỉ ở chế độ sửa (61-67).
- JS xem trước (98-124): chọn tệp → `URL.createObjectURL`; gõ URL https → xem trước;
  tích xóa → về ảnh mặc định. Chỉ gán `.src`, không innerHTML.

**View hiển thị (tất cả dùng accessor, thêm `loading="lazy" referrerpolicy="no-referrer"`)**
- `sanpham/danhsach.blade.php:39-42`; `_product-card.blade.php:5,28,34-38` (kèm
  `data-image` cho quick view); `product.blade.php:7,26-28`; `cart.blade.php:30-33`;
  `_quick-view-modal.blade.php:13`; POS `pos/index.blade.php:248-256` dùng `product.anh_url`.
- `StorefrontCartController.php:45`: session giỏ lưu `anh_url` đã phân giải.
- Escape: toàn bộ `src="{{ ... }}"` (Blade `{{ }}`); test dữ liệu bẩn
  `https://x.com/a.png" onload="alert(1)` chứng minh không thoát attribute.

**Fallback ảnh lỗi dùng chung**
- `resources/js/app.js:44-52` và `resources/js/storefront.js:190-198`: listener
  `error` capture-phase, thay ảnh hỏng bằng `document.body.dataset.imgFallback`
  đúng một lần mỗi thẻ (chống lặp vô hạn).
- `layouts/master.blade.php:11` và `storefront/layouts/app.blade.php:11`:
  `data-img-fallback="{{ asset('img/no-image.svg') }}"`.
- Đã `npm run build` lại (manifest mới) — bắt buộc để test render `@vite` không 500.
- **CSP**: dự án không có sẵn CSP → không thêm (đúng yêu cầu). Nếu sau này thêm CSP,
  cần `img-src https:` cho ảnh ngoài.

### 1.3. Test phần A (A3 — viết trước, đỏ trước)

- `tests/Unit/AnhSanPhamTest.php` (33 ca): 6 URL hợp lệ (CDN không đuôi, query
  string, cổng, đúng 255 ký tự, trim), 13 URL lỗi (kể cả mảng), null, 5 ca accessor,
  8 ca xóa tệp an toàn (kể cả traversal/backslash/legacy `upload/` với tệp thật tạm).
- `tests/Feature/SanPhamAnhUrlTest.php` (26 ca): thêm bằng URL, 9 ca từ chối, tải
  tệp với `Storage::fake`, cả hai → tệp thắng + thông báo, đổi tệp→URL xóa tệp cũ,
  URL→tệp, URL→URL không xóa gì, sửa không gửi trường ảnh giữ nguyên, nút xóa ảnh,
  render escape trên 6 trang (admin, home, chi tiết, danh mục, tìm kiếm, giỏ),
  dữ liệu bẩn vẫn escape, POS JSON `anh_url`, thu ngân 403 / thủ kho được thêm /
  khách hàng redirect, xóa sản phẩm ảnh URL không lỗi + không xóa tệp, chặn xóa khi
  có hóa đơn / khi nằm trong kiểm kê (không 500).
- Toàn bộ test bật `Http::preventStrayRequests()` — chứng minh server không gọi
  HTTP tới URL người dùng.
- **Điều chỉnh driver (kỳ vọng sai, không phải sửa code để né test)**: danh sách
  rule trong test unit bỏ `'string'` vì với giá trị mảng, Laravel báo lỗi
  `validation.string` chung chung TRƯỚC thông điệp tùy chỉnh; bản thân Rule đã từ
  chối non-string bằng đúng thông điệp yêu cầu. Controller cũng dùng
  `['nullable', new AnhSanPhamUrl()]` nhất quán.

## 2. Phần B — Dashboard tổng quan

### 2.1. Kiểm chứng giả thuyết bằng chứng cứ (B1)

| Giả thuyết | Kết luận | Bằng chứng |
|---|---|---|
| (a) Cache cũ | **SAI** | Chỉ có `Cache::remember` trong `app/Services/CanhBaoMatKhauMacDinh.php:25` (cảnh báo mật khẩu, không liên quan số liệu). Dashboard đọc thẳng Eloquent mỗi request |
| (b) Sai định nghĩa | **ĐÚNG — nguyên nhân chính** | `ThongKeController.php:17` (cũ): `SanPham::count()` đếm cả sản phẩm tạm dừng dưới nhãn chung "Sản phẩm"; danh sách tồn thấp không lọc `trang_thai`; **không có bất kỳ chỉ số số lượng tồn nào** (tồn tốt/hỏng/giá trị) |
| (c) Thời điểm trừ kho đơn QR | **ĐÚNG như thiết kế, gây hiểu nhầm** | Đơn `cho_thanh_toan` trừ kho NGAY khi tạo QR; hủy quá hạn hoàn kho; webhook hoàn tất KHÔNG trừ lần nữa. Test B3 chứng minh từng bước (10→7 khi tạo QR, vẫn 7 sau webhook, về 10 sau hủy). Không đổi nghiệp vụ — chỉ ghi rõ thời điểm trên dashboard |
| (d) Không tự làm mới | **ĐÚNG** | View cũ không có JS nào gọi lại số liệu; phải F5 |
| (e) Lệch `soluong` vs `stock_movements` | **SAI trên dữ liệu hiện tại** | Đối soát CHỈ ĐỌC trên DB thật `doan_cnw` (SQL mục 12 README, chạy qua `tinker --execute`): **300 sản phẩm, 0 lệch**. Không cần đề xuất sửa dữ liệu |

Test tái hiện viết trước khi sửa: `tests/Feature/TongQuanSoLieuTest.php` — đỏ vì
thiếu endpoint/định nghĩa (xem số liệu mục 4).

### 2.2. Thay đổi (B2 — chỉ ĐỌC/HIỂN THỊ, không đụng chỗ ghi kho)

**Service chỉ-đọc duy nhất `app/Services/TongQuanKhoService.php` (mới)**
- Một truy vấn `selectRaw` COUNT/SUM trực tiếp, **không cache** (không có điểm ghi
  nào cần invalidate vì không dùng cache).
- Định nghĩa chỉ số (ghi rõ trên dashboard và README):

| Khóa JSON | Nhãn hiển thị | Định nghĩa |
|---|---|---|
| `so_mat_hang_dang_kinh_doanh` | Mặt hàng đang kinh doanh | COUNT sản phẩm `trang_thai = true` |
| `so_con_hang` | Mặt hàng còn hàng | COUNT `trang_thai = true AND soluong > 0` |
| `so_het_hang` | Mặt hàng hết hàng | COUNT `trang_thai = true AND soluong = 0` |
| `tong_ton_tot` | Tổng tồn tốt | SUM(`soluong`) **mọi** sản phẩm kể cả tạm dừng (hàng vẫn trong kho) |
| `tong_ton_hong` | Tổng hàng hỏng | SUM(`soluong_hong`) mọi sản phẩm |
| `gia_tri_ton` | Giá trị tồn theo giá nhập | SUM(`soluong × gia_nhap`) mọi sản phẩm, **không gồm hàng hỏng** |
| `so_hoa_don` | Hóa đơn | COUNT mọi hóa đơn (giữ định nghĩa cũ) |
| `cap_nhat_luc` | Cập nhật lúc HH:MM:SS | `now()->timezone('Asia/Ho_Chi_Minh')` — giờ Việt Nam, không đổi timezone hệ thống |

**Endpoint JSON admin-only**
- `GET /tong-quan/so-lieu` tên `tongquan.solieu` (`routes/web.php:153-155`) trong
  nhóm `role:admin`, thêm `throttle:30,1`. Middleware thực tế:
  `web, auth, staff.only, role:admin, throttle:30,1`.
- `ThongKeController::getSoLieuTongQuan` (`app/Http/Controllers/ThongKeController.php:33-36`).
- Thu ngân/thủ kho → 403; khách hàng → redirect storefront; guest → redirect đăng nhập.

**Dashboard**
- `ThongKeController::getTongQuan` (15-31) dùng chung service; danh sách tồn thấp
  lọc thêm `trang_thai = true` (sản phẩm ẩn không hiện cảnh báo).
- `resources/views/tongquan.blade.php`: 7 thẻ chỉ số có nhãn rõ ràng (30-84), dòng
  "Cập nhật lúc … (giờ Việt Nam)" (32), chú thích cách tính (86-92), vùng cảnh báo
  mạng ẩn sẵn (34-38).
- JS polling (125-170): `fetch` mỗi **30 giây**, chỉ chạy khi tab visible
  (`visibilitychange` → tạm dừng khi ẩn, chạy lại + làm mới ngay khi hiện); cập nhật
  `textContent` từng ô `[data-chi-so]` bằng `toLocaleString('vi-VN')` — không reload;
  lỗi mạng/HTTP → **giữ số cũ**, hiện cảnh báo nhỏ, không xóa số, không 500.

### 2.3. Test phần B (B3)

`tests/Feature/TongQuanSoLieuTest.php` (18 ca, SQLite :memory:):
bán tiền mặt giảm tồn ngay; tạo QR trừ kho ngay (10→7); webhook không trừ lần nữa;
hủy quá hạn bằng lệnh `donhang:huy-qua-han` hoàn kho (7→10); trả hàng `con_tot`
tăng tồn tốt; trả hàng `loi_hong` tăng `soluong_hong`, tồn tốt không đổi; nhập kho
trực tiếp (+5, giá trị tồn theo giá nhập mới 1.200); xác nhận hóa đơn nhập (+4);
kiểm kê điều chỉnh (+2 sau duyệt); sản phẩm ẩn không được tính vào số mặt hàng
nhưng tồn vẫn tính + không hiện trên trang; xóa sản phẩm không còn được tính; đếm hết hàng đúng;
endpoint chỉ admin (403/302 đúng vai trò); guest redirect đăng nhập; số trên trang
khớp endpoint; không còn số cũ sau thao tác; giá trị tồn = Σ(soluong×gia_nhap)
không gồm hỏng; route đúng GET/HEAD + `throttle:30,1` + `role:admin`.

**Điều chỉnh driver**: assertion guest tách thành test riêng
(`test_guest_bi_chuyen_ve_dang_nhap`) vì `actingAs()` dính xuyên các request trong
cùng một test method — request "guest" cũ thực ra vẫn là khách hàng nên bị
`staff.only` redirect về storefront. Guest dùng GET thường (JSON guest nhận 401
theo chuẩn Laravel).

## 3. Đối soát chỉ đọc DB thật và route

### 3.1. Đối soát kho (chỉ SELECT, không sửa dữ liệu)

SQL đúng mục 12 README (README:935-944), chạy qua `php artisan tinker --execute`
trên `doan_cnw`:

```sql
SELECT p.id, p.soluong,
       COALESCE(SUM(CASE WHEN sm.loai IN ('nhap','ban','trahang','dieuchinh')
                         THEN sm.soluong ELSE 0 END), 0) AS tong_bien_dong
FROM products p
LEFT JOIN stock_movements sm ON sm.product_id = p.id
GROUP BY p.id, p.soluong
HAVING p.soluong <> tong_bien_dong;
```

Kết quả: **300 sản phẩm, 0 dòng lệch** → không có drift, không cần dry-run sửa dữ
liệu, không có mục "Cần người dùng xác nhận" nào về kho.

### 3.2. Route

`php artisan route:list --json`: **104 route** (trước đợt này 103). Đúng **1 route
mới**: `GET|HEAD tong-quan/so-lieu => tongquan.solieu`
(`web,auth,staff.only,role:admin,throttle:30,1`). Không đổi/xóa route nào khác.

## 4. Kết quả kiểm thử (số thật)

| Lượt | Lệnh | Kết quả |
|---|---|---|
| Đỏ (3 tệp test mới, trước triển khai) | `XDEBUG_MODE=off php artisan test tests/Unit/AnhSanPhamTest.php tests/Feature/SanPhamAnhUrlTest.php tests/Feature/TongQuanSoLieuTest.php` | **70 failed, 6 passed (86 assertions), 3.20s** — 6 pass là ca quyền/giữ-nguyên vốn đã đúng |
| Xanh phần A | `... test tests/Unit/AnhSanPhamTest.php tests/Feature/SanPhamAnhUrlTest.php` | **59 passed (156 assertions), 2.23s** |
| Xanh phần B (sau sửa driver guest) | `... test tests/Feature/TongQuanSoLieuTest.php` | **18 passed (86 assertions), 0.99s** |
| Full suite | `XDEBUG_MODE=off php artisan test` | **252 passed, 1 skipped (1310 assertions), 219.44s**, exit 0 — 253 ca: 44 Unit + 209 Feature; skip duy nhất `KhoaDongMysqlTest` (cần MySQL) |

Ghi chú trung thực: lượt chạy phần B đầu tiên có 1 fail do kỳ vọng sai của driver
(`actingAs` dính xuyên request) — đã sửa driver, không sửa code để né; chạy lại
xanh 18/18. Flake known `returns.ma_phieu` (trùng mã phiếu trong cùng giây) **không
xuất hiện** trong lượt full suite này; nếu tái diễn ở lượt sau, báo cả lượt đỏ và
lượt chạy lại xác nhận.

## 5. Rủi ro còn lại

1. **URL ảnh phụ thuộc host bên thứ ba**: host chết/chặn hotlink → ảnh hỏng; đã có
   fallback ảnh mặc định phía trình duyệt. Server không kiểm tra nội dung URL (đúng
   thiết kế chống SSRF) — có thể lưu URL trỏ tới nội dung không phải ảnh.
2. **Giới hạn 255 ký tự**: URL CDN dài kèm query string có thể vượt 255 → bị từ chối
   (đúng cột hiện có). Muốn dài hơn phải đổi schema — ngoài phạm vi, cần xác nhận.
3. **Ảnh legacy `storage/products/*.svg`** (lệnh scrape) cần `php artisan
   storage:link` để hiển thị; accessor đã hỗ trợ nhưng không kiểm chứng từng tệp.
4. **Định nghĩa đếm mới**: "Mặt hàng đang kinh doanh" chỉ đếm sản phẩm bật; tổng tồn
   vẫn gồm sản phẩm tạm dừng. Đây là thay đổi HIỂN THỊ có chủ đích, đã ghi chú thích
   ngay trên dashboard; người dùng quen nhãn "Sản phẩm" cũ có thể ngạc nhiên.
5. **Thời điểm trừ kho đơn QR không đổi** (trừ khi tạo, hoàn khi hủy, webhook không
   trừ lại). Dashboard đã ghi chú; nếu muốn đổi thời điểm trừ kho là quyết định
   nghiệp vụ — chờ xác nhận, không tự đổi.
6. **Polling chạy phía trình duyệt**: nhiều tab cùng mở thì mỗi tab tự gọi (throttle
   30/phút mỗi IP bảo vệ server); số liệu là snapshot tại thời điểm gọi, không phải
   realtime tuyệt đối.
7. **Chưa kiểm thử trình duyệt thật**: các test là HTTP-level (render HTML + JSON);
   hành vi JS polling/fallback/xem trước chưa chạy tay trên trình duyệt trong đợt này.
8. Flake known: `PosWorkflowTest` trả hàng thỉnh thoảng trùng `ma_phieu`
   (YmdHis + 2 số ngẫu nhiên) — không sửa trong đợt này (ngoài phạm vi).

## 6. Cần người dùng xác nhận

- Không có đề xuất sửa dữ liệu kho (đối soát 0 lệch).
- Không có migration/schema nào được đề xuất thêm.
- Nếu muốn URL dài hơn 255 ký tự hoặc đổi thời điểm trừ kho đơn QR: cần quyết định
  riêng, đợt này không tự làm.

## 7. Commit (theo nhóm, trên nhánh — không merge/push)

| Commit | Nhóm | Nội dung |
|---|---|---|
| `8273b88` | test: | Test phần A (33 unit + 26 feature), viết trước — đỏ |
| `e2cda88` | fix: | Vá `getXoa`: thiếu tham số `Request` (500 sau khi xóa) + guard đủ 3 bảng FK `restrictOnDelete` |
| `c14a7fe` | feat: | Phần A: Rule URL, accessor `anh_url`, `TepAnhSanPham`, form 2 chế độ, mọi view + JS fallback |
| `9f70502` | test: | Test phần B (18 feature), viết trước — đỏ |
| `3569fec` | feat: | Phần B: `TongQuanKhoService`, endpoint `tongquan.solieu`, dashboard nhãn rõ + polling 30s |
| (commit chứa báo cáo này) | docs: | README mục 7/8/9/17/18 + `docs/BAO_CAO_ANH_URL_TONG_QUAN.md` |

Ba tệp untracked có sẵn từ trước (`docs/BAO_CAO_KIEM_TRA_LUONG_DA.md`,
`docs/BAO_CAO_XU_LY_OPERA_GX_VA_CMD_WINDOWS.md`,
`scripts/windows/chay-schedule-hidden.vbs`) không thuộc phạm vi đợt này — không commit.