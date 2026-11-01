@extends('layouts.master')

@section('title', 'Đơn nhập hàng')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">Đơn nhập hàng</h1><a class="btn btn-primary" href="{{ route('nhaphang.donnhap.them') }}">Tạo đơn nhập</a></div>
<div class="card"><div class="table-responsive"><table class="table table-striped mb-0">
<thead><tr><th>Mã đơn</th><th>Nhà cung cấp</th><th>Tổng tiền</th><th>Trạng thái</th><th></th></tr></thead>
<tbody>@forelse ($donNhaps as $donNhap)<tr><td>{{ $donNhap->ma_don }}</td><td>{{ $donNhap->nhaCungCap->ten_nhacungcap }}</td><td>{{ number_format($donNhap->tong_tien, 0, ',', '.') }} đ</td><td>{{ str_replace('_', ' ', $donNhap->trang_thai) }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('nhaphang.donnhap.chitiet', $donNhap) }}">Chi tiết</a></td></tr>
@empty <tr><td colspan="5" class="text-center">Chưa có đơn nhập.</td></tr>@endforelse</tbody>
</table></div></div><div class="mt-3">{{ $donNhaps->links() }}</div>
@endsection
