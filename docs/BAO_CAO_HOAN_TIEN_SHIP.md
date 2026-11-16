# Báo cáo: Quy tắc hoàn tiền cho đơn có phí giao hàng (2026-11-16)

Làm trực tiếp trên `main`. Tag backup: `backup/main-truoc-hoan-ship-20261116`.

## 1. Hành vi TRƯỚC → SAU (số cụ thể)

Ví dụ chuẩn: đơn **hàng 100.000 + ship 30.000 = khách trả 130.000**, trả 40.000 hàng (40%).

| Tình huống | Trước | Sau |
|---|---|---|
| Trả 40% hàng, không tick | 40.000 × (130.000/100.000) = **52.000** (gồm ship tỷ lệ) | 40.000 × (100.000/100.000) = **40.000** |
| Trả 40% + admin tick "Hoàn cả phí giao" | (không có tính năng) | 40.000 + 30.000 = **70.000** |
| Trả nốt 60% hàng lần sau | 60% × 1.3 = 78.000 | 60.000, **không hoàn thêm ship** |
| Tổng hoàn tích lũy | có thể vượt 130.000 do tỷ lệ | kẹp ≤ `tong_thanh_toan` = 130.000 |

- Đơn **không ship** (POS, pickup, `phi_giao_hang` = 0/null/thiếu cột): tỷ lệ = `(tong_thanh_toan − 0)/tong_tien` → **giống hệt hành vi cũ**.
- Đơn **có giảm giá/điểm**: tỷ lệ trên tiền hàng sau giảm giá = `(tong_thanh_toan − phi_giao_hang)/tong_tien` — ví dụ hàng 160.000, giảm 16.000, ship 30.000 → tỷ lệ 144.000/160.000 = 0,9.

## 2. Quy tắc đã triển khai

1. `TraHangController::postTraHang` — tỷ lệ hoàn đổi từ `tong_thanh_toan/tong_tien`
   sang `(tong_thanh_toan − phi_giao_hang)/tong_tien`; `phi_giao_hang` đọc qua
   `KiemTraSchema::coCot` (thiếu cột → 0 → hành vi cũ).
2. Tick `hoan_phi_giao` (POST): **chỉ `role=admin`** (403 với thungan/thukho),
   bắt buộc `ly_do` non-empty (422), chỉ khi `phi_giao_hang > 0` và đơn **chưa**
   hoàn ship (`returns.tien_hoan_phi_giao > 0` của phiếu hoan_thanh/cho_duyet → 422).
   Một lần/đơn; phí ship hoàn = toàn bộ `phi_giao_hang`.
3. `tien_hoan_phi_giao` cộng vào `tong_hoan` nhưng **không** tính vào `diem_thu_hoi`
   (điểm thu hồi = `floor(tiền hàng hoàn / pos.diem_moi_dong)`, khớp cơ sở cộng điểm
   `diem_tichluy_nhan = floor(tiền hàng sau giảm giá / diem_moi_dong)` — vốn đã loại ship).
4. Kẹp tổng hoàn tích lũy ≤ `tong_thanh_toan` (sai số làm tròn ≤ 1đ được hấp thụ).
5. Nhật ký: `hoan_phi_giao_hang` ghi quyết định (mã phiếu + mã đơn, không PII).
6. UI `trahang/chon`: bảng phân rã "Tiền hàng (sau giảm giá) / Phí giao hàng /
   Tổng đã thanh toán" + ghi chú quy tắc; checkbox chỉ render cho admin khi
   `phi_giao_hang > 0` và chưa hoàn ship. `trahang/xem` hiện phân rã khi có ship hoàn.
7. Báo cáo `ThongKeController::getDoanhThu`: `doanh_thu` = tiền hàng thuần − tiền hàng hoàn;
   `phi_giao_hang` = ship − ship đã hoàn; **key mới `hoan_phi_giao_hang`** = tổng ship đã hoàn
   (dòng riêng, không trộn vào doanh thu/lợi nhuận). `loi_nhuan` giữ nguyên (đã theo giá vốn).
8. Hủy đơn đã thanh toán (HM4): **không đổi** — `canhoantien` và Telegram đã hiển thị
   `tong_thanh_toan` (gồm ship) đúng quy tắc 3.

## 3. Migration mới (CHƯA áp lên doan_cnw)

| Migration | Nội dung | Guard | down() |
|---|---|---|---|
| `2026_11_16_000002_add_tien_hoan_phi_giao_to_returns_table` | `returns.tien_hoan_phi_giao` unsigned int default 0, after `tong_hoan` | `hasColumn` | drop có điều kiện |

Thứ tự áp lên `doan_cnw`: **sau** `2026_11_16_000001_add_can_hoan_tien_to_invoices_table`
→ nay tổng **5 migration pending** (xem `docs/SO_TAY_TRIEN_KHAI.md` mục 1).
Thiếu cột → trả hàng chạy hành vi cũ; tick ship → 422 rõ ràng, không 500.

## 4. File đã sửa

- `app/Http/Controllers/TraHangController.php` — tỷ lệ mới, kiểm tra admin/lý do/một-lần,
  kẹp tổng, `diem_thu_hoi` loại ship, ghi nhật ký, `getChon` truyền biến phân rã.
- `app/Models/PhieuTra.php` — fillable + cast `tien_hoan_phi_giao`.
- `app/Http/Controllers/ThongKeController.php` — `getDoanhThu` tách `hoan_phi_giao_hang`.
- `resources/views/trahang/chon.blade.php`, `xem.blade.php` — phân rã + checkbox admin.
- `database/migrations/2026_11_16_000002_...` — cột mới (guard).

## 5. Test

- Mới `tests/Feature/TraHangPhiGiaoTest.php` (10 test): không tick → 80.000; tick →
  110.000 rồi lần hai không hoàn ship, tích lũy = 190.000; thungan 403; thiếu lý do 422;
  giảm giá tỷ lệ 0,9 → 36.000; điểm thu hồi = 0 khi hoàn ship; báo cáo tách đúng;
  thiếu cột → hành vi cũ + tick 422; đơn không ship giữ nguyên + tick 422.
- Mới `tests/Feature/TraHangPhiGiaoMysqlCopyTest.php`: làm tròn decimal MySQL
  (33.333 × 0,900009 → 30.000), tick ship +30.000, tích lũy = 90.000.
- **Đổi kỳ vọng test cũ** `TraHangDonOnlineTest` (2 chỗ, có chú thích tại chỗ):
  130.000→100.000 và 65.000→50.000 — cố ý, vì quy tắc mới không còn hoàn ship tỷ lệ.
- Số liệu suite: cập nhật README mục 9/18 sau khi paratest xanh.

## 6. Rủi ro / lưu ý

- Phiếu trả tạo **trước** thay đổi này trên đơn có ship có `tien_hoan_phi_giao = 0`
  và `tong_hoan` đã gồm phần ship tỷ lệ → báo cáo mới sẽ tính phần đó vào "tiền hàng
  hoàn" (hơi trừ thừa doanh thu hàng). Không sửa dữ liệu cũ; ảnh hưởng chỉ đơn đã trả.
- `diem_thu_hoi` là ước lượng theo `floor(tổng hoàn/điểm)` từng phiếu — tích lũy nhiều
  phiếu có thể lệch ±1 điểm do làm tròn (hành vi cũ giữ nguyên).
- Checkbox là UI hint; server kiểm tra lại toàn bộ (role, lý do, một lần, có ship).

## 7. Cần người dùng xác nhận

- Quy tắc "hoàn cả phí giao = toàn bộ `phi_giao_hang`" (không prorate) — đã chọn theo
  prompt; nếu muốn prorate theo % hàng trả thì cần sửa.
- Phiếu `cho_duyet` (trên ngưỡng) cũng chiếm suất "đã hoàn ship" — hai phiếu chờ duyệt
  liên tiếp không cùng hoàn ship (an toàn, bảo thủ).
- Sau khi áp migration lên `doan_cnw`: chạy `php artisan migrate:status` xác nhận
  0 pending trước khi dùng tick trên dữ liệu thật.
