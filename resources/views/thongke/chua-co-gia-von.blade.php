@extends('layouts.master')

@section('title', 'Dòng bán chưa có giá vốn')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Dòng bán chưa có giá vốn</h1>
        <p class="page-subtitle">
            {{ number_format($tongDong, 0, ',', '.') }} dòng trên hóa đơn đã thanh toán có <code>gia_von = 0</code>.
            Các dòng này khiến lợi nhuận báo cáo cao hơn thực tế — cần kiểm tra lại giá nhập của sản phẩm.
        </p>
    </div>
    <div>
        <a href="{{ route('thongke.index') }}" class="btn btn-outline-secondary">← Về thống kê</a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead>
                <tr>
                    <th>Hóa đơn</th>
                    <th>Ngày bán</th>
                    <th>Sản phẩm</th>
                    <th class="text-end">SL</th>
                    <th class="text-end">Đơn giá</th>
                    <th class="text-end">Thành tiền</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($cacDong as $dong)
                <tr>
                    <td>{{ $dong->ma_hoadon }}</td>
                    <td>{{ \Carbon\Carbon::parse($dong->ngay_ban)->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') }}</td>
                    <td>
                        {{ $dong->ten_sanpham }}
                        @if ($dong->sku)<br><small class="text-muted">{{ $dong->sku }}</small>@endif
                    </td>
                    <td class="text-end">{{ number_format($dong->soluong, 0, ',', '.') }}</td>
                    <td class="text-end">{{ number_format($dong->don_gia, 0, ',', '.') }} đ</td>
                    <td class="text-end">{{ number_format($dong->thanh_tien, 0, ',', '.') }} đ</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center">Không có dòng nào thiếu giá vốn.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $cacDong->links() }}</div>
@endsection
