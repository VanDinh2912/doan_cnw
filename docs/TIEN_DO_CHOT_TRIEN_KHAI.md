# TIẾN ĐỘ — CHỐT TRIỂN KHAI (làm trực tiếp trên `main`)

Cập nhật sau mỗi hạng mục. Đọc file này đầu tiên khi tiếp tục.

## Trạng thái tổng

- [x] Bước 0 — git sạch trên `main`, stash rỗng, tag `backup/main-truoc-p3-huy-hoan-tien-20261116` tồn tại.
- Baseline full suite: đang chạy nền (`php artisan test --compact`), kết quả sẽ ghi khi xong.
- [x] HM1 — ma trận vai trò × thao tác màn hình đơn online: server + UI đã đúng sẵn
  (routes `role:` + CheckRole 403; view ẩn nút pickup cho thuko, ẩn hủy-đã-TT cho
  non-admin). Thêm `tests/Feature/DonHangOnlinePhanQuyenTest.php` — 4 test / 83
  assertions xanh. KHÔNG đổi code nghiệp vụ (không cần).
- [x] HM2 — tốc độ bộ test: phpunit.xml đã tối ưu sẵn; profile không có điểm
  nghẽn đơn lẻ (artifact profiler); cài `brianium/paratest 7.8.5` --dev →
  `vendor/bin/paratest` full suite 780 test / 3418 assertions / 44 skip / 0 fail
  trong 447.77s (~5× nhanh hơn serial ~2326s). Lưu ý: `artisan test --parallel`
  lỗi do Collision — dùng `vendor/bin/paratest` trực tiếp.
- [x] HM3 — merge `feat/sepay-doi-soat` --no-ff (`912dbbb`): xung đột chỉ README
  (giữ cả hai); thêm guard hasColumn migration ma_tham_chieu + guard runtime
  WebhookController/SepayDoiSoat. Full suite + MySQL-copy xanh trước commit.
- [x] HM4 — `docs/SO_TAY_TRIEN_KHAI.md` (migration chờ áp, quy trình, env, guard,
  dựng máy, bật cờ 8 bước).
- [x] HM5 — README 9/17/18 + `docs/BAO_CAO_CHOT_TRIEN_KHAI.md` + git status.

## Kết quả cuối

- Full suite (paratest): 736 pass / 44 skip / 0 fail / 3418 assertions / 447.77s.
- MySQL-copy: 44 pass / 364 assertions / 14.23s.
- Commits: 58a4110 → 527c29f → 912dbbb → docs. Không push.

## Lệnh tiếp tục

```powershell
cd <thư-mục-dự-án>
$env:XDEBUG_MODE='off'; php artisan test --compact
# MySQL-copy (filter CHÍNH XÁC):
$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='doan_cnw_copy'; $env:DB_USERNAME='root'; $env:DB_PASSWORD=''
php artisan test --compact --filter='MysqlCopyTest|KhoaDongMysqlTest'
```
