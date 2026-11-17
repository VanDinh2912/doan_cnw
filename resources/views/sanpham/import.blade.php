@extends('layouts.master')

@section('title', 'Import sản phẩm Excel')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Import sản phẩm từ Excel</h1>
        <p class="page-subtitle">Tạo mới / cập nhật thông tin sản phẩm — tồn kho và giá nhập không bị ảnh hưởng</p>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('sanpham.danhsach') }}">
        <i class="fa-solid fa-arrow-left"></i>
        <span>Quay lại danh sách</span>
    </a>
</div>

<div class="card" style="max-width: 760px;">
    <div class="card-body">
        <div class="alert alert-info">
            <strong>Quy trình 2 bước an toàn:</strong> tải file lên → hệ thống kiểm tra toàn bộ và hiển thị bản xem trước → bạn xác nhận mới ghi dữ liệu. File có bất kỳ dòng lỗi nào sẽ <strong>không</strong> được ghi.
        </div>

        <h6 class="fw-semibold mb-2">Cột bắt buộc trong file (đúng tên, không phân biệt hoa thường):</h6>
        <p class="font-monospace text-muted mb-3">sku, barcode, ten_sanpham, danh_muc, hang, don_vi, gia_ban</p>
        <ul class="text-muted small mb-3">
            <li><strong>sku</strong>: bắt buộc, là khóa nhận diện — trùng SKU trong hệ thống = cập nhật, chưa có = tạo mới.</li>
            <li><strong>danh_muc</strong>: mã loại sản phẩm (ví dụ CAP, CS, PIN) — xem tại trang Loại sản phẩm.</li>
            <li><strong>hang</strong>: tên hãng phải trùng hãng đã có và đang kích hoạt (Hoco, Borofone, Remax). Để trống khi tạo mới = không gán hãng; để trống khi cập nhật = giữ nguyên hãng hiện tại.</li>
            <li><strong>gia_ban</strong>: số nguyên không âm (VNĐ).</li>
            <li>Sản phẩm <strong>tạo mới</strong> sẽ ở trạng thái <strong>NGỪNG BÁN</strong>, tồn 0, giá nhập 0 — cần nhập kho rồi bật "Đang bán" ở form sửa sản phẩm.</li>
            <li><strong>Không</strong> import: tồn kho (phải qua phiếu nhập kho), giá nhập, trạng thái bán.</li>
        </ul>

        <a class="btn btn-outline-primary btn-sm mb-3" href="{{ route('sanpham.importmau') }}">
            <i class="fa-solid fa-file-excel"></i>
            <span>Tải file mẫu (.xlsx)</span>
        </a>

        <form method="POST" action="{{ route('sanpham.import.upload') }}" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-semibold" for="file">File Excel (.xlsx, tối đa 2MB, tối đa 1000 dòng)</label>
                <input class="form-control" type="file" id="file" name="file" accept=".xlsx" required>
                @error('file')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>
            <button class="btn btn-primary" type="submit">
                <i class="fa-solid fa-eye"></i>
                <span>Tải lên &amp; xem trước</span>
            </button>
        </form>
    </div>
</div>
@endsection
