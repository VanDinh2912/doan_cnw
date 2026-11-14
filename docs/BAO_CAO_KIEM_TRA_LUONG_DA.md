# Báo cáo: Kiểm tra luồng nghiệp vụ DA

- **Ngày kiểm tra:** 2026-11-11
- **Nhánh:** `feat/sepay-doi-soat` — commit `b0a347d`
- **Phạm vi:** toàn bộ luồng nghiệp vụ đang có trong mã nguồn: xác thực/phân quyền,
  storefront, POS, thanh toán tiền mặt, QR chuyển khoản, webhook SePay, đối soát,
  hóa đơn/in/xuất báo cáo, trả hàng và điểm, ba đường nhập kho, kiểm kê, thống kê,
  nhật ký, lệnh nền.
- **Tính chất:** chỉ đọc (read-only). Không sửa mã nguồn, không sửa dữ liệu,
  không commit/push.
- **Môi trường:** PHP 8.4.15, Laravel 12, test trên SQLite in-memory; cấu hình
  runtime MySQL `doan_cnw`.
- **Quy mô:** 100 route (không tính route vendor), 20 controller, 28 tệp test.

## 1. Kết luận nhanh

Các luồng lõi (bán hàng, thanh toán, webhook, trả hàng, nhập kho) được thiết kế
tốt: có `DB::transaction`, khóa theo thứ tự sản phẩm → khách hàng → hóa đơn,
chống xử lý trùng theo mã giao dịch, và có test bao phủ các tình huống đua.

Kết quả kiểm thử toàn bộ:

```
Tests:    1 skipped, 205 passed (1249 assertions)
Duration: 205.38s
```

Lượt chạy này không có test đỏ. Test bị bỏ qua là `KhoaDongMysqlTest` (cần MySQL
bản sao để kiểm tra khóa dòng thật).

Tuy nhiên phát hiện **02 lỗi mức P1** nằm đúng ở luồng xóa:

1. `SanPhamController::getXoa` dùng `$request` nhưng không khai báo tham số →
   mọi lần xóa sản phẩm thành công đều kết thúc bằng HTTP 500.
2. `NhapHangController::xoaNhaCungCap` mắc cùng lỗi → xóa nhà cung cấp luôn 500;
   ngoài ra guard không kiểm tra hóa đơn nhập nên nhà cung cấp đã có hóa đơn nhập
   còn bị lỗi khóa ngoại trước khi xóa.

Hai lỗi này không bị test phát hiện vì `RouteKhoiTest` chỉ quét route GET; hai
route xóa dùng phương thức DELETE.

Ngoài ra có 08 vấn đề P2 (kiểm soát nội bộ, trùng mã phiếu, sai lệch báo cáo,
ghi đè giỏ tạm) và một số P3 được liệt kê ở mục 5.

Không phát hiện rò rỉ token/bí mật trong mã hoặc log; webhook xác thực bằng
`hash_equals`; client SePay chỉ đọc.

## 2. Phương pháp và bằng chứng

| Kiểm tra | Cách thực hiện | Kết quả |
|---|---|---|
| Bản đồ route | `php artisan route:list --except-vendor` | 100 route |
| Biên dịch view | `php artisan view:cache` | Thành công, không lỗi Blade |
| Hợp lệ composer | `composer validate --no-check-publish` | Hợp lệ |
| Test toàn bộ | `XDEBUG_MODE=off php artisan test` | 205 pass, 1 skip, 1249 assertions, 205.38s |
| Quét tĩnh biến `$request` | Script PHP tokenize (chỉ đọc) | Chỉ 2 phương thức ở P1 |
| Truy vết luồng | Đọc controller → service → model → migration → view | Xem mục 3–4 |

Giới hạn của phương pháp: không chạy trình duyệt E2E, không đo tải, không kiểm
thử khóa dòng trên MySQL thật (test tương ứng bị skip), không kiểm tra sao lưu
thật trên MySQL.

## 3. Bản đồ luồng nghiệp vụ

| # | Luồng | Điểm vào chính | Quyền | Xử lý lõi | Bảng chính |
|---|---|---|---|---|---|
| 1 | Đăng nhập / đăng ký / đăng xuất | `GET|POST /dangnhap`, `/dang-ky`, `/dangxuat` | công khai | `AuthController` | `users`, `customers` |
| 2 | Storefront | `/`, `/danh-muc/*`, `/san-pham/*`, `/tim-kiem` | công khai | `StorefrontController` | `products`, `categories` |
| 3 | Giỏ storefront | `/gio-hang/*` | công khai | `StorefrontCartController` | session, `products` |
| 4 | Giỏ POS + giữ đơn | `/pos`, `/giohang/*`, `/hold/*` | admin, thungan | `GioHangController`, `HoldController` | session, `held_carts` |
| 5 | Thanh toán tiền mặt | `POST /pos/thanhtoan` | admin, thungan | `DonHangController::postThanhToan` | `invoices`, `invoice_details`, `stock_movements`, `customers` |
| 6 | Tạo QR chờ chuyển khoản | `POST /pos/tao-qr` | admin, thungan | `ThanhToanChuyenKhoanService` | `invoices`, sản phẩm, khách hàng |
| 7 | Polling / xác nhận / hủy chờ | `/donhang/trangthai/*`, `/donhang/xacnhan/*`, `/donhang/huycho/*` | admin, thungan | `DonHangController`, service | `invoices`, `stock_movements` |
| 8 | Webhook SePay | `POST /api/webhook/thanhtoan` | API key | `WebhookController`, `SepayWebhook` | `invoices`, `payment_webhooks` |
| 9 | Đối soát | `/doisoat/*`, `sepay:doi-soat` | admin | `DoiSoatController`, command | `payment_webhooks` |
| 10 | Hóa đơn, in, xuất báo cáo | `/donhang/*`, `/baocao/*` | admin (+ thungan/thukho tùy route) | `DonHangController`, `BaoCaoController`, Exports | `invoices`, `stock_movements` |
| 11 | Trả hàng và điểm | `/trahang/*` | admin, thungan (duyệt: admin) | `TraHangController` | `returns`, `return_items`, `customers` |
| 12 | Nhập kho | `/nhaphang/*`, `/hoadonnhap/*`, `/kho/nhapkho` | admin, thukho | `NhapHangController`, `HoaDonNhapController`, `KhoController` | `purchase_orders`, `purchase_invoices`, `stock_movements` |
| 13 | Kiểm kê | `/kiemke/*` | admin, thukho (duyệt: admin) | `KiemKeController` | `stocktakes`, `stocktake_items` |
| 14 | Thống kê, nhật ký, lệnh nền | `/thongke/*`, `/nhatky`, scheduler | admin / hệ thống | `ThongKeController`, `NhatKyController`, commands | nhiều bảng |

## 4. Chi tiết từng luồng

### 4.1. Xác thực và phân quyền

- Đăng nhập giới hạn `throttle:10,1` tại `routes/web.php:23-25`; đăng ký **không**
  có throttle (`routes/web.php:27-29`).
- Đăng ký không tin `role` do client gửi: `unset($data['role'])` và gán cứng
  `khachhang` tại `app/Http/Controllers/AuthController.php:97-106`. Có liên kết
  khách vãng lai theo số điện thoại và từ chối số đã gắn tài khoản.
- Đăng nhập/đăng ký đều `session()->regenerate()`; đăng xuất hủy phiên và tạo
  token mới (`AuthController.php:62,138,145-151`).
- Phân quyền bằng alias `role` (`app/Http/Middleware/CheckRole.php`) trả 403 tiếng
  Việt; khách hàng vào khu vực nhân viên bị chuyển về storefront bởi `staff.only`.
- Nhận xét: luồng đúng. Thiếu luồng quên/đặt lại mật khẩu (không có route) — phù
  hợp phạm vi đồ án nhưng cần ghi nhận khi vận hành thật.

### 4.2. Storefront

- Trang chủ, danh mục, chi tiết, tìm kiếm chỉ hiển thị sản phẩm `trang_thai = true`
  (`StorefrontController.php:12-45,69-105`).
- Giỏ storefront lưu trong session, chỉ có thêm/sửa/xóa/xóa hết
  (`StorefrontCartController.php:27,56,88,97`).
- **Không có đặt hàng/thanh toán online** — đã ghi trong README; đây là giới hạn
  phạm vi, không phải lỗi.
- Giỏ storefront tách biệt hoàn toàn với giỏ POS (`gio_hang`), đúng thiết kế.

### 4.3. Giỏ POS và giữ đơn

- Thêm sản phẩm kiểm tra tồn và trạng thái; giỏ lưu session theo `user_id`.
- Giữ đơn lưu ảnh chụp giỏ vào `held_carts`; khôi phục/ xóa có kiểm tra chủ sở hữu.
- Lệnh `hold:clean` xóa giỏ tạm quá 24 giờ hằng ngày (`routes/console.php:7`).
- Vấn đề P2: khôi phục giỏ tạm **ghi đè toàn bộ** giỏ đang có
  (`HoldController.php:59`), không hỏi hay gộp.

### 4.4. Thanh toán tiền mặt

`DonHangController::postThanhToan` (`app/Http/Controllers/DonHangController.php:194-320`):

- Validate giảm giá 0–100%, tiền khách đưa, phương thức, khách hàng, điểm.
- Sắp xếp giỏ theo id sản phẩm, `lockForUpdate` từng sản phẩm, kiểm tra tồn và
  trạng thái — chống bán âm kho khi hai quầy bán cùng lúc.
- Tính giảm giá, giảm điểm, tiền thừa; từ chối tiền mặt không đủ.
- Tạo hóa đơn, chi tiết, trừ tồn, ghi `stock_movements` loại `ban`, cập nhật điểm
  khách; xóa giỏ sau khi commit.
- Chạy trong transaction với 2 lần thử lại khi deadlock.

Điểm cần lưu ý: khi chọn `phuong_thuc = chuyenkhoan`, hệ thống đánh dấu đã thanh
toán ngay mà không đối chiếu ngân hàng (`DonHangController.php:252-275`) — xem P2-01.

### 4.5. QR chờ chuyển khoản

- `postTaoQr` (`DonHangController.php:55-94`) gọi
  `ThanhToanChuyenKhoanService::taoHoaDonChoThanhToan`
  (`app/Services/ThanhToanChuyenKhoanService.php:22-133`):
  khóa sản phẩm, **trừ tồn ngay** khi tạo đơn `cho_thanh_toan`, trừ điểm đã dùng,
  sinh URL VietQR với nội dung là mã hóa đơn.
- Polling `getTrangThai` hủy lười khi quá hạn (`DonHangController.php:97-112`);
  lệnh `donhang:huy-qua-han` chạy mỗi phút làm cùng việc.
- Hủy chờ hoàn tồn bằng bút toán dương `dieuchinh` và hoàn điểm đã dùng
  (`ThanhToanChuyenKhoanService.php:228-302`).
- Xác nhận thủ công `postXacNhanThanhToan` (`DonHangController.php:115-131`) là
  phương án dự phòng khi mất mạng; không có xác minh ngân hàng, không ghi nhật ký
  hoạt động — xem P2-01.

### 4.6. Webhook SePay

`WebhookController::postThanhToan` (`app/Http/Controllers/WebhookController.php:27-173`):

1. Xác thực API key bằng `hash_equals` (`app/Services/Payment/SepayWebhook.php`).
2. Validate payload, chuẩn hóa, chỉ nhận giao dịch tiền vào.
3. Chống trùng theo id giao dịch (`WebhookController.php:56`) — kiểm tra cả cột cũ
   `ma_giaodich` và cột mới `ma_giao_dich`.
4. Trích mã hóa đơn bằng regex `HD([0-9]{8})([0-9]{4})`; xử lý các nhánh: không
   thấy mã, hóa đơn không tồn tại, đơn đã hủy, đơn đã thanh toán, chuyển thiếu,
   chuyển đủ/dư, thanh toán trễ hạn.
5. Hoàn tất qua `hoanTatThanhToan` (khóa đơn và khóa sản phẩm/khách hàng theo thứ
   tự, chỉ xử lý khi còn `cho_thanh_toan`, cộng điểm đúng một lần).
6. Ghi `payment_webhooks`; bất thường đưa vào hàng chờ đối soát; Telegram gửi sau
   commit.

Điểm trừ: hóa đơn đã thanh toán thì webhook trả thành công ngay và **không ghi
nhận giao dịch** (`WebhookController.php:105-107`) — xem P2-02.

### 4.7. Đối soát

- Thủ công: `/doisoat` cho admin, lọc trạng thái, đánh dấu đã xử lý kèm ghi chú
  3–1000 ký tự, chống xử lý lặp, ghi nhật ký (`DoiSoatController.php:14-80`).
- Tự động: `sepay:doi-soat --gio=6` chạy mỗi 10 phút, chỉ khi có
  `SEPAY_API_TOKEN`; chế độ **chỉ báo cáo**, `--ap-dung` bị từ chối có chủ đích;
  tự phân trang, chống trùng, giới hạn tin Telegram
  (`app/Console/Commands/SepayDoiSoat.php`).
- Client API chỉ đọc, che token khi báo lỗi, có thử lại 401/429/5xx và giãn nhịp
  gọi (`app/Services/SepayApiClient.php`).

### 4.8. Hóa đơn, in và xuất báo cáo

- Danh sách lọc theo ngày, phân trang 20 (`DonHangController.php:152-176`); chỉ in
  hóa đơn `da_thanh_toan`, khổ K58/K80 (`DonHangController.php:178-193`).
- Xuất XLSX/PDF hóa đơn và xuất nhập tồn (`app/Exports/*`).
- Sai lệch số liệu: xem P2-04 và P2-05.

### 4.9. Trả hàng và điểm

`TraHangController::postTaoPhieu` (`app/Http/Controllers/TraHangController.php:40-109`):

- Chỉ hóa đơn đã thanh toán, trong hạn `pos.han_tra_ngay` (mặc định 7 ngày).
- Khóa hóa đơn và chi tiết hóa đơn; trừ số lượng đã trả ở các phiếu
  `hoan_thanh`/`cho_duyet` để chống trả vượt.
- Hoàn tiền theo tỷ lệ giá trị thanh toán thực; thu hồi/hoàn điểm theo công thức
  lũy kế, có chặn trần.
- Phiếu ≤ 500.000 đ tự duyệt; trên ngưỡng chờ admin duyệt.
- Khi áp dụng: hàng tốt cộng `soluong` + bút toán `trahang`; hàng hỏng cộng
  `soluong_hong` + `trahang_hong` (`TraHangController.php:167-215`).
- Vấn đề P2: mã phiếu sinh bằng `YmdHis` + 2 số ngẫu nhiên nên có thể trùng khi
  hai quầy trả hàng trong cùng giây.

### 4.10. Nhập kho (ba đường)

1. **Nhập nhanh** `/kho/nhapkho`: khóa sản phẩm, cộng tồn, cập nhật giá nhập, ghi
   bút toán `nhap` (`KhoController.php:29-60`).
2. **Đơn nhập (purchase order)**: trạng thái `cho_duyet → cho_giao → dang_giao →
   da_nhan` / `da_huy`; nhận hàng chạy trong transaction, cộng tồn đúng một lần
   (`NhapHangController.php:82-190`).
3. **Hóa đơn nhập**: nhập tay hoặc bóc tách PDF, kiểm tra trùng số hóa đơn theo
   nhà cung cấp + ký hiệu, xác nhận một lần với khóa hóa đơn và sản phẩm, cảnh
   báo lệch giá >10%, tự tạo sản phẩm mới khi dòng hàng chưa có
   (`app/Http/Controllers/HoaDonNhapController.php`,
   `app/Services/HoaDonNhapService.php:18-62`).

### 4.11. Kiểm kê

- Tạo phiếu chụp tồn hệ thống của toàn bộ sản phẩm đang bán (`KiemKeController.php:27-48`).
- Gửi số đếm thực, hệ thống tính `chenh_lech` so với tồn **tại thời điểm gửi**
  (`KiemKeController.php:56-67`).
- Admin duyệt: khóa phiếu, khóa sản phẩm, cộng/trừ đúng `chenh_lech`, ghi bút
  toán `dieuchinh` (`KiemKeController.php:69-90`).
- Vấn đề P2: chênh lệch được chốt ở bước gửi và áp dụng mù ở bước duyệt; hai
  phiếu kiểm kê chồng lấn có thể tính cùng một sai lệch hai lần.

### 4.12. Thống kê

`ThongKeController::getDoanhThu` (`app/Http/Controllers/ThongKeController.php:33-70`):

- Doanh thu = `tong_thanh_toan` trừ hoàn tiền của phiếu trả `hoan_thanh` — đúng.
- Lợi nhuận = (giá bán − giá vốn) × số lượng, trừ phần trả hàng — **không trừ
  giảm giá hóa đơn và điểm đã dùng**, nên lợi nhuận bị báo cao.
- Tải toàn bộ hóa đơn đã thanh toán vào bộ nhớ, không giới hạn kỳ.
- Top 5 sản phẩm đã trừ số lượng trả — đúng.

### 4.13. Nhật ký hoạt động

- Ghi qua `NhatKyService::ghi`; danh sách lọc theo hành động/đối tượng, phân trang 30.
- Các controller có ghi nhật ký: Auth, Đối soát, Giữ đơn, Nhập nhanh, Kiểm kê,
  Nhập hàng, Sản phẩm, Trả hàng.
- Không ghi nhật ký: thanh toán POS, tạo QR, xác nhận/hủy chờ, hóa đơn nhập,
  khách hàng, loại sản phẩm. Với POS, hóa đơn và bút toán kho vẫn là nguồn đối
  chiếu, nhưng thao tác xác nhận thủ công nên có vết riêng.

### 4.14. Lệnh nền và kiểm tra hệ thống

`routes/console.php`:

| Lệnh | Tần suất | Ghi chú |
|---|---|---|
| `hold:clean` | hằng ngày | Xóa giỏ tạm quá 24 giờ |
| `donhang:huy-qua-han` | mỗi phút | Hủy đơn QR quá hạn, hoàn tồn/điểm |
| `sao-luu:csdl` | 02:00 hằng ngày | Sao lưu CSDL, giữ N bản mới |
| `sepay:doi-soat --gio=6` | mỗi 10 phút | Chỉ khi có token; chế độ báo cáo |
| heartbeat scheduler | mỗi phút | Cho `he-thong:kiem-tra` biết scheduler còn sống |

`he-thong:kiem-tra` kiểm tra 9 nhóm: scheduler, đơn quá hạn, cấu hình (không in
bí mật), độ mới đối soát, môi trường/debug/url, quyền ghi, log, engine InnoDB,
migration.

## 5. Phát hiện

### 5.1. P1 — phải sửa trước khi vận hành

#### P1-01. Xóa sản phẩm luôn trả HTTP 500 sau khi xóa

**Vị trí:** `app/Http/Controllers/SanPhamController.php:68-78`

```php
public function getXoa(SanPham $sanPham): RedirectResponse
{
    if ($sanPham->chiTietDonHangs()->exists() || $sanPham->bienDongKhos()->exists()) {
        return back()->with('error', 'Không thể xóa sản phẩm đã phát sinh giao dịch.');
    }

    $this->xoaHinhAnh($sanPham->hinh_anh);
    $sanPham->delete();
    NhatKyService::ghi($request, 'xoa', 'san_pham', $sanPham->id, 'Xóa sản phẩm '.$sanPham->sku);
    ...
}
```

`$request` không tồn tại trong phạm vi phương thức (không có tham số). Dòng gọi
`NhatKyService::ghi` ném `TypeError` vì tham số đầu yêu cầu `Illuminate\Http\Request`.

**Tác động:**

- Sản phẩm đã bị xóa khỏi CSDL và ảnh đã bị xóa khỏi storage, sau đó request
  trả 500 — người dùng thấy lỗi nhưng thao tác đã xảy ra.
- Guard chỉ kiểm tra `invoice_details` và `stock_movements`. Các bảng còn khóa
  ngoại `restrictOnDelete` tới `products` gồm `stocktake_items`
  (`database/migrations/2026_01_01_000016_create_stocktakes_tables.php:22`),
  `purchase_invoice_items`
  (`database/migrations/2026_01_01_000018_create_purchase_invoices_tables.php:37`),
  `return_items`
  (`database/migrations/2026_01_01_000014_create_returns_tables.php:30`).
  Sản phẩm đã nằm trong phiếu kiểm kê/hóa đơn nhập/phiếu trả sẽ đi qua guard,
  **xóa ảnh trước**, rồi `delete()` ném lỗi khóa ngoại → 500 và mất ảnh.

**Cách sửa đề xuất:**

```php
public function getXoa(Request $request, SanPham $sanPham): RedirectResponse
{
    $dangDuocDung = $sanPham->chiTietDonHangs()->exists()
        || $sanPham->bienDongKhos()->exists()
        || DB::table('stocktake_items')->where('product_id', $sanPham->id)->exists()
        || DB::table('purchase_invoice_items')->where('product_id', $sanPham->id)->exists()
        || DB::table('return_items')->where('product_id', $sanPham->id)->exists();

    if ($dangDuocDung) {
        return back()->with('error', 'Không thể xóa sản phẩm đã phát sinh giao dịch.');
    }
    // chỉ xóa ảnh sau khi chắc chắn được phép xóa
}
```

Nên bổ sung test DELETE cho route này và route nhà cung cấp.

#### P1-02. Xóa nhà cung cấp luôn trả HTTP 500

**Vị trí:** `app/Http/Controllers/NhapHangController.php:55-63`

Cùng lỗi `$request` không khai báo. Ngoài ra guard chỉ kiểm tra đơn nhập
(`donNhaps`) trong khi bảng `purchase_invoices.supplier_id` cũng là
`restrictOnDelete` (`database/migrations/2026_01_01_000018_create_purchase_invoices_tables.php:18`).

**Tác động:** nhà cung cấp chưa có dữ liệu → xóa xong 500; nhà cung cấp đã có
hóa đơn nhập → lỗi khóa ngoại 500; chỉ trường hợp đã có đơn nhập mới nhận thông
báo lỗi tử tế.

**Cách sửa:** thêm `Request $request` và kiểm tra thêm:

```php
if ($nhaCungCap->donNhaps()->exists()
    || DB::table('purchase_invoices')->where('supplier_id', $nhaCungCap->id)->exists()) {
    return back()->with('error', 'Không thể xóa nhà cung cấp đã có đơn nhập hoặc hóa đơn nhập.');
}
```

### 5.2. P2 — nên sửa sớm

#### P2-01. Thanh toán chuyển khoản thủ công không có xác minh ngân hàng

- POS cho chọn `chuyenkhoan` và lưu hóa đơn `da_thanh_toan` ngay
  (`DonHangController.php:252-275`).
- `postXacNhanThanhToan` cho thu ngân xác nhận tay đơn QR, ghi chú
  "Thu ngân xác nhận thủ công", không ghi nhật ký hoạt động
  (`DonHangController.php:115-131`).

**Ảnh hưởng:** kiểm soát nội bộ yếu; báo cáo có thể ghi nhận tiền chưa về. Đây là
phương án dự phòng có chủ đích, nhưng nên bắt buộc ghi nhật ký và hiển thị cảnh
báo trên hóa đơn.

#### P2-02. Webhook cho hóa đơn đã thanh toán bị bỏ qua không ghi vết

`WebhookController.php:105-107` trả `success` ngay khi `trang_thai = da_thanh_toan`,
không tạo `payment_webhooks`, không cảnh báo Telegram, không vào hàng chờ đối soát.
Nếu khách chuyển khoản lần hai (hoặc webhook đến muộn sau xác nhận tay), giao dịch
đó không có vết trong hệ thống. Lệnh `sepay:doi-soat` có thể phát hiện sau đó
(phân loại `hoadon_da_thanh_toan`, tạo bản ghi `can_doi_soat`) nhưng chỉ khi token
API đã bật và giao dịch còn trong cửa sổ 6 giờ.

**Đề xuất:** gọi `ghiCanDoiSoat($txn, $donHang->id, $maHoaDon, 'hoadon_da_thanh_toan')`
trước khi trả về.

#### P2-03. Trùng mã phiếu do sinh bằng `YmdHis` + 2 số ngẫu nhiên

Cùng một mẫu ở bốn nơi:

- Trả hàng `TH...`: `TraHangController.php:77`
- Kiểm kê `KK...`: `KiemKeController.php:32`
- Đơn nhập `PN...`: `NhapHangController.php:96`
- Hóa đơn nhập `HN...`: `HoaDonNhapController.php:140`

Hai giao dịch trong cùng một giây có xác suất trùng (1/90 mỗi cặp). Cột mã có
unique index nên bên thua nhận lỗi 500. Đây là nguyên nhân flake đã ghi nhận ở
`PosWorkflowTest` khi chạy toàn bộ suite. **Đề xuất:** dùng bộ đếm trong DB hoặc
sinh mã + thử lại khi vi phạm unique.

#### P2-04. "Tồn đầu" trong báo cáo xuất nhập tồn bỏ qua trả hàng

`app/Exports/XuatNhapTonExport.php:20-23` tính:

```php
$nhap = $movements->where('loai', 'nhap')->sum('soluong');
$xuat = -$movements->where('loai', 'ban')->sum('soluong');
$dieuchinh = $movements->where('loai', 'dieuchinh')->sum('soluong');
// Tồn đầu = soluong hiện tại - nhap + xuat - dieuchinh
```

Bút toán `trahang` (cộng tồn khi trả hàng tốt) không được đưa vào công thức, nên
tồn đầu bị đẩy cao đúng bằng lượng hàng trả trong kỳ. `trahang_hong` cộng vào
`soluong_hong` và cũng không xuất hiện trong báo cáo.

**Đề xuất:** trừ `trahang` khỏi công thức tồn đầu và thêm cột hàng hỏng nếu cần.

#### P2-05. Lợi nhuận thống kê bỏ qua giảm giá và điểm

`ThongKeController.php:54-62` tính lợi nhuận theo `don_gia` gốc của từng dòng,
không dùng `tong_thanh_toan` (đã trừ giảm giá % và điểm). Khi hóa đơn có giảm
giá/điểm, lợi nhuận bị báo cao; hoàn tiền cũng tính theo giá gốc nên sai lệch
không tự triệt tiêu. Ngoài ra doanh thu gom theo `created_at` thay vì
`thoi_gian_thanh_toan`, đơn QR tạo cuối ngày trả tiền đầu ngày hôm sau bị tính
sai kỳ.

#### P2-06. Chênh lệch kiểm kê có thể bị áp dụng hai lần

`KiemKeController.php:56-67` chốt `chenh_lech = thực tế − tồn hiện tại` lúc gửi
phiếu; `KiemKeController.php:69-90` áp dụng nguyên giá trị đó lúc duyệt. Nếu có
phiếu kiểm kê khác chồng lấn (hoặc điều chỉnh thủ công không qua bút toán) trong
khoảng giữa, cùng một sai lệch có thể bị cộng/trừ hai lần. Nên khóa phiếu và kiểm
tra phiên bản tồn, hoặc chỉ cho một phiếu kiểm kê mở tại một thời điểm.

#### P2-07. Khôi phục giỏ tạm ghi đè giỏ đang có

`HoldController.php:59` đặt thẳng `gio_hang` bằng dữ liệu giỏ tạm. Thu ngân đang
có giỏ sẽ mất nội dung mà không được cảnh báo. **Đề xuất:** xác nhận trước hoặc
gộp giỏ.

#### P2-08. Mã hóa đơn đầu tiên trong ngày phụ thuộc gap lock

`DonHangController.php:322-333` và `ThanhToanChuyenKhoanService.php:309-320` lấy
mã cuối cùng của ngày bằng `orderByDesc('id')->lockForUpdate()`. Khi chưa có hóa
đơn nào trong ngày, không có dòng để khóa; hai quầy đồng thời có thể sinh cùng
`HD...-0001`. Trên MySQL REPEATABLE READ, gap lock thường chặn được, nhưng hành
vi không được bảo đảm trên SQLite hoặc isolation thấp hơn; bên thua nhận lỗi 500.
**Đề xuất:** dùng bảng đếm/sequence hoặc retry khi vi phạm unique.

### 5.3. P3 — cải thiện

| Mã | Vị trí | Nội dung |
|---|---|---|
| P3-01 | `WebhookController.php:56`, `ThanhToanChuyenKhoanService.php:194-195` | Hai cột `ma_giaodich` (cũ) và `ma_giao_dich` (mới) cùng lưu một giá trị; `KiemTraDuLieu` chỉ kiểm cột cũ. Nên hợp nhất hoặc ghi rõ cột chuẩn. |
| P3-02 | `routes/web.php:16,59,61,65,30` | Thao tác thay đổi trạng thái qua GET (thêm/xóa giỏ, khôi phục giỏ tạm, đăng xuất). Không có CSRF cho GET; link lạ có thể thao tác giỏ của người đang đăng nhập. |
| P3-03 | `routes/web.php:27-29` | Đăng ký không giới hạn tần suất, có thể bị spam tài khoản. |
| P3-04 | `DonHangController.php:88` | Endpoint tạo QR trả nguyên `$e->getMessage()` cho trình duyệt; lỗi SQL/storage có thể lộ chi tiết. Nên trả thông điệp chung và ghi log. |
| P3-05 | Nhiều controller | Danh sách sản phẩm, khách hàng, loại, kho, nhà cung cấp dùng `->get()` không phân trang; dữ liệu lớn sẽ chậm. |
| P3-06 | `SepayDoiSoat.php:154-171` | Dự phòng đọc toàn bộ webhook 30 ngày vào bộ nhớ cho mỗi giao dịch thiếu; ghép theo mã tham chiếu không kèm số tiền có thể ghép nhầm khi ngân hàng tái sử dụng mã. |
| P3-07 | `DonHangController.php:115-131,134-147` | Xác nhận/hủy chờ thanh toán không ghi nhật ký hoạt động. |
| P3-08 | `ThongKeController.php:20-31` | Tổng quan đếm mọi hóa đơn, gồm cả `cho_thanh_toan` và `da_huy`. |
| P3-09 | `KiemKeController.php:37` | Thời điểm chụp tồn dùng `now()->addSecond()`, lệch tương lai 1 giây; vô hại nhưng khó giải thích khi đối chiếu. |
| P3-10 | `app/Http/Controllers/*` | Chưa có luồng quên/đặt lại mật khẩu; vận hành thật cần bổ sung. |

## 6. Đối chiếu với README

- README nêu storefront chưa có đặt hàng online — đúng với mã hiện tại.
- README mô tả ba đường nhập kho, trả hàng theo ngưỡng 500.000 đ, điểm tích lũy,
  QR chờ 15 phút, hủy lười — đều khớp mã.
- README ghi nhận flake `PosWorkflowTest` do trùng mã phiếu trả — khớp P2-03.
- README nêu "205 test" — lượt chạy này đạt 205 pass, 1 skip.
- Báo cáo này không so sánh với nhánh `dev`/`main` theo phạm vi đã thống nhất.

## 7. Phạm vi chưa kiểm tra

- Không chạy E2E trên trình duyệt; giao diện chỉ được xác nhận qua biên dịch Blade
  và đọc view.
- Không chạy test khóa dòng trên MySQL thật (`KhoaDongMysqlTest` bị skip).
- Không đo hiệu năng, không kiểm thử tải.
- Không kiểm tra sao lưu/khôi phục thật trên MySQL.
- `RouteKhoiTest` chỉ quét GET cho 3 vai trò nhân viên và khách; chưa quét POST/
  DELETE/PUT và chưa có vai trò `khachhang` — đây là lý do P1 lọt lưới.
- Không kiểm tra webhook/Telegram/SePay bằng dịch vụ thật.

## 8. Thứ tự khắc phục đề xuất

1. **P1-01, P1-02:** thêm `Request $request`, mở rộng guard khóa ngoại, bổ sung
   test DELETE. Đây là lỗi chắc chắn xảy ra khi người dùng bấm xóa.
2. **P2-02:** ghi vết giao dịch tiền vào cho hóa đơn đã thanh toán.
3. **P2-03, P2-08:** thay cơ chế sinh mã bằng sequence/retry để hết trùng mã.
4. **P2-04, P2-05:** sửa công thức tồn đầu và lợi nhuận để báo cáo khớp sổ.
5. **P2-01, P2-07:** thêm nhật ký/cảnh báo cho xác nhận tay; xác nhận khi khôi
   phục giỏ.
6. **P2-06:** ràng buộc một phiếu kiểm kê mở tại một thời điểm.
7. **P3:** xử lý theo lô khi có thời gian.

## 9. Phụ lục

### 9.1. Kết quả kiểm thử

Trích cuối output:

```
  Tests:    1 skipped, 205 passed (1249 assertions)
  Duration: 205.38s
```

Test bị bỏ qua:

```
WARN  Tests\Feature\KhoaDongMysqlTest
- hoa don bi khoa boi phien khac thi hoan tat phai cho va that bai → Cần MySQL bản sao...
```

Các nhóm test đáng chú ý đã qua: `PosWorkflowTest` (thanh toán, tồn, điểm, trả
hàng, giữ đơn, kiểm kê), `ToanVenGiaoDichTest` (D01–D06: hủy quá hạn hai lần chỉ
hoàn kho một lần, webhook trùng chỉ xử lý một lần, bán khi tồn còn 1), `RouteKhoiTest`
(4 vai trò, không có 500), `SepayApiClientTest`, `PdfInvoiceParserTest`,
`ThongBaoLoiTest`.

### 9.2. Ma trận quyền (tóm tắt)

| Nhóm chức năng | admin | thungan | thukho | khachhang |
|---|---|---|---|---|
| Storefront, giỏ storefront | có | có | có | có |
| POS, hóa đơn, trả hàng, khách hàng, giữ đơn | có | có | 403/chuyển hướng | chuyển hướng |
| Nhập kho, hóa đơn nhập, sản phẩm, loại, kiểm kê, kho | có | 403 | có | chuyển hướng |
| Đối soát, thống kê, nhật ký, duyệt trả hàng, xuất hóa đơn | có | 403 | 403 | chuyển hướng |

Ghi chú: khách hàng vào khu vực nhân viên được chuyển về storefront bởi middleware
`staff.only`; tài khoản `khachhang` chưa được đưa vào `RouteKhoiTest`.
