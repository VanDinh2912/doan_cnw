# Dữ liệu kiểm thử import sản phẩm (Excel)

Hai file `.xlsx` để kiểm thử thủ công luồng import sản phẩm
(`/sanpham/import-excel`) trên môi trường dev.

> ⚠️ **CHỈ upload trên `doan_cnw_dev`. KHÔNG upload vào `doan_cnw` thật**
> vì file 120 dòng sẽ tạo 120 sản phẩm thật ở trạng thái NGỪNG BÁN
> (tồn 0, giá nhập 0). SKU `IMP-0001`..`IMP-0120` và `ERR-xxxx` không
> trùng `products.sku`/`products.barcode` trên cả hai DB (đã kiểm tra
> 2026-11).

## File

| File | Nội dung | Kỳ vọng preview |
|---|---|---|
| `import_sanpham_120_dong.xlsx` | 120 dòng hợp lệ, SKU `IMP-0001`..`IMP-0120` | Tạo mới 120, cập nhật 0, lỗi 0. Mỗi dòng có cảnh báo "Sẽ tạo ở trạng thái NGỪNG BÁN, tồn 0, giá nhập 0 — cần nhập kho rồi bật 'Đang bán'". |
| `import_sanpham_LOI_mau.xlsx` | 8 dòng, cố ý sai | `ok=false`, **không có nút xác nhận**. Lỗi đúng 7 dòng (3 đến 9): dòng 3+4 trùng SKU `ERR-0002`; dòng 5 hãng `Hocoo` không tồn tại; dòng 6 giá bán `abc` không phải số; dòng 7 mã loại `ZZZ` không tồn tại; dòng 8 thiếu SKU; dòng 9 SKU `=1+1` chứa ký tự không hợp lệ hoặc bắt đầu bằng = + - @. Dòng 2 (`ERR-0001`) hợp lệ. |

### Lưu ý về dòng 9 file lỗi

Dòng 9 có SKU `=1+1` (lưu dạng text). Từ khi siết validate, service
**chặn** SKU/barcode không khớp `^[A-Za-z0-9][A-Za-z0-9._/-]{0,49}$`
(ký tự đầu không được là `= + - @`, tab, xuống dòng hay ký tự điều
khiển) — đây là lỗi dòng 9 trong preview. Khi export lại, cột sku được
escape formula injection nên không thành công thức trong Excel.

## Mã loại (danh_muc) đã dùng

`CAP` (Cáp sạc), `BCS` (Bộ củ sạc kèm cáp) — cả hai tồn tại trong
`categories.ma_loai` của `doan_cnw_dev`. Hãng dùng: `Hoco`, `Borofone`,
`Remax` (tồn tại và `kich_hoat=1`).

**Điều kiện trước khi test:** bảng `brands` trên `doan_cnw_dev` phải có
3 hãng `Hoco`, `Borofone`, `Remax` đang kích hoạt — import tra hãng theo
`ten` nên ID dev lệch so với prod không sao (dev hiện có id 2/3/4). Nếu
`brands` trống, preview sẽ báo "hãng không tồn tại" cho cả 2 file.

## Cách dùng

1. Bật env trỏ `doan_cnw_dev` (`DB_DATABASE=doan_cnw_dev` trong `.env`
   hoặc `.env.dev` tuỳ cấu hình), đăng nhập admin.
2. Vào `/sanpham/import-excel` → tải file → xem preview.
3. **Không bấm "Xác nhận import"** trừ khi muốn tạo thật trên dev.

Kỳ vọng đã verify qua service trực tiếp (`docFile` + `lapKeHoach`) trên
`doan_cnw_dev` — không ghi DB, products giữ nguyên 300. Để test qua
trình duyệt: đăng nhập admin trên env dev, upload từng file ở bước 2
và đối chiếu preview với bảng "Kỳ vọng preview" ở trên (file lỗi phải
không có nút xác nhận).

## Dọn dev sau khi test (nếu đã lỡ import)

```sql
-- Xoa 120 SP mau (chi chay tren doan_cnw_dev)
DELETE FROM products WHERE sku LIKE 'IMP-%' OR sku LIKE 'ERR-%';
```

Sau đó vào `/sanpham` tắt/bật lại nếu cần; `activity_logs` giữ nguyên
bản ghi `import_san_pham` (append-only, không xóa).

## Cập nhật M2 (import linh hoạt)

Từ M2, import sản phẩm khớp cột theo **tên tiêu đề chuẩn hóa** (không phân
biệt hoa/thường/dấu/khoảng trắng/gạch) + bí danh trong `config/import_sp.php`.
Chỉ `sku` bắt buộc; ô trống = không đổi; cột hệ thống (`soluong`,
`trang_thai`, `gia_nhap`...) và cột lạ bị bỏ qua + liệt kê trong preview.
File xuất từ hệ thống nhập lại được nguyên trạng.

| File mới | Nội dung | Kỳ vọng |
|---|---|---|
| `import_sanpham_chi_gia.xlsx` | Chỉ 2 cột `sku, gia_ban` — cập nhật giá hàng loạt | SKU `IMP-GIA-01/02` chưa tồn tại → tạo mới sẽ báo lỗi thiếu trường (ten_sanpham/danh_muc/don_vi). Để test cập nhật giá thật: đổi SKU thành SKU sản phẩm có sẵn trên dev trước khi upload. |
| `import_sanpham_linh_hoat.xlsx` | Cột đảo thứ tự, tên tiếng Việt ("Mã SP"/"Tên"/"Danh mục"/"Giá"/"ĐVT"), cột lạ "ghi chú nội bộ" + "Số lượng" | Preview: cột lạ + `Số lượng` bị bỏ qua (Số lượng = hệ thống quản lý); tạo mới 2 SP `IMP-LINH-01/02` trạng thái NGỪNG BÁN. |

Lưu ý: `import_sanpham_chi_gia.xlsx` chỉ đúng nghĩa "cập nhật giá" khi SKU
đã tồn tại — với SKU mới nó minh họa lỗi "thiếu trường bắt buộc khi tạo mới".

## Cập nhật GD1 (ảnh qua URL)

Thêm cột tùy chọn `hinh_anh_url` (bí danh: "Ảnh", "URL ảnh", "Hình ảnh"...).
URL chỉ được KIỂM ĐỊNH DẠNG ở preview; ảnh thật chỉ tải về + lưu cục bộ
(`sanpham/anh-<sha256>.<ext>`, GD re-encode, bỏ metadata) khi XÁC NHẬN.
Chỉ jpg/png/webp ≤2MB, mỗi cạnh ≤6000px, http/https cổng 80/443, chặn IP
nội bộ/SSRF; tối đa 20 ảnh/lượt; URL trống = giữ ảnh cũ; lỗi 1 ảnh chỉ cảnh
báo, sản phẩm vẫn nhập. Cần DB có 3 cột `hinh_anh_nguon/sha256/tai_luc`
(migration `2026_11_24_000001`) — thiếu cột thì cột này bị bỏ qua.

| File mới | Nội dung | Kỳ vọng |
|---|---|---|
| `import_sanpham_co_anh_url.xlsx` | 2 dòng: `IMP-ANH-01` có `hinh_anh_url` trỏ `https://picsum.photos/...` (URL thật công khai, test thủ công), `IMP-ANH-02` để trống | Preview: không gọi mạng. Xác nhận: tải ảnh về local cho dòng 1, dòng 2 không ảnh. Ảnh lỗi (mất mạng/URL chết) vẫn tạo SP, chỉ cảnh báo. |
