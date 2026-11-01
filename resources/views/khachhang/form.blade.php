@extends('layouts.master')

@section('title', $khachHang->exists ? 'Sửa khách hàng' : 'Thêm khách hàng')

@section('content')
<h1 class="h3 mb-3">{{ $khachHang->exists ? 'Sửa khách hàng' : 'Thêm khách hàng' }}</h1>
<form class="card card-body" method="POST" action="{{ $khachHang->exists ? route('khachhang.postsua', $khachHang) : route('khachhang.postthem') }}">
    @csrf
    @if ($khachHang->exists) @method('PUT') @endif
    <div class="mb-3">
        <label class="form-label" for="ho_ten">Họ tên</label>
        <input class="form-control" id="ho_ten" name="ho_ten" value="{{ old('ho_ten', $khachHang->ho_ten) }}" required>
    </div>
    <div class="mb-3">
        <label class="form-label" for="so_dienthoai">Số điện thoại</label>
        <input class="form-control" id="so_dienthoai" name="so_dienthoai" value="{{ old('so_dienthoai', $khachHang->so_dienthoai) }}" required>
    </div>
    <div class="mb-3">
        <label class="form-label" for="dia_chi">Địa chỉ</label>
        <input class="form-control" id="dia_chi" name="dia_chi" value="{{ old('dia_chi', $khachHang->dia_chi) }}">
    </div>
    <div>
        <a class="btn btn-secondary" href="{{ route('khachhang.danhsach') }}">Hủy</a>
        <button class="btn btn-primary" type="submit">Lưu</button>
    </div>
</form>
@endsection
