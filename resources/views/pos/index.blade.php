@extends('layouts.master')

@section('title', 'Bán hàng POS')

@section('content')
<h1 class="h3 mb-3">Bán hàng POS</h1>
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-body">
                <label class="form-label" for="tim-kiem">Tìm theo tên, SKU hoặc barcode</label>
                <input class="form-control" id="tim-kiem" type="search" autocomplete="off" placeholder="Nhập từ khóa...">
                <div class="list-group mt-2" id="ket-qua"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Giỏ hàng</strong>
                <form method="POST" action="{{ route('giohang.lammoi') }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger" type="submit">Làm mới</button>
                </form>
            </div>
            <div class="card-body">
                @php $gioHang = session('gio_hang', []); $tongTien = collect($gioHang)->sum(fn ($item) => $item['gia_ban'] * $item['soluong']); @endphp
                @if (count($gioHang))
                    <form method="POST" action="{{ route('giohang.capnhat') }}">
                        @csrf
                        @method('PUT')
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead><tr><th>Sản phẩm</th><th>Đơn giá</th><th style="width:120px">SL</th><th>Thành tiền</th><th></th></tr></thead>
                                <tbody>
                                @foreach ($gioHang as $item)
                                    <tr>
                                        <td>{{ $item['ten_sanpham'] }}<small class="d-block text-muted">{{ $item['sku'] }}</small></td>
                                        <td>{{ number_format($item['gia_ban'], 0, ',', '.') }} đ</td>
                                        <td><input class="form-control" name="soluong[{{ $item['id'] }}]" type="number" min="1" max="{{ $item['ton_kho'] }}" value="{{ $item['soluong'] }}"></td>
                                        <td>{{ number_format($item['gia_ban'] * $item['soluong'], 0, ',', '.') }} đ</td>
                                        <td><a class="btn btn-sm btn-outline-danger" href="{{ route('giohang.xoa', $item['id']) }}">Xóa</a></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <button class="btn btn-outline-primary" type="submit">Cập nhật giỏ</button>
                    </form>
                    <hr>
                    <div class="text-end fs-5"><strong>Tổng: {{ number_format($tongTien, 0, ',', '.') }} đ</strong></div>
                    <form class="border-top mt-3 pt-3" method="POST" action="{{ route('donhang.thanhtoan') }}">
                        @csrf
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label" for="giam_gia_phantram">Giảm giá (%)</label>
                                <input class="form-control" id="giam_gia_phantram" name="giam_gia_phantram" type="number" min="0" max="100" step="0.01" value="{{ old('giam_gia_phantram', 0) }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="tien_khach_dua">Tiền khách đưa</label>
                                <input class="form-control" id="tien_khach_dua" name="tien_khach_dua" type="number" min="0" value="{{ old('tien_khach_dua', 0) }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="phuong_thuc">Thanh toán</label>
                                <select class="form-select" id="phuong_thuc" name="phuong_thuc">
                                    <option value="tienmat">Tiền mặt</option>
                                    <option value="chuyenkhoan">Chuyển khoản</option>
                                </select>
                            </div>
                        </div>
                        <button class="btn btn-success w-100 mt-3" type="submit">Thanh toán</button>
                    </form>
                @else
                    <p class="text-muted mb-0">Giỏ hàng đang trống.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script>
const input = document.getElementById('tim-kiem');
const result = document.getElementById('ket-qua');
let timer;
input.addEventListener('input', () => {
    clearTimeout(timer);
    const query = input.value.trim();
    result.innerHTML = '';
    if (!query) return;
    timer = setTimeout(async () => {
        const response = await fetch(`{{ route('sanpham.timkiem') }}?q=${encodeURIComponent(query)}`);
        const products = await response.json();
        products.forEach(product => {
            const link = document.createElement('a');
            link.className = 'list-group-item list-group-item-action';
            link.href = `{{ url('/giohang/them') }}/${product.id}`;
            link.textContent = `${product.ten_sanpham} - ${product.sku} (${product.soluong})`;
            result.appendChild(link);
        });
        if (!products.length) result.innerHTML = '<div class="text-muted p-2">Không tìm thấy sản phẩm còn hàng.</div>';
    }, 250);
});
</script>
@endpush
@endsection
