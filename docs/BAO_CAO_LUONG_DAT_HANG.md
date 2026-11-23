# Báo cáo hiện trạng luồng đặt hàng storefront (guest + khách đăng nhập)

- Nhánh: `docs/thiet-ke-dat-hang` (từ `fix/anh-url-validate`). Đợt này **chỉ đọc mã nguồn và viết tài liệu**: không sửa code ứng dụng, không migration, không sửa test hiện có, không merge/push.
- Ngày khảo sát: 2026-11-12. Môi trường: PHP 8.4.15, Laravel 12, PHPUnit 11.5.56.
- Bằng chứng chạy thật: test thăm dò tạm `DatHangProbeTest` (14 test, 196 assertions, **OK**, ~1,1 s) trên SQLite `:memory:` theo `phpunit.xml`; file tạm nằm ngoài repo, **không commit**. Schema DB thật `doan_cnw` đọc qua `information_schema` (chỉ SELECT).
- Thiết kế đề xuất: [THIET_KE_DAT_HANG_ONLINE.md](THIET_KE_DAT_HANG_ONLINE.md).

## 1. Kết luận nhanh

1. **Storefront hiện KHÔNG có luồng đặt hàng.** Nút "Thanh toán" trong giỏ bị `disabled` với title "Thanh toán — sắp ra mắt" ([cart.blade.php:74-77](../resources/views/storefront/cart.blade.php#L74-L77)). Không tồn tại route nào tạo đơn từ storefront: toàn bộ 9 route `storefront.*` chỉ là xem sản phẩm + giỏ hàng session.
2. **Mọi route tạo đơn/thanh toán đều là route nhân viên** (`auth` + `staff.only` + `role:admin,thungan`). Guest gọi → 302 về `/dangnhap` (hoặc 401 nếu client gửi `Accept: application/json`); khách role `khachhang` → 302 về trang chủ kèm "Trang này dành cho nhân viên."; không đơn nào được tạo, không kho nào bị trừ (đã chứng minh bằng probe).
3. **Giỏ hàng chỉ là hiển thị + session**, chưa phải bước checkout: giá trong giỏ là snapshot lúc thêm/cập nhật, trang giỏ không đối chiếu lại DB. Có một số lỗ hổng mức vừa/thấp liệt kê ở mục 6.
4. **Liên kết SĐT khi đăng ký là KHÔNG xác minh**: ai đăng ký bằng SĐT của khách lẻ có sẵn sẽ chiếm luôn hồ sơ + điểm tích lũy của khách đó ([AuthController.php:109-132](../app/Http/Controllers/AuthController.php#L109-L132)). Đây là rủi ro cao nhất cần xử lý trước khi cho đặt hàng online gắn điểm.
5. Phần lớn hạ tầng đơn hàng (giữ kho, QR, webhook, hủy quá hạn, đối soát, in hóa đơn, trả hàng) **tái sử dụng được gần như nguyên vẹn** — chi tiết trong tài liệu thiết kế.

## 2. Bản đồ route liên quan (bằng chứng audit middleware)

Nguồn: [routes/web.php](../routes/web.php), [bootstrap/app.php:14-24](../bootstrap/app.php#L14-L24). Probe đã duyệt toàn bộ `Route::getRoutes()` và assert mọi route `donhang./pos./hold./giohang./khachhang./trahang.` đều chứa `auth`.

| Nhóm | Route | Middleware | Guest | khachhang | admin/thungan |
|---|---|---|---|---|---|
| Storefront | `GET /`, `/danh-muc/{loai}`, `/san-pham/{sp}`, `/tim-kiem` ([web.php:7-10](../routes/web.php#L7-L10)) | `web` | 200 | 200 | 200 |
| Giỏ storefront | `GET /gio-hang`, `GET /gio-hang/them/{sp}`, `PUT /gio-hang/cap-nhat`, `GET /gio-hang/xoa/{sp}`, `DELETE /gio-hang/lam-moi` ([web.php:12-18](../routes/web.php#L12-L18)) | `web` (không auth) | dùng được | dùng được | dùng được |
| POS | `GET /pos`, `GET /pos/vietqr` ([web.php:51-52](../routes/web.php#L51-L52)) | `auth,staff.only,role:admin,thungan` | 302 `/dangnhap` | 302 `/` + error | 200 |
| Tạo đơn QR | `POST /donhang/taoqr` ([web.php:53](../routes/web.php#L53)) | như trên | 302 | 302 | 200 JSON |
| Polling | `GET /donhang/trangthai/{don}` ([web.php:54](../routes/web.php#L54)) | như trên | 302 | 302 | 200 JSON |
| Xác nhận/hủy | `POST /donhang/xacnhan/{don}`, `POST /donhang/huycho/{don}` ([web.php:55-56](../routes/web.php#L55-L56)) | như trên | 302 | 302 | 200 JSON |
| Thanh toán POS | `POST /donhang/thanhtoan` ([web.php:69-70](../routes/web.php#L69-L70)) | như trên | 302 | 302 | tạo đơn |
| Danh sách/in | `GET /donhang`, `GET /donhang/inhoadon/{don}` ([web.php:71-74](../routes/web.php#L71-L74)) | như trên | 302 | 302 | 200 |
| Giỏ POS | `/giohang/*` ([web.php:61-68](../routes/web.php#L61-L68)) | như trên | 302 | 302 | dùng được |
| Khách hàng | `/khachhang/*` ([web.php:75-90](../routes/web.php#L75-L90)) | như trên | 302 | 302 | dùng được |
| Webhook | `POST /api/webhook/thanhtoan` ([web.php:34-37](../routes/web.php#L34-L37)) | `throttle:60,1`, miễn CSRF ([bootstrap/app.php:15-17](../bootstrap/app.php#L15-L17)), chắn API-key trong controller | 401 nếu không có key | 401 | 401 nếu không key |

Cơ chế chặn:

- Guest: `redirectGuestsTo(fn () => route('auth.dangnhap'))` ([bootstrap/app.php:19](../bootstrap/app.php#L19)) → 302; riêng client JSON nhận 401 `Unauthenticated` (probe ghi cả hai biến thể).
- `khachhang`: `staff.only` = [RedirectKhachhangFromStaff.php:15-19](../app/Http/Middleware/RedirectKhachhangFromStaff.php#L15-L19) → redirect `storefront.home` + flash error "Trang này dành cho nhân viên."
- Sai role nhân viên (vd thukho gọi route thu ngân): [CheckRole.php:15-17](../app/Http/Middleware/CheckRole.php#L15-L17) → 403 "Bạn không có quyền truy cập chức năng này."

**Khẳng định đã kiểm chứng:** không có route tạo đơn từ storefront. Probe assert danh sách tên route `storefront.*` đúng bằng 9 route xem/giỏ ở trên; gọi chéo mọi route đơn hàng với giỏ storefront đầy vẫn không tạo được đơn nào (`DonHang::count()` không đổi, tồn kho không đổi).

## 3. Luồng khách vãng lai (chưa đăng nhập) — kết quả chạy thật

| Bước | Thao tác | Kết quả probe | Bằng chứng |
|---|---|---|---|
| 1 | `GET /` | 200 | evidence dòng 2 |
| 2 | `GET /san-pham/{id}` (SP đang bán) | 200 | [StorefrontController.php:69-85](../app/Http/Controllers/StorefrontController.php#L69-L85) |
| 3 | `GET /gio-hang/them/{id}` | 302 về `/gio-hang`, flash success; SP ẩn/hết hàng → error; SP không tồn tại → 404 | [StorefrontCartController.php:27-54](../app/Http/Controllers/StorefrontCartController.php#L27-L54) |
| 4 | `GET /gio-hang` | 200, hiện tên SP + tổng tiền từ session | [StorefrontCartController.php:19-25](../app/Http/Controllers/StorefrontCartController.php#L19-L25) |
| 5 | Bấm "Thanh toán" | **Không bấm được** — nút `disabled`, title "Thanh toán — sắp ra mắt" | [cart.blade.php:74-77](../resources/views/storefront/cart.blade.php#L74-L77) |
| 6 | Gọi thẳng `POST /donhang/thanhtoan`, `POST /donhang/taoqr`, `GET /pos/vietqr`, `GET/POST /donhang/*`, `/pos`, `/khachhang`, `/tongquan`, `/giohang/them` | Tất cả 302 → `/dangnhap` (browser) / 401 (JSON). Không đơn, không trừ kho | evidence dòng 10-35 |
| 7 | `POST /api/webhook/thanhtoan` không key / key sai | 401 | [WebhookController.php:31-35](../app/Http/Controllers/WebhookController.php#L31-L35), [SepayWebhook.php:13-36](../app/Services/Payment/SepayWebhook.php#L13-L36) |

Session giỏ (`storefront_cart`) chứa đúng 7 khóa mỗi dòng: `id, sku, ten_sanpham, hinh_anh, gia_ban (float), soluong, ton_kho` — chụp từ DB lúc thêm ([StorefrontCartController.php:41-49](../app/Http/Controllers/StorefrontCartController.php#L41-L49)). Không có trường giá nào được nhận từ request.

## 4. Luồng khách đã đăng nhập (role `khachhang`)

- Giỏ hàng: thêm/xem/sửa/xóa hoạt động y hệt guest (probe: 200/302, session đúng). **Không có trang tài khoản, không có lịch sử đơn, không có route đặt hàng/thanh toán nào cho khách** — đăng nhập xong chỉ khác guest ở chỗ bị chặn khỏi trang nhân viên bằng redirect thay vì bắt đăng nhập.
- Đăng ký ([AuthController.php:77-143](../app/Http/Controllers/AuthController.php#L77-L143)): validate `name/email/so_dienthoai (regex ^0\d{9,10}$)/password confirmed`; `role` từ client bị `unset` (:98), luôn tạo `khachhang` (:106) — probe thử nhét `role=admin` vẫn ra `khachhang`.
- **Liên kết SĐT (không xác minh)** — probe 3 kịch bản:
  1. SĐT đã có hồ sơ khách lẻ (50 điểm): tài khoản mới **chiếm liên kết** `customers.user_id`, giữ nguyên 50 điểm, `ho_ten` bị ghi đè tên người đăng ký (:129-132). Không OTP, không email xác nhận.
  2. SĐT đã liên kết tài khoản khác: `ValidationException` "Số điện thoại đã được liên kết với tài khoản khác." (:123-127), transaction rollback — user thứ hai không được tạo.
  3. SĐT mới: tạo `customers` mới `diem_tichluy=0` (:111-121).
- Sau liên kết, dữ liệu khách "thấy" được: **không gì cả** — không có UI nào hiển thị hồ sơ/điểm/đơn cho role `khachhang`. Điểm chỉ tồn tại trong bảng `customers` và được thu ngân dùng ở POS.

## 5. Đối chiếu server của giỏ hàng (khi hiển thị / khi đổi số lượng)

| Tình huống | Hành vi thật (probe) | Nhận xét |
|---|---|---|
| Hiển thị giỏ (`index`) | Tính tổng từ `gia_ban` **trong session**, không fetch lại DB (:22) | Giá/tồn có thể cũ |
| Thêm (`add`) | Chặn `trang_thai=false` hoặc `soluong<1` (:29); mỗi lần +1, vượt tồn → error (:37-39) | Không có tham số số lượng từ client |
| Sửa (`update`) | Validate `soluong.* integer min:1` (**không có max**); fetch lại SP, `qty > soluong` → error; refresh `ton_kho` + `gia_ban` (:73-80); **không kiểm tra lại `trang_thai`** | Xem lỗ hổng L2/L3 |
| SP ẩn sau khi thêm | `update` vẫn thành công (probe tăng lên 3) | L3 |
| SP bị xóa sau khi thêm | `update` báo lỗi chung; `index` vẫn hiển thị SP đã xóa | L2 |
| Giá DB đổi sau khi thêm | `index` vẫn tính giá cũ; chỉ `update` mới fetch giá mới | L2 |
| Nhét `gia_ban=1`, `tong_tien=1` vào request sửa giỏ | Bị bỏ qua; session vẫn 150.000 từ DB | Không tamper được giá |
| `soluong` = 0, -3, "abc", 1.5 | 302 + lỗi validation tiếng Việt, giỏ không đổi | Đạt |
| `soluong` = 999.999 / 999.999.999.999 | 302 + error vượt tồn, giỏ không đổi | Đạt (chặn bằng tồn, không bằng max) |
| SP không tồn tại / SP ẩn | `them/999999` → 404; `san-pham/{SP ẩn}` → 404 ([StorefrontController.php:71](../app/Http/Controllers/StorefrontController.php#L71)) | Đạt |
| CSRF | `PUT /gio-hang/cap-nhat`, `DELETE /gio-hang/lam-moi`, `POST /donhang/thanhtoan` không token → **419**; `GET /gio-hang/them/{id}` không token vẫn đổi giỏ | GET thêm/xóa là state-changing, không CSRF |

## 6. Danh sách lỗ hổng / rủi ro của giỏ + luồng khách

| Mã | Mức | Mô tả | Bằng chứng |
|---|---|---|---|
| L1 | Cao | **Chiếm hồ sơ khách lẻ qua SĐT không xác minh**: đăng ký bằng SĐT bất kỳ của khách walk-in → sở hữu điểm tích lũy + lịch sử mua của họ. Nguy hiểm hơn khi đặt hàng online gắn điểm/hồ sơ. | [AuthController.php:109-132](../app/Http/Controllers/AuthController.php#L109-L132); probe DANGKY |
| L2 | Vừa | **Giỏ hiển thị dữ liệu cũ**: `index` không đối chiếu DB — giá cũ, tồn cũ, SP đã xóa/ẩn vẫn hiện và vẫn tính tiền. Nếu checkout sau này đọc thẳng session sẽ bán sai giá. | [StorefrontCartController.php:19-25](../app/Http/Controllers/StorefrontCartController.php#L19-L25); probe EDGE giá cũ |
| L3 | Vừa | `update` không kiểm tra lại `trang_thai`: SP ngừng kinh doanh vẫn tăng số lượng trong giỏ được. | [StorefrontCartController.php:73-80](../app/Http/Controllers/StorefrontCartController.php#L73-L80); probe LO HONG |
| L4 | Thấp | **Thêm/xóa giỏ bằng GET không CSRF**: attacker nhúng `<img src="/gio-hang/them/{id}">` để nhét hàng vào giỏ nạn nhân (phiền, chưa mất tiền vì chưa có checkout); crawler/prefetch cũng có thể tự thêm giỏ. | probe CSRF; [web.php:14,16](../routes/web.php#L14) |
| L5 | Thấp | `soluong.*` không có `max`: số cực lớn bị chặn bởi tồn kho chứ không bởi luật validate; nên thêm `max` tường minh khi làm checkout. | [StorefrontCartController.php:58-64](../app/Http/Controllers/StorefrontCartController.php#L58-L64) |
| L6 | Thấp | Không throttle cho thêm giỏ/đăng ký (đăng nhập có `throttle:10,1`). Spam giỏ chỉ tốn session, nhưng khi có checkout phải throttle đặt hàng. | [web.php:23-25](../routes/web.php#L23-L25) |
| L7 | Vừa (vận hành) | **DB thật không có khóa ngoại nào** (0 FK trên MySQL 8.4.7): bảng gốc tạo thời MyISAM nên định nghĩa FK bị bỏ qua, migration InnoDB ([2026_11_10_000003](../database/migrations/2026_11_10_000003_chuyen_bang_mysql_sang_innodb.php)) chỉ đổi engine không tạo lại FK. Transaction/`lockForUpdate` hiện đã có tác dụng (InnoDB), nhưng toàn vẹn tham chiếu chỉ nhờ code. Đơn online tăng nguy cơ bản ghi mồ côi nếu code mới ẩu. | information_schema: `REFERENTIAL_CONSTRAINTS` = 0 dòng; index `*_foreign` vẫn còn |
| L8 | Lưu ý | `customers.dia_chi` DB thật là **VARCHAR(150)** trong khi migration khai báo 255 — cùng dạng lệch migration/DB thật như `products.hinh_anh` đã sửa ở pha trước. Thiết kế địa chỉ nhận hàng phải theo 150. | information_schema; [migration customers:15](../database/migrations/2026_01_01_000006_create_customers_table.php#L15) |
| L9 | Lưu ý | `ThongBaoService` ghép `ghiChu` thẳng vào tin Telegram `parse_mode=HTML` — ghi chú đơn online do khách nhập có thể chèn HTML/link vào tin nhắn nhân viên. Phải escape trước khi truyền. | [ThongBaoService.php:64-74](../app/Services/ThongBaoService.php#L64-L74) |

Điểm đã tốt (giữ nguyên khi làm tiếp): không nhận giá từ client; chặn tồn kho cả thêm lẫn sửa; 404 tiếng Việt; CSRF cho PUT/DELETE/POST; webhook chắn API-key `hash_equals` + idempotent theo mã giao dịch; đăng ký không leo thang role được.

## 7. Hạ tầng tái sử dụng được (tóm tắt — chi tiết file:dòng trong tài liệu thiết kế)

| Thành phần | Vị trí | Dùng lại cho đơn online |
|---|---|---|
| Giữ kho khi tạo đơn chờ + hoàn kho khi hủy + hoàn tất idempotent | [ThanhToanChuyenKhoanService.php](../app/Services/ThanhToanChuyenKhoanService.php) (`taoHoaDonChoThanhToan` :22-125, `hoanTatThanhToan` :135-218, `huyChoThanhToan` :228-304) | Nguyên vẹn, chỉ cần nguồn giỏ khác (session storefront) và `user_id` cho đơn guest |
| Sinh mã `HD{Ymd}-{XXXX}` có khóa | Service :309-320; [DonHangController.php:322-333](../app/Http/Controllers/DonHangController.php#L322-L333) | Giữ nguyên — webhook parse theo mẫu này |
| QR VietQR | [VietQrService.php:46-74](../app/Services/VietQrService.php#L46-L74); nội dung làm sạch ≤25 ký tự :33-40 (mã HD 14 ký tự vừa) | Nguyên vẹn |
| Webhook SePay + đối soát | [WebhookController.php](../app/Http/Controllers/WebhookController.php) (thiếu tiền :112-125, dư/trễ hạn :127-141, hàng chờ đối soát :184-200); [SepayWebhook.php:71-80](../app/Services/Payment/SepayWebhook.php#L71-L80) | Nguyên vẹn nếu đơn online nằm chung bảng `invoices` |
| Hủy quá hạn | [HuyHoaDonQuaHan.php:16-44](../app/Console/Commands/HuyHoaDonQuaHan.php#L16-L44) chạy **mỗi phút** ([console.php:9](../routes/console.php#L9)); hủy lười khi polling [DonHangController.php:97-109](../app/Http/Controllers/DonHangController.php#L97-L109) | Cần thêm hạn giữ theo đơn (cột `han_giu`) nếu đơn pickup giữ lâu hơn 15 phút |
| Thông báo Telegram | [ThongBaoService.php](../app/Services/ThongBaoService.php) (tự tắt khi chưa cấu hình :21-23, timeout 5s, che token :54-59) | Thêm mẫu tin "đơn online mới"; escape ghi chú (L9) |
| Nhật ký | [NhatKyService.php:10-25](../app/Services/NhatKyService.php#L10-L25) | Ghi đặt hàng/xác nhận/hủy; không log PII đầy đủ |
| In hóa đơn | [DonHangController.php:178-192](../app/Http/Controllers/DonHangController.php#L178-L192) (chỉ `da_thanh_toan`) | Dùng được sau khi đơn online hoàn tất |
| Trả hàng | `returns.invoice_id` ([migration returns:15](../database/migrations/2026_01_01_000014_create_returns_tables.php#L15)) | Chỉ chạy nếu đơn online là bản ghi `invoices` |
| Cấu hình | [config/pos.php](../config/pos.php): `cho_thanh_toan_phut=15` :12, điểm :6-7, key SePay :13, Telegram :14-15 | Thêm khóa hạn giữ online riêng |

## 8. Ảnh hưởng tới báo cáo nếu thêm đơn online

| Báo cáo | Lọc trạng thái | Đơn `cho_thanh_toan`/`da_huy` online có làm lệch? |
|---|---|---|
| Doanh thu/lợi nhuận ([ThongKeController.php:48,60-71](../app/Http/Controllers/ThongKeController.php#L48)) | chỉ `da_thanh_toan`, trừ phiếu trả `hoan_thanh` | **Không** |
| Top sản phẩm ([ThongKeController.php:90](../app/Http/Controllers/ThongKeController.php#L90)) | chỉ `da_thanh_toan` | **Không** |
| Xuất hóa đơn xlsx/pdf ([HoaDonExport.php:30](../app/Exports/HoaDonExport.php#L30)) | chỉ `da_thanh_toan` | **Không** |
| Dashboard tổng quan ([TongQuanKhoService.php:36](../app/Services/TongQuanKhoService.php#L36)) | `so_hoa_don = DonHang::count()` — **mọi trạng thái** | **Có**: đơn chờ/hủy online sẽ đội số "hóa đơn"; cần lọc `kenh`/`trang_thai` khi triển khai |
| Tồn kho dashboard ([TongQuanKhoService.php:20-27](../app/Services/TongQuanKhoService.php#L20-L27)) | theo `products.soluong` | Phản ánh hàng đang giữ (vì giữ kho trừ ngay) — đúng bản chất, cần chú thích "đã gồm hàng giữ cho đơn chờ" |
| Danh sách đơn POS ([DonHangController.php:152-176](../app/Http/Controllers/DonHangController.php#L152-L176)) | không lọc | Đơn online sẽ lẫn vào danh sách POS — cần lọc/badge `kenh` |

## 9. Schema thật đối chiếu migration (chỉ đọc `information_schema`, DB `doan_cnw`)

MySQL 8.4.7, tất cả bảng liên quan đã InnoDB. **0 khóa ngoại** (xem L7). Số đơn thật: 4 `da_thanh_toan`, 1 `da_huy`, 0 `cho_thanh_toan`.

- `invoices`: `ma_hoadon varchar(30) unique`; **`user_id bigint NOT NULL`** (FK khai báo trong migration nhưng DB thật không có constraint); `customer_id` nullable; `phuong_thuc enum('tienmat','chuyenkhoan') default 'tienmat'`; **`trang_thai enum('cho_thanh_toan','da_thanh_toan','da_huy') default 'da_thanh_toan'`**; `ma_giaodich varchar(100) unique` + `ma_giao_dich varchar(40) unique` (hai cột cùng mục đích, service ghi cả hai); tiền `decimal(12,0)`; `ghi_chu text`; index `(trang_thai,created_at)`.
- `invoice_details`: `invoice_id`, `product_id` NOT NULL; `soluong int unsigned`; `don_gia/gia_von/thanh_tien decimal(12,0)`; `unique(invoice_id,product_id)` (migration).
- `customers`: `user_id` nullable unique; `ho_ten varchar(100)`; `so_dienthoai varchar(15) unique`; **`dia_chi varchar(150)` thật** (migration 255 — L8); `diem_tichluy int unsigned default 0`.
- `payment_webhooks`: `ma_giao_dich_ngoai varchar(80) unique`; `invoice_id` nullable; `so_tien bigint unsigned`; `ket_qua varchar(20)`; `payload json`; `trang_thai_xu_ly varchar(20) default 'chua_xu_ly'`; `nguoi_xu_ly_id` nullable; index `(trang_thai_xu_ly,created_at)`.
- `users`: `role enum('admin','thungan','thukho','khachhang') default 'khachhang'`; `email varchar(150)`.

Hệ quả thiết kế chính: (a) thêm trạng thái mới phải `ALTER ENUM` trên MySQL thật — rủi ro cần backup; (b) `user_id NOT NULL` là trở ngại lớn nhất cho đơn guest nếu dùng lại bảng `invoices`; (c) mọi so sánh trạng thái hiện dùng string tự do, không có hằng số tập trung.

## 10. Phụ lục — test thăm dò tạm (không commit)

- File: `<thư-mục-người-dùng>\AppData\Local\Temp\dat-hang-probe\DatHangProbeTest.php` + `evidence.txt` (120 dòng bằng chứng).
- Chạy từ gốc `DA`: `XDEBUG_MODE=off php vendor/bin/phpunit <thư-mục-người-dùng>/AppData/Local/Temp/dat-hang-probe/DatHangProbeTest.php`
- Kết quả: **OK (14 tests, 196 assertions)**, PHP 8.4.15, ~1,1 s, SQLite `:memory:` (theo `phpunit.xml`, không chạm DB thật).
- Phủ: guest/khachhang × 12 route đơn hàng (browser 302 + JSON 401), webhook 401, đối chứng thungan tạo đơn tiền mặt + QR + xác nhận + hủy hoàn kho, đăng ký liên kết SĐT 3 kịch bản + leo thang role, 6 edge số lượng, tamper giá, giá cũ, SP ẩn/xóa sau khi thêm, CSRF 419, audit middleware toàn bộ route.
- Lưu ý môi trường: test nạp cấu hình VietQR từ env nên `url_qr` khác null (số tài khoản nhận là công khai trên QR; báo cáo này không in lại). Không đọc/ghi `.env` trực tiếp.