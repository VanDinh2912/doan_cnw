@extends('layouts.master')

@section('title', 'Hóa đơn')

@section('content')
<h1 class="h3 mb-3">Hóa đơn</h1>
<form class="card card-body mb-3" method="get" action="{{ route('donhang.danhsach') }}">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label" for="tu_ngay">Từ ngày</label>
            <input class="form-control" id="tu_ngay" name="tu_ngay" type="date" value="{{ $tuNgay }}">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="den_ngay">Đến ngày</label>
            <input class="form-control" id="den_ngay" name="den_ngay" type="date" value="{{ $denNgay }}">
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button class="btn btn-primary" type="submit">Lọc</button>
            <a class="btn btn-outline-secondary" href="{{ route('donhang.danhsach') }}">Xóa lọc</a>
        </div>
    </div>
</form>
<div class="card">
    <div class="table-responsive">
        <table class="table table-striped mb-0 js-data-table">
            <thead><tr><th>Mã hóa đơn</th><th>Thu ngân</th><th>Ngày</th><th>Tổng thanh toán</th><th>Trạng thái</th><th></th></tr></thead>
            <tbody>
            @forelse ($donHangs as $donHang)
                <tr>
                    <td>{{ $donHang->ma_hoadon }}</td>
                    <td>{{ $donHang->user->name }}</td>
                    <td>{{ $donHang->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ number_format($donHang->tong_thanh_toan, 0, ',', '.') }} đ</td>
                    <td>
                        @if ($donHang->trang_thai === 'da_thanh_toan')
                            <span class="badge bg-success">Đã thanh toán</span>
                        @elseif ($donHang->trang_thai === 'cho_thanh_toan')
                            <span class="badge bg-warning text-dark">Chờ thanh toán</span>
                        @elseif ($donHang->trang_thai === 'da_huy')
                            <span class="badge bg-secondary">Đã hủy</span>
                        @else
                            <span class="badge bg-light text-dark">{{ $donHang->trang_thai }}</span>
                        @endif
                    </td>
                    <td class="text-nowrap">
                        @if ($donHang->trang_thai === 'da_thanh_toan')
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('donhang.inhoadon', $donHang) }}">Xem/in</a>
                            <a class="btn btn-sm btn-outline-warning" href="{{ route('trahang.chon', $donHang) }}">Đổi trả</a>
                        @else
                            <button class="btn btn-sm btn-outline-secondary" disabled>Chưa thanh toán</button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td class="text-center" colspan="6">Chưa có hóa đơn.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $donHangs->links() }}</div>
@endsection
