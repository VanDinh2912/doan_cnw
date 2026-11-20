@extends('layouts.master')

@section('title', 'Khách hàng — '.$khachHang->ho_ten)

@section('content')
@php
    $laAdmin = auth()->user()?->role === 'admin';
@endphp
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Khách hàng: {{ $khachHang->ho_ten }}</h1>
    <div>
        <a class="btn btn-outline-primary" href="{{ route('khachhang.danhsach') }}">← Danh sách</a>
        <a class="btn btn-outline-secondary" href="{{ route('khachhang.sua', $khachHang) }}">Sửa</a>
    </div>
</div>

@if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header fw-bold">Thông tin hồ sơ</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th style="width:40%">Họ tên</th><td>{{ $khachHang->ho_ten }}</td></tr>
                    <tr><th>Số điện thoại</th><td>{{ $khachHang->so_dienthoai }}</td></tr>
                    <tr><th>Địa chỉ</th><td>{{ $khachHang->dia_chi ?: '—' }}</td></tr>
                    <tr><th>Tài khoản</th><td>
                        @if ($khachHang->user)
                            {{ $khachHang->user->name }} ({{ $khachHang->user->email }})
                            <br><small class="text-muted">
                                Ngày tạo: {{ $khachHang->user->created_at?->format('H:i d/m/Y') ?: '—' }}
                                · Đăng nhập gần nhất: {{ $khachHang->user->last_login_at?->format('H:i d/m/Y') ?: 'Chưa đăng nhập' }}
                                @if ($khachHang->user->khoa_luc)
                                    · <span class="text-danger">Tài khoản bị khóa</span>
                                @endif
                            </small>
                        @else
                            <span class="text-muted">Chưa liên kết tài khoản</span>
                        @endif
                    </td></tr>
                    <tr><th>Điểm tích lũy</th><td>{{ number_format($khachHang->diem_tichluy) }}</td></tr>
                    <tr><th>Tổng chi tiêu</th><td><strong>{{ number_format($tongChiTieu, 0, ',', '.') }} đ</strong></td></tr>
                </table>
                @if ($laAdmin && $khachHang->user && $khachHang->user->role === 'khachhang'
                        && $khachHang->user->khoa_luc === null && ! $khachHang->biKhoa())
                <hr class="my-2">
                <form method="POST" action="{{ route('khachhang.datlaimatkhau', $khachHang) }}"
                      onsubmit="return confirm('Đặt lại mật khẩu tài khoản này? Mọi phiên đăng nhập sẽ bị hủy.')" data-can-pin="nghiemtrong">
                    @csrf
                    <label class="form-label">Đặt lại mật khẩu (chỉ admin)</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="ly_do_khoa" class="form-control" maxlength="255"
                               placeholder="Lý do (bắt buộc)" required>
                        <button type="submit" class="btn btn-outline-danger">Tạo MK tạm</button>
                    </div>
                    <small class="text-muted">MK tạm chỉ hiện 1 lần; khách sẽ bị bắt đổi MK khi đăng nhập.</small>
                    @error('ly_do_khoa')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </form>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100" id="khoa">
            <div class="card-header fw-bold">Trạng thái</div>
            <div class="card-body">
                @if ($khachHang->biKhoa())
                    <p><span class="badge bg-danger">Đã khóa</span>
                        <small class="text-muted">từ {{ $khachHang->khoa_den?->format('H:i d/m/Y') }}</small></p>
                    <p><strong>Lý do:</strong> {{ $khachHang->ly_do_khoa }}</p>
                    @if ($khachHang->user?->khoa_luc)
                        <p class="text-muted mb-0"><small>Tài khoản đăng nhập cũng đang bị khóa.</small></p>
                    @endif
                    @if ($laAdmin)
                    <form method="POST" action="{{ route('khachhang.mokhoa', $khachHang) }}" class="mt-2"
                          onsubmit="return confirm('Mở khóa khách hàng này?')" data-can-pin="nghiemtrong">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm">Mở khóa</button>
                    </form>
                    @endif
                @else
                    <p><span class="badge bg-success">Hoạt động</span></p>
                    @if ($laAdmin)
                    <form method="POST" action="{{ route('khachhang.khoa', $khachHang) }}" class="mt-2" data-can-pin="nghiemtrong">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label">Lý do khóa (bắt buộc)</label>
                            <input type="text" name="ly_do_khoa" class="form-control form-control-sm" maxlength="255"
                                   placeholder="VD: khách yêu cầu, gian lận điểm..." required>
                            @error('ly_do_khoa')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-danger btn-sm"
                                onclick="return confirm('Khóa khách hàng này?')">Khóa khách hàng</button>
                    </form>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header fw-bold">Lịch sử đơn hàng ({{ $donHangs->total() }})</div>
    <div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead>
                <tr><th>Mã đơn</th><th>Kênh</th><th>Thanh toán</th><th>Giao hàng</th><th>Ngày tạo</th><th class="text-end">Tổng tiền</th></tr>
            </thead>
            <tbody>
            @forelse ($donHangs as $don)
                <tr>
                    <td>{{ $don->ma_hoadon }}</td>
                    <td>{{ $don->kenh === 'online' ? 'Online' : 'POS' }}</td>
                    <td>{{ $don->trang_thai_tieng_viet ?? $don->trang_thai }}</td>
                    <td>{{ $don->hinh_thuc_nhan_tieng_viet ?? '—' }}</td>
                    <td>{{ $don->created_at?->format('H:i d/m/Y') }}</td>
                    <td class="text-end">{{ number_format((float) $don->tong_thanh_toan, 0, ',', '.') }} đ</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted">Khách chưa có đơn hàng nào.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($donHangs->hasPages())
        <div class="card-footer">{{ $donHangs->links() }}</div>
    @endif
</div>
@endsection
