@extends('layouts.master')

@section('title', 'Sản phẩm')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Sản phẩm</h1>
        <p class="page-subtitle">Danh mục hàng hóa đang kinh doanh</p>
    </div>
    <a class="btn btn-primary" href="{{ route('sanpham.them') }}">
        <i class="fa-solid fa-plus"></i>
        <span>Thêm sản phẩm</span>
    </a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle js-data-table">
            <thead>
            <tr>
                <th>Ảnh</th>
                <th>SKU</th>
                <th>Tên sản phẩm</th>
                <th>Loại</th>
                <th class="text-end">Giá bán</th>
                <th class="text-center">Tồn kho</th>
                <th>Trạng thái</th>
                <th class="text-end">Thao tác</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($sanPhams as $sanPham)
                @php
                    $badge = $sanPham->soluong === 0 ? 'danger' : ($sanPham->soluong <= $sanPham->soluong_toithieu ? 'warning' : 'success');
                @endphp
                <tr>
                    <td>
                        <img class="table-thumb"
                             src="{{ $sanPham->hinh_anh ? (str_starts_with($sanPham->hinh_anh, 'upload/') ? asset($sanPham->hinh_anh) : asset('storage/'.$sanPham->hinh_anh)) : asset('img/no-image.svg') }}"
                             alt="{{ $sanPham->ten_sanpham }}">
                    </td>
                    <td><span class="font-monospace">{{ $sanPham->sku }}</span></td>
                    <td>{{ $sanPham->ten_sanpham }}</td>
                    <td>{{ $sanPham->loaiSanPham->ten_loai }}</td>
                    <td class="text-end money">{{ number_format($sanPham->gia_ban, 0, ',', '.') }} đ</td>
                    <td class="text-center"><span class="badge text-bg-{{ $badge }}">{{ $sanPham->soluong }}</span></td>
                    <td>
                        @if ($sanPham->trang_thai)
                            <span class="badge text-bg-success">Đang bán</span>
                        @else
                            <span class="badge text-bg-danger">Tạm dừng</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('sanpham.sua', $sanPham) }}">Sửa</a>
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('sanpham.tem', $sanPham) }}">Tem</a>
                        <form class="d-inline" method="POST" action="{{ route('sanpham.xoa', $sanPham) }}" onsubmit="return confirm('Xóa sản phẩm này?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td class="text-center text-muted py-4" colspan="8">Chưa có sản phẩm.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection