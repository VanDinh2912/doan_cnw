# Web POS & Quản lý kho

Ứng dụng web quản lý bán hàng tại quầy (POS) và quản lý kho cho cửa hàng phụ
kiện điện thoại. Đây là đồ án Laravel, tập trung vào quy trình:

```text
Đăng nhập → Quản lý sản phẩm → Bán hàng POS → Thanh toán → Hóa đơn
                                      ↓
                              Trừ tồn kho / Lịch sử kho
```

## 1. Công nghệ sử dụng

- PHP 8.4.x
- Laravel 12
- Composer 2.x
- MySQL 8.x
- Bootstrap 5
- jQuery và DataTables
- Font Awesome
- Blade Template
- PHPUnit

## 2. Yêu cầu môi trường

Đảm bảo máy đã cài:

- PHP và Composer.
- WAMP/XAMPP với Apache và MySQL.
- PHP extensions: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `xml`.
- Git (nếu cần lấy mã nguồn).

Phiên bản đã kiểm tra trên máy phát triển:

```text
PHP       8.4.15
Composer  2.10.3
Laravel   12.68.0
MySQL     8.4.7
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

### 3.4. Cấu hình MySQL

Tạo database tên `doan_cnw` trong phpMyAdmin, sau đó kiểm tra `.env`:

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

Nếu muốn dùng QR chuyển khoản trong màn hình POS, thêm cấu hình:

```env
POS_BANK_CODE=VCB
POS_BANK_ACCOUNT=0123456789
POS_BANK_NAME="TEN CHU TAI KHOAN"
```

Chỉ dùng thông tin tài khoản demo hoặc tài khoản do chủ dự án cung cấp. Tính năng hiện không có webhook đối soát tự động.

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

### 4.3. VietQR với tài khoản ngân hàng thật

Chỉ cấu hình tài khoản do chủ dự án cho phép. Không commit các giá trị này lên
Git và không gửi file `.env` cho người khác:

```env
POS_BANK_CODE=VCB
POS_BANK_ACCOUNT=0123456789
POS_BANK_NAME="TEN CHU TAI KHOAN"
```

Thay `POS_BANK_CODE` bằng mã ngân hàng được VietQR hỗ trợ, nhập đúng số tài
 khoản và tên chủ tài khoản. Sau đó:

1. Xóa cache cấu hình bằng `php artisan config:clear`.
2. Chạy ứng dụng và mở `/pos`.
3. Chọn thanh toán chuyển khoản để hiển thị QR.
4. Dùng ứng dụng ngân hàng trên điện thoại quét QR.
5. Kiểm tra đúng người nhận, số tiền và nội dung trước khi chuyển.
6. Thu ngân chỉ xác nhận thanh toán sau khi kiểm tra giao dịch thành công
   trong ứng dụng ngân hàng hoặc sao kê.

Phiên bản hiện tại chỉ tạo QR và xác nhận thủ công; QR không tự biết giao dịch
đã thành công. Không đánh dấu đã thanh toán chỉ dựa vào việc khách gửi ảnh
chụp màn hình chuyển khoản.

### 4.4. Webhook xác nhận thanh toán ngân hàng

Webhook không thể tự triển khai chỉ bằng tài khoản ngân hàng thông thường.
Cần đăng ký một cổng thanh toán hoặc dịch vụ trung gian có API/webhook, ví dụ
VietQR động qua một nhà cung cấp được ngân hàng hỗ trợ. Quy trình triển khai
an toàn:

1. Chọn nhà cung cấp có tài liệu API, chữ ký webhook và môi trường sandbox.
2. Tạo `ma_giao_dich` duy nhất cho mỗi hóa đơn, không dùng mã hóa đơn dễ đoán.
3. Lưu hóa đơn ở trạng thái `cho_xac_nhan`, số tiền phải thu và mã giao dịch.
4. Tạo endpoint HTTPS riêng, ví dụ `/api/payment/webhook`.
5. Xác thực chữ ký hoặc secret của nhà cung cấp trước khi đọc payload.
6. Kiểm tra số tiền, mã giao dịch, ngân hàng nhận và trạng thái giao dịch.
7. Xử lý idempotent: cùng một webhook gửi lại không được tạo hóa đơn hoặc
   cộng điểm lần thứ hai.
8. Ghi log tối thiểu gồm mã giao dịch, trạng thái và thời điểm; không ghi
   secret hoặc toàn bộ thông tin nhạy cảm.
9. Chỉ chuyển hóa đơn sang `da_thanh_toan` sau khi tất cả kiểm tra thành công.
10. Kiểm thử sandbox, webhook gửi lặp, sai chữ ký, sai số tiền và timeout
    trước khi dùng production.

Không mở endpoint webhook không xác thực và không tin dữ liệu do trình duyệt
gửi lên. Pha webhook chưa nằm trong code hiện tại; cần chốt nhà cung cấp và
tài liệu API trước khi lập trình.

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

### Hóa đơn nhập

- Tạo hóa đơn nháp, thêm nhiều dòng hàng và đính kèm PDF.
- Giá nhập là giá chưa thuế; thuế và tổng thanh toán tính ở cấp hóa đơn.
- Chỉ xác nhận mới nhập kho; xác nhận dùng transaction, khóa dòng và ghi
  `purchase_invoice_id`.
- Hóa đơn đã nhập kho không được sửa/hủy hoặc xác nhận lần hai.

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

## 8. Các URL chính

| URL | Chức năng | Quyền |
|---|---|---|
| `/dangnhap` | Đăng nhập | Khách |
| `/tongquan` | Dashboard | Mọi vai trò |
| `/pos` | Màn hình bán hàng | Admin, thu ngân |
| `/sanpham` | Quản lý sản phẩm | Admin, thủ kho |
| `/loaisanpham` | Quản lý loại sản phẩm | Admin, thủ kho |
| `/kho` | Xem tồn kho | Admin, thủ kho |
| `/kho/nhap` | Nhập kho | Admin, thủ kho |
| `/kho/lichsu` | Lịch sử biến động | Admin, thủ kho |
| `/donhang` | Danh sách hóa đơn | Admin, thu ngân |
| `/hoadonnhap` | Danh sách hóa đơn nhập | Admin, thủ kho |
| `/hoadonnhap/them` | Tạo hóa đơn nhập nháp | Admin, thủ kho |
| `/hoadonnhap/xem/{id}` | Xem hóa đơn nhập | Admin, thủ kho |
| `/thongke/doanhthu` | Doanh thu JSON | Admin |
| `/thongke/topsanpham` | Top sản phẩm JSON | Admin |

## 9. Kiểm thử

Chạy toàn bộ test:

```powershell
php artisan test
```

Các test nghiệp vụ hiện có:

- Khách mở được trang đăng nhập.
- Thu ngân thanh toán thành công và tồn kho giảm đúng.
- Thanh toán vượt tồn kho không tạo hóa đơn.
- Thanh toán chuyển khoản tự động ghi đúng tổng thanh toán và không có tiền thừa.
- Không bán được sản phẩm đã tạm dừng kinh doanh.
- Lọc hóa đơn theo khoảng ngày.
- Thu ngân không truy cập được chức năng kho.
- Hóa đơn nhập có thể tạo nháp, đính kèm PDF, xác nhận nhập kho một lần và hủy
  hóa đơn nháp; xác nhận dùng transaction và khóa dòng sản phẩm.

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

## 11. Cấu trúc thư mục chính

```text
app/
├── Console/Commands/
│   ├── KiemTraDuLieu.php
│   └── ResetDemoData.php
├── Http/Controllers/
│   ├── AuthController.php
│   ├── DonHangController.php
│   ├── GioHangController.php
│   ├── KhoController.php
│   ├── LoaiSanPhamController.php
│   ├── SanPhamController.php
│   ├── ThongKeController.php
│   └── HoaDonNhapController.php
├── Http/Middleware/CheckRole.php
└── Models/
    ├── BienDongKho.php
    ├── ChiTietDonHang.php
    ├── DonHang.php
    ├── LoaiSanPham.php
    ├── HoaDonNhap.php
    ├── ChiTietHoaDonNhap.php
    └── SanPham.php

database/
├── migrations/
│   ├── 2026_01_01_000017_add_import_invoice_fields.php
│   ├── 2026_01_01_000018_create_purchase_invoices_tables.php
│   └── 2026_01_01_000019_add_purchase_invoice_to_stock_movements.php
└── seeders/DatabaseSeeder.php

app/Services/
└── HoaDonNhapService.php

resources/views/
├── auth/
├── donhang/
├── kho/
├── layouts/
├── hoadonnhap/
├── loaisanpham/
├── pos/
└── sanpham/
```

## 12. Cơ sở dữ liệu

Các bảng nghiệp vụ:

- `users`: tài khoản và vai trò.
- `categories`: loại sản phẩm.
- `products`: sản phẩm và tồn kho.
- `invoices`: hóa đơn.
- `invoice_details`: chi tiết hóa đơn.
- `stock_movements`: lịch sử nhập/bán/điều chỉnh kho.
- `purchase_invoices`: hóa đơn nhập, thuế, tổng tiền và trạng thái.
- `purchase_invoice_items`: các dòng hàng của hóa đơn nhập.
- `purchase_orders`, `purchase_order_items`: đơn nhập hàng theo quy trình giao
  nhận.
- `returns`, `return_items`: trả hàng; `held_carts`: giỏ tạm.
- `stocktakes`, `stocktake_items`: kiểm kê và chênh lệch tồn.
- `activity_logs`: nhật ký hoạt động.

Quy ước biến động kho:

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

- Luồng đăng nhập và phân quyền theo ba vai trò.
- CRUD sản phẩm, loại sản phẩm, POS, thanh toán và hóa đơn.
- Quản lý tồn kho, nhập kho trực tiếp và lịch sử biến động.
- Upload ảnh, POS dạng thẻ, tìm barcode và in tem Code 128.
- In hóa đơn K58/K80 bằng trình duyệt.
- Khách hàng và tích điểm.
- Giá vốn, lợi nhuận và xuất báo cáo XLSX/PDF.
- VietQR thủ công với xác nhận chuyển khoản thủ công.
- Nhà cung cấp và đơn nhập hàng theo quy trình trạng thái nhiều bước.
- Nhật ký hoạt động cơ bản cho đăng nhập, sản phẩm, nhập kho, nhà cung cấp và
  đơn nhập hàng.
- Đổi/trả hàng có kiểm tra hạn, số lượng, tình trạng hàng, hoàn tiền, điểm và
  tồn kho.
- Hold giỏ hàng và kiểm kê kho có kiểm tra bán xen, duyệt điều chỉnh và nhật ký.
- Bộ dữ liệu thay thế gồm 12 loại hàng, 300 sản phẩm, 81 nhà cung cấp, 100
  khách hàng và một hóa đơn nhập 300 dòng.
- PDF hóa đơn mẫu chỉ được lưu làm file đính kèm; không parse PDF để suy đoán
  SKU, barcode hoặc giá bán.
- Tài liệu dự án được lưu trong `TaiLieu/`.
- Lộ trình mở rộng sau demo được ghi tại
  `TaiLieu/KeHoach/Ke hoach.md`.

### Còn thiếu hoặc chưa triển khai

- Phân quyền động theo permission.
- Webhook đối soát chuyển khoản tự động.
- Quét barcode bằng webcam.
- In tự động không qua hộp thoại trình duyệt.
- CRUD tài khoản người dùng, ứng dụng mobile và API riêng.
- Các luồng ngoài scope như permission động, API/mobile và tích hợp bên ngoài.

## 15. Lộ trình triển khai tiếp theo

Ưu tiên các phần đã có dữ liệu mẫu trong
`TaiLieu/ThongTinNhaCungCap/`, không mở rộng sang mobile hoặc API khi chưa
cần cho đồ án.

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

### Pha 15A — Hóa đơn nhập — Đã hoàn thành

Chi tiết quy tắc, checklist, nhật ký thay đổi, bảng số kiểm tra và kết quả
thực tế nằm trong [Ke hoach.md](TaiLieu/KeHoach/Ke%20hoach.md). Mã test riêng
dùng tiền tố `T-HDN01`, `T-HDN02`, ... để không trùng T43 của phụ lục.

### Pha 16 — Báo cáo, xuất file và giá vốn — Đã hoàn thành

### Pha 17 — Nhật ký hoạt động và phân quyền nâng cao — Đã hoàn thành một phần

Nhật ký cơ bản có tại `/nhatky`, ghi người dùng, hành động, đối tượng, thời gian,
IP và mô tả. Permission động vẫn chờ chốt ma trận quyền.

### Pha 18 — Đổi/trả hàng — Đã hoàn thành một phần

- Đã có tạo phiếu trả, trả hàng tốt/hỏng, kiểm tra hạn và số lượng cộng dồn.
- Đã hoàn tiền theo tỷ lệ, cập nhật tồn/kho lỗi, thu hồi điểm và ghi nhật ký.
- Đã cập nhật thống kê doanh thu/lợi nhuận và top sản phẩm theo doanh thu ròng.
- Chưa có luồng đổi sản phẩm tạo hóa đơn bù trừ riêng; hình thức `doi_hang`
  hiện lưu lựa chọn hoàn và cần bổ sung nếu đồ án yêu cầu.

### Pha 19 — Tạm lưu giỏ hàng — Đã hoàn thành

### Pha 20 — Kiểm kê kho — Đã hoàn thành phần cốt lõi

### Pha 21 — ROP và ABC — Chưa triển khai

### Pha 22 — Khuyến mãi và voucher — Chưa triển khai

### Pha 23 — Tài khoản, permission và REST API — Chưa triển khai

### Pha 24 — Tích hợp bên ngoài — Chưa triển khai

Các tích hợp thanh toán/webhook, hóa đơn điện tử, in ESC/POS và sàn thương mại
điện tử chỉ làm sau khi có tài khoản sandbox, tài liệu API và xác nhận.

- Chốt nhà cung cấp có sandbox và webhook có chữ ký.
- Thêm mã giao dịch duy nhất và trạng thái chờ xác nhận.
- Xác thực chữ ký, số tiền, mã giao dịch và xử lý webhook lặp.
- Chỉ dùng production sau khi test sai chữ ký, sai số tiền và timeout.

Mỗi pha phải được triển khai trên một branch riêng, chạy test trước khi
merge vào `main`. Không import dữ liệu mẫu hoặc reset database production.

## 16. Lưu ý bảo mật

- Không commit file `.env` hoặc thông tin mật khẩu thật.
- Luôn dùng `@csrf` cho form POST/PUT/DELETE.
- Không hiển thị dữ liệu người dùng bằng `{!! !!}`.
- Không dùng tài khoản demo trong môi trường production.
- Không chạy `migrate:fresh` hoặc `demo:reset` trên dữ liệu thật.