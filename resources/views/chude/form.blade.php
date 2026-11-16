@extends('layouts.master')

@section('title', $chuDe->exists ? 'Sửa chủ đề' : 'Thêm chủ đề')

@section('content')
<h1 class="h3 mb-3">{{ $chuDe->exists ? 'Sửa chủ đề' : 'Thêm chủ đề' }}</h1>
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ $chuDe->exists ? route('chude.postsua', $chuDe) : route('chude.postthem') }}">
            @csrf
            @if ($chuDe->exists)
                @method('PUT')
            @endif
            <div class="mb-3">
                <label class="form-label" for="ten_chude">Tên chủ đề</label>
                <input class="form-control" id="ten_chude" name="ten_chude" type="text" value="{{ old('ten_chude', $chuDe->ten_chude) }}" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="mo_ta">Mô tả</label>
                <textarea class="form-control" id="mo_ta" name="mo_ta" rows="3">{{ old('mo_ta', $chuDe->mo_ta) }}</textarea>
            </div>
            <a class="btn btn-secondary" href="{{ route('chude.danhsach') }}">Hủy</a>
            <button class="btn btn-primary" type="submit">Lưu</button>
        </form>
    </div>
</div>
@endsection