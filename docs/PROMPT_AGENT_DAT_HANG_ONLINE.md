# Prompt cho agent: triển khai đặt hàng online (Web POS)

> Cách dùng: dán toàn bộ phần bên dưới đường kẻ vào agent lập trình (Claude Code, Codex...). Mỗi lượt chỉ đổi dòng **PHA CỦA LƯỢT NÀY** ở mục 2. Đặt 3 tài liệu `README.md`, `BAO_CAO_LUONG_DAT_HANG.md`, `THIET_KE_DAT_HANG_ONLINE.md` trong `docs/` của repo trước khi chạy.

---

# VAI TRÒ

Bạn là kỹ sư backend Laravel 12 / PHP 8.4 làm việc trong repo **Web POS & Quản lý kho** (đồ án, cửa hàng phụ kiện điện thoại). Nhiệm vụ: **triển khai từng pha** của tính năng "đặt hàng online tối thiểu" (khách vãng lai + khách đăng nhập, thanh toán VietQR/SePay hoặc trả tiền tại quầy) theo thiết kế đã có. Bạn không thiết kế lại; bạn triển khai đúng thiết kế, có test, an toàn với DB thật.

# 1. ĐỌC TRƯỚC KHI LÀM (bắt buộc, theo thứ tự)

1. `docs/THIET_KE_DAT_HANG_ONLINE.md`: nguồn sự thật về thiết kế (mục 2 tái sử dụng R1-R15, mục 3 luồng, mục 5-6 dữ liệu/migration, mục 7 đe dọa T1-T13, mục 8 pha P0-P5, mục 9 test T-DH01..26, mục 10 handoff FE).
2. `docs/BAO_CAO_LUONG_DAT_HANG.md`: hiện trạng có bằng chứng (lỗ hổng L1-L9, schema DB thật).
3. `README.md`: mục 9 (kiểm thử), 13 (Git), 15-17 (lộ trình, bảo mật, giới hạn đã biết).

Số dòng `file:dòng` trong tài liệu có thể đã lệch. **Đọc lại mã thực tế** của từng R-thành phần trước khi sửa hoặc gọi; không suy ra từ tài liệu.

# 2. PHẠM VI LƯỢT NÀY

**PHA CỦA LƯỢT NÀY: P0**

Chỉ làm đúng pha ghi ở trên theo bảng mục 8 của tài liệu thiết kế (nội dung, test chính, tiêu chí hoàn thành). Không làm trước pha sau. Khi xong, **dừng và báo cáo**; không tự chuyển sang pha kế tiếp. Thứ tự phụ thuộc: P0 → P1 → {P2, P3} → P4 → P5.

Ngoài phạm vi toàn dự án (không làm, không thiết kế): giao hàng/vận chuyển, phí ship, Zalo/SMS, sàn TMĐT, hóa đơn điện tử, hoàn tiền tự động, mã khuyến mãi, email xác nhận đơn.

# 3. QUYẾT ĐỊNH ĐÃ CHỐT (dùng đề xuất mặc định của tài liệu, mục 11)

- Chỉ pickup tại cửa hàng, không giao hàng (D1). Hỗ trợ cả QR và trả tại quầy (D2). Cho guest đặt bằng tên + SĐT (D3).
- Hạn giữ kho: QR 30 phút, pickup 24 giờ, đặt qua `config/pos.php` (D4).
- Thu ngân xác nhận pickup; admin xử lý đối soát (D5).
- **v1 không cho dùng điểm online**; chỉ cộng điểm nhận khi `da_thanh_toan` và có `customer_id` (D6). Không tự gắn ngược đơn guest vào hồ sơ khi khách đăng ký cùng SĐT (D8).
- Dashboard `so_hoa_don` chỉ đếm `da_thanh_toan` (D9). Honeypot ẩn, chưa captcha (D10). Ngưỡng 3 đơn mở/SĐT, 10 SP/dòng, 5 đơn/phút/IP (D12).
- Nếu gặp điểm tài liệu chưa quyết hoặc mâu thuẫn với mã thực tế: **dừng, nêu vấn đề kèm phương án đề xuất**; không tự chọn im lặng.

# 4. NGUYÊN TẮC KỸ THUẬT BẮT BUỘC

1. **Tái sử dụng tối đa.** Đơn online là bản ghi trong bảng `invoices` (Phương án 1). Dùng lại `ThanhToanChuyenKhoanService` (giữ kho, hoàn tất idempotent, hủy hoàn kho/điểm), `VietQrService`, `WebhookController`, `HuyHoaDonQuaHan`, `ThongBaoService`, `NhatKyService`. Chỉ sửa tối thiểu như cột "Sửa cần thiết" ở mục 2 tài liệu thiết kế.
2. **Không thêm giá trị vào bất kỳ enum nào** (`invoices.trang_thai`, `phuong_thuc`, `users.role`, `stock_movements.loai`). Dùng đúng 3 trạng thái `cho_thanh_toan / da_thanh_toan / da_huy`; phân biệt bằng `kenh` + `phuong_thuc`.
3. **Server là nguồn sự thật** về giá, tồn kho, trạng thái. Bỏ qua mọi trường tiền/`customer_id`/trạng thái từ request. Checkout tính lại từ DB trong `DB::transaction(..., 2)` với `lockForUpdate` sản phẩm theo id tăng dần.
4. **Không tin giỏ session** khi checkout (giỏ chỉ để hiển thị; hiện còn lỗ hổng L2/L3).
5. **Bảo mật theo mô hình đe dọa mục 7** (T1-T13). Mỗi tính năng mới phải nêu rõ nó chặn T-nào và có test tương ứng. Đặc biệt: escape `ghi_chu_khach` khi render **và** trước khi ghép vào tin Telegram HTML (L9); không dùng `{!! !!}` cho dữ liệu khách; form POST có `@csrf`; che SĐT trong log/nhật ký (`090****567`); lỗi tra cứu dùng thông báo chung, không phân biệt sai mã hay sai SĐT.
6. **Ngôn ngữ:** thông báo lỗi/validation hiển thị cho người dùng bằng tiếng Việt, theo phong cách các controller hiện có.
7. **Theo convention sẵn có** của repo (tên controller/model/route tiếng Việt không dấu như `DonHang`, `donhang.*`, `storefront.*`). Đọc vài file lân cận trước khi đặt tên.

# 5. AN TOÀN DỮ LIỆU VÀ MÔI TRƯỜNG (tuyệt đối)

- **Không đọc, ghi hay in nội dung `.env`.** Cần cấu hình tạm thì truyền biến môi trường theo tiến trình.
- **Không chạm DB thật `doan_cnw`.** Test chạy trên SQLite `:memory:` theo `phpunit.xml`. Muốn thử MySQL chỉ dùng bản sao `doan_cnw_copy` (tên kết thúc `_copy`, theo cơ chế của `AnhUrlMysqlCopyTest`). Không bao giờ chạy `migrate:fresh`, `demo:reset`, `migrate:rollback` hay import dữ liệu mẫu trên dữ liệu thật.
- **Không chạy migration trên MySQL thật.** Bạn chỉ viết migration và chứng minh trên SQLite + bản sao; việc áp lên production do người vận hành làm sau khi backup (`sao-luu:csdl`). Ghi rõ quy trình áp và rollback trong báo cáo.
- DB thật có **0 khóa ngoại** (L7) và `customers.dia_chi` là VARCHAR(150) (L8). Đừng giả định có FK; đừng tạo FK mới cho bảng cũ.
- **Không commit** file tạm, evidence, hay secret. Không đưa số tài khoản, API key, token vào code/log/báo cáo.
- Môi trường dev là Windows/PowerShell: chạy test bằng
  `$env:XDEBUG_MODE='off'; php artisan test --compact; Remove-Item Env:XDEBUG_MODE`

# 6. QUY TRÌNH LÀM VIỆC

1. `git status` sạch. Tạo nhánh riêng cho pha từ nhánh nền do người dùng chỉ định, tên dạng `feat/dat-hang-p0-...`. Không push, không merge, không đụng `main`.
2. Chạy **full suite trước khi sửa** và ghi lại baseline (số test pass/skip, hiện README ghi 376 test: 338 pass, 38 skip, nhưng hãy lấy số thật).
3. Viết **test đỏ trước** theo danh sách T-DH của pha (mục 9 tài liệu). Xác nhận chúng fail đúng lý do rồi mới viết mã.
4. Triển khai tối thiểu để test xanh. Giữ diff nhỏ, không refactor ngoài phạm vi, không sửa test hiện có để cho qua (nếu buộc phải đổi kỳ vọng của test cũ vì thiết kế yêu cầu, nêu rõ lý do từng test).
5. Chạy lại **full suite**: phải xanh, không giảm số test, không tăng số skip ngoài các test MySQL-copy đã có.
6. Tự rà soát diff theo checklist mục 7 dưới đây.
7. Commit theo `feat:`/`test:`/`docs:` (tiếng Việt không dấu như lịch sử repo), mỗi commit một ý.

# 7. CHECKLIST TỰ RÀ SOÁT TRƯỚC KHI BÁO XONG

- [ ] Không có giá trị enum mới; migration chỉ thêm cột nullable/default và nullable hóa `user_id`; `down()` có điều kiện và ghi chú rủi ro khi còn dòng `user_id` null.
- [ ] Mọi nơi đọc `invoices.user_id` (view in hóa đơn, danh sách đơn, báo cáo, nhật ký, trả hàng) chịu được `null`, hiển thị "Đơn online".
- [ ] POS cũ không đổi hành vi: các test POS/webhook/trả hàng/báo cáo hiện có vẫn xanh nguyên trạng.
- [ ] Không có chỗ nào nhận giá, tổng tiền, `customer_id`, `user_id`, trạng thái từ request của guest.
- [ ] Mọi dữ liệu khách nhập đều được escape khi render và khi ghép Telegram; không có PII đầy đủ trong log.
- [ ] Route mới công khai có throttle và CSRF đúng như tài liệu; route quản trị nằm trong nhóm `auth + staff.only + role:admin,thungan` sẵn có.
- [ ] Mỗi test T-DH của pha tồn tại và đã từng đỏ trước khi xanh.
- [ ] Không file tạm, secret, `.env` nào lọt vào commit (`git diff --stat` kiểm tra).

# 8. ĐIỂM CẦN CẨN TRỌNG ĐÃ PHÁT HIỆN KHI ĐỌC TÀI LIỆU

Xử lý các điểm này một cách bảo thủ và báo lại trong báo cáo:

- **Endpoint polling `/don-hang/{ma_tra_cuu}/trang-thai` đang ghi tham số `sdt` trong query** (mục 10 thiết kế). Điều này đưa SĐT vào URL/log truy cập, mâu thuẫn T5. Đề xuất: sau khi tra cứu hợp lệ, lưu bằng chứng đã xác thực vào session (hoặc gửi SĐT bằng POST) thay vì để SĐT trên URL. Nếu thuộc pha bạn đang làm, chọn phương án an toàn và nêu rõ; nếu không, ghi lại cho pha sau.
- **Hai cột mã giao dịch song song** (`ma_giaodich` 100 ký tự, `ma_giao_dich` 40 ký tự, đều unique): service đang ghi cả hai. Không gộp hay bỏ cột; mọi đường tạo/hoàn tất đơn mới phải giữ hành vi ghi cả hai.
- **Webhook không kiểm tra hạn** (README mục 17, test T-CK18): đơn quá hạn nhưng chưa bị hủy vẫn được hoàn tất khi tiền về. Đừng đổi hành vi này; hạn theo đơn (`han_giu`) chỉ ảnh hưởng đến scheduler/hủy lười (R7).
- **Giữ kho ngay khi tạo đơn chờ** làm `products.soluong` giảm; đừng thêm bước trừ kho lần hai khi hoàn tất.
- **`GET /gio-hang/them/{sp}` và `/xoa/{sp}` đổi trạng thái không CSRF** (L4): chỉ đổi sang POST ở P5; đừng đổi sớm khi FE hiện tại còn dùng link GET.
- Số dòng, tên hàm và đường dẫn trong tài liệu có thể đã lệch so với nhánh bạn đang làm. Khi lệch, tin mã thực tế và ghi nhận sai khác.

# 9. ĐỊNH DẠNG BÁO CÁO KHI XONG (ngắn, có bằng chứng)

1. **Tóm tắt** (3-5 dòng): làm gì, nhánh nào, commit nào.
2. **Kết quả test**: baseline trước → sau (số pass/skip/assertions), danh sách test T-DH mới và trạng thái; dán đoạn kết quả `php artisan test` thật.
3. **Thay đổi theo file**: bảng `file → thay đổi → T/R liên quan`.
4. **Migration**: nội dung, kết quả up/down trên SQLite (và bản sao MySQL nếu có chạy), lệnh/quy trình áp lên production cho người vận hành.
5. **Sai khác so với thiết kế** và lý do (nếu có), kèm các điểm ở mục 8 đã xử lý ra sao.
6. **Việc chưa làm / rủi ro còn lại / câu hỏi cần người dùng quyết**.
7. Xác nhận từng dòng checklist mục 7 là đạt hay không đạt; không đạt thì nói rõ.

Không tuyên bố "xong" nếu full suite chưa xanh hoặc một mục checklist chưa đạt. Báo cáo trung thực những gì chưa kiểm chứng được.

# 10. KHI BẠN KHÔNG CHẮC

Dừng và hỏi (nêu vấn đề, 2-3 phương án, đề xuất của bạn) thay vì đoán, nhất là với: thay đổi cấu trúc DB, thay đổi hành vi POS/webhook đang chạy, phạm vi pha, hoặc bất kỳ việc gì có thể chạm dữ liệu thật.
