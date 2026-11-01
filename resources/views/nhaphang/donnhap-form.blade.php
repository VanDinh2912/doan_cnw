@extends('layouts.master')

@section('title', 'Tạo đơn nhập')

@section('content')
<h1 class="h3 mb-3">Tạo đơn nhập hàng</h1>
<form method="POST" action="{{ route('nhaphang.donnhap.postthem') }}">@csrf
<div class="card mb-3"><div class="card-body row g-3">
<div class="col-md-6"><label class="form-label">Nhà cung cấp</label><select class="form-select" name="supplier_id" required><option value="">-- Chọn --</option>@foreach ($nhaCungCaps as $nhaCungCap)<option value="{{ $nhaCungCap->id }}">{{ $nhaCungCap->ten_nhacungcap }}</option>@endforeach</select></div>
<div class="col-md-3"><label class="form-label">Ngày dự kiến</label><input class="form-control" type="date" name="ngay_dukien"></div>
<div class="col-12"><label class="form-label">Ghi chú</label><textarea class="form-control" name="ghi_chu"></textarea></div>
</div></div>
<div class="card"><div class="card-body"><h2 class="h5">Sản phẩm</h2><p class="text-muted">Nhập ít nhất một dòng. Có thể thêm tối đa 10 dòng.</p>
@for ($i = 0; $i < 10; $i++)<div class="row g-2 mb-2"><div class="col-md-6"><select class="form-select" name="items[{{ $i }}][product_id]"><option value="">-- Sản phẩm --</option>@foreach ($sanPhams as $sanPham)<option value="{{ $sanPham->id }}">{{ $sanPham->sku }} - {{ $sanPham->ten_sanpham }}</option>@endforeach</select></div><div class="col-md-2"><input class="form-control" type="number" min="1" name="items[{{ $i }}][soluong_dat]" placeholder="Số lượng"></div><div class="col-md-3"><input class="form-control" type="number" min="0" step="0.01" name="items[{{ $i }}][don_gia_nhap]" placeholder="Giá nhập"></div></div>@endfor
<a class="btn btn-secondary mt-3" href="{{ route('nhaphang.donnhap') }}">Hủy</a> <button class="btn btn-primary mt-3">Tạo đơn</button>
</div></div></form>
@endsection
