@extends('layouts.master')

@section('title', 'Hóa đơn')

@section('content')
<h1 class="h3 mb-3">Hóa đơn</h1>
<div class="card">
    <div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead><tr><th>Mã hóa đơn</th><th>Thu ngân</th><th>Ngày</th><th>Tổng thanh toán</th><th></th></tr></thead>
            <tbody>
            @forelse ($donHangs as $donHang)
                <tr>
                    <td>{{ $donHang->ma_hoadon }}</td>
                    <td>{{ $donHang->user->name }}</td>
                    <td>{{ $donHang->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ number_format($donHang->tong_thanh_toan, 0, ',', '.') }} đ</td>
                    <td><a class="btn btn-sm btn-outline-primary" href="{{ route('donhang.inhoadon', $donHang) }}">Xem/in</a></td>
                </tr>
            @empty
                <tr><td class="text-center" colspan="5">Chưa có hóa đơn.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $donHangs->links() }}</div>
@endsection
