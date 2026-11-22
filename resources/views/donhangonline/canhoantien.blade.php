@extends('layouts.master')

@section('title', 'Đơn cần hoàn tiền')

@section('content')
<h1 class="h3 mb-3">Đơn cần hoàn tiền thủ công</h1>

@if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

@if ($thieuMigration)
    <div class="alert alert-warning">Cần áp migration cột <code>can_hoan_tien</code> để dùng chức năng này.</div>
@else
<div class="card">
    <div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead><tr>
                <th>Mã đơn</th><th>Khách</th><th>SĐT</th><th>Tổng</th>
                <th>Ngày hủy</th><th>Lý do / ghi chú</th><th></th>
            </tr></thead>
            <tbody>
            @forelse ($donHangs as $don)
                <tr>
                    <td><a href="{{ route('donhangonline.chitiet', $don) }}">{{ $don->ma_hoadon }}</a></td>
                    <td>{{ $don->ten_khach_dat ?? $don->khachHang?->ho_ten ?? '—' }}</td>
                    <td>{{ $don->so_dienthoai_dat ?? '—' }}</td>
                    <td>{{ number_format((float) $don->tong_thanh_toan, 0, ',', '.') }} đ</td>
                    <td>{{ $don->updated_at->format('d/m/Y H:i') }}</td>
                    <td><small>{{ $don->ghi_chu_noi_bo ?? $don->ghi_chu ?? '—' }}</small></td>
                    <td>
                        <form method="POST" action="{{ route('donhangonline.dahoantien', $don) }}" class="d-flex gap-1" data-can-pin="a">
                            @csrf
                            <input type="text" name="ghi_chu" class="form-control form-control-sm" placeholder="Ghi chú hoàn tiền" required minlength="5">
                            <button class="btn btn-sm btn-success text-nowrap" type="submit">Đã hoàn tiền</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Không có đơn nào cần hoàn tiền.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $donHangs->links() }}</div>
@endif
@endsection
