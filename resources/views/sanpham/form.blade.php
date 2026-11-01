@extends('layouts.master')

@section('title', $sanPham->exists ? 'Sửa sản phẩm' : 'Thêm sản phẩm')

@section('content')
<h1 class="h3 mb-3">{{ $sanPham->exists ? 'Sửa sản phẩm' : 'Thêm sản phẩm' }}</h1>
<div class="card">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" action="{{ $sanPham->exists ? route('sanpham.postsua', $sanPham) : route('sanpham.postthem') }}">
            @csrf
            @if ($sanPham->exists) @method('PUT') @endif
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="ten_sanpham">Tên sản phẩm</label>
                    <input class="form-control" id="ten_sanpham" name="ten_sanpham" value="{{ old('ten_sanpham', $sanPham->ten_sanpham) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="sku">SKU</label>
                    <input class="form-control" id="sku" name="sku" value="{{ old('sku', $sanPham->sku) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="barcode">Barcode</label>
                    <input class="form-control" id="barcode" name="barcode" value="{{ old('barcode', $sanPham->barcode) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="category_id">Loại sản phẩm</label>
                    <select class="form-select" id="category_id" name="category_id" required>
                        <option value="">-- Chọn loại --</option>
                        @foreach ($loais as $loai)
                            <option value="{{ $loai->id }}" @selected(old('category_id', $sanPham->category_id) == $loai->id)>{{ $loai->ten_loai }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="don_vi">Đơn vị</label>
                    <input class="form-control" id="don_vi" name="don_vi" value="{{ old('don_vi', $sanPham->don_vi ?: 'Cái') }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="hinh_anh">Hình ảnh</label>
                    <input class="form-control" id="hinh_anh" name="hinh_anh" type="file" accept="image/*">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="gia_nhap">Giá nhập</label>
                    <input class="form-control" id="gia_nhap" name="gia_nhap" type="number" min="0" value="{{ old('gia_nhap', $sanPham->gia_nhap ?: 0) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="gia_ban">Giá bán</label>
                    <input class="form-control" id="gia_ban" name="gia_ban" type="number" min="0" value="{{ old('gia_ban', $sanPham->gia_ban ?: 0) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="soluong">Số lượng</label>
                    <input class="form-control" id="soluong" name="soluong" type="number" min="0" value="{{ old('soluong', $sanPham->soluong ?: 0) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="soluong_toithieu">Tồn tối thiểu</label>
                    <input class="form-control" id="soluong_toithieu" name="soluong_toithieu" type="number" min="0" value="{{ old('soluong_toithieu', $sanPham->soluong_toithieu ?: 5) }}" required>
                </div>
                <div class="col-12 form-check ms-2">
                    <input class="form-check-input" id="trang_thai" name="trang_thai" type="checkbox" value="1" @checked(old('trang_thai', $sanPham->trang_thai))>
                    <label class="form-check-label" for="trang_thai">Đang kinh doanh</label>
                </div>
            </div>
            <div class="mt-4">
                <a class="btn btn-secondary" href="{{ route('sanpham.danhsach') }}">Hủy</a>
                <button class="btn btn-primary" type="submit">Lưu</button>
            </div>
        </form>
    </div>
</div>
@endsection
