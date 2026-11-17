@extends('layouts.master')

@section('title', 'Khách hàng')

@section('content')
@php $laAdmin = auth()->user()?->role === 'admin'; @endphp
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Khách hàng</h1>
    <a class="btn btn-primary" href="{{ route('khachhang.them') }}">Thêm khách hàng</a>
</div>
@if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
<div class="card">
    <div class="table-responsive">
        <table class="table table-striped mb-0 js-data-table">
            <thead><tr><th>Họ tên</th><th>Số điện thoại</th><th>Địa chỉ</th><th>Điểm</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
            <tbody>
            @forelse ($khachHangs as $khachHang)
                <tr>
                    <td><a href="{{ route('khachhang.chitiet', $khachHang) }}">{{ $khachHang->ho_ten }}</a></td>
                    <td>{{ $khachHang->so_dienthoai }}</td>
                    <td>{{ $khachHang->dia_chi ?: '—' }}</td>
                    <td>{{ $khachHang->diem_tichluy }}</td>
                    <td>
                        @if ($khachHang->biKhoa())
                            <span class="badge bg-danger" title="{{ $khachHang->ly_do_khoa }}">Đã khóa</span>
                        @else
                            <span class="badge bg-success">Hoạt động</span>
                        @endif
                    </td>
                    <td>
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('khachhang.chitiet', $khachHang) }}">Xem</a>
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('khachhang.sua', $khachHang) }}">Sửa</a>
                        @if ($laAdmin)
                            @if ($khachHang->biKhoa())
                                <form class="d-inline" method="POST" action="{{ route('khachhang.mokhoa', $khachHang) }}"
                                      onsubmit="return confirm('Mở khóa khách hàng này?')">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-success" type="submit">Mở khóa</button>
                                </form>
                            @else
                                <a class="btn btn-sm btn-outline-danger" href="{{ route('khachhang.chitiet', $khachHang) }}#khoa">Khóa</a>
                            @endif
                        @endif
                        <form class="d-inline" method="POST" action="{{ route('khachhang.xoa', $khachHang) }}" onsubmit="return confirm('Xóa khách hàng này?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td class="text-center" colspan="6">Chưa có khách hàng.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
