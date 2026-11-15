@extends('layouts.master')

@section('title', 'Tài khoản nhân viên')

@section('content')
<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Tài khoản nhân viên</h1>
    <a class="btn btn-primary" href="{{ route('taikhoan.them') }}">Thêm tài khoản</a>
</div>
<form class="row g-2 mb-3" method="GET" action="{{ route('taikhoan.danhsach') }}">
    <div class="col-md-3">
        <input class="form-control" name="q" value="{{ request('q') }}" placeholder="Tìm theo tên hoặc email">
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-secondary">Tìm kiếm</button>
    </div>
    @if (request('q'))
        <div class="col-auto">
            <a class="btn btn-link" href="{{ route('taikhoan.danhsach') }}">Xóa bộ lọc</a>
        </div>
    @endif
</form>
@if (session('temp_password'))
    <div class="alert alert-warning d-flex align-items-center gap-2 mb-3" role="alert">
        <i class="fa-solid fa-key"></i>
        <div>
            <strong>Mật khẩu tạm:</strong> <code>{{ session('temp_password') }}</code>
            <small class="d-block text-muted mt-1">Chỉ hiển thị một lần. Lưu lại ngay!</small>
        </div>
    </div>
@endif
<div class="card">
    <div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead>
                <tr>
                    <th>Tên</th>
                    <th>Email</th>
                    <th>Vai trò</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($users as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>
                        @if ($user->role === 'admin') <span class="badge bg-danger">Admin</span>
                        @elseif ($user->role === 'thungan') <span class="badge bg-primary">Thu ngân</span>
                        @elseif ($user->role === 'thukho') <span class="badge bg-success">Thủ kho</span>
                        @else <span class="badge bg-secondary">{{ $user->role }}</span>
                        @endif
                    </td>
                    <td>
                        @if (array_key_exists('khoa_luc', $user->getAttributes()) && $user->khoa_luc !== null)
                            <span class="badge bg-danger">Đã khóa</span>
                        @else
                            <span class="badge bg-success">Hoạt động</span>
                        @endif
                    </td>
                    <td>
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('taikhoan.sua', $user) }}">Sửa</a>
                        @if (array_key_exists('khoa_luc', $user->getAttributes()) && $user->khoa_luc === null)
                            <form class="d-inline" method="POST" action="{{ route('taikhoan.khoa', $user) }}" onsubmit="return confirm('Khóa tài khoản này?')">
                                @csrf
                                <button class="btn btn-sm btn-outline-warning" type="submit">Khóa</button>
                            </form>
                        @else
                            <form class="d-inline" method="POST" action="{{ route('taikhoan.mokhoa', $user) }}" onsubmit="return confirm('Mở khóa tài khoản này?')">
                                @csrf
                                <button class="btn btn-sm btn-outline-success" type="submit">Mở khóa</button>
                            </form>
                        @endif
                        <form class="d-inline" method="POST" action="{{ route('taikhoan.datlaimatkhau', $user) }}" onsubmit="return confirm('Đặt lại mật khẩu? Mật khẩu tạm mới sẽ được hiển thị một lần.')">
                            @csrf
                            <button class="btn btn-sm btn-outline-secondary" type="submit">Đặt lại mật khẩu</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td class="text-center" colspan="5">Chưa có tài khoản nhân viên.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $users->links() }}</div>
@endsection
