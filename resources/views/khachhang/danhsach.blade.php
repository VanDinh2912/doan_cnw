@extends('layouts.master')

@section('title', 'Khách hàng')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Khách hàng</h1>
    <a class="btn btn-primary" href="{{ route('khachhang.them') }}">Thêm khách hàng</a>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-striped mb-0 js-data-table">
            <thead><tr><th>Họ tên</th><th>Số điện thoại</th><th>Địa chỉ</th><th>Điểm</th><th>Thao tác</th></tr></thead>
            <tbody>
            @forelse ($khachHangs as $khachHang)
                <tr>
                    <td>{{ $khachHang->ho_ten }}</td>
                    <td>{{ $khachHang->so_dienthoai }}</td>
                    <td>{{ $khachHang->dia_chi ?: '—' }}</td>
                    <td>{{ $khachHang->diem_tichluy }}</td>
                    <td>
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('khachhang.sua', $khachHang) }}">Sửa</a>
                        <form class="d-inline" method="POST" action="{{ route('khachhang.xoa', $khachHang) }}" onsubmit="return confirm('Xóa khách hàng này?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td class="text-center" colspan="5">Chưa có khách hàng.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
