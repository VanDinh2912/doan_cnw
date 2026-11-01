@extends('layouts.master')

@section('title', 'Nhà cung cấp')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Nhà cung cấp</h1>
    <a class="btn btn-primary" href="{{ route('nhaphang.nhacungcap.them') }}">Thêm nhà cung cấp</a>
</div>
<form class="row g-2 mb-3">
    <div class="col-md-5"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Tìm theo tên nhà cung cấp"></div>
    <div class="col-auto"><button class="btn btn-outline-secondary">Tìm kiếm</button></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-striped mb-0">
    <thead><tr><th>Tên</th><th>Liên hệ</th><th>Điện thoại</th><th>Địa chỉ</th><th></th></tr></thead>
    <tbody>@forelse ($nhaCungCaps as $nhaCungCap)
        <tr><td>{{ $nhaCungCap->ten_nhacungcap }}</td><td>{{ $nhaCungCap->nguoi_lienhe ?: '-' }}</td><td>{{ $nhaCungCap->so_dienthoai ?: '-' }}</td><td>{{ $nhaCungCap->dia_chi ?: '-' }}</td><td class="text-nowrap">
            <a class="btn btn-sm btn-outline-primary" href="{{ route('nhaphang.nhacungcap.sua', $nhaCungCap) }}">Sửa</a>
            <form class="d-inline" method="POST" action="{{ route('nhaphang.nhacungcap.xoa', $nhaCungCap) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" onclick="return confirm('Xóa nhà cung cấp này?')">Xóa</button></form>
        </td></tr>
    @empty <tr><td colspan="5" class="text-center">Chưa có nhà cung cấp.</td></tr>@endforelse</tbody>
</table></div></div>
<div class="mt-3">{{ $nhaCungCaps->links() }}</div>
@endsection
