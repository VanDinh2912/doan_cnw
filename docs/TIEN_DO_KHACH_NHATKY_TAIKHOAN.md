# Tiến độ: Hồ sơ khách · SĐT · Nhật ký kiểm toán · Quản lý tài khoản

- **Nhánh:** `feat/khach-nhatky-taikhoan` — nền `feat/dat-hang-p2-giao-tan-noi` @ `14c8f54`
  (phương án 1 người dùng duyệt 2026-11-15: nền P2 đầy đủ, Stage 1 merge
  `fix/khach-hang-so-dien-thoai`).
- **Báo cáo:** `docs/BAO_CAO_KHACH_NHATKY_TAIKHOAN.md`.
- **Baseline:** 522 test — 480 pass / 42 skip (MySQL-copy) / 0 fail, 2255 assertions,
  477.59s (Xdebug off, sqlite `:memory:`, 2026-11-15).

| Stage | Nội dung | Trạng thái | Commit |
|---|---|---|---|
| 0 | Nền + khảo sát chỉ đọc (triệu chứng 1–3, schema, nhật ký, users) | **XONG** | `0dc99ad` |
| 1 | Kiểm soát SĐT: merge nhánh SĐT cũ + bổ sung spec (model mutator, dry-run, hồ sơ) | Chưa bắt đầu | — |
| 2 | Nhật ký kiểm toán append-only (migration viết sẵn, trait/observer, /nhatky, don-cu) | Chưa bắt đầu | — |
| 3 | Tài khoản nhân viên tối thiểu (khóa, bắt đổi mật khẩu, /taikhoan) | Chưa bắt đầu | — |
| 4 | Hồ sơ khách + lịch sử đơn + tài khoản khách + chống đăng ký rác | Chưa bắt đầu | — |
| 5 | Hợp nhất + báo cáo tổng + README | Chưa bắt đầu | — |

## Stage 0 — kết quả chính (chi tiết trong báo cáo)

- DB thật `doan_cnw`: **36/36 migration đã áp (kể cả M1+M3)** — trái tiền đề prompt;
  guard giữ tới khi user xác nhận gỡ. `he-thong:kiem-tra`: 1 FAIL (thiếu
  SEPAY_WEBHOOK_API_KEY — đã biết, user tự xoay token) + 5 WARN; InnoDB toàn bộ.
- Triệu chứng 1: trang lịch sử **500 khi có đơn POS** (`orders.blade.php:77`
  `route('don-hang.trangthai', null)` — ViewException MissingRequiredParameter);
  dữ liệu thật: hồ sơ 102/user 4 có đúng 1 đơn POS ma_tra_cuu NULL → đang 500 thật.
  Link chi tiết đơn online cần bằng chứng session → 302 về tra cứu. `postDat` CÓ gắn
  customer_id khi đăng nhập (DatHangOnlineController:225-227).
- Triệu chứng 2: che inline `3****2` (profile/orders.blade:20) ≠ CheThongTin `3****3`;
  form hồ sơ điền sẵn SĐT đầy đủ (`profile.blade.php:72`).
- Triệu chứng 3: nền KHÔNG validate định dạng SĐT ở mọi điểm nhập → chữ được lưu
  (bằng chứng test tạm). Giả thuyết "chữ → đã tồn tại" không tái hiện được trên nền
  (không có chuẩn hóa); nhánh fix cũng không strip chữ → ghi nhận chưa kiểm chứng.
- Dữ liệu thật (chỉ đọc): 101 khách, 0 rỗng/NULL/sai/trùng (bản ghi sai trong báo cáo
  11-11 đã được sửa); 4 users (mỗi vai trò 1); 11 invoices (4 online, 7 pos);
  activity_logs 40 dòng.
- Đăng ký **tự liên kết hồ sơ theo SĐT không chứng minh sở hữu** → lộ điểm/lịch sử
  (bằng chứng) + thông báo trùng lộ tồn tại → Stage 4 đổi mặc định an toàn theo spec.
- Phát hiện phụ: `customers.dia_chi` varchar(150) vs validate max:255 (chờ xác nhận).
- Test tạm `TmpStage0KhaoSatTest` 9/9 pass, KHÔNG commit, đã xóa.

## Việc dở / bước kế tiếp

- Stage 1: merge `fix/khach-hang-so-dien-thoai` vào nhánh (giữ test hai phía, giải
  xung đột README nếu có), đối chiếu spec mới: chốt model mutator/saving, bail định
  dạng trước unique, bỏ qua rỗng/NULL + bản ghi đang sửa, áp cả điểm nhập hồ sơ khách
  (ThongTinCaNhanController) + import/seeder nếu phạm vi cho phép, thống kê dry-run.
- Điểm đã DỪNG hỏi người dùng: (1) chọn nền — ĐÃ duyệt phương án 1; (2) các mục
  "Cần người dùng xác nhận" 0.14 không chặn Stage 1–3.

## Ràng buộc cố định

- Không `.env`; DB thật chỉ ĐỌC; migration chỉ viết sẵn + chứng minh SQLite/`doan_cnw_copy`;
  filter MySQL-copy chính xác `--filter='MysqlCopyTest|KhoaDongMysqlTest'`; không enum/FK
  mới; không đổi nghiệp vụ POS/webhook/online; commit tách test → fix/feat → docs;
  không merge main/push; không đụng wip/*.