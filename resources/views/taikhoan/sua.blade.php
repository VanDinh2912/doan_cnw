@extends('layouts.master')

@section('title', 'Sửa tài khoản nhân viên')

@section('content')
<h1 class="h3 mb-3">Sửa tài khoản nhân viên</h1>
<form class="card card-body" method="POST" action="{{ route('taikhoan.update', $user) }}">
    @csrf
    @method('PUT')
    <div class="mb-3">
        <label class="form-label" for="name">Họ tên</label>
        <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user->name) }}" required>
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="mb-3">
        <label class="form-label" for="email">Email</label>
        <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required>
        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="mb-3">
        <label class="form-label" for="role">Vai trò</label>
        <select class="form-select @error('role') is-invalid @enderror" id="role" name="role" required>
            <option value="">-- Chọn vai trò --</option>
            <option value="admin" @selected(old('role', $user->role) === 'admin')">Admin</option>
            <option value="thungan" @selected(old('role', $user->role) === 'thungan')">Thu ngân</option>
            <option value="thukho" @selected(old('role', $user->role) === 'thukho')">Thủ kho</option>
        </select>
        @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="mb-3">
        <label class="form-label">Trạng thái</label>
        <div>
            @if (array_key_exists('khoa_luc', $user->getAttributes()) && $user->khoa_luc !== null)
                <span class="badge bg-danger">Đã khóa</span>
            @else
                <span class="badge bg-success">Hoạt động</span>
            @endif
        </div>
    </div>
    <div>
        <a class="btn btn-secondary" href="{{ route('taikhoan.danhsach') }}">Hủy</a>
        <button class="btn btn-primary" type="submit">Lưu thay đổi</button>
    </div>
</form>
@endsection
