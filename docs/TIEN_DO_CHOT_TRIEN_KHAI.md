# TIẾN ĐỘ — CHỐT TRIỂN KHAI (làm trực tiếp trên `main`)

Cập nhật sau mỗi hạng mục. Đọc file này đầu tiên khi tiếp tục.

## Trạng thái tổng

- [x] Bước 0 — git sạch trên `main`, stash rỗng, tag `backup/main-truoc-p3-huy-hoan-tien-20261116` tồn tại.
- Baseline full suite: đang chạy nền (`php artisan test --compact`), kết quả sẽ ghi khi xong.
- [x] HM1 — ma trận vai trò × thao tác màn hình đơn online: server + UI đã đúng sẵn
  (routes `role:` + CheckRole 403; view ẩn nút pickup cho thuko, ẩn hủy-đã-TT cho
  non-admin). Thêm `tests/Feature/DonHangOnlinePhanQuyenTest.php` — 4 test / 83
  assertions xanh. KHÔNG đổi code nghiệp vụ (không cần).
- [ ] HM2 — tốc độ bộ test: phpunit.xml đã tối ưu sẵn (BCRYPT_ROUNDS=4,
  CACHE/SESSION=array, QUEUE=sync). Chờ baseline xong → chạy `--profile` →
  tối ưu an toàn → đo lại. ParaTest: chưa cài (composer.json không có;
  chỉ là gợi ý của package khác) — sẽ thử require --dev nếu mạng cho phép,
  nếu không ghi rõ.
- [ ] HM3 — đưa `feat/sepay-doi-soat` vào main.
- [ ] HM4 — `docs/SO_TAY_TRIEN_KHAI.md`.
- [ ] HM5 — hoàn tất: README 9/17/18, `docs/BAO_CAO_CHOT_TRIEN_KHAI.md`,
  git status cuối.

## Lệnh tiếp tục

```powershell
cd D:\Study\xampp\www\DA
$env:XDEBUG_MODE='off'; php artisan test --compact
# MySQL-copy (filter CHÍNH XÁC):
$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='doan_cnw_copy'; $env:DB_USERNAME='root'; $env:DB_PASSWORD=''
php artisan test --compact --filter='MysqlCopyTest|KhoaDongMysqlTest'
```
