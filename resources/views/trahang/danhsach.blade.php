@extends('layouts.master')
@section('title', 'Phiếu trả hàng')
@section('content')
<div class="d-flex justify-content-between mb-3"><h1 class="h3">Phiếu trả hàng</h1></div>
<div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Mã phiếu</th><th>Hóa đơn</th><th>Tiền hoàn</th><th>Trạng thái</th><th></th></tr></thead><tbody>
@forelse ($phieuTras as $phieuTra)<tr><td>{{ $phieuTra->ma_phieu }}</td><td>{{ $phieuTra->donHang->ma_hoadon }}</td><td>{{ number_format($phieuTra->tong_hoan, 0, ',', '.') }} đ</td><td>{{ str_replace('_', ' ', $phieuTra->trang_thai) }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('trahang.xem', $phieuTra) }}">Xem</a></td></tr>@empty<tr><td colspan="5" class="text-center">Chưa có phiếu trả.</td></tr>@endforelse
</tbody></table></div></div><div class="mt-3">{{ $phieuTras->links() }}</div>
@endsection
