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
