# Báo cáo Stage 3-4 — Khách hàng, nhật ký toàn bộ, tài khoản & chốt main

Ngày: 2026-11-16. Nhánh: `feat/khach-nhatky-taikhoan` (tách từ chuỗi P0→P2).
Phạm vi: lịch sử đơn hàng khách, UX SĐT, kiểm soát SĐT mọi điểm nhập, nhật ký
kiểm toán toàn bộ model, quản lý tài khoản nhân viên/khách hàng, sửa lỗi 302
`/taikhoan`, đánh giá & bịt lỗ hổng liên kết hồ sơ theo SĐT.

---

## 1. Bước 1 — Nguyên nhân gốc lỗi 302 ở GET /taikhoan

**Triệu chứng:** `TaiKhoanNhanVienStage3Test::test_admin_truy_cap_duoc_taikhoan`
và `RouteKhoiTest::test_route_get_khong_tham_so_vai_tro_quan_tri` trả **302 thay
vì 200** — lỗi có sẵn trên HEAD sạch (đã verify bằng stash ở phiên trước).

**Nguyên nhân gốc (2 lớp):**

1. `TaiKhoanNhanVienController::index()` kiểm tra migration bằng
   `array_key_exists('khoa_luc', (new User())->getAttributes())`. Model mới khởi
   tạo **luôn có `$attributes = []`** → `array_key_exists` luôn `false` → guard
   luôn kết luận "thiếu migration" → `back()->with('error', ...)` → **302 cho
   mọi vai trò, kể cả khi DB thật đã migrate**. Đây là bug production thật,
   không chỉ lỗi test. Cùng lỗi ở `update()`/`khoa()` (dòng `(new User())`).
2. Sau khi sửa guard, lộ bug thứ hai: `->with('customer')` eager-load quan hệ
   không tồn tại (quan hệ đúng là `khachHang`) → **500**.

**Cách sửa:**

- Thêm `app/Support/KiemTraSchema.php`: cache `Schema::hasTable/hasColumn`
  trong biến tĩnh (tối đa 1 truy vấn/(bảng,cột)/tiến trình), có `datLai()` để
  test reset sau khi đổi schema giữa chừng.
- `TaiKhoanNhanVienController`: mọi method public guard bằng
  `thieuMigrationStage3()` (kiểm tra cả `khoa_luc` + `bat_buoc_doi_mat_khau`
  trên bảng `users` thật); khi thiếu cột trả redirect một lần kèm thông báo
  "Cần áp migration Stage 3…" — không 500, không vòng lặp. Các check
  `array_key_exists` bên trong được thay bằng dùng cột trực tiếp (guard đã
  chặn trường hợp thiếu cột).
- Middleware `CheckKhoaLuc`/`CheckBatBuocDoiMatKhau` **giữ nguyên**: đã đúng
  spec — `array_key_exists` trên user đã nạp, thuộc tính vắng = không cờ,
  không redirect.
- `tests/TestCase.php::setUp()` gọi `KiemTraSchema::datLai()` mỗi test.

**Test mới** (`tests/Feature/TaiKhoanGuardTest.php`, 6 test):

- admin factory không refresh vào được `/taikhoan` (200);
- admin bị khóa → đăng xuất + redirect đăng nhập;
- cờ `bat_buoc_doi_mat_khau` → redirect `/doi-mat-khau` đúng một lần, trang
  đổi MK vẫn 200, sau đổi vào `/tongquan` bình thường (không vòng lặp);
- thứ tự middleware: user vừa khóa vừa cờ đổi MK → **khóa thắng** (logout,
  không được cờ cứu phiên);
- DB thiếu 2 cột Stage 3 (DROP COLUMN SQLite): đăng nhập, POS tiền mặt,
  scheduler `donhang:huy-qua-han` vẫn chạy; `/taikhoan` redirect một lần kèm
  thông báo migration.

---

## 2. Bước 1B-a — Diff `tests/Feature/DoiSoatTest.php` (4 dòng)

```diff
-        $this->assertDatabaseCount('activity_logs', 0);
+        $this->assertDatabaseMissing('activity_logs', ['hanh_dong' => 'doi_soat_danh_dau']);
```

(2 chỗ: `test_T_DS04_danh_dau_xu_ly_bat_buoc_ghi_chu`,
`test_T_DS06_nhan_vien_khong_duoc_danh_dau_xu_ly`.)

**Giải thích từng dòng:** assertion cũ "toàn bảng activity_logs = 0" ngầm giả
định không có ghi nhật ký nào khác trong test. Sau khi gắn `GhiNhatKy` cho
14+ model, chính việc tạo dữ liệu test đã sinh hàng trăm dòng `tao` → count ≠ 0
dù hành vi nghiệp vụ không đổi. Assertion mới kiểm **đúng đối tượng cần kiểm**:
thao tác đối soát bị cấm/thiếu ghi chú KHÔNG được phát hành hành động miền
`doi_soat_danh_dau`. Nghiệp vụ **không bị nới** — thậm chí chặt hơn vì chỉ
nhìn đúng action thay vì mọi dòng log.

**Bằng chứng đỏ → xanh** (không dùng stash; thay file test bằng bản HEAD rồi
khôi phục):

- File test bản HEAD + code mới: 2 test **đỏ** —
  `Entries found: 1399` (toàn bộ log model event).
- File test bản mới + code mới: `DoiSoatTest` **8/8 pass**.

---

## 3. Bước 1B-b — Nhật ký khi DB thiếu cột/bảng

**Thay đổi:**

- `GhiNhatKy::ghiThayDoiNhatKy()`: guard `KiemTraSchema::coBang('activity_logs')`
  + lọc từng key dữ liệu theo `coCot()` — DB thiếu cột mở rộng thì vẫn ghi
  **mức tối thiểu** (user_id, hanh_dong, doi_tuong, doi_tuong_id, mo_ta, ip).
- `NhatKyService::ghi()`: guard `coBang` + đổi `Schema::hasColumn` (truy vấn
  mỗi lần gọi) sang `KiemTraSchema::coCot` (cache).

**Test mới** (`tests/Feature/NhatKyThieuCotTest.php`, 3 test): bỏ 6 cột mở
rộng của `activity_logs` → POS tiền mặt + đăng nhập + scheduler vẫn chạy, nhật
ký vẫn ghi mức tối thiểu; drop hẳn bảng → giao dịch vẫn thành công.

**Quyết định — lỗi ghi nhật ký sau khi migration đã áp lên DB thật:**
**để exception làm thất bại giao dịch** (không try/catch nuốt lỗi). Lý do:
nhật ký kiểm toán là yêu cầu tuân thủ — mất audit âm thầm nguy hiểm hơn
rollback một giao dịch; guard chỉ tồn tại để bắc cầu giai đoạn chưa migrate,
sau migrate mọi lỗi ghi log còn lại là lỗi thật cần lộ ra ngay. Guard sẽ được
gỡ (xem mục 7).

---

## 4. Bước 1B-c — Diff `DatHangOnlineController.php` & điều kiện quyền

Diff 36 dòng: 3 method (`getTrangThai`, `postPoll`, `postHuy`) đổi từ check
`coBangChung()` (bằng chứng session) sang `coQuyenXem()`:

```php
private function coQuyenXem(Request $request, DonHang $don): bool
{
    if ($this->coBangChung($request, $don->ma_tra_cuu)) {
        return true;                       // (a) guest có bằng chứng tra cứu
    }
    $khachHang = $request->user()?->khachHang;
    return $khachHang !== null
        && $don->customer_id === $khachHang->id;  // (b) chủ đơn đã login
}
```

**Điều kiện chính xác:** được xem/trạng thái/poll/hủy khi **(a)** session có
`don_hang_xac_thuc[ma_tra_cuu]` (bằng chứng do `postTraCuu`/`postDat` ghi sau
khi xác thực SĐT), **HOẶC (b)** user đăng nhập có `customers.user_id =
users.id` và `customer_id` của đơn trùng `khachHang->id`. **SĐT không tham
gia phân quyền** — `so_dienthoai_dat` trên đơn chỉ là dữ liệu hiển thị.
`postHuy` vẫn yêu cầu `trang_thai === 'cho_thanh_toan'`; `huyChoThanhToan`
idempotent (khóa dòng + kiểm lại trạng thái trong transaction).

**Test mới** (`tests/Feature/DonHangQuyenChuSoHuuTest.php`, 6 test): chủ đơn
login xem/poll/hủy OK + hoàn kho; khách B (kể cả đơn ghi đúng SĐT của B) bị
chặn cả 3; guest không bằng chứng bị chặn; guest có bằng chứng vẫn xem được;
đơn `da_thanh_toan`/`da_huy` không hủy được; hủy hoàn kho đúng một lần.

---

## 5. Bước 2 — Đánh giá rủi ro liên kết hồ sơ theo SĐT: **CÓ LỘ → ĐÃ BỊT**

**Bằng chứng lỗ hổng (trước fix):** `AuthController::postDangKy` tra
`KhachHang::where('so_dienthoai')`; nếu hồ sơ tồn tại và `user_id = null`
(khách vãng lai tại quầy) → **tự động gán `user_id` + ghi đè `ho_ten`** cho
tài khoản mới. Bất kỳ ai biết SĐT khách đều đăng ký email riêng → chiếm hồ
sơ: xem toàn bộ đơn POS/online (`profile.orders`), điểm tích lũy, địa chỉ,
SĐT; poll/hủy đơn chờ của nạn nhân.

**Fix (không thêm cột/migration mới):**

- `postDangKy`: hồ sơ trùng SĐT **không còn tự liên kết**. Đã có chủ → từ
  chối với thông báo chung "Số điện thoại đã được đăng ký." Chưa có chủ → tài
  khoản tạo thành công nhưng `user_id` giữ NULL, chờ nhân viên xác nhận.
  SĐT chưa ai dùng → vẫn tạo hồ sơ mới gắn `user_id` như cũ (an toàn —
  không có dữ liệu cũ để lộ).
- `KhachHangController` + `khachhang/form.blade.php`: màn sửa khách hàng có
  select "Tài khoản khách hàng" — chỉ liệt kê user role `khachhang` chưa liên
  kết hồ sơ nào (hoặc đang liên kết chính hồ sơ này); validate role +
  `user_id` unique trên `customers` (một tài khoản chỉ một hồ sơ; một tài
  khoản đã có hồ sơ không gán được hồ sơ thứ hai). Không gửi field → giữ
  nguyên liên kết (tương thích request cũ).
- Mọi quyền đọc hồ sơ (`profile.*`, `coQuyenXem`, checkout online) vốn đã đi
  qua `customers.user_id` → sau fix, mọi liên kết đều do nhân viên xác nhận
  hoặc do chủ tài khoản tự tạo hồ sơ mới → an toàn.

**Hành vi trước/sau:**

| Tình huống | Trước | Sau |
|---|---|---|
| Đăng ký trùng SĐT hồ sơ chưa liên kết | Tự chiếm hồ sơ | Tài khoản tạo được, không liên kết; nhân viên gán thủ công |
| Đăng ký trùng SĐT đã liên kết | Từ chối | Từ chối (thông báo chung) |
| Đơn đặt khi đang login | Gắn `customer_id` hồ sơ đã liên kết | Giữ nguyên |
| Khách vãng lai mua tại quầy | Hồ sơ `user_id=null` | Giữ nguyên — chỉ hiện trong tài khoản sau khi nhân viên gán |

**Test:** `tests/Feature/LienKetHoSoTheoSDTTest.php` (4 test) — đăng ký trùng
SĐT không liên kết/không xem được đơn-điểm-địa chỉ/SĐT, không poll-hủy đơn
chờ; nhân viên gán → B thấy đúng đơn; chặn gán tài khoản đã có hồ sơ; chặn
gán tài khoản role nhân viên. `AuthFlowTest` + `KhachHangSoDienThoaiTest` cập
nhật 2 assertion theo hành vi mới (ghi rõ lý do trong comment test).

---

## 6. Kết quả test

*(Cập nhật số cuối sau khi chạy full suite + MySQL-copy — xem mục 8.)*

Các nhóm đã chạy riêng trên cây cuối:

| Nhóm | Kết quả |
|---|---|
| `TaiKhoanGuardTest` (mới) | 6/6 |
| `TaiKhoanNhanVienStage3Test` | 31/31 (2 test trước đỏ nay xanh) |
| `RouteKhoiTest` vai trò admin | xanh (65 assertions route đó) |
| `NhatKyThieuCotTest` (mới) | 3/3 |
| `DonHangQuyenChuSoHuuTest` (mới) | 6/6 |
| `LienKetHoSoTheoSDTTest` (mới) | 4/4 |
| `AuthFlowTest` | 7/7 |
| `DoiSoatTest` | 8/8 |
| `KhachHangSoDienThoaiTest` + `SoDienThoaiStage1Test` + `KhachHangLichSuDonTest` + `NhatKyToanBoModelTest` + `NhatKyStage2Test` | 97/97 |

---

## 7. Gỡ guard sau khi migrate (quy trình vận hành)

Guard "code đi trước migration" gồm: `KiemTraSchema` + các `coBang/coCot`
trong `GhiNhatKy`, `NhatKyService`, `TaiKhoanNhanVienController`,
`DonHang::scopeQuaHanCho`, `ThanhToanChuyenKhoanService`, `NhatKyController`,
các test `*ThieuCot*`/`LoiB*`.

Sau khi migration áp lên DB thật `doan_cnw`, theo thứ tự:

1. **Sao lưu**: `php artisan sao-luu:csdl` (hoặc mysqldump thủ công) + xác
   nhận file backup tồn tại.
2. **Áp migration theo thứ tự**: `php artisan migrate --force` trên DB thật —
   chỉ sau khi đã chạy thử trên `doan_cnw_copy` và `he-thong:kiem-tra` sạch
   mục migration.
3. **Đối chiếu information_schema**: xác nhận đủ cột
   `users.khoa_luc`, `users.bat_buoc_doi_mat_khau`, 6 cột `activity_logs`
   (`doi_tuong_loai`, `truoc`, `sau`, `user_agent`, `nguon`, `nhom_id`), và
   các cột online M1/M3 trên `invoices` nếu thuộc đợt này.
4. **Gỡ guard**: xóa `KiemTraSchema` + các nhánh `coBang/coCot/hasColumn`
   trong code (mỗi chỗ có comment `TODO(gỡ guard)`), xóa/viết lại các test
   `*ThieuCot*`, `LoiBThieuCotOnlineTest`; chạy lại full suite + MySQL-copy.
5. **Kiểm tra**: `php artisan he-thong:kiem-tra` → 0 FAIL; smoke `/taikhoan`,
   `/nhatky`, POS, đăng nhập.

---

## 8. Số liệu cuối

*(điền sau full suite + MySQL-copy — mục 9 báo cáo chat)*
