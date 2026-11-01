# Kế hoạch tổng thể Web POS & Quản lý kho

Tài liệu này hợp nhất lộ trình trong `Giai đoạn 1.pdf`, `Giai đoạn 2.pdf` và
`Giai đoạn 3.pdf`. Các pha 0–10 là nền tảng và lõi POS; các pha 11–17 là mở
rộng đã được triển khai theo hồ sơ; các pha 18–24 là lộ trình mở rộng tiếp theo.

## 1. Mục tiêu và nguyên tắc chung

- Phạm vi chính: ứng dụng Web POS và quản lý kho cho cửa hàng phụ kiện điện thoại.
- Công nghệ: PHP 8.x, Laravel, Composer, MySQL, Bootstrap 5, jQuery/DataTables,
  Chart.js và PHPUnit.
- Bắt đầu từ CSDL, danh mục, sản phẩm, POS, thanh toán, hóa đơn, kho, thống kê,
  phân quyền rồi mới mở rộng.
- Mọi thay đổi tiền, tồn kho và điểm phải chạy trong transaction; thao tác bán
  phải khóa sản phẩm theo `product_id` bằng `lockForUpdate`.
- Tên bảng dùng tiếng Anh số nhiều; tên cột, Model và Controller theo quy ước
  hiện có của dự án.
- Sau mỗi pha phải chạy test, kiểm tra migration/view/route và merge qua Gitflow.
- Không dùng dữ liệu thật khi `migrate:fresh` hoặc `demo:reset`; không tích hợp
  dịch vụ ngoài nếu chưa có tài khoản sandbox và tài liệu API.

## 2. Giai đoạn 1 - Nền tảng và luồng lõi

### Pha 0 - Khởi tạo CSDL, Model và Seeder

Tạo migration cho người dùng, loại sản phẩm, sản phẩm, hóa đơn, chi tiết hóa
đơn và biến động kho; tạo Model, quan hệ Eloquent, ba tài khoản demo và dữ liệu
Hoco mẫu. Kiểm tra `migrate:fresh --seed` và các khóa ngoại.

### Pha 1 - Model và Controller theo bài học

Hoàn thiện Model/Controller loại sản phẩm với các hàm danh sách, thêm, sửa,
xóa và redirect/flash message đúng quy ước.

### Pha 2 - Layout và CRUD sản phẩm

Tạo layout Bootstrap/sidebar, flash message, CRUD sản phẩm, validate SKU/barcode
duy nhất, giá không âm, upload ảnh và badge tồn kho.

### Pha 3 - POS, tìm kiếm và giỏ hàng

Tìm theo tên/SKU/barcode, trả tối đa 10 kết quả, lưu giỏ trong session, gộp
sản phẩm trùng, không cho số lượng trong giỏ vượt tồn và tính tiền ở giao diện.

### Pha 4 - Thanh toán và trừ tồn

Server tính lại toàn bộ tiền; transaction tạo hóa đơn, chi tiết, trừ tồn và ghi
movement `ban`. Khóa sản phẩm theo thứ tự tăng dần để tránh bán vượt tồn/deadlock.

### Pha 5 - Hóa đơn

Danh sách/lọc hóa đơn, xem chi tiết và in hóa đơn K80 bằng trình duyệt với CSS
`@media print`.

### Pha 6 - Kho

Xem tồn, nhập kho, cập nhật giá nhập, ghi movement `nhap` và cảnh báo sản phẩm
sắp hết theo ngưỡng tối thiểu.

### Pha 7 - Thống kê

Doanh thu theo ngày, Top 5 sản phẩm và các thẻ tổng quan bằng Chart.js; lợi nhuận
được tính theo giá vốn.

### Pha 8 - Đăng nhập và phân quyền role

Đăng nhập/đăng xuất, middleware `admin`, `thungan`, `thukho`, ẩn menu theo quyền
và trả 403 tiếng Việt khi truy cập trái phép.

### Pha 9 - Mở rộng demo ban đầu

Hỗ trợ barcode scanner dạng keyboard wedge và VietQR thủ công; không bao gồm
webcam hoặc webhook đối soát tự động.

### Pha 10 - Kiểm thử và rà soát

Kiểm thử thanh toán đủ/vượt tồn, rollback, giỏ rỗng, phân quyền, hóa đơn, kho,
thống kê và rà soát race condition/XSS/CSRF/validation.

## 3. Giai đoạn 2 - Mở rộng theo hồ sơ hoàn chỉnh

### Pha 11–13 - Hình ảnh, barcode, in nhiệt và VietQR

- Card View POS, ảnh dự phòng, lọc theo loại và upload qua Storage.
- Barcode scanner keyboard, tem Code 128, in hóa đơn K58/K80.
- VietQR động với xác nhận chuyển khoản thủ công.

### Pha 14 - Khách hàng và tích điểm

CRUD/tìm khách hàng, cộng điểm theo doanh thu, đổi điểm, khóa khách hàng trong
transaction và lưu số điểm/giá trị tại hóa đơn.

### Pha 15 - Nhà cung cấp và đơn nhập

CRUD nhà cung cấp; tạo đơn nhập nhiều sản phẩm; quy trình duyệt/giao/nhận/hủy;
nhận hàng cập nhật tồn, giá vốn và movement trong transaction.

### Pha 16 - Báo cáo, xuất file và giá vốn

Lưu giá vốn tại thời điểm bán, thống kê doanh thu/lợi nhuận theo ngày/tuần/tháng,
xuất hóa đơn và báo cáo xuất nhập tồn XLSX/PDF.

### Pha 17 - Nhật ký hoạt động và phân quyền nâng cao

Audit log admin cho các thao tác quan trọng. Permission động là phần mở rộng
chưa bật vì cần chốt ma trận quyền và tránh làm hỏng role hiện tại.

## 4. Trạng thái triển khai

- Pha 0–17: phần lõi và các mở rộng an toàn đã hoàn thành.
- Pha 18: đổi/trả hàng cốt lõi đã hoàn thành; đổi hàng tạo hóa đơn bù trừ riêng
  chưa triển khai.
- Pha 19: giữ/khôi phục/xóa giỏ tạm và tự dọn sau 24 giờ đã hoàn thành.
- Pha 20: kiểm kê, tính chênh lệch có bán xen và duyệt điều chỉnh đã hoàn thành;
  báo cáo riêng XLSX/PDF và hủy phiếu chưa làm.

# Giai đoạn 3 - Lộ trình mở rộng Web POS

## 1. Đánh giá tài liệu

File PDF `phu-luc-giai-doan-2-web-pos.pdf` phù hợp với đồ án Web POS và quản lý
kho. Đây là phụ lục mở rộng sau Giai đoạn 2, không phải yêu cầu bắt buộc cho
demo hiện tại. Tài liệu bổ sung các pha 18 đến 24, test từ T43 và các truy vấn
kiểm tra nhất quán.

Các phần đã có nền tảng trong dự án:

- Transaction và khóa dòng tồn kho.
- Khách hàng, tích điểm và giá vốn.
- Nhà cung cấp, đơn nhập hàng.
- Nhật ký hoạt động.
- Thống kê và xuất báo cáo.

Vì vậy có thể tiếp tục triển khai các phần nội bộ trước; các phần phụ thuộc
nhà cung cấp bên ngoài cần xác minh trước khi lập trình.

## 2. Nguyên tắc triển khai

- Làm từng pha, chạy test sau mỗi pha.
- Không làm hỏng luồng POS, kho, hóa đơn và phân quyền role hiện tại.
- Mọi thay đổi tồn kho, tiền, điểm và voucher phải nằm trong transaction.
- Khóa các dòng liên quan theo thứ tự ổn định để tránh tranh chấp dữ liệu.
- Dùng doanh thu ròng sau đổi/trả cho thống kê, ROP và ABC.
- Không dùng dữ liệu thật khi chạy `migrate:fresh` hoặc reset demo.
- Không triển khai tích hợp ngoài nếu chưa có tài khoản sandbox, tài liệu API
  và xác nhận của chủ dự án.

## 3. Thứ tự thực hiện

### Pha 18 - Đổi/trả hàng

**Ưu tiên cao**, vì ảnh hưởng tồn kho, tiền hoàn, điểm và doanh thu.

**Trạng thái: Đã hoàn thành phần cốt lõi.**

- Tạo `returns`, `return_items`.
- Thêm `products.soluong_hong` và liên kết `stock_movements.return_id`.
- Chỉ cho trả hóa đơn đã thanh toán trong thời hạn cấu hình.
- Không cho trả vượt số lượng đã bán trừ các lần trả trước.
- Tính tiền hoàn theo tỷ lệ giảm giá và điểm của hóa đơn gốc.
- Hàng tốt cộng lại kho bán; hàng lỗi đưa vào kho lỗi.
- Thu hồi điểm nhưng không để điểm khách hàng âm.
- Phiếu vượt ngưỡng phải chờ admin duyệt.
- Ghi nhật ký và cập nhật thống kê doanh thu/lợi nhuận ròng.

**Cần kiểm thử:** trả đủ, trả vượt, trả nhiều lần, giảm giá, hàng lỗi, thu hồi
điểm và quá hạn.

Đã kiểm thử trả hàng tốt/hỏng, trả vượt số lượng, cập nhật tồn kho, kho lỗi và
ghi movement trong transaction. Hình thức đổi hàng hiện mới lưu lựa chọn hoàn;
chưa tạo hóa đơn bù trừ riêng.

### Pha 19 - Tạm lưu giỏ hàng

**Trạng thái: Hoàn thành.**

- Tạo bảng `held_carts` lưu theo người dùng.
- Thêm giữ, xem, khôi phục và xóa giỏ.
- Không trừ tồn kho khi giữ giỏ.
- Khi khôi phục phải kiểm tra lại tồn, giá và trạng thái sản phẩm.
- Tự xóa giỏ quá 24 giờ bằng Artisan command/scheduler.

Đã kiểm thử giữ/khôi phục bình thường và phân quyền khôi phục giỏ của người khác.

### Pha 20 - Kiểm kê kho

**Trạng thái: Hoàn thành phần cốt lõi.**

- Tạo `stocktakes`, `stocktake_items` và liên kết biến động kho.
- Chụp tồn đầu kỳ và thời điểm bắt đầu đếm.
- Tính chênh lệch theo các biến động phát sinh sau thời điểm đếm.
- Admin duyệt trong transaction có `lockForUpdate`.
- Ghi điều chỉnh kho và activity log.
- Xuất báo cáo chênh lệch Excel/PDF.

Đã kiểm thử gửi duyệt, tính chênh lệch và duyệt điều chỉnh trong transaction.
Báo cáo Excel/PDF và hủy phiếu chưa cần thiết cho scope hiện tại.

### Pha 21 - ROP và ABC

- Bổ sung thời gian giao hàng nhà cung cấp và nhà cung cấp chính của sản phẩm.
- Tính ROP từ doanh số 30 ngày, thời gian giao và tồn an toàn.
- Cảnh báo sản phẩm dưới ROP và gợi ý số lượng nhập.
- Phân nhóm ABC theo doanh thu ròng 90 ngày.
- Hiển thị Pareto bằng Chart.js.

**Điều kiện:** cần có đủ dữ liệu bán/nhập; khi chưa đủ 7 ngày bán dùng ngưỡng
tồn tối thiểu hiện tại.

### Pha 22 - Khuyến mãi và voucher

- Tạo bảng promotions, vouchers, invoice_promotions.
- Hỗ trợ phần trăm, tiền trừ, mua X tặng Y, flash sale và voucher.
- Áp dụng theo thời gian, hạng khách, ưu tiên và cho phép cộng dồn.
- Khóa voucher bằng `lockForUpdate` để không vượt số lượt.
- Quà tặng có giá bán 0 nhưng vẫn trừ tồn và lưu giá vốn.
- Tính khuyến mãi trên server bằng service riêng.

**Cần xác minh trước:** quy tắc khuyến mãi thực tế, mức giảm tối đa và có cho
cộng dồn hay không.

### Pha 23 - Tài khoản, permission và REST API

- CRUD tài khoản, khóa tài khoản và đặt lại mật khẩu.
- Gán role và permission chi tiết.
- Không cho admin tự xóa/tự gỡ quyền hoặc xóa admin cuối cùng.
- Giữ tương thích middleware role hiện tại.
- Chỉ thêm REST API khi thật sự cần cho ứng dụng khác.
- Nếu làm API, dùng Sanctum, tiền tố `/api/v1`, throttle và phản hồi JSON
  thống nhất.

**Cần xác minh trước:** danh sách permission chính thức và có cần mobile/API
trong đồ án hay không.

### Pha 24 - Tích hợp bên ngoài

Chỉ thực hiện sau cùng và chỉ khi có xác nhận:

- Webhook VietQR qua SePay, Casso, payOS hoặc nhà cung cấp tương đương.
- Hóa đơn điện tử, Zalo ZNS/SMS qua tài khoản đối tác có chi phí.
- In im lặng ESC/POS bằng QZ Tray, luôn giữ phương án in trình duyệt dự phòng.
- Đồng bộ tồn kho Shopee/TikTok Shop qua API và webhook.

Các phần này cần tài khoản sandbox, secret, tài liệu API hiện hành và môi
trường HTTPS. Không tự chọn nhà cung cấp hoặc dùng thông tin thật khi chưa
được xác minh.

## 4. Trạng thái hiện tại

- Giai đoạn 1: hoàn thành phần nền tảng ban đầu.
- Giai đoạn 2: hoàn thành lõi POS, kho và các mở rộng đã được xác định.
- Sau Giai đoạn 2: đã triển khai nhà cung cấp/đơn nhập và audit log cơ bản.
- Giai đoạn 3: đã triển khai Pha 18–20 phần an toàn; các phần cần xác minh vẫn
  để trong lộ trình.

## 5. Đề xuất bước tiếp theo

Nếu tiếp tục không cần xác minh bên ngoài, làm theo thứ tự:

1. Hoàn thiện đổi hàng bù trừ nếu nghiệp vụ yêu cầu.
2. Pha 21 - ROP và ABC nếu có đủ dữ liệu bán/nhập.
3. Xác minh nghiệp vụ rồi mới làm Pha 22.
4. Chỉ làm Pha 23 và 24 khi có nhu cầu rõ ràng.
