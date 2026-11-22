# Mã duyệt hai-người (PIN mức M2) — hướng dẫn vận hành

Phân hệ bảo vệ thao tác nhạy cảm gồm 4 mức, khai báo tập trung tại
`config/pin_muc.php` (tên route → K/M1/M2/A). Middleware `pin.muc`
đọc mức theo tên route; route ghi/xuất dữ liệu bắt buộc có tên và có
khóa trong file này (test `PinKiemKeRouteTest` fail nếu thiếu).

| Mức | Nhân viên (thungan/thukho) | Admin |
|-----|----------------------------|-------|
| K   | Không yêu cầu | Không yêu cầu |
| M1  | PIN riêng, cửa sổ 5 phút | PIN riêng, cửa sổ 5 phút |
| M2  | Mã duyệt do admin khác cấp (một lần, ràng buộc thao tác + tham số, hết hạn) | PIN riêng mức nghiêm trọng, mỗi lần |
| A   | 403 — chỉ admin | PIN riêng mức nghiêm trọng, mỗi lần |

Nguyên tắc hai người: người thực hiện thao tác **không thể** là người
cấp mã (server chặn `nhan_vien_id = admin_cap_id`).

## Cờ cấu hình

- `POS_PIN_BAT` (mặc định `false`): tắt = mọi thứ như cũ, **không đọc
  bảng `yeu_cau_duyet`**.
- `POS_MA_DUYET_BAT` (mặc định `true`, chỉ có nghĩa khi PIN bật):
  - `true` + bảng `yeu_cau_duyet` tồn tại → M2 = mã duyệt.
  - `false` → M2 tạm hạ M1 (PIN thường). **Mất nguyên tắc hai người** —
    `he-thong:kiem-tra` báo WARN rõ ràng; chỉ dùng khi admin vắng.
  - `true` mà thiếu bảng → `he-thong:kiem-tra` báo **FAIL**.
- `POS_MA_DUYET_HET_HAN_PHUT` (mặc định 10, kẹp 2–30): thời gian mã
  `da_cap` còn hiệu lực.

## Luồng sử dụng

1. Nhân viên gọi thao tác M2 (hoặc thao tác có điều kiện vượt ngưỡng:
   `sanpham.postsua` đổi `gia_ban`/`trang_thai`, `donhang.thanhtoan`/
   `taoqr` vượt ngưỡng giảm giá/điểm) → nhận `423 CAN_MA_DUYET`.
2. Hộp thoại PIN (pin.js) hiện nút **"Gửi yêu cầu cho quản lý"** →
   `POST /yeu-cau-duyet/gui`. Server tự sinh `mo_ta_thao_tac` từ route +
   đối tượng thật (client không gửi mô tả), kiểm route thuộc whitelist
   M2 + vai trò + đối tượng tồn tại, tối đa 5 yêu cầu chờ/NV, throttle
   20 req/phút theo IP.
3. Admin vào **Hệ thống → Yêu cầu duyệt** (`/yeu-cau-duyet`, badge đếm
   `cho_cap`, poll nhẹ). Danh sách nổi bật: người yêu cầu, thao tác,
   đối tượng, tham số ràng buộc, thời gian.
4. Admin bấm **Cấp mã** → bắt PIN nghiêm trọng (mức A) → mã 6 số hiện
   **một lần**, cỡ lớn, nút copy, header `no-store`; không flash,
   không log, không audit nội dung mã.
5. Nhân viên nhập mã vào ô trong hộp thoại → request gốc được gửi lại
   kèm `_ma_duyet`. Server verify nguyên tử trong transaction của thao
   tác: đúng HMAC + đúng nhân viên + trạng thái `da_cap` + chưa hết hạn
   + tham số khớp ràng buộc → thực hiện, đánh `da_dung`. Sai → bộ đếm
   riêng `ma_duyet_sai:{user}` (5 lần/15 phút khóa), thông báo chung
   "Mã duyệt không đúng hoặc đã hết hạn".

### Ràng buộc tham số

- Route có id tĩnh (xóa SP #12, xác nhận HĐ nhập #5…): khóa theo
  `ten_route + doi_tuong_loai + doi_tuong_id`.
- `sanpham.postsua`: khóa `gia_ban_moi` + `trang_thai_ban_moi` — rule
  **eq**, mã chỉ đúng cho đúng giá/trạng thái đã duyệt.
- `donhang.thanhtoan`/`taoqr`: khóa `giam_gia_phantram`, `diem_su_dung`,
  `tong_gio` (tổng giỏ session lúc tạo yêu cầu, admin nhìn thấy) —
  rule **lte**: giá trị gửi lên phải ≤ giá trị đã duyệt, cùng nhân
  viên, cùng route.

## Công tắc production

Script: `scripts/windows/cong-tac-pin-prod.ps1`

```powershell
.\scripts\windows\cong-tac-pin-prod.ps1 -Che trang-thai   # chỉ đọc
.\scripts\windows\cong-tac-pin-prod.ps1 -Che bat          # bật cả 2 cờ
.\scripts\windows\cong-tac-pin-prod.ps1 -Che tat         # tắt cả 2 cờ
```

Trước khi `-Che bat` script bắt buộc đạt:

1. Artisan đang kết nối `doan_cnw`, không có `APP_ENV` trong phiên.
2. `users` đủ 5 cột `pin_*`; bảng `yeu_cau_duyet` đã tồn tại
   (chạy migrate trên prod **chỉ khi được duyệt**).
3. Mọi admin có `pin_doi_luc` mới hơn mốc lộ PIN (nhật ký `pin_dat`
   nguồn console, ưu tiên `activity_logs` id=463).
4. Mọi thu ngân/thủ kho đang hoạt động (`khoa_luc` trống hoặc đã qua)
   có `pin_hash` — thiếu thì WARN kèm email đã che.

Script tự sao lưu `.env` trước khi sửa và nhắc chạy `config:cache`.
`-Che trang-thai` không ghi gì.

Sau khi bật, chạy `php artisan he-thong:kiem-tra` — mục "Mã duyệt
hai-người" phải OK; mục "PIN thao tác nhạy cảm" phải OK (mọi admin có
PIN).

## Rollback

- Tắt nhanh: `-Che tat` (về hành vi không PIN) hoặc `POS_MA_DUYET_BAT=false`
  (M2 → PIN thường, mất nguyên tắc hai người — chỉ tạm).
- Không cần drop bảng `yeu_cau_duyet`; dữ liệu yêu cầu cũ giữ nguyên
  để audit.

## Lưu ý vận hành

- Admin vắng → các thao tác M2 tạm dừng (hoặc dùng van xả
  `POS_MA_DUYET_BAT=false` với rủi ro đã nói ở trên). Phương án "quản lý
  ca được cấp quyền duyệt" là backlog, chưa làm.
- Mã đọc miệng bị nghe chỉ dùng được cho đúng thao tác + tham số đã
  duyệt, một lần, trong hạn — rủi ro còn lại chủ yếu ở route động POS
  (đã chấp nhận trong thiết kế).
- Audit: `ma_duyet_yeu_cau`, `ma_duyet_cap`, `ma_duyet_tu_choi`,
  `ma_duyet_sai`, `ma_duyet_dung` trong `activity_logs` — không chứa
  mã, không chứa PII thừa.
