@extends('layouts.master')

@section('title', 'Tồn kho')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Tồn kho</h1>
    <div><a class="btn btn-primary" href="{{ route('kho.nhapkho') }}">Nhập kho</a> <a class="btn btn-outline-secondary" href="{{ route('kho.lichsu') }}">Lịch sử</a></div>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead><tr><th>SKU</th><th>Sản phẩm</th><th>Loại</th><th>Tồn kho</th><th>Ngưỡng cảnh báo</th><th>Trạng thái</th></tr></thead>
            <tbody>
            @foreach ($sanPhams as $sanPham)
                @php $badge = $sanPham->soluong === 0 ? 'danger' : ($sanPham->soluong <= $sanPham->soluong_toithieu ? 'warning' : 'success'); @endphp
                <tr>
                    <td>{{ $sanPham->sku }}</td><td>{{ $sanPham->ten_sanpham }}</td><td>{{ $sanPham->loaiSanPham->ten_loai }}</td>
                    <td><span class="badge text-bg-{{ $badge }}">{{ $sanPham->soluong }}</span></td>
                    <td>{{ $sanPham->soluong_toithieu }}</td><td>{{ $sanPham->soluong <= $sanPham->soluong_toithieu ? 'Tồn thấp' : 'Bình thường' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
