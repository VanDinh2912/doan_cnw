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

### Pha 19 - Tạm lưu giỏ hàng

- Tạo bảng `held_carts` lưu theo người dùng.
- Thêm giữ, xem, khôi phục và xóa giỏ.
- Không trừ tồn kho khi giữ giỏ.
- Khi khôi phục phải kiểm tra lại tồn, giá và trạng thái sản phẩm.
- Tự xóa giỏ quá 24 giờ bằng Artisan command/scheduler.

**Cần kiểm thử:** giữ/khôi phục bình thường và khôi phục khi sản phẩm đã hết.

### Pha 20 - Kiểm kê kho

- Tạo `stocktakes`, `stocktake_items` và liên kết biến động kho.
- Chụp tồn đầu kỳ và thời điểm bắt đầu đếm.
- Tính chênh lệch theo các biến động phát sinh sau thời điểm đếm.
- Admin duyệt trong transaction có `lockForUpdate`.
- Ghi điều chỉnh kho và activity log.
- Xuất báo cáo chênh lệch Excel/PDF.

**Cần kiểm thử:** có bán xen trong lúc kiểm kê, duyệt chênh lệch và hủy phiếu.

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
- Giai đoạn 3: tài liệu kế hoạch; chưa tự động triển khai Pha 18 trở đi.

## 5. Đề xuất bước tiếp theo

Nếu tiếp tục không cần xác minh bên ngoài, làm theo thứ tự:

1. Pha 18 - Đổi/trả hàng.
2. Pha 19 - Tạm lưu giỏ hàng.
3. Pha 20 - Kiểm kê kho.
4. Pha 21 - ROP và ABC.
5. Xác minh nghiệp vụ rồi mới làm Pha 22.
6. Chỉ làm Pha 23 và 24 khi có nhu cầu rõ ràng.

