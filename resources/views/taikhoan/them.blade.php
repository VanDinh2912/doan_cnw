@extends('layouts.master')

@section('title', 'Thêm tài khoản nhân viên')

@section('content')
<h1 class="h3 mb-3">Thêm tài khoản nhân viên</h1>
<form class="card card-body" method="POST" action="{{ route('taikhoan.store') }}" data-can-pin="nghiemtrong">
    @csrf
    <div class="mb-3">
        <label class="form-label" for="name">Họ tên</label>
        <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="mb-3">
        <label class="form-label" for="email">Email</label>
        <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email') }}" required>
        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="mb-3">
        <label class="form-label" for="role">Vai trò</label>
        <select class="form-select @error('role') is-invalid @enderror" id="role" name="role" required>
            <option value="">-- Chọn vai trò --</option>
            <option value="admin" @selected(old('role') === 'admin')">Admin</option>
            <option value="thungan" @selected(old('role') === 'thungan')">Thu ngân</option>
            <option value="thukho" @selected(old('role') === 'thukho')">Thủ kho</option>
        </select>
        @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="alert alert-info">
        <i class="fa-solid fa-info-circle"></i>
        Mật khẩu tạm sẽ được sinh ngẫu nhiên và hiển thị một lần sau khi tạo. Người dùng phải đổi mật khẩu ở lần đăng nhập đầu tiên.
    </div>
    <div>
        <a class="btn btn-secondary" href="{{ route('taikhoan.danhsach') }}">Hủy</a>
        <button class="btn btn-primary" type="submit">Tạo tài khoản</button>
    </div>
</form>
@endsection
