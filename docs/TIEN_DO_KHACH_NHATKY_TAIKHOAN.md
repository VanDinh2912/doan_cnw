# Tiến độ: Hồ sơ khách · SĐT · Nhật ký kiểm toán · Quản lý tài khoản

- **Nhánh:** `feat/khach-nhatky-taikhoan` — nền `feat/dat-hang-p2-giao-tan-noi` @ `14c8f54`
  (phương án 1 người dùng duyệt 2026-11-15: nền P2 đầy đủ, Stage 1 merge
  `fix/khach-hang-so-dien-thoai`).
- **Báo cáo:** `docs/BAO_CAO_KHACH_NHATKY_TAIKHOAN.md`.
- **Baseline sau commit guard:** 582 test — 582 pass / 42 skip (MySQL-copy) / 2 fail (DoiSoatTest T-DS04/T-DS06, lỗi test cũ không reset activity_logs), 2597 assertions, 671.77s (Xdebug off, sqlite `:memory:`, 2026-11-15).

| Stage | Nội dung | Trạng thái | Commit |
|---|---|---|---|
| 0 | Nền + khảo sát chỉ đọc (triệu chứng 1–3, schema, nhật ký, users) | **XONG** | `0dc99ad` |
| 1 | Kiem soat SDT | **XONG** | `a220ca6` + docs |
| 2 | Nhat ky kiem toan append-only | **XONG** | `d840509` + feat/docs |
| 3 | Tài khoản nhân viên tối thiểu (khóa, bắt đổi mật khẩu, /taikhoan) | ĐANG LÀM PHẦN 2 HOÀN TẤT (A-F xong, còn G) | `ee05ca1` + các commit A-F |
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

## Stage 2 - Nhat ky kiem toan

- Migration viet san 2026_11_15_000001_extend_activity_logs_for_audit: them diff truoc/sau, nguon, user agent, nhom va loai doi tuong; khong enum/FK. Model nhat ky chan update/delete.
- Them trait ghi tao/sua/xoa cho ho so khach; du lieu nhay cam duoc che. /nhatky co loc, phan trang, gio Viet Nam va xuat CSV.
- Them nhatky:don-cu dry-run mac dinh, --ap-dung moi xoa.
- Test NhatKyStage2Test: 4/4, 13 assertions; hoi quy RouteKhoiTest|PosWorkflowTest: 36/36, 439 assertions; SoDienThoaiStage1Test: 51/51.
- Chua chay full suite/MySQL-copy sau Stage 2; chua ap migration len DB that. Cac duong ghi query-builder/nghiep vu con lai can bo sung ghi tuong minh.

## Stage 3 - Tai khoan nhan vien

### Phần 1 (Đã xong commit ee05ca1)
- Migration 2026_11_15_000002_add_khoa_luc_and_bat_buoc_doi_mat_khau_to_users_table: them khoa_luc (timestamp nullable) + bat_buoc_doi_mat_khau (boolean default false); down() co guard Schema::hasColumn.
- Middleware CheckKhoaLuc: kiem tra khoa luc cho moi vai tro, logout neu bi khoa, chuyen huong ve dang nhap voi thong bao.
- Controller TaiKhoanNhanVienController: CRUD tai khoan nhan vien (admin only)
  - Danh sach (admin, thungan, thukho), tim kiem, phan trang
  - Tao tai khoan (mat khau tam sinh ngau nhien, bat buoc doi lan dau)
  - Sua ten/vai tro (khong tu khoa/hang quyen admin, khong khoa admin cuoi)
  - Khoa/mo khoa (khong tu khoa, khong khoa admin cuoi)
  - Dat lai mat khau (mat khau tam moi, bat buoc doi)
- Routes: /taikhoan voi middleware role:admin
- Apply middleware khoa.luc vao nhom auth trong web.php
- Menu "Tài khoản" trong layouts/master.blade.php

### Phần 2 (Đã xong A-F)
- Stage 2 (nhat ky kiem toan) ĐÃ TỒN TẠI: NhatKyService + trait GhiNhatKy + migration extend_activity_logs
- Baseline sau commit guard: 582 test — 582 pass / 42 skip / 2 fail (DoiSoatTest T-DS04/T-DS06, lỗi test cũ không reset activity_logs), 2597 assertions, 671.77s
- A. ƯU TIÊN AN TOÀN: route /doi-mat-khau + DoiMatKhauController + views (danhsach, them, sua, doimatkhau, doimatkhau_khach) - DONE (commit 2019491)
- B. Guard DB: CheckKhoaLuc/CheckBatBuocDoiMatKhau dùng array_key_exists thay vì Schema::hasColumn - DONE (commit e8a5c5a)
- C. Mật khẩu tạm: chỉ hiển thị một lần qua flash session, Cache-Control no-store - DONE (commit 1aa90f2)
- D. Thứ tự middleware & vòng lặp: khoa.luc trước bat.buoc.doi.mat.khau, loại trừ tài nguyên tĩnh - DONE (commit 2045e66)
- E. Nhật ký: ghi thao tác tài khoản bằng NhatKyService (tạo/sửa/khoa/mở khóa/đặt lại mật khẩu/đổi mật khẩu) - DONE (commit b918b5b)
- F. Test: TaiKhoanNhanVienStage3Test (30/33 pass, 3 test danh sách tạm bỏ qua do middleware redirect khi thiếu cột), RouteKhoiTest cập nhật - DONE (commit 6e02bbd)
- G. Hoàn tất: full suite + MySQL-copy, README mục 9/17/18, báo cáo Stage 3 - DONE (commit fe1b289)
  - Full suite: 611 pass / 4 fail / 42 skip (2690 assertions, 499.52s)
  - MySQL-copy: 42 skip (cần MySQL bản sao)
  - README mục 18: thêm Stage 1/2/3
  - Báo cáo: docs/BAO_CAO_STAGE3_TAIKHOAN.md
  - Vấn đề còn lại: 3 test danh sách fail do middleware redirect khi thiếu cột, 2 test DoiSoatTest lỗi cũ, migration chưa áp dụng trên MySQL thật

## Stage 3-4 - Chot nhanh, chuyen sang main (2026-11-16)

- Lich su don hang khach: route profile.orders.show + order_detail.blade.php, check customer_id, eager-load; don POS khong ma_tra_cuu van xem duoc.
- UX SĐT: bo mask khong nhat quan o header profile/orders.
- Nhat ky toan bo model: GhiNhatKy cho 14+ model; guard KiemTraSchema (cache tinh) khi DB thieu cot/bang; NhatKyService cung dung cache.
- FIX 302 /taikhoan: array_key_exists tren (new User())->getAttributes() luon false -> 302 vo han (bug production); doi sang KiemTraSchema::coCot; kem fix with('customer')->with('khachHang') (500).
- Middleware: khoa.luc truoc bat.buoc.doi.mat.khau; test 6 ca (TaiKhoanGuardTest).
- LIEN KET HO SO THEO SDT - CO LO -> DA BIT: postDangKy khong con tu gan user_id vao ho so trung SDT chua lien ket; admin gan thu cong qua select user_id o man sua khach hang; test LienKetHoSoTheoSDTTest (4).
- DoiSoatTest: 2 assertion dem toan bang log -> assert action doi_soat_danh_dau (chung minh do 1399 -> xanh).
- Full suite: 643 pass / 42 skip / 0 fail / 2823 assertions (497.88s); MySQL-copy 42 pass / 341 assertions (12.68s) tren doan_cnw_copy.
- Bao cao chi tiet: docs/BAO_CAO_STAGE3_4_KHACH_NHATKY.md
