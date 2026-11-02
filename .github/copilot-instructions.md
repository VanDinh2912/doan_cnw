# Hướng dẫn phát triển dự án Web POS & Quản lý kho

Tài liệu hướng dẫn dành cho GitHub Copilot / Antigravity AI Assistant khi làm việc trên codebase dự án Web POS & Quản lý kho.

## 1. Quy tắc kiến trúc & Luồng dữ liệu lõi
- **Công nghệ**: PHP 8.4+, Laravel 12+, MySQL, Blade Template, Bootstrap 5, jQuery, DataTables, PHPUnit.
- **Thứ tự khóa trong giao dịch (Transaction Lock Order)**: Luôn tuân thủ thứ tự để tránh Deadlock:
  `Sản phẩm (product_id tăng dần)` $\to$ `Khách hàng (customer_id)` $\to$ `Hóa đơn (invoice_id)`.
- **Trạng thái hóa đơn**:
  - `cho_thanh_toan`: Hóa đơn chuyển khoản đang chờ quét QR. Đã trừ kho và ghi `stock_movements` loại `ban` ngay khi tạo (giữ hàng), trừ điểm sử dụng của khách.
  - `da_thanh_toan`: Đã nhận tiền thành công (từ webhook hoặc xác nhận tay). Cộng điểm tích lũy nhận cho khách hàng, cập nhật `ma_giaodich`, `thoi_gian_thanh_toan`.
  - `da_huy`: Hóa đơn bị hủy hoặc hết hạn chờ thanh toán. Tự động hoàn lại tồn kho (`stock_movements` loại `dieuchinh` với số lượng dương) và hoàn lại `diem_su_dung` cho khách hàng.
- **Thống kê doanh thu & in ấn**: Chỉ các hóa đơn `da_thanh_toan` mới được tính vào doanh thu thống kê và được phép in hóa đơn (trả 403 nếu cố in hóa đơn `cho_thanh_toan`).

## 2. Thanh toán Chuyển khoản (SePay Webhook & VietQR)
- **Tạo QR**: Không dùng mã giả lập; tạo URL ảnh VietQR server-side (`VietQrService`) với nội dung là `ma_hoadon` chuẩn (`HDYYYYMMDD-XXXX`).
- **Webhook SePay**:
  - Endpoint: `POST /api/webhook/thanhtoan` (name `webhook.thanhtoan`), miễn trừ CSRF trong `bootstrap/app.php`, giới hạn tần suất `throttle:60,1`.
  - Xác thực: Header `Authorization: Apikey <KEY>` so sánh bằng `hash_equals`.
  - Idempotency: Kiểm tra `id` giao dịch SePay trước khi xử lý. Trả về HTTP 200 `{success: true}` nếu giao dịch đã từng xử lý.
  - Số tiền: Nếu $\ge$ tổng thanh toán $\to$ hoàn tất (ghi chú nếu khách chuyển dư). Nếu $<$ tổng thanh toán $\to$ giữ nguyên trạng thái chờ và gửi cảnh báo.
  - Chỉ xử lý giao dịch tiền vào (`transferType = 'in'`).
- **Thông báo Telegram**:
  - Gửi qua Telegram Bot API với timeout 5 giây, bọc `try/catch`. Không bao giờ làm gián đoạn hoặc rollback giao dịch thanh toán nếu Telegram gặp sự cố.
  - Gửi thông báo sau khi database transaction đã commit (`DB::afterCommit`).

## 3. Quy ước Gitflow & Kiểm thử
- Mỗi tính năng mới phát triển trên nhánh riêng `feature/...`.
- Sau mỗi mốc hoàn thành, chạy test và tạo commit rõ ràng.
- Toàn bộ test PHPUnit phải xanh (`PASS`) trước khi hợp nhất (`merge`) vào nhánh `main`.
