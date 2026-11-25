# Bộ test case toàn hệ thống (keytest)

Bộ kịch bản kiểm thử dùng cho báo cáo đồ án và buổi bảo vệ. File Excel:
`TestCase_HeThong.xlsx` — **các cột "KẾT QUẢ THỰC TẾ" và "TRẠNG THÁI" cố tình
để trống** để người kiểm thử điền tay khi chạy kịch bản (không điền hộ, không
bịa kết quả).

## Cấu trúc file

- **16 sheet module** (01–16): Storefront khách; Tài khoản & hồ sơ khách; Đặt
  hàng online & nhận hàng; POS; Trả hàng; Kho/nhập hàng/kiểm kê; Sản phẩm/loại/
  hãng; Khách hàng & điểm; Tin tức & bình luận; Liên hệ & tuyển dụng;
  Thống kê/báo cáo/nhật ký; Import/Export Excel (gồm ảnh URL); PIN & mã duyệt;
  Xác nhận thủ công & đối soát; Bảo mật chung; SePay/VietQR.
- **Truy vết**: mọi route hệ thống → module → mức PIN (`config/pin_muc.php`) →
  test tự động **thực sự gọi tới** (route() theo tên, URI khớp, hoặc class
  App\ cùng module — trích bằng `scripts/kiem-thu/trich-test-refs.php`).
- **Khoảng trống**: route mà KHÔNG file test nào gọi `route('tên')` hay URI
  tương ứng — phương pháp: quét `route()`, `->get/post/put/delete(...)` trong
  `tests/` rồi khớp tên route + regex URI (tham số `{x}` → `[^/]+`).
- **Ma trận quyền**: module × Guest/Admin/Thu ngân/Thủ kho/Khách — suy ra từ
  middleware `role:`/`auth`/`staff.only` trong `route:list -v` (không suy đoán).
- **Kịch bản demo**: 25 bước demo bảo vệ ánh xạ sang mã TC (không chứa
  mật khẩu/PIN/URL nội bộ).
- **Chạy tay trước bảo vệ**: các TC ưu tiên Cao chia nhóm A (chỉ đọc, an toàn
  trên dữ liệu thật) và B (có ghi — cảnh báo lưu vĩnh viễn vào `doan_cnw`).
- **Tổng hợp**: số TC theo module + thống kê độ tin cậy ánh xạ.

Mỗi TC có mã duy nhất dạng `MOD-001`…; cột Loại phân loại: chức năng /
phân quyền / bảo mật / validate / biên. Cột **"Độ tin cậy ánh xạ"** chấm mức
chắc chắn của test tự động tương ứng:

- **xác nhận** (xanh): test gọi route()/URI của module hoặc class App\ cùng
  module — có bằng chứng test chạm đúng đối tượng.
- **từ khóa** (vàng): test tồn tại và tên khớp chủ đề nhưng chưa chứng minh
  gọi đúng route/hành vi — kiểm tra thủ công khi dùng cho báo cáo.
- **không có** (đỏ): chưa có test tự động — bắt buộc kiểm thử tay.

Mỗi TC có mã duy nhất dạng `MOD-001`…; cột Loại phân loại: chức năng /
phân quyền / bảo mật / validate / biên. Dữ liệu kiểm thử trong file đều là dữ
liệu GIẢ — không chứa PIN/mật khẩu/hash/PII thật.

## Cách điền kết quả

1. Mở sheet module, lần lượt thực hiện "Các bước" trên môi trường thật.
2. Ghi kết quả vào "KẾT QUẢ THỰC TẾ" (mô tả ngắn những gì quan sát được) và
   chọn "Đạt / Không đạt / Không kiểm được" ở "TRẠNG THÁI".
3. Với TC có "Test tự động tương ứng", có thể đối chiếu bằng cách chạy test đó.

## Chạy test tự động

```powershell
php artisan test            # toàn bộ (sqlite :memory:, không ghi DB thật)
php artisan test --filter=TenTestClass
```

## Tái tạo file Excel

```powershell
php scripts/kiem-thu/sinh-xlsx.php
```

Script đọc `scripts/kiem-thu/data-tc.php` (dữ liệu TC), `php artisan
route-list -v`, `config/pin_muc.php` và danh sách `tests/Feature|Unit` cùng `storage/app/test-refs.json`
(route/URI/class mỗi file test gọi tới — tự chạy `trich-test-refs.php` nếu thiếu).
Sửa TC thì sửa `data-tc.php` rồi chạy lại — cột KẾT QUẢ THỰC TẾ/TRẠNG THÁI
sẽ bị ghi đè (file là sản phẩm sinh tự động).

## Hạn chế đã biết

- **SePay/VietQR chưa kiểm thử với dịch vụ thật** — webhook/đối soát mới kiểm
  thử qua fake/mock; cần 1 giao dịch chuyển khoản thật nhỏ để xác nhận
  đầu-cuối.
- **Thuế bán hàng chưa tách VAT** — hệ thống chưa có khái niệm thuế.
- **Giao tận nơi đang tắt** (`POS_GIAO_TAN_NOI_BAT=false`) — luồng giao hàng
  chỉ mới có khung, chưa vận hành.
- **Chưa có quên mật khẩu qua email** — chỉ đổi MK khi đã đăng nhập hoặc admin
  đặt lại; không có flow reset qua email.
- Cột "Test tự động tìm thấy" trong Truy vết là khớp từ khóa tự động — có thể
  sót hoặc thừa; kiểm tra thủ công khi dùng cho báo cáo.
- Một số TC "Test tự động tương ứng" ghi tên lớp ở mức lớp (không ghi từng
  hàm) để tránh bám quá sát refactor.
