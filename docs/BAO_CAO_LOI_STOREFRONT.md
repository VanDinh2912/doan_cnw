# BÁO CÁO LỖI STOREFRONT — CHẨN ĐOÁN VÀ SỬA

**Ngày:** 2026-11-11
**Nhánh:** `feat/san-sang-van-hanh` (HEAD kế tiếp `b0c3636`; merge-base với chính nhánh
này = HEAD, tức xuất phát từ `feat/san-sang-van-hanh`)
**Phạm vi:** chi tiết sản phẩm (`/san-pham/{sanPham}`) và danh mục (`/danh-muc/{loaiSanPham}`)
**Ràng buộc đã giữ:** không đọc/ghi `.env`; không chạy migrate/seed/demo:reset trên DB thật;
không tắt/comment route storefront; không thêm tính năng, không thêm migration/slug.

---

## 1. Kết luận nhanh

| Route | Trạng thái thật | Nguyên nhân |
|-------|-----------------|-------------|
| `GET /san-pham/{id}` | **Lỗi code → HTTP 500** | Query `$related` không eager-load `loaiSanPham`, trong khi view thẻ sản phẩm đọc quan hệ này và app chặn lazy-load ngoài production |
| `GET /danh-muc/{id}` | **Đúng, HTTP 200** | Binding theo `id`; view/model đều có |
| `GET /danh-muc/sac` | 404 | **Sai đầu vào**: route binding theo `id`, "sac" không phải id. Bảng `categories` **không có cột `slug`** |
| `GET /san-pham/999999`, `/danh-muc/999999` | 404 (trang lỗi tiếng Việt) | Đúng thiết kế |

> Báo cáo trước chẩn đoán sai ở hai điểm: (1) "trả về trang mặc định Laravel kèm logo"
> thực chất là **trang lỗi 500 ở chế độ debug** (có logo Laravel); (2) "danh mục 404 vì
> thiếu cột slug" — thực chất do gọi `/danh-muc/sac` (chuỗi) trong khi binding theo id;
> `/danh-muc/1` vẫn trả 200. View `storefront.product` và `storefront.category` **đều tồn tại**.

---

## 2. Bằng chứng tái hiện (không suy đoán)

### 2.1 Quy trình

1. Server cổng 8081 là `php artisan serve` (PID cha 16980, con 30492) chạy từ đúng
   `D:\Study\xampp\www\DA`; đã dừng bằng `taskkill /F /T`.
2. `php artisan route:clear` → "Route cache cleared successfully"; **không tồn tại**
   `bootstrap/cache/routes-*.php` trước lẫn sau (không có route cache cũ).
3. Khởi động lại `php artisan serve --host=127.0.0.1 --port=8081` từ đúng thư mục dự án.
4. Lấy id thật (chỉ đọc, `tinker --execute`): DB `doan_cnw`, 12 loại, 300 sản phẩm;
   `category id=1` = "Củ sạc"; `product id=1` = "Củ sạc điện thoại (...)".

### 2.2 `curl -i` sau khi restart sạch

| URL | Trước sửa | Sau sửa | Ghi chú |
|-----|-----------|---------|---------|
| `/` | 200 | 200 | — |
| `/san-pham/1` | **500** (`<html lang="en">`) | **200** (`<html lang="vi">`, tiêu đề "… — PhoneShop") | Lỗi thật |
| `/san-pham/999999` | 404 | 404 | Trang lỗi tiếng Việt |
| `/danh-muc/1` | 200 | 200 | Đúng (id thật) |
| `/danh-muc/sac` | 404 | 404 | Sai đầu vào (không phải id; không có slug) |
| `/danh-muc/999999` | 404 | 404 | Trang lỗi tiếng Việt |

### 2.3 Log `storage/logs/laravel.log`

Trích nguyên nhân 500 (lượt gọi `/san-pham/1`):

```text
Illuminate\Database\LazyLoadingViolationException:
Attempted to lazy load [loaiSanPham] on model [App\Models\SanPham] but lazy loading is disabled.
  at vendor/.../Eloquent/Concerns/HasAttributes.php:610
  ...
  storage/framework/views/fc0186b903b92d533cd8c90fe00e4156.php(57): Model->__isset('loaiSanPham')
```

Compiled view `fc0186…php` có comment `PATH ... resources/views/storefront/partials/_product-card.blade.php`,
dòng 57 tương ứng nguồn `_product-card.blade.php:29`.

---

## 3. Nguyên nhân kèm file:dòng

### (a) Môi trường — **loại trừ**
- Không có `bootstrap/cache/routes-*.php`; `route:clear` chạy sạch; server khởi động lại
  từ đúng thư mục dự án mà 500 **vẫn tái hiện**. Không phải cache route hay server cũ.

### (b) Dữ liệu/id — **chỉ giải thích `/danh-muc/sac`**
- Model không override khóa route ([`LoaiSanPham.php:9-21`](../app/Models/LoaiSanPham.php#L9-L21),
  [`SanPham.php:10-45`](../app/Models/SanPham.php#L10-L45)) → binding mặc định theo `id`.
- Route khai báo `{loaiSanPham}`/`{sanPham}` ([`routes/web.php:8-9`](../routes/web.php#L8-L9)).
- Bảng chưa có cột `slug`: [`create_categories_table.php:11-16`](../database/migrations/2026_01_01_000001_create_categories_table.php#L11-L16),
  [`create_products_table.php:11-24`](../database/migrations/2026_01_01_000002_create_products_table.php#L11-L24).
  Vì vậy `/danh-muc/1` đúng, `/danh-muc/sac` 404 là kỳ vọng, không phải lỗi code.

### (c) Lỗi code — **nguyên nhân 500 thật**
1. `StorefrontController::product()` tải `$sanPham->load('loaiSanPham')` ([`:69-73`](../app/Http/Controllers/StorefrontController.php#L69-L73))
   nhưng query **sản phẩm liên quan** không eager-load quan hệ ([`:75-82`](../app/Http/Controllers/StorefrontController.php#L75-L82)).
2. View chi tiết render thẻ sản phẩm cho từng sản phẩm liên quan
   ([`product.blade.php:88-99`](../resources/views/storefront/product.blade.php#L88-L99)).
3. Thẻ sản phẩm đọc `$sanPham->loaiSanPham` ([`_product-card.blade.php:29`](../resources/views/storefront/partials/_product-card.blade.php#L29)).
4. Ngoài production, app chặn lazy-load ([`AppServiceProvider.php:28-30`](../app/Providers/AppServiceProvider.php#L28-L30))
   → ném `LazyLoadingViolationException` → 500.
5. Chi tiết framework: Laravel chỉ bật cờ chặn lazy-load khi một truy vấn hydrate **nhiều hơn 1**
   model ([`vendor/.../Eloquent/Builder.php:472-474`](../vendor/laravel/framework/src/Illuminate/Database/Eloquent/Builder.php#L472-L474)).
   Trên DB thật, danh sách liên quan trả tới 4 dòng nên mới kích hoạt; đây là lý do lỗi chỉ
   xuất hiện khi danh mục có từ 2 sản phẩm còn hàng trở lên.

Các trang khác không lỗi vì đã eager-load: trang chủ (`:23`, `:34`), danh mục (`:47`),
tìm kiếm (`:100`).

---

## 4. Sửa tối thiểu

Thêm eager-load đúng một dòng trong query sản phẩm liên quan:
[`StorefrontController.php:80`](../app/Http/Controllers/StorefrontController.php#L80) `->with('loaiSanPham')`.
Không đổi route, view, model, schema.

---

## 5. Test (viết trước khi sửa)

File [`tests/Feature/StorefrontProductTest.php`](../tests/Feature/StorefrontProductTest.php)
(dữ liệu tạo trên SQLite `:memory:`; dự án chưa có factory cho hai model này nên tạo bằng
`LoaiSanPham::create()`/`SanPham::create()` như `StorefrontHomeTest`):

| Test | Dòng | Nội dung |
|------|------|----------|
| `test_trang_chi_tiet_san_pham_tra_200_va_hien_thi_ten` | [`:14`](../tests/Feature/StorefrontProductTest.php#L14) | Tạo 1 sản phẩm chính + **2 sản phẩm liên quan** (đủ để kích hoạt chặn lazy-load), GET `storefront.product` → 200 + tên |
| `test_trang_danh_muc_tra_200_va_hien_thi_ten` | [`:29`](../tests/Feature/StorefrontProductTest.php#L29) | GET `storefront.category` → 200 + tên loại |
| `test_san_pham_khong_ton_tai_tra_404_tieng_viet` | [`:40`](../tests/Feature/StorefrontProductTest.php#L40) | `/san-pham/999999` → 404 + "Không tìm thấy trang" |
| `test_danh_muc_khong_ton_tai_tra_404_tieng_viet` | [`:47`](../tests/Feature/StorefrontProductTest.php#L47) | `/danh-muc/999999` → 404 + "Không tìm thấy trang" |
| `test_link_danh_muc_tren_trang_chu_tro_dung_route` | [`:54`](../tests/Feature/StorefrontProductTest.php#L54) | Trang chủ chứa `href="{route('storefront.category', $loai)}"` đúng route |

Kết quả:

| Lượt | Kết quả |
|------|---------|
| Trước sửa (`--filter=StorefrontProductTest`) | **1 failed, 4 passed** — đỏ đúng lỗi `Attempted to lazy load [loaiSanPham] ... (_product-card.blade.php)` |
| Sau sửa | **5 passed (10 assertions)** |
| Toàn bộ suite (`XDEBUG_MODE=off php artisan test --compact`) | **171 passed, 1 skipped (824 assertions), 309.48s**, exit 0 |

---

## 6. Trước / sau

| Hạng mục | Trước | Sau |
|----------|-------|-----|
| `/san-pham/1` | 500, `LazyLoadingViolationException` | 200, view + tiêu đề tiếng Việt |
| Query sản phẩm liên quan | Không eager-load `loaiSanPham` | `->with('loaiSanPham')` (1 dòng) |
| `/danh-muc/1` | 200 | 200 (không đổi) |
| `/danh-muc/sac` | 404 | 404 (không đổi; đúng vì binding theo id) |
| Test storefront | `StorefrontHomeTest` (6) | thêm `StorefrontProductTest` (5) |

---

## 7. Cần người dùng xác nhận

- **URL theo slug (tùy chọn):** muốn `/danh-muc/{slug}` thay vì id thì phải thêm cột `slug`
  (migration mới) + khai báo `{loaiSanPham:slug}` và cập nhật link. Đợt này **không** thêm
  migration thay đổi schema DB thật; cần chủ dự án quyết định trước.
- Không đọc/ghi `.env`, không chạy migrate/seed/demo:reset, không merge/push trong đợt này.
