# Quy tắc hiển thị & điền sẵn PII khách hàng (storefront)

Cập nhật: nhánh `fix/ux-an-ten-lien-ket-anh` (A1). Áp dụng cho mọi trang
phía khách (`resources/views/storefront/`, `dathang/`, `thongtincanhan/`).

## 1. Tên hiển thị — MỘT nguồn duy nhất

- `App\Support\TenHienThi::khachDangNhap()` là nguồn duy nhất cấp tên hiển
  thị của khách đang đăng nhập trên storefront (header, placeholder, v.v.).
  Luôn trả `ChePII::hoTen()` — **tên đã che**, kể cả sau khi khách xác thực
  lại hồ sơ. Header KHÔNG còn điều kiện theo route.
- Dữ liệu đầy đủ (họ tên, SĐT, email, địa chỉ) chỉ được hiển thị:
  - trong cửa sổ xác thực `HoSoXacThuc` ở trang hồ sơ (`profile.*`), hoặc
  - trong khu quản trị cho staff.
- Bình luận công khai trên bài viết dùng `ChePII::tenRutGon()` —
  "Tên + chữ cái đầu họ" (vd "An N."), không lộ họ tên đầy đủ nhưng vẫn
  nhận diện được người bình luận.
- Avatar/header dùng icon chung (`fa-user`), không chữ cái đầu tên thật.

## 2. Form liên hệ / trang thụ động — KHÔNG prefill

`LienHeController::getForm` không truyền `prefill` PII. Khách đăng nhập
vẫn phải nhập lại họ tên/SĐT/email; `postGui` gắn `user_id` khi lưu;
trang cảm ơn không in lại dữ liệu đã gửi.

## 3. Trang giao dịch — GIỮ prefill (ranh giới)

Form **đặt hàng online** (`dathang/form`) CỐ Ý giữ prefill họ tên/SĐT/email:
khách đang thực hiện giao dịch cho chính mình — bắt nhập lại sẽ gây ma sát
và dễ sai sót giao hàng. Đây là ngoại lệ có chủ đích so với trang thụ động.

Nguyên tắc phân loại:

| Loại trang | Prefill PII | Lý do |
|---|---|---|
| Liên hệ, tuyển dụng, trang tĩnh, tin tức, giỏ hàng, tra cứu | Không | Thụ động / không cần định danh sẵn |
| Đặt hàng online (checkout) | Có | Giao dịch của chính khách, cần chính xác |
| Hồ sơ khách | Chỉ trong cửa sổ xacthuc | Bảo vệ PII khi máy dùng chung |

Nếu sau này thêm form giao dịch khác (vd đổi trả online), áp cùng nguyên
tắc: trang "thụ động" không prefill; trang "giao dịch xác nhận" được prefill.
