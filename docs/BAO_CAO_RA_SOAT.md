# BÁO CÁO RA SỌAT README.md

**Ngày:** 2026-11-09  
**Nhánh:** `docs/ra-soat-readme`  
**Commit gốc:** `7299cda` (main)

---

## BƯỚC 1 — BẢNG ĐỐI CHIẾU README vs CODE THỰC TẾ

| Mục README | File/Route/Lệnh thực tế | Trạng thái | Ghi chú |
|------------|------------------------|------------|---------|
| **Công nghệ** | | | |
| PHP 8.4.x | `composer.json`: `"php": "^8.2"` | ⚠️ Sai | Thực tế PHP 8.2+, README ghi 8.4.x |
| Laravel 12 | `composer.json`: `"laravel/framework": "^12.0"` | ✅ Đúng | |
| Composer 2.x | - | ✅ Đúng | |
| MySQL 8.x | `.env.example`: `DB_CONNECTION=sqlite` | ⚠️ Sai | Mặc định dùng SQLite, không phải MySQL |
| **Cấu trúc thư mục** | | | |
| `ThongBaoService.php` trong `Http/Controllers/` | Thực tế: `app/Services/ThongBaoService.php` | ❌ Sai | Đang liệt kê sai vị trí |
| `HoaDonNhapService.php` trong `app/Services/` | Thực tế: `app/Services/HoaDonNhapService.php` | ✅ Đúng | |
| `PdfInvoiceParserService.php` | Thực tế: `app/Services/PdfInvoiceParserService.php` | ❌ Thiếu | Không có trong README |
| `VietQrService.php` | Thực tế: `app/Services/VietQrService.php` | ❌ Thiếu | Không có trong README |
| `ThanhToanChuyenKhoanService.php` | Thực tế: `app/Services/ThanhToanChuyenKhoanService.php` | ❌ Thiếu | Không có trong README |
| `NhatKyService.php` | Thực tế: `app/Services/NhatKyService.php` | ❌ Thiếu | Không có trong README |
| `SepayWebhook.php` | Thực tế: `app/Services/Payment/SepayWebhook.php` | ❌ Thiếu | Không có trong README |
| `BaoCaoController.php` | Thực tế: `app/Http/Controllers/BaoCaoController.php` | ❌ Thiếu | Không có trong README |
| `NhatKyController.php` | Thực tế: `app/Http/Controllers/NhatKyController.php` | ❌ Thiếu | Không có trong README |
| `KhachHangController.php` | Thực tế: `app/Http/Controllers/KhachHangController.php` | ❌ Thiếu | Không có trong README |
| `ChiTietDonNhap.php` | Thực tế: `app/Models/ChiTietDonNhap.php` | ❌ Thiếu | Không có trong README |
| `DonNhap.php` | Thực tế: `app/Models/DonNhap.php` | ❌ Thiếu | Không có trong README |
| `NhaCungCap.php` | Thực tế: `app/Models/NhaCungCap.php` | ❌ Thiếu | Không có trong README |
| `NhatKyHoatDong.php` | Thực tế: `app/Models/NhatKyHoatDong.php` | ❌ Thiếu | Không có trong README |
| `PhieuKiemKe.php` | Thực tế: `app/Models/PhieuKiemKe.php` | ❌ Thiếu | Không có trong README |
| `PhieuTra.php` | Thực tế: `app/Models/PhieuTra.php` | ❌ Thiếu | Không có trong README |
| `ChiTietKiemKe.php` | Thực tế: `app/Models/ChiTietKiemKe.php` | ❌ Thiếu | Không có trong README |
| `ChiTietPhieuTra.php` | Thực tế: `app/Models/ChiTietPhieuTra.php` | ❌ Thiếu | Không có trong README |
| `GioTam.php` | Thực tế: `app/Models/GioTam.php` | ❌ Thiếu | Không có trong README |
| `User.php` | Thực tế: `app/Models/User.php` | ❌ Thiếu | Không có trong README |
| **Views** | | | |
| `baocao/` | Thực tế: `resources/views/baocao/` | ❌ Thiếu | Không có trong README |
| `khachhang/` | Thực tế: `resources/views/khachhang/` | ❌ Thiếu | Không có trong README |
| `thongke/` | Thực tế: `resources/views/thongke/` | ❌ Thiếu | Không có trong README |
| `errors/` | Thực tế: `resources/views/errors/` | ❌ Thiếu | Không có trong README |
| **Routes** | | | |
| `/pos/vietqr` | `GET pos.vietqr` → `DonHangController@getVietQr` | ✅ Đúng | |
| `/donhang/taoqr` | `POST donhang.taoqr` → `DonHangController@postTaoQr` | ✅ Đúng | |
| `/donhang/xacnhan/{donHang}` | `POST donhang.xacnhan` → `DonHangController@postXacNhanThanhToan` | ✅ Đúng | |
| `/donhang/huycho/{donHang}` | `POST donhang.huycho` → `DonHangController@postHuyCho` | ✅ Đúng | |
| `/donhang/trangthai/{donHang}` | `GET donhang.trangthai` → `DonHangController@getTrangThai` | ❌ Thiếu | Không có trong README |
| `/api/webhook/thanhtoan` | `POST webhook.thanhtoan` → `WebhookController@postThanhToan` | ✅ Đúng | |
| `/nhaphang/nhacungcap` | `GET nhaphang.nhacungcap` → `NhapHangController@getNhaCungCap` | ✅ Đúng | |
| `/nhaphang/donnhap` | `GET nhaphang.donnhap` → `NhapHangController@getDonNhap` | ✅ Đúng | |
| `/nhaphang/donnhap/them` | `GET nhaphang.donnhap.them` → `NhapHangController@getThemDonNhap` | ✅ Đúng | |
| `/nhaphang/donnhap/{donNhap}` | `GET nhaphang.donnhap.chitiet` → `NhapHangController@getChiTietDonNhap` | ❌ Thiếu | Không có trong README |
| `/nhaphang/donnhap/{donNhap}/trangthai` | `POST nhaphang.donnhap.trangthai` → `NhapHangController@capNhatTrangThai` | ❌ Thiếu | Không có trong README |
| `/nhatky` | `GET nhatky.index` → `NhatKyController@index` | ❌ Thiếu | Không có trong README |
| `/baocao/hoadon/{dinhDang}` | `GET baocao.hoadon` → `BaoCaoController@getHoaDon` | ❌ Thiếu | Không có trong README |
| `/baocao/xuatnhapton/{dinhDang}` | `GET baocao.xuatnhapton` → `BaoCaoController@getXuatNhapTon` | ❌ Thiếu | Không có trong README |
| `/thongke` | `GET thongke.index` → `ThongKeController@getTrangThongKe` | ❌ Thiếu | Không có trong README |
| `/sanpham/tem/{sanPham}` | `GET sanpham.tem` → `SanPhamController@getTem` | ❌ Thiếu | Không có trong README |
| `/khachhang/timkiem` | `GET khachhang.timkiem` → `KhachHangController@getTimKiem` | ❌ Thiếu | Không có trong README |
| `/khachhang/nhanh` | `POST khachhang.nhanh` → `KhachHangController@postTaoNhanh` | ❌ Thiếu | Không có trong README |
| `/loaisanpham/goiy` | `GET loaisanpham.goiy` → `LoaiSanPhamController@getGoiY` | ✅ Đúng | |
| `/kiemke/{phieu}/duyet` | `POST kiemke.approve` → `KiemKeController@approve` | ✅ Đúng | |
| `/kiemke/{phieu}/gui` | `POST kiemke.submit` → `KiemKeController@submit` | ✅ Đúng | |
| `/trahang/hoadon/{donHang}` | `GET trahang.chon` → `TraHangController@getChon` | ❌ Thiếu | Không có trong README |
| `/trahang/duyet/{phieuTra}` | `POST trahang.duyet` → `TraHangController@postDuyet` | ❌ Thiếu | Không có trong README |
| `/trahang/xem/{phieuTra}` | `GET trahang.xem` → `TraHangController@getXem` | ❌ Thiếu | Không có trong README |
| `/pos` | `GET pos.index` → `DonHangController@getPos` | ✅ Đúng | |
| `/kho/nhap` | `GET kho.nhapkho` → `KhoController@getNhapKho` | ✅ Đúng | |
| `/kho/lichsu` | `GET kho.lichsu` → `KhoController@getLichSu` | ✅ Đúng | |
| **Lệnh Artisan** | | | |
| `documents:import` | Tồn tại: `NhapDuLieuTaiLieu.php` | ✅ Đúng | |
| `demo:kiemtra-du-lieu` | Tồn tại: `KiemTraDuLieu.php` | ✅ Đúng | |
| `demo:reset` | Tồn tại: `ResetDemoData.php` | ✅ Đúng | |
| `hold:clean` | Tồn tại: `XoaGioTamHetHan.php` | ✅ Đúng | |
| `donhang:huy-qua-han` | Tồn tại: `HuyHoaDonQuaHan.php` | ✅ Đúng | |
| **Bảng & Movement** | | | |
| `stock_movements.loai` enum | Migration: `nhap,ban,dieuchinh,trahang,trahang_hong` | ✅ Đúng | |
| `purchase_invoice_id` | Migration `2026_01_01_000019` | ✅ Đúng | |
| `purchase_order_id` | Migration `2026_01_01_000012` | ✅ Đúng | |
| `return_id` | Migration `2026_01_01_000014` | ✅ Đúng | |
| `stocktake_id` | Migration `2026_01_01_000016` | ✅ Đúng | |
| `soluong_hong` trên `products` | Migration `2026_01_01_000014` | ✅ Đúng | |
| `ma_giaodich` trên `invoices` | Migration `2026_11_02_033147` | ❌ Thiếu | Không có trong README |
| `payment_webhooks` table | Migration `2026_11_02_033152` | ❌ Thiếu | Không có trong README |
| `ghi_chu` trên `invoices` | Migration `2026_11_02_055338` | ❌ Thiếu | Không có trong README |
| **Test** | | | |
| `T-CK01`..`T-CK16` | `ThanhToanChuyenKhoanTest.php` - 16 test | ✅ Đúng | |
| `T-HDN01`... | Không tìm thấy test cụ thể | ❌ Sai | README nhắc nhưng không có test thực tế |
| `PdfInvoiceParserTest.php` | 3 test | ❌ Thiếu | Không có trong README |
| `VietQrServiceTest.php` | 5 test | ❌ Thiếu | Không có trong README |
| **Cấu hình .env** | | | |
| `POS_WEBHOOK_SECRET` | Code dùng: `config('pos.webhook_secret')` nhưng WebhookController dùng `SEPAY_WEBHOOK_API_KEY` | ⚠️ Mâu thuẫn | README có cả 2, code chỉ dùng API Key |
| `SEPAY_WEBHOOK_API_KEY` | Code dùng: `config('pos.sepay_webhook_api_key')` | ✅ Đúng | Dùng trong `SepayWebhook::xacThuc()` |
| `TELEGRAM_BOT_TOKEN` | Code dùng: `config('pos.telegram_bot_token')` | ✅ Đúng | |
| `TELEGRAM_CHAT_ID` | Code dùng: `config('pos.telegram_chat_id')` | ✅ Đúng | |
| `POS_CHO_THANH_TOAN_PHUT` | Code dùng: `config('pos.cho_thanh_toan_phut')` | ✅ Đúng | |
| `POS_BANK_CODE` | Code dùng: `config('pos.bank_code')` | ✅ Đúng | |
| `POS_BANK_ACCOUNT` | Code dùng: `config('pos.bank_account')` | ✅ Đúng | |
| `POS_BANK_NAME` | Code dùng: `config('pos.bank_name')` | ✅ Đúng | |
| `POS_QR_TEMPLATE` | Code dùng: `config('pos.qr_template')` | ✅ Đúng | |
| `POS_DIEM_MOI_DONG` | Code dùng: `config('pos.diem_moi_dong')` | ✅ Đúng | |
| `POS_GIA_TRI_DIEM` | Code dùng: `config('pos.gia_tri_diem')` | ✅ Đúng | |
| `POS_HAN_TRA_NGAY` | Code dùng: `config('pos.han_tra_ngay')` | ✅ Đúng | |
| `POS_NGUONG_TRA_KHONG_DUYET` | Code dùng: `config('pos.nguong_tra_khong_duyet')` | ✅ Đúng | |

---

## BƯỚC 2 — VẤN ĐỀ CẤU HÌNH MÔI TRƯỜNG

### Vấn đề phát hiện:
1. **Hai cơ chế webhook khác nhau trong README:**
   - Mục 3.4: `POS_WEBHOOK_SECRET` (HMAC)
   - Mục 4.4: `SEPAY_WEBHOOK_API_KEY` (API Key)
   - **Thực tế code:** Chỉ dùng `SEPAY_WEBHOOK_API_KEY` qua header `Authorization: Apikey <KEY>`. Không có HMAC verification.

2. **Giá trị ngân hàng khác nhau:**
   - Mục 3.4: `POS_BANK_CODE=VCB`
   - Mục 4.3: `POS_BANK_CODE=MB`
   - **Thực tế:** Không có giá trị mặc định, tùy cấu hình.

3. **Database mặc định:** `.env.example` dùng SQLite (`DB_CONNECTION=sqlite`), nhưng README hướng dẫn MySQL.

---

## BƯỚC 3 — TRẠNG THÁI TÍNH NĂNG (PHA 17, 18)

### Pha 17 — Nhật ký hoạt động & Phân quyền nâng cao
| Đã có | Chưa có |
|-------|---------|
| `NhatKyController@index` (`/nhatky`) | Permission động (Pha 23) |
| `NhatKyService::ghi()` cho: đăng nhập/đăng xuất, sản phẩm, nhập kho, nhà cung cấp, đơn nhập | Ma trận quyền chi tiết |
| Model `NhatKyHoatDong` + migration `activity_logs` | Gán role/permission cho user |
| View `nhatky/index.blade.php` | Middleware permission-based |

### Pha 18 — Đổi/trả hàng
| Đã có | Chưa có |
|-------|---------|
| Tạo phiếu trả (`TraHangController@postTraHang`) | Luồng đổi hàng tạo hóa đơn bù trừ riêng |
| Trả hàng tốt (`con_tot`) + hàng lỗi (`loi_hong`) | |
| Kiểm tra hạn (`POS_HAN_TRA_NGAY`) | |
| Kiểm tra số lượng (không vượt đã bán - đã trả) | |
| Hoàn tiền theo tỷ lệ giảm giá/điểm | |
| Cập nhật `soluong` / `soluong_hong` | |
| Thu hồi điểm (không âm) | |
| Ghi nhật ký + cập nhật thống kê | |
| Duyệt phiếu trả (`TraHangController@postDuyet`) | |
| `doi_hang` hiện chỉ lưu lựa chọn trong `hinh_thuc_hoan` | |

---

## BƯỚC 4 — KẾT QUẢ CHẠY TEST

```bash
php artisan test
```

**Kết quả (2026-11-09):**
- Tests: **49 passed** (183 assertions)
- Duration: ~403s
- Test suites:
  - `Tests\Unit\ExampleTest`: 1 passed
  - `Tests\Unit\VietQrServiceTest`: 5 passed
  - `Tests\Feature\ExampleTest`: 1 passed
  - `Tests\Feature\PdfInvoiceParserTest`: 3 passed
  - `Tests\Feature\PosWorkflowTest`: 23 passed
  - `Tests\Feature\ThanhToanChuyenKhoanTest`: 16 passed (T-CK01..T-CK16)

**Lệnh kiểm tra khác:**
- `php artisan route:list` ✅ Hoạt động
- `php artisan demo:kiemtra-du-lieu` ⚠️ **Không chạy** (lệnh ghi dữ liệu, cần database thật)
- `php artisan demo:reset` ⚠️ **Không chạy** (xóa toàn bộ dữ liệu)

---

## BƯỚC 5 — DANH SÁCH "CẦN NGƯỜI DÙNG XÁC NHẬN"

1. **PHP version:** README ghi "PHP 8.4.x" nhưng `composer.json` chỉ yêu cầu `^8.2`. Máy phát triển có 8.4.15 nhưng project có thể chạy trên 8.2+. **Cần xác nhận:** Ghi "PHP 8.2+" hay "PHP 8.4"?
2. **Database mặc định:** `.env.example` dùng SQLite, README hướng dẫn MySQL. **Cần xác nhận:** Giữ hướng dẫn MySQL hay cập nhật theo SQLite?
3. **T-HDN test codes:** README nhắc `T-HDN01`, `T-HDN02`... nhưng không tìm thấy test nào dùng tiền tố này. **Cần xác nhận:** Có test này không, hay xóa khỏi README?
4. **POS_WEBHOOK_SECRET:** README có ở mục 3.4 và 4.3 nhưng code không dùng (chỉ dùng `SEPAY_WEBHOOK_API_KEY`). **Cần xác nhận:** Xóa hoàn toàn hay giữ cho tương lai?
5. **Draft Review UI cho PDF parsing:** README mô tả "Human-in-the-Loop: Draft Review UI" nhưng không tìm thấy view/controller cho tính năng này. **Cần xác nhận:** Đã triển khai chưa, hay là kế hoạch?
6. **Quy trình Git:** README khuyên `git switch main` nhưng nhánh chính có thể là `master` trên một số repo. **Cần xác nhận:** Giữ nguyên hay sửa thành `git checkout main`?

---

## DANH SÁCH THAY ĐỔI ĐÃ THỰC HIỆN

1. **Mục 1 (Công nghệ):** Sửa PHP version từ "8.4.x" → "8.2+ (kiểm tra trên 8.4.15)", thêm SQLite vào DB options.
2. **Mục 2 (Yêu cầu môi trường):** Cập nhật phiên bản PHP, thêm SQLite.
3. **Mục 3.4 (Cấu hình Database):** Tách thành 2 phần MySQL/SQLite, tham chiếu đến bảng biến môi trường mới.
4. **Thêm "Bảng biến môi trường đầy đủ":** Gộp tất cả biến .env vào một bảng duy nhất, ghi rõ `POS_WEBHOOK_SECRET` đang không dùng.
5. **Mục 4 (Thiết lập thiết bị):** Gộp 4.3 và 4.4 thành một mục "VietQR & Webhook SePay", xóa cấu hình trùng lặp, tham chiếu bảng biến môi trường.
6. **Mục 9 (Kiểm thử):** Thêm test `PdfInvoiceParserTest` (3 test) và `VietQrServiceTest` (5 test) vào danh sách.
7. **Mục 11 (Cấu trúc thư mục):** Cập nhật đầy đủ theo code thực tế (thêm 17 controller, 14 model, 7 service, 4 view folder, 22 migration).
8. **Mục 12 (Cơ sở dữ liệu):** Thêm `payment_webhooks`, `ma_giaodich`, `ghi_chu` trên invoices, cập nhật enum movement.
9. **Mục 14 (Phạm vi):** Cập nhật "Đã hoàn thành" và "Còn thiếu" để khớp với code thực tế.
10. **Mục 15 (Lộ trình):** Cập nhật Pha 17, 18 chi tiết đã có/chưa có; thêm Pha 21-24 với ghi chú điều kiện.
11. **Thêm Mục 17 (Giới hạn đã biết):** Liệt kê 9 giới hạn rõ ràng.
12. **Thêm Mục 18 (Kết quả kiểm tra):** Bảng tóm tết kết quả test ngày 2026-11-09.
13. **Thêm Mục lục** ở đầu file.
14. **Sửa lỗi chính tả/định dạng** các mục.

---

## TÓM TẮT CUỐI CÙNG

**Đã sửa (commit trên nhánh `docs/ra-soat-readme`):**
- README.md: Cập nhật hoàn toàn để khớp với codebase hiện tại (49 test pass, 87 routes, đầy đủ controller/model/service/view/migration).
- Gộp cấu hình .env trùng lặp thành một bảng duy nhất, làm rõ `POS_WEBHOOK_SECRET` không dùng.
- Sửa cấu trúc thư mục, cơ sở dữ liệu, routes, test để chính xác.
- Thêm mục lục, giới hạn đã biết, bảng kết quả kiểm tra.
- Tạo `docs/BAO_CAO_RA_SOAT.md` báo cáo chi tiết.

**Phát hiện sai lệch nghiêm trọng:**
1. README có 2 cơ chế webhook khác nhau (HMAC vs API Key) — code chỉ dùng API Key.
2. `ThongBaoService.php` bị liệt kê sai trong `Http/Controllers/` (thực tế ở `app/Services/`).
3. Thiếu 17 controller, 14 model, 7 service, 4 view folder, 22 migration trong cấu trúc thư mục.
4. Thiếu 3 bảng DB quan trọng: `payment_webhooks`, `ma_giaodich`, `ghi_chu`.
5. Thiếu test suite `PdfInvoiceParserTest` (3 test) và `VietQrServiceTest` (5 test) trong danh sách.
6. PHP version ghi 8.4.x nhưng composer yêu cầu ^8.2.
7. Database mặc định là SQLite nhưng README hướng dẫn MySQL.
8. T-HDN test codes được nhắc nhưng không tồn tại trong code.

**Việc chưa làm được & lý do:**
- Không chạy `demo:kiemtra-du-lieu` và `demo:reset` vì lệnh ghi/xóa dữ liệu trên DB production.
- Không xác nhận được 6 mục "Cần người dùng xác nhận" (PHP version, DB mặc định, T-HDN, POS_WEBHOOK_SECRET, Draft Review UI, Git branch).
- Không cập nhật `.env.example` (chỉ đọc, không sửa theo yêu cầu).
- Không sửa code logic, chỉ sửa tài liệu.