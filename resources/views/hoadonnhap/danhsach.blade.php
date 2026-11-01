@extends('layouts.master')
@section('title', 'Hóa đơn nhập')
@section('content')
<div class="d-flex justify-content-between mb-3"><h1 class="h3">Hóa đơn nhập</h1><a class="btn btn-primary" href="{{ route('hoadonnhap.them') }}">Thêm hóa đơn</a></div>
<div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Mã phiếu</th><th>Số hóa đơn</th><th>Nhà cung cấp</th><th>Tổng thanh toán</th><th>Trạng thái</th><th></th></tr></thead><tbody>
@forelse ($hoaDons as $hoaDon)<tr><td>{{ $hoaDon->ma_phieu }}</td><td>{{ $hoaDon->so_hoadon }}</td><td>{{ $hoaDon->nhaCungCap->ten_nhacungcap }}</td><td>{{ number_format($hoaDon->tong_thanh_toan, 0, ',', '.') }} đ</td><td>{{ str_replace('_', ' ', $hoaDon->trang_thai) }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('hoadonnhap.xem', $hoaDon) }}">Xem</a></td></tr>@empty<tr><td colspan="6" class="text-center">Chưa có hóa đơn nhập.</td></tr>@endforelse
</tbody></table></div></div><div class="mt-3">{{ $hoaDons->links() }}</div>
@endsection
