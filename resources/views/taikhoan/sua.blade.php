@extends('layouts.master')

@section('title', 'Sửa tài khoản nhân viên')

@section('content')
<h1 class="h3 mb-3">Sửa tài khoản nhân viên</h1>
<form class="card card-body" method="POST" action="{{ route('taikhoan.update', $user) }}" data-can-pin="nghiemtrong">
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
            <option value="admin" @selected(old('role', $user->role) === 'admin')>Admin</option>
            <option value="thungan" @selected(old('role', $user->role) === 'thungan')>Thu ngân</option>
            <option value="thukho" @selected(old('role', $user->role) === 'thukho')>Thủ kho</option>
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

<div class="card mt-3">
    <div class="card-header fw-bold">PIN xác nhận thao tác</div>
    <div class="card-body">
        <p class="mb-2">
            Trạng thái:
            @if ($user->pin_hash)
                <span class="badge bg-success">Đã có PIN</span>
                <small class="text-muted">(đổi lần cuối: {{ $user->pin_doi_luc ?? '—' }})</small>
            @else
                <span class="badge bg-secondary">Chưa có PIN</span>
            @endif
        </p>
        @if (isset($xinPinGanNhat))
            <p class="small text-muted mb-2">
                Yêu cầu gần nhất: {{ $xinPinGanNhat->mo_ta }} — {{ $xinPinGanNhat->created_at }}
            </p>
        @endif
        <form method="POST" action="{{ route('taikhoan.datlaipin', $user) }}" data-can-pin="nghiemtrong"
              onsubmit="return confirm('Cấp PIN tạm mới? PIN hiện tại của nhân viên sẽ vô hiệu và họ bắt buộc đổi PIN.');">
            @csrf
            <div class="input-group" style="max-width: 34rem;">
                <input type="text" name="ly_do" class="form-control" maxlength="255" required
                       placeholder="Lý do cấp/đặt lại PIN (bắt buộc, ghi vào nhật ký)" value="{{ old('ly_do') }}">
                <button class="btn btn-outline-primary" type="submit">Đặt lại PIN</button>
            </div>
            <div class="form-text">PIN tạm 6 số hiện đúng một lần sau khi bạn xác nhận PIN của chính mình — đồng thời nhân viên tự xem được một lần tại trang "PIN tạm của tôi".</div>
        </form>
    </div>
</div>
@endsection
