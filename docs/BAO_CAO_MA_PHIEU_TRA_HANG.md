# Báo cáo: sửa trùng mã phiếu trả hàng trong cùng một giây

- **Ngày**: 2026-11-12
- **Nhánh**: `fix/ma-phieu-tra-hang` (từ `feat/anh-url-tong-quan` @ `7e3379b`,
  nhánh này đã chứa toàn bộ đợt ảnh URL + dashboard; không merge/push theo yêu cầu).
- **Phạm vi**: chỉ sửa nơi sinh `returns.ma_phieu`. Các nơi sinh mã tương tự khác
  được khảo sát và ghi nhận bên dưới, không sửa trong đợt này.
- **Ràng buộc giữ đúng**: không đọc/ghi `.env`; không chạy migrate/seed/demo:reset
  trên CSDL thật (test dùng SQLite `:memory:` + `RefreshDatabase`); không đổi
  cột/migration; không merge/push.

## 1. Khảo sát mọi nơi sinh mã (yêu cầu 1)

Mẫu ngẫu nhiên `PREFIX + now()->format('YmdHis') + random_int(10, 99)` — chỉ có
**90 giá trị hậu tố mỗi giây**, cột đều `VARCHAR(30) UNIQUE`:

| Nơi sinh | File:dòng (trước sửa) | Mã | Bảng/cột unique |
|---|---|---|---|
| Phiếu trả hàng | `app/Http/Controllers/TraHangController.php:77` | `TH…` | `returns.ma_phieu` — `database/migrations/2026_01_01_000014_create_returns_tables.php:14` |
| Phiếu kiểm kê | `app/Http/Controllers/KiemKeController.php:32` | `KK…` | `stocktakes.ma_phieu` — `…000016_create_stocktakes_tables.php:13` |
| Hóa đơn nhập | `app/Http/Controllers/HoaDonNhapController.php:140` | `HN…` | `purchase_invoices.ma_phieu` — `…000018_create_purchase_invoices_tables.php:13` |
| Đơn nhập hàng | `app/Http/Controllers/NhapHangController.php:96` | `PN…` | `purchase_orders.ma_don` — `…000010_create_purchase_orders_table.php:13` |

Mã hóa đơn dùng mẫu **khác** — chuỗi tuần tự theo ngày `HD{Ymd}-{XXXX}`, có
`lockForUpdate` và nằm trong `DB::transaction(..., 2)` (thử lại 2 lần):

- `app/Http/Controllers/DonHangController.php:322-333` (`taoMaHoaDon`, gọi tại `:260`).
- `app/Services/ThanhToanChuyenKhoanService.php:309-320` (`taoMaHoaDon`, gọi tại `:74`).
- Webhook chỉ đọc lại mã, không sinh: `app/Services/Payment/SepayWebhook.php:76`
  (parse `HD{ngày}-{số}` từ nội dung chuyển khoản).

Không liên quan: `app/Console/Commands/SyncTaiLieuDataCommand.php:196` —
`rand(15, 60)` là số lượng tồn kho giả lập, không phải mã phiếu.

## 2. Nguyên nhân flake

`random_int(10, 99)` chỉ có 90 kết quả. Hai phiếu trả tạo trong cùng một giây
(trong `PosWorkflowTest` là các test gọi `trahang.luu` liên tiếp, ví dụ
`tests/Feature/PosWorkflowTest.php:458` và `:472`; các test hoàn điểm còn tạo
nhiều phiếu hơn trong một vòng lặp) có xác suất trùng ≈ 1/90 cho mỗi cặp —
đủ để thỉnh thoảng đỏ toàn suite. Khi trùng, `INSERT` ném
`SQLSTATE[23000] UNIQUE constraint failed: returns.ma_phieu` → HTTP 500,
phiếu không được tạo.

## 3. Test tái hiện — đỏ trước (yêu cầu 2)

Tệp mới: `tests/Feature/TraHangTrungMaPhieuTest.php` (2 test), kèm bộ khóa:

- `tests/Support/KhoaBoSinhSo.php` — giữ giá trị khóa (mặc định `null`).
- `tests/Support/bo-sinh-so-ngau-nhien.php` — định nghĩa
  `App\Http\Controllers\random_int()` ủy quyền về `\random_int` gốc khi không
  khóa. PHP phân giải lời gọi hàm không đủ định danh trong namespace của
  controller sang hàm này trước, nên **khóa được RNG mà không sửa code
  production**; các test khác không bị ảnh hưởng (mặc định trả số thật).

Test chính `test_hai_phieu_tra_cung_giay_rng_bi_khoa_trung_van_thanh_cong`:
đóng băng `Carbon::setTestNow('2026-11-12 10:00:00')`, khóa RNG = 42, tạo hóa đơn
đã thanh toán (chi tiết số lượng 2), POST `trahang.luu` hai lần liên tiếp, mỗi
lần trả 1 sản phẩm `con_tot`. Kỳ vọng: cả hai thành công, mã khác nhau, đúng
định dạng `/^TH\d{16}$/`, mã đầu đúng `TH2026111210000042` (chứng minh bộ khóa
có hiệu lực), kho hoàn +2, đủ 2 biến động `trahang`.

Test phụ `test_het_90_hau_to_trong_giay_thi_bao_loi_va_khong_tao_phieu`: dựng sẵn
90 phiếu phủ kín hậu tố 10..99 của giây đó, POST phiếu thứ 91 → 500 có kiểm soát,
không tạo thêm bản ghi (chứng minh vòng dò có giới hạn, không lặp vô hạn).

**Kết quả chạy trước sửa (đỏ thật):**

```text
FAIL  Tests\Feature\TraHangTrungMaPhieuTest
⨯ hai phieu tra cung giay rng bi khoa trung van thanh cong   18.20s
✓ het 90 hau to trong giay thi bao loi va khong tao phieu     3.64s

Expected response status code [201, 301, 302, 303, 307, 308] but received 500.
PDOException: SQLSTATE[23000]: Integrity constraint violation:
19 UNIQUE constraint failed: returns.ma_phieu
(vị trí: POST trahang.luu lần hai, dòng 64 của test)

Tests:    1 failed, 1 passed (4 assertions)
Duration: 27.67s
```

Đỏ đúng bản chất lỗi (unique/500 ở phiếu thứ hai), không phải đỏ do thiếu seam.
Commit nhóm test: `ccbe3dc`.

## 4. Sửa tối thiểu (yêu cầu 3)

Chỉ đổi `app/Http/Controllers/TraHangController.php` (+43/−3 dòng), giữ nguyên
định dạng in `TH + YmdHis + 2 chữ số` (18 ký tự, vừa cột VARCHAR(30)):

1. **`sinhMaPhieuTra()` (`:152-171`)** — sinh mã trong transaction của
   `postTraHang`: lấy các hậu tố đã dùng của giây hiện tại
   (`WHERE ma_phieu LIKE 'TH{YmdHis}%'`, dùng index unique), chọn điểm bắt đầu
   `random_int(10, 99)` rồi **quét tiến quay vòng 10..99 tối đa 90 bước** đến hậu
   tố trống. Nhờ quét tiến, kể cả khi RNG lặp lại giá trị (hoặc bị khóa) vẫn luôn
   ra mã khác — đây là phương án "hậu tố tăng dần theo giây" cho phép của yêu cầu.
   Hết 90 chỗ trong giây → `RuntimeException` (trần cứng của định dạng 2 chữ số,
   có test phủ).
2. **Vòng thử lại giới hạn khi vi phạm unique (`:89-100`)** — `PhieuTra::create`
   bọc trong vòng tối đa 5 lần; bắt `QueryException` và chỉ thử lại (sinh mã mới)
   khi `loiTrungMaPhieu()` (`:173-177`) xác nhận SQLSTATE `23000` + thông điệp
   chứa `ma_phieu`; lỗi khác hoặc hết lượt thì ném lại. Lớp này phòng trường hợp
   giao dịch đồng thời chèn cùng mã giữa lúc dò và lúc tạo (cửa sổ race mà phép
   dò trước không thấy). Trên cả MySQL/InnoDB lẫn SQLite, lỗi duplicate key chỉ
   hoàn tác câu lệnh chứ không hủy transaction, nên thử lại trong cùng
   `DB::transaction` là an toàn.

Không đổi cột, không migration, không đổi nghiệp vụ hoàn tiền/điểm/kho, không
đổi route. Các phương thức mới nằm cùng namespace `App\Http\Controllers` nên bộ
khóa RNG của test vẫn có hiệu lực sau sửa. Commit nhóm fix: `bbde0fd`.

## 5. Kiểm chứng sau sửa (yêu cầu 4)

| Lượt | Lệnh | Kết quả thật |
|---|---|---|
| Test mới sau sửa | `XDEBUG_MODE=off php artisan test --filter=TraHangTrungMaPhieuTest` | **2 passed (13 assertions), 5.38s** |
| Trả hàng lần 1 | `XDEBUG_MODE=off php artisan test tests/Feature/PosWorkflowTest.php --filter='return\|zero_cash'` | **11 passed (113 assertions), 32.40s** |
| Trả hàng lần 2 | như trên | **11 passed (113 assertions), 31.14s** |
| Trả hàng lần 3 | như trên | **11 passed (113 assertions), 32.58s** |
| Trả hàng lần 4 | như trên | **11 passed (113 assertions), 30.80s** |
| Trả hàng lần 5 | như trên | **11 passed (113 assertions), 27.63s** |
| Full suite cuối | `XDEBUG_MODE=off php artisan test` | **254 passed, 1 skipped (1323 assertions), 213.79s**, exit 0 |
| Cú pháp | `php -l app/Http/Controllers/TraHangController.php` | No syntax errors detected |
| Route | `php artisan route:list --json` | **104 routes** (không đổi) |

11 test trả hàng của `PosWorkflowTest` gồm: trả hàng tốt/hỏng một lần, vượt số
lượng, quá hạn, hóa đơn giảm giá + thu hồi điểm, nhiều lần trả cộng dồn, làm tròn
floor, tiền hoàn 0, chờ duyệt giữ chỗ, dòng trùng, hàng lỗi hoàn điểm, trả toàn
phần. **5/5 lượt liên tiếp xanh, không lượt nào đỏ** — flake đã hết. Full suite
tăng đúng 2 test/13 assertions so với đợt trước (252→254 pass, 1310→1323); skip
duy nhất vẫn là `KhoaDongMysqlTest` (cần bản sao MySQL).

## 6. Rủi ro còn lại / chưa làm

1. **KK/HN/PN chưa sửa**: `KiemKeController.php:32`, `HoaDonNhapController.php:140`,
   `NhapHangController.php:96` vẫn dùng mẫu ngẫu nhiên cũ, còn nguy cơ trùng
   trong cùng giây (tần suất nghiệp vụ thấp hơn phiếu trả). Cùng cơ chế sửa có
   thể áp dụng lại; cần đợt riêng nếu người dùng yêu cầu.
2. **Trần 90 phiếu trả/giây**: hết hậu tố trong giây sẽ báo lỗi có kiểm soát
   (500 + thông điệp rõ), không silent drop. Muốn trần cao hơn phải đổi định dạng
   mã (đổi schema/phiếu in) — ngoài phạm vi.
3. **Chuỗi HD theo ngày**: `taoMaHoaDon` đọc mã cuối `FOR UPDATE` rồi +1; hai
   giao dịch đồng thời khi chưa có mã nào trong ngày vẫn có cửa sổ race lý thuyết
   (đã có `DB::transaction(..., 2)` giảm thiểu). Không đụng tới trong đợt này.
4. Test mới khóa RNG bằng hàm cùng namespace — kỹ thuật test chuẩn của PHP,
   không sửa production; nếu sau này chuyển sinh mã sang service khác namespace
   thì phải chuyển seam tương ứng.

## 7. Commit (theo nhóm, trên nhánh — không merge/push)

| Nhóm | Commit | Nội dung |
|---|---|---|
| test: | `ccbe3dc` | Test tái hiện đỏ + bộ khóa RNG (`tests/Support/…`, `TraHangTrungMaPhieuTest`) |
| fix: | `bbde0fd` | `sinhMaPhieuTra` quét tiến + vòng thử lại unique trong `TraHangController` |
| docs: | (commit chứa báo cáo này) | Báo cáo này + README mục 9/17/18 + ghi chú nối ở báo cáo đợt ảnh URL |

Ba tệp untracked có sẵn từ trước (`docs/BAO_CAO_KIEM_TRA_LUONG_DA.md`,
`docs/BAO_CAO_XU_LY_OPERA_GX_VA_CMD_WINDOWS.md`,
`scripts/windows/chay-schedule-hidden.vbs`) không thuộc phạm vi đợt này — không commit.