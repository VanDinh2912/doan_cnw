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

### Pha 15A - Hóa đơn nhập

**Trạng thái: Đã triển khai và kiểm tra.**

#### Quy tắc nghiệp vụ

- Nhà cung cấp phải có mã số thuế; hóa đơn phải có số hóa đơn, ký hiệu, ngày
  hóa đơn, thuế suất và các dòng hàng hợp lệ.
- `gia_nhap` của sản phẩm và `don_gia` trên dòng hóa đơn là giá **chưa thuế**.
- Thuế được tính và lưu ở cấp hóa đơn; tổng thanh toán bằng tiền hàng cộng
  tiền thuế và các khoản điều chỉnh hợp lệ.
- Hóa đơn có trạng thái `nhap_tam`, `da_nhap_kho` hoặc `da_huy`.
- Chỉ hóa đơn đã xác nhận mới tăng tồn kho, cập nhật giá nhập và ghi movement
  liên kết bằng `purchase_invoice_id`.
- Xác nhận nhập kho chạy trong transaction và khóa hóa đơn/sản phẩm bằng
  `lockForUpdate`.
- Hóa đơn đã nhập kho thì không được sửa hoặc hủy; không được xác nhận lần hai.
- Giá bán hiện tại không tự đổi khi nhập hàng; hệ số lãi chỉ dùng để gợi ý khi
  tạo sản phẩm mới.

#### Checklist triển khai

| Ngày | Hạng mục | Kết quả |
|---|---|---|
| 2026-11-01 | Tạo migration, model, service và liên kết movement | Đã hoàn thành |
| 2026-11-01 | Nạp 12 loại hàng, 300 sản phẩm, 81 nhà cung cấp, 100 khách hàng | Đã hoàn thành |
| 2026-11-01 | Nạp một hóa đơn nhập 300 dòng và file PDF đính kèm | Đã hoàn thành |
| 2026-11-02 | Bổ sung controller, route, view nháp/xem/sửa/xác nhận/hủy | Đã hoàn thành |
| 2026-11-02 | Cho phép thủ kho tải PDF hóa đơn lên khi tạo/sửa hóa đơn | Đã hoàn thành |
| 2026-11-02 | Chạy `php artisan demo:kiemtra-du-lieu` và bộ test | Đã hoàn thành |

#### Nhật ký thay đổi

- 2026-11-01: thay dữ liệu demo bằng workbook `LoaiHangHoa.xlsx`; không
  trích xuất SKU, barcode hoặc giá bán từ PDF.
- 2026-11-01: thêm hóa đơn nhập, chi tiết hóa đơn và `purchase_invoice_id`
  cho movement.
- 2026-11-02: sửa kỳ vọng test theo SKU mới, bổ sung lệnh kiểm tra dữ liệu và
  thống nhất tài liệu.
- 2026-11-02: bổ sung upload PDF vào hóa đơn nhập và test lưu file đính kèm.

#### Bảng số kiểm tra và kết quả thực tế

| Chỉ tiêu | Kỳ vọng | Kết quả |
|---|---:|---:|
| `categories` | 12 | 12 |
| `products` | 300 | 300 |
| `suppliers` | 81 | 81 |
| `customers` | 100 | 100 |
| `purchase_invoices` | 1 | 1 |
| `purchase_invoice_items` | 300 | 300 |
| Tổng tồn kho | 2.405 | 2.405 |
| Tổng movement nhập | 2.405 | 2.405 |
| Tiền hàng | 183.080.616 | 183.080.616 |
| Tiền thuế | 14.646.449 | 14.646.449 |
| Tổng thanh toán | 197.727.065 | 197.727.065 |
| Sản phẩm có barcode | 24 | 24 |

Kết quả chạy thực tế `php artisan demo:kiemtra-du-lieu`: **Dữ liệu hợp lệ**.
Truy vấn kiểm tra tồn kho dùng các movement ảnh hưởng tồn hàng tốt
(`nhap`, `ban`, `trahang`, `dieuchinh`) và loại `trahang_hong` vì hàng lỗi
được lưu ở `soluong_hong`. Kết quả hiện tại: **0 dòng sai lệch**. Quy tắc này
tiếp tục cho 0 dòng sau bán, trả hàng lỗi và kiểm kê; trả hàng lỗi không cộng
vào tồn hàng tốt.

Mã test riêng cho hóa đơn nhập dùng tiền tố `T-HDN` (`T-HDN01`, `T-HDN02`, ...)
để không trùng các mã T43 trở đi của phụ lục.

### Pha 15B - Bóc tách hóa đơn nhập PDF cục bộ (Local Parsing - Không dùng API)

**Trạng thái: Đã hoàn thành và kiểm thử pass 100%.**

#### 1. Mục tiêu và nguyên tắc
- Hoạt động **100% offline trên máy chủ nội bộ (XAMPP/PHP)**, tuyệt đối không phụ thuộc dịch vụ ngoài (không dùng OpenAI/Gemini API), không phát sinh chi phí và không bị lỗi quá hạn mức token/quota.
- Áp dụng nguyên lý **Partial Parsing with Fallback (Bóc tách một phần kèm phương án dự phòng)**:
  - Bóc tách tự động tối đa các trường có cấu trúc.
  - Trường nào thiếu, rớt dòng hoặc sản phẩm chưa nhận diện được sẽ đưa lên giao diện tiền kiểm tra (**Human-in-the-Loop**) để thủ kho bổ sung/chỉnh sửa.
- Tuân thủ **Non-blocking Workflow**: Nếu file PDF bị lỗi định dạng, mã hóa hoặc không bóc tách được chi tiết, hệ thống chỉ cảnh báo nhẹ nhàng và mở form nhập liệu thủ công (file PDF vẫn được lưu trữ làm chứng từ đính kèm). Không làm sập ứng dụng hay chặn công việc của thủ kho.

#### 2. Quy trình xử lý (Data Pipeline)
1. **Tiếp nhận PDF**: Thủ kho chọn file PDF hóa đơn tại màn hình `/hoadonnhap/them`.
2. **Trích xuất văn bản nội bộ (Local Raw Text Extraction)**:
   - Sử dụng thư viện PHP thuần `smalot/pdfparser` để đọc toàn bộ chuỗi văn bản trong file PDF ngay trên server PHP.
3. **Phân tích Heuristic & Regex (Heuristic Parser)**:
   - *Thông tin chung (Header)*: Dùng biểu thức chính quy (Regex) quét các mẫu chuẩn hóa đơn Việt Nam:
     - Số hóa đơn (`Số/No:`), Ký hiệu (`Ký hiệu/Serial:`).
     - Ngày lập hóa đơn (`Ngày ... tháng ... năm ...` hoặc `dd/mm/yyyy`).
     - Mã số thuế người bán (`Mã số thuế/MST:`).
     - Tổng tiền thanh toán (`Tổng cộng tiền thanh toán:`).
   - *Bảng hàng hóa (Items)*:
     - Nhận diện phạm vi bảng: Bắt đầu sau dòng tiêu đề cột (`Tên hàng hóa`, `STT`, `Đơn giá`...) và kết thúc trước dòng tổng kết (`Cộng tiền hàng`, `Thuế suất`...).
     - Phân tích từng dòng dữ liệu: Tách mẫu `[Tên sản phẩm] [Số lượng] [Đơn giá] [Thành tiền]`.
     - Xử lý dòng gãy (Multiline): Tự động gộp các dòng mô tả phụ vào tên hàng của dòng trước nếu không chứa số lượng/đơn giá riêng biệt.
4. **Thuật toán Khớp sản phẩm (Product Matching Funnel)**:
   - *Ưu tiên 1 (Exact Match)*: Tìm theo Barcode hoặc SKU trong CSDL `products`.
   - *Ưu tiên 2 (Fuzzy String Match)*: Sử dụng hàm nội tại PHP `similar_text()` so khớp chuỗi không phân biệt hoa thường. Nếu độ tương đồng $\ge 75\%$, tự động chọn sản phẩm tương ứng và hiển thị nhãn độ tin cậy.
   - *Fallback*: Trả về `product_id = null` kèm tên đọc được để thủ kho tự chọn qua dropdown autocomplete hoặc bấm nút `[+ Tạo nhanh sản phẩm]`.
5. **Giao diện Tiền kiểm tra (Draft Review UI - Human-in-the-Loop)**:
   - Tự động điền dữ liệu đã bóc tách vào form hóa đơn nhập dạng bảng lưới (Editable Grid).
   - Đánh dấu viền màu cảnh báo (Highlight) trực quan cho các ô chưa bóc tách được hoặc sản phẩm chưa khớp CSDL.
   - Kiểm tra tính toán phía Client: Cảnh báo ngay nếu `Tiền hàng + Tiền thuế != Tổng thanh toán`.
6. **Xác nhận & Lưu trữ (DB Transaction)**:
   - Chạy qua validation phía server.
   - Thực thi trong một `DB::transaction()` duy nhất: Tạo hóa đơn nhập, chi tiết hóa đơn, tăng tồn kho hàng tốt, ghi biến động kho `purchase_invoice_id` và lưu file PDF đính kèm vào `storage/app/public/hoadonnhap_pdf/`.

#### 3. Kết quả triển khai và kiểm thử
- **Thư viện**: Cài đặt thành công `smalot/pdfparser` ^2.13.
- **Service**: Tạo `app/Services/PdfInvoiceParserService.php` bóc tách thông tin chung và 300 dòng hàng với độ chính xác 100%.
- **Controller & Route**: Tạo endpoint `POST /hoadonnhap/boc-tach-pdf` phân quyền `admin,thukho`.
- **Giao diện**: Nâng cấp `resources/views/hoadonnhap/form.blade.php` có thẻ bóc tách tự động, AJAX preview và bảng lưới động chỉnh sửa dòng hàng.
- **Kiểm thử tự động**: Viết `tests/Feature/PdfInvoiceParserTest.php` với 3 test case kiểm tra bóc tách hóa đơn 300 dòng, phân quyền truy cập và xử lý file hỏng (3 passed, 6 assertions).
- **Tính toàn vẹn**: Chạy `php artisan demo:kiemtra-du-lieu` kết quả **Dữ liệu hợp lệ**.

### Pha 16 - Báo cáo, xuất file và giá vốn

Lưu giá vốn tại thời điểm bán, thống kê doanh thu/lợi nhuận theo ngày/tuần/tháng,
xuất hóa đơn và báo cáo xuất nhập tồn XLSX/PDF.

### Pha 17 - Nhật ký hoạt động và phân quyền nâng cao

Audit log admin cho các thao tác quan trọng. Permission động là phần mở rộng
chưa bật vì cần chốt ma trận quyền và tránh làm hỏng role hiện tại.

## 4. Trạng thái triển khai

- Pha 0–17, trong đó có Pha 15A Hóa đơn nhập: phần lõi và các mở rộng an toàn
  đã hoàn thành.
- Pha 15B Bóc tách hóa đơn nhập PDF cục bộ: đã hoàn thành và kiểm thử pass 100%.
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
- Đã thay thế dữ liệu demo bằng 12 loại hàng, 300 sản phẩm, 81 nhà cung cấp,
  100 khách hàng và một hóa đơn nhập 300 dòng. PDF mẫu chỉ được lưu làm file
  đính kèm, không dùng để suy đoán dữ liệu sản phẩm.
- Luồng hóa đơn nhập đã có nháp, đính kèm chứng từ, xác nhận nhập kho bằng
  transaction/lock, movement liên kết hóa đơn và lệnh kiểm tra dữ liệu
  `demo:kiemtra-du-lieu`.
- Giai đoạn 3: đã triển khai Pha 18–20 phần an toàn; các phần cần xác minh vẫn
  để trong lộ trình.

## 5. Đề xuất bước tiếp theo

Nếu tiếp tục không cần xác minh bên ngoài, làm theo thứ tự:

1. ~~Triển khai Giai đoạn 4 (Pha 13a Chuẩn hóa VietQrService và Pha 13b Webhook thanh toán).~~ ✅ Hoàn thành.
2. Hoàn thiện đổi hàng bù trừ nếu nghiệp vụ yêu cầu.
3. Pha 21 - ROP và ABC nếu có đủ dữ liệu bán/nhập.
4. Xác minh nghiệp vụ rồi mới làm Pha 22.
5. Chỉ làm Pha 23 khi có nhu cầu rõ ràng.

# Giai đoạn 4 - Chuẩn hóa VietQR và Webhook thanh toán (Pha 13a & 13b)

## 1. Đánh giá tài liệu Giai đoạn 4
Tài liệu `Giai doan 4.pdf` (được chuẩn hóa từ phụ lục VietQR) mở rộng và chuẩn hóa cơ chế thanh toán không dùng tiền mặt trong hệ thống Web POS:
- **Tính hợp lý**: Rất cao. Hiện tại mã QR đang được dựng bằng JavaScript ở phía client với chuỗi nội dung tĩnh (`Thanh toan Web POS`), tiềm ẩn nguy cơ sai lệch số tiền hoặc lộ cấu hình. Việc chuyển sang `App\Services\VietQrService` giúp kiểm soát chặt chẽ số tiền tính từ server, làm sạch nội dung chuyển khoản và cấu hình tập trung.
- **Tính thực tế**: 
  - Pha 13a (VietQrService) có thể triển khai ngay lập tức, chi phí thấp, kiểm thử độc lập bằng unit test (T61–T65) và chạy được ngay trên POS.
  - Pha 13b (Webhook thanh toán) triển khai endpoint chuẩn nhận webhook từ các cổng đối soát (SePay, Casso, payOS, Sandbox), xử lý Idempotent, khóa dòng hóa đơn `lockForUpdate()`, kiểm tra tính toàn vẹn số tiền và cập nhật trạng thái hóa đơn trong transaction.

## 2. Quy tắc nghiệp vụ và tiêu chuẩn thiết kế

### 2.1. Lớp VietQrService (Pha 13a)
- Đặt tại `app/Services/VietQrService.php`.
- Cấu hình trong `config/pos.php`: `bank_code`, `bank_account`, `bank_name`, `qr_template`.
- Các hàm cốt lõi:
  - `enabled(): bool`: Kiểm tra mã ngân hàng (2-10 ký tự) và số tài khoản (6-19 chữ số).
  - `cleanNoiDung(string $text): string`: Chuyển chuỗi sang không dấu (`Str::ascii`), xóa ký tự đặc biệt, gộp khoảng trắng, cắt tối đa 25 ký tự.
  - `url(int $amount, string $noiDung): ?string`: Sinh URL ảnh `https://img.vietqr.io/image/...` nếu `enabled()` và `$amount > 0`; trả về `null` nếu chưa cấu hình hoặc tiền $\le 0$.
- Route `GET /pos/vietqr` (tên `pos.vietqr`): Lấy tổng thanh toán từ session giỏ hàng của thu ngân, gọi service sinh URL và trả về cho màn hình POS.

### 2.2. Webhook xác nhận thanh toán (Pha 13b)
- Dữ liệu:
  - Thêm `invoices.ma_giao_dich` (VARCHAR 40, UNIQUE, nullable).
  - Trạng thái hóa đơn hỗ trợ `cho_xac_nhan` (chờ tiền về) và `da_thanh_toan`.
  - Bảng `payment_webhooks`: `nha_cung_cap`, `ma_giao_dich_ngoai` (UNIQUE), `invoice_id`, `so_tien`, `ket_qua`, `created_at`.
- Quy trình:
  - Tiếp nhận tại `POST /api/webhook/thanhtoan`.
  - Xác thực secret/token đối tác.
  - Mở `DB::transaction()`: Kiểm tra `ma_giao_dich_ngoai` (chống xử lý trùng / idempotent). Khóa hóa đơn bằng `lockForUpdate()`. Đối soát số tiền.
  - Nếu hợp lệ: chuyển `da_thanh_toan`, trừ tồn kho, ghi movement và lưu nhật ký.

## 3. Kịch bản kiểm thử (T61 đến T68)
- **T61**: Tạo URL VietQR đúng định dạng với ngân hàng, số tài khoản, số tiền, template và nội dung.
- **T62**: Chưa cấu hình ngân hàng thì `url()` trả về `null`, POS thông báo chưa cấu hình.
- **T63**: Số tiền $\le 0$ thì `url()` trả về `null`.
- **T64**: Làm sạch nội dung chuyển khoản: tiếng Việt có dấu chuyển thành không dấu, xóa ký tự lạ, tối đa 25 ký tự.
- **T65**: Số tiền trên QR được lấy từ giỏ hàng server, không tin dữ liệu trình duyệt.
- **T66**: Webhook gửi trùng `ma_giao_dich_ngoai` chỉ xử lý một lần, lần sau trả 200 và ghi nhận trùng.
- **T67**: Webhook sai secret hoặc sai số tiền không đổi trạng thái hóa đơn, ghi log kết quả.
- **T68**: Webhook đến sau khi thu ngân đã xác nhận thủ công không được trừ kho lần hai.

## 4. Kế hoạch triển khai từng bước
- **Bước 1** ✅: Thêm `'qr_template' => env('POS_QR_TEMPLATE', 'compact2')` vào `config/pos.php`.
- **Bước 2** ✅: Xây dựng `App\Services\VietQrService` chuẩn hóa logic tạo QR và làm sạch nội dung.
- **Bước 3** ✅: Viết Unit Test `tests/Unit/VietQrServiceTest.php` kiểm tra toàn bộ ca T61 đến T65 (5/5 passed).
- **Bước 4** ✅: Thêm route `GET /pos/vietqr` và cập nhật view `pos/index.blade.php` để lấy QR an toàn từ server thay vì build client-side.
- **Bước 5** ✅: Tạo migration bảng `payment_webhooks` và cột `ma_giao_dich` trong `invoices`. Tạo Model `PaymentWebhook`. Cập nhật Model `DonHang` (sửa `ma_giaodich` → `ma_giao_dich`, thêm relationship `paymentWebhooks`).
- **Bước 6** ✅: Xây dựng `PaymentWebhookController` tại `POST /api/webhook/thanhtoan` và viết Feature Test T66–T68 (3/3 passed, 13 assertions).

## 5. Tổng kết Giai đoạn 4

**Trạng thái**: ✅ Hoàn thành toàn bộ 6 bước.

### File đã tạo mới
| File | Mô tả |
|------|-------|
| `app/Services/VietQrService.php` | Service tạo URL ảnh VietQR server-side |
| `app/Models/PaymentWebhook.php` | Model bảng log webhook thanh toán |
| `app/Http/Controllers/Api/PaymentWebhookController.php` | Controller nhận webhook, HMAC, idempotent, lockForUpdate |
| `routes/api.php` | Route API `POST /api/webhook/thanhtoan` |
| `database/migrations/2026_11_02_033147_add_ma_giao_dich_to_invoices_table.php` | Thêm cột `ma_giao_dich` vào invoices |
| `database/migrations/2026_11_02_033152_create_payment_webhooks_table.php` | Tạo bảng `payment_webhooks` |
| `tests/Unit/VietQrServiceTest.php` | Unit test T61–T65 (5 tests) |
| `tests/Feature/PaymentWebhookTest.php` | Feature test T66–T68 (3 tests) |

### File đã sửa
| File | Thay đổi |
|------|----------|
| `config/pos.php` | Thêm `qr_template`, `webhook_secret` |
| `bootstrap/app.php` | Đăng ký `routes/api.php` |
| `routes/web.php` | Thêm route `GET /pos/vietqr` |
| `app/Http/Controllers/DonHangController.php` | Thêm method `getVietQr()` trả JSON |
| `app/Models/DonHang.php` | Sửa fillable `ma_giao_dich`, thêm relationship `paymentWebhooks()` |
| `resources/views/pos/index.blade.php` | Refactor `capNhatQr()` từ client-side sang AJAX fetch server |

