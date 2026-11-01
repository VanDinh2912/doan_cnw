@extends('layouts.master')

@section('title', 'Nhập kho')

@section('content')
<h1 class="h3 mb-3">Nhập kho</h1>
<div class="card"><div class="card-body">
    <form method="POST" action="{{ route('kho.postnhapkho') }}">
        @csrf
        <div class="mb-3"><label class="form-label" for="product_id">Sản phẩm</label>
            <select class="form-select" id="product_id" name="product_id" required><option value="">-- Chọn sản phẩm --</option>
                @foreach ($sanPhams as $sanPham)<option value="{{ $sanPham->id }}" @selected(old('product_id') == $sanPham->id)>{{ $sanPham->sku }} - {{ $sanPham->ten_sanpham }} (tồn {{ $sanPham->soluong }})</option>@endforeach
            </select>
        </div>
        <div class="row g-3"><div class="col-md-4"><label class="form-label" for="soluong">Số lượng nhập</label><input class="form-control" id="soluong" name="soluong" type="number" min="1" value="{{ old('soluong', 1) }}" required></div>
            <div class="col-md-4"><label class="form-label" for="gia_nhap">Giá nhập</label><input class="form-control" id="gia_nhap" name="gia_nhap" type="number" min="0" value="{{ old('gia_nhap', 0) }}" required></div>
        </div>
        <div class="mt-3"><label class="form-label" for="ghi_chu">Ghi chú</label><textarea class="form-control" id="ghi_chu" name="ghi_chu" rows="2">{{ old('ghi_chu') }}</textarea></div>
        <a class="btn btn-secondary mt-3" href="{{ route('kho.danhsach') }}">Hủy</a> <button class="btn btn-primary mt-3" type="submit">Lưu nhập kho</button>
    </form>
</div></div>
@endsection
