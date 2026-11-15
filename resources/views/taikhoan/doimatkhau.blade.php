@extends('layouts.master')

@section('title', 'Đổi mật khẩu')

@section('content')
<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
<h1 class="h3 mb-3">Đổi mật khẩu</h1>
@if (session('warning'))
    <div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
        <i class="fa-solid fa-triangle-exclamation"></i><div>{{ session('warning') }}</div>
    </div>
@endif
<form class="card card-body" method="POST" action="{{ route('doi-mat-khau.post') }}">
    @csrf
    <div class="mb-3">
        <label class="form-label" for="mat_khau_hien_tai">Mật khẩu hiện tại</label>
        <input class="form-control @error('mat_khau_hien_tai') is-invalid @enderror" id="mat_khau_hien_tai" name="mat_khau_hien_tai" type="password" required autocomplete="current-password">
        @error('mat_khau_hien_tai') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="mb-3">
        <label class="form-label" for="mat_khau_moi">Mật khẩu mới</label>
        <input class="form-control @error('mat_khau_moi') is-invalid @enderror" id="mat_khau_moi" name="mat_khau_moi" type="password" required autocomplete="new-password">
        @error('mat_khau_moi') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <small class="text-muted">Mật khẩu phải có ít nhất 8 ký tự, bao gồm chữ, số và ký tự đặc biệt.</small>
    </div>
    <div class="mb-3">
        <label class="form-label" for="mat_khau_xac_nhan">Xác nhận mật khẩu mới</label>
        <input class="form-control @error('mat_khau_xac_nhan') is-invalid @enderror" id="mat_khau_xac_nhan" name="mat_khau_xac_nhan" type="password" required autocomplete="new-password">
        @error('mat_khau_xac_nhan') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div>
        <a class="btn btn-secondary" href="{{ route('tongquan') }}">Hủy</a>
        <button class="btn btn-primary" type="submit">Đổi mật khẩu</button>
    </div>
</form>
@endsection
