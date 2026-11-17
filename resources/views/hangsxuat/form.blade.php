@extends('layouts.master')

@section('title', $hang->exists ? 'Sửa hãng sản xuất' : 'Thêm hãng sản xuất')

@section('content')
<h1 class="h3 mb-3">{{ $hang->exists ? 'Sửa hãng sản xuất' : 'Thêm hãng sản xuất' }}</h1>
<div class="card">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data"
              action="{{ $hang->exists ? route('hangsxuat.postsua', $hang) : route('hangsxuat.postthem') }}">
            @csrf
            @if ($hang->exists)
                @method('PUT')
            @endif
            <div class="mb-3">
                <label class="form-label" for="ten">Tên hãng</label>
                <input class="form-control" id="ten" name="ten" type="text" value="{{ old('ten', $hang->ten) }}" required maxlength="100">
                @error('ten')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            @if ($hang->exists)
                <div class="mb-3">
                    <label class="form-label" for="slug">Slug (để trống = giữ nguyên)</label>
                    <input class="form-control" id="slug" name="slug" type="text" value="{{ old('slug', $hang->slug) }}" maxlength="100">
                    <div class="form-text">Slug tạo tự động từ tên khi thêm mới; đổi slug có thể ảnh hưởng URL/logo.</div>
                    @error('slug')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            @endif
            <div class="mb-3">
                <label class="form-label" for="logo">Logo (JPG/PNG/WebP, tối đa 2MB)</label>
                @if ($hang->logo)
                    <div class="mb-2">
                        <img src="{{ asset('storage/'.$hang->logo) }}" alt="" style="height:48px;">
                        <label class="form-check form-check-inline ms-2">
                            <input class="form-check-input" type="checkbox" name="xoa_logo" value="1">
                            <span class="form-check-label">Xóa logo</span>
                        </label>
                    </div>
                @endif
                <input class="form-control" id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp">
                @error('logo')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3 form-check">
                <input class="form-check-input" type="checkbox" name="kich_hoat" value="1" id="kich_hoat"
                       {{ old('kich_hoat', $hang->exists ? $hang->kich_hoat : true) ? 'checked' : '' }}>
                <label class="form-check-label" for="kich_hoat">Đang kích hoạt (hiện trong bộ lọc storefront)</label>
            </div>
            <a class="btn btn-secondary" href="{{ route('hangsxuat.danhsach') }}">Hủy</a>
            <button class="btn btn-primary" type="submit">Lưu</button>
        </form>
    </div>
</div>
@endsection
