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

Nếu MySQL có mật khẩu, điền mật khẩu vào `DB_PASSWORD`.

### 3.5. Tạo bảng và dữ liệu mẫu

Lệnh dưới đây xóa và tạo lại toàn bộ bảng trong database đã cấu hình:

```powershell
php artisan migrate:fresh --seed --force
```

Không chạy lệnh này trên database có dữ liệu cần giữ.

## 4. Chạy ứng dụng

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

## 5. Tài khoản demo

Tất cả tài khoản demo đều có mật khẩu `123456`.

| Vai trò | Email | Quyền chính |
|---|---|---|
| Quản lý | `admin@example.com` | Toàn quyền và xem thống kê |
| Thu ngân | `thungan@example.com` | POS, giỏ hàng, thanh toán, hóa đơn |
| Thủ kho | `thukho@example.com` | Sản phẩm, loại sản phẩm, nhập kho |

Mật khẩu trên chỉ dùng cho demo, không dùng trong môi trường thật.

## 6. Chức năng chính

### Đăng nhập và phân quyền

- Đăng nhập, đăng xuất, ghi nhớ đăng nhập.
- Middleware phân quyền theo `admin`, `thungan`, `thukho`.
- Truy cập trái phép trả trang 403 tiếng Việt.
- Dashboard tổng quan dành cho người dùng đã đăng nhập.

### Quản lý danh mục và sản phẩm

- CRUD loại sản phẩm.
- CRUD sản phẩm.
- Validate tên, SKU, barcode, giá và số lượng.
- Upload hình ảnh vào `public/upload/sanpham`.
- Badge tồn kho:
  - Đỏ: hết hàng.
  - Vàng: tồn kho nhỏ hơn hoặc bằng ngưỡng tối thiểu.
  - Xanh: tồn kho bình thường.

### POS và thanh toán

- Tìm sản phẩm theo tên, SKU hoặc barcode.
- Chỉ hiển thị sản phẩm đang bán và còn hàng.
- Giới hạn tối đa 10 kết quả tìm kiếm.
- Giỏ hàng được lưu trong session.
- Gộp sản phẩm trùng thành một dòng.
- Tính giảm giá, tiền khách đưa và tiền thừa.
- Thanh toán bằng tiền mặt hoặc chuyển khoản.
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
- Giá trên hóa đơn được lưu tại thời điểm bán.
- Các bảng danh sách có tìm kiếm và phân trang bằng DataTables.

### Quản lý kho

- Xem tồn kho.
- Nhập kho theo sản phẩm.
- Cập nhật số lượng và giá nhập trong transaction.
- Ghi lịch sử biến động loại `nhap`.
- Cảnh báo sản phẩm tồn thấp trên dashboard.

### Thống kê

Admin có thể truy cập:

- Doanh thu theo ngày.
- Top 5 sản phẩm bán chạy.

## 7. Các URL chính

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
| `/thongke/doanhthu` | Doanh thu JSON | Admin |
| `/thongke/topsanpham` | Top sản phẩm JSON | Admin |

## 8. Kiểm thử

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

Kiểm tra cú pháp PHP:

```powershell
Get-ChildItem -Recurse -Filter *.php |
    Where-Object { $_.FullName -notmatch '\\vendor\\|\\storage\\' } |
    ForEach-Object { php -l $_.FullName }
```

## 9. Reset dữ liệu demo

Lệnh reset sẽ xóa toàn bộ bảng, chạy migration, nạp lại dữ liệu mẫu và xóa cache:

```powershell
php artisan demo:reset
```

Bỏ qua bước xác nhận:

```powershell
php artisan demo:reset --force
```

Trên Windows có thể chạy bằng cách nhấp đúp:

```text
reset_demo.bat
```

Không sử dụng lệnh reset trên môi trường production.

## 10. Cấu trúc thư mục chính

```text
app/
├── Console/Commands/ResetDemoData.php
├── Http/Controllers/
│   ├── AuthController.php
│   ├── DonHangController.php
│   ├── GioHangController.php
│   ├── KhoController.php
│   ├── LoaiSanPhamController.php
│   ├── SanPhamController.php
│   └── ThongKeController.php
├── Http/Middleware/CheckRole.php
└── Models/
    ├── BienDongKho.php
    ├── ChiTietDonHang.php
    ├── DonHang.php
    ├── LoaiSanPham.php
    └── SanPham.php

database/
├── migrations/
└── seeders/DatabaseSeeder.php

resources/views/
├── auth/
├── donhang/
├── kho/
├── layouts/
├── loaisanpham/
├── pos/
└── sanpham/
```

## 11. Cơ sở dữ liệu

Các bảng nghiệp vụ:

- `users`: tài khoản và vai trò.
- `categories`: loại sản phẩm.
- `products`: sản phẩm và tồn kho.
- `invoices`: hóa đơn.
- `invoice_details`: chi tiết hóa đơn.
- `stock_movements`: lịch sử nhập/bán/điều chỉnh kho.

Quy ước biến động kho:

- `nhap`: số lượng dương, `invoice_id` là `NULL`.
- `ban`: số lượng âm, có `invoice_id`.
- `dieuchinh`: dùng cho điều chỉnh thủ công, `invoice_id` là `NULL`.

## 12. Quy trình Git đề xuất

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

## 13. Phạm vi chưa triển khai

Các phần sau chưa thuộc scope cốt lõi hiện tại:

- Quét barcode bằng webcam.
- Tích hợp VietQR động.
- CRUD tài khoản người dùng.
- Ứng dụng mobile hoặc API riêng.
- Báo cáo xuất Excel/PDF nâng cao.
- Tích hợp cổng thanh toán thật.

Chỉ nên triển khai các phần mở rộng sau khi toàn bộ luồng POS, kho, hóa đơn
và phân quyền đã được kiểm thử ổn định.

## 14. Lưu ý bảo mật

- Không commit file `.env` hoặc thông tin mật khẩu thật.
- Luôn dùng `@csrf` cho form POST/PUT/DELETE.
- Không hiển thị dữ liệu người dùng bằng `{!! !!}`.
- Không dùng tài khoản demo trong môi trường production.
- Không chạy `migrate:fresh` hoặc `demo:reset` trên dữ liệu thật.