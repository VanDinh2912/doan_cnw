@extends('layouts.master')

@section('title', 'Sản phẩm')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Sản phẩm</h1>
    <a class="btn btn-primary" href="{{ route('sanpham.them') }}">Thêm sản phẩm</a>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-striped align-middle mb-0 js-data-table">
            <thead>
            <tr><th>Ảnh</th><th>SKU</th><th>Tên sản phẩm</th><th>Loại</th><th>Giá bán</th><th>Tồn kho</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr>
            </thead>
            <tbody>
            @forelse ($sanPhams as $sanPham)
                @php
                    $badge = $sanPham->soluong === 0 ? 'danger' : ($sanPham->soluong <= $sanPham->soluong_toithieu ? 'warning' : 'success');
                @endphp
                <tr>
                    <td>
                        @if ($sanPham->hinh_anh)
                            <img src="{{ asset($sanPham->hinh_anh) }}" alt="{{ $sanPham->ten_sanpham }}" style="height:40px;width:40px;object-fit:cover">
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $sanPham->sku }}</td>
                    <td>{{ $sanPham->ten_sanpham }}</td>
                    <td>{{ $sanPham->loaiSanPham->ten_loai }}</td>
                    <td>{{ number_format($sanPham->gia_ban, 0, ',', '.') }} đ</td>
                    <td><span class="badge text-bg-{{ $badge }}">{{ $sanPham->soluong }}</span></td>
                    <td>{{ $sanPham->trang_thai ? 'Đang bán' : 'Tạm dừng' }}</td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('sanpham.sua', $sanPham) }}">Sửa</a>
                        <form class="d-inline" method="POST" action="{{ route('sanpham.xoa', $sanPham) }}" onsubmit="return confirm('Xóa sản phẩm này?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td class="text-center" colspan="8">Chưa có sản phẩm.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
