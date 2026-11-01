@extends('layouts.master')

@section('title', $loai->exists ? 'Sửa loại sản phẩm' : 'Thêm loại sản phẩm')

@section('content')
<h1 class="h3 mb-3">{{ $loai->exists ? 'Sửa loại sản phẩm' : 'Thêm loại sản phẩm' }}</h1>
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ $loai->exists ? route('loaisanpham.postsua', $loai) : route('loaisanpham.postthem') }}">
            @csrf
            @if ($loai->exists)
                @method('PUT')
            @endif
            <div class="mb-3">
                <label class="form-label" for="ten_loai">Tên loại</label>
                <input class="form-control" id="ten_loai" name="ten_loai" type="text" value="{{ old('ten_loai', $loai->ten_loai) }}" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="mo_ta">Mô tả</label>
                <textarea class="form-control" id="mo_ta" name="mo_ta" rows="3">{{ old('mo_ta', $loai->mo_ta) }}</textarea>
            </div>
            <a class="btn btn-secondary" href="{{ route('loaisanpham.danhsach') }}">Hủy</a>
            <button class="btn btn-primary" type="submit">Lưu</button>
        </form>
    </div>
</div>
@endsection
