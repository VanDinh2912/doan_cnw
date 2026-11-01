@extends('layouts.master')

@section('title', $nhaCungCap->exists ? 'Sửa nhà cung cấp' : 'Thêm nhà cung cấp')

@section('content')
<h1 class="h3 mb-3">@yield('title')</h1>
<form method="POST" action="{{ $nhaCungCap->exists ? route('nhaphang.nhacungcap.postsua', $nhaCungCap) : route('nhaphang.nhacungcap.postthem') }}">
    @csrf @if ($nhaCungCap->exists) @method('PUT') @endif
    <div class="card"><div class="card-body">
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Tên nhà cung cấp</label><input class="form-control" name="ten_nhacungcap" value="{{ old('ten_nhacungcap', $nhaCungCap->ten_nhacungcap) }}" required></div>
            <div class="col-md-6"><label class="form-label">Mã số thuế</label><input class="form-control" name="ma_so_thue" value="{{ old('ma_so_thue', $nhaCungCap->ma_so_thue) }}" required></div>
            <div class="col-md-6"><label class="form-label">Người liên hệ</label><input class="form-control" name="nguoi_lienhe" value="{{ old('nguoi_lienhe', $nhaCungCap->nguoi_lienhe) }}"></div>
            <div class="col-md-6"><label class="form-label">Số điện thoại</label><input class="form-control" name="so_dienthoai" value="{{ old('so_dienthoai', $nhaCungCap->so_dienthoai) }}"></div>
            <div class="col-md-6"><label class="form-label">Địa chỉ</label><input class="form-control" name="dia_chi" value="{{ old('dia_chi', $nhaCungCap->dia_chi) }}"></div>
            <div class="col-12"><label class="form-label">Ghi chú</label><textarea class="form-control" name="ghi_chu">{{ old('ghi_chu', $nhaCungCap->ghi_chu) }}</textarea></div>
        </div>
        <a class="btn btn-secondary mt-3" href="{{ route('nhaphang.nhacungcap') }}">Hủy</a> <button class="btn btn-primary mt-3">Lưu</button>
    </div></div>
</form>
@endsection
