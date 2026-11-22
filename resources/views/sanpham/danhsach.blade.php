@extends('layouts.master')

@section('title', 'Sản phẩm')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Sản phẩm</h1>
        <p class="page-subtitle">Danh mục hàng hóa đang kinh doanh</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-success" href="{{ route('sanpham.xuatexcel') }}">
            <i class="fa-solid fa-file-excel"></i>
            <span>Xuất Excel</span>
        </a>
        @if ($laAdmin ?? false)
            <a class="btn btn-outline-primary" href="{{ route('sanpham.import') }}">
                <i class="fa-solid fa-file-import"></i>
                <span>Import Excel</span>
            </a>
        @endif
        <a class="btn btn-primary" href="{{ route('sanpham.them') }}">
            <i class="fa-solid fa-plus"></i>
            <span>Thêm sản phẩm</span>
        </a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('sanpham.danhsach') }}" class="d-flex align-items-center gap-2">
            <label class="form-label mb-0 text-nowrap" for="loc">Lọc:</label>
            <select class="form-select form-select-sm" id="loc" name="loc" style="max-width: 260px;" onchange="this.form.submit()">
                <option value="" @selected(($loc ?? '') === '')>Tất cả sản phẩm</option>
                <option value="ngung_ban" @selected(($loc ?? '') === 'ngung_ban')>Ngừng bán</option>
                <option value="gia_nhap_0" @selected(($loc ?? '') === 'gia_nhap_0')>Chưa có giá nhập (0đ)</option>
            </select>
            @if (($loc ?? '') !== '')
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('sanpham.danhsach') }}">Xóa lọc</a>
            @endif
        </form>
    </div>
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
                <th>Hãng</th>
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
                             src="{{ $sanPham->anh_url }}"
                             alt="{{ $sanPham->ten_sanpham }}"
                             loading="lazy" referrerpolicy="no-referrer">
                    </td>
                    <td><span class="font-monospace">{{ $sanPham->sku }}</span></td>
                    <td>{{ $sanPham->ten_sanpham }}</td>
                    <td>{{ $sanPham->loaiSanPham->ten_loai }}</td>
                    <td>{{ $sanPham->hangSanXuat?->ten ?? '—' }}</td>
                    <td class="text-end money">{{ number_format($sanPham->gia_ban, 0, ',', '.') }} đ</td>
                    <td class="text-center"><span class="badge text-bg-{{ $badge }}">{{ $sanPham->soluong }}</span></td>
                    <td>
                        @if ($sanPham->trang_thai)
                            <span class="badge text-bg-success">Đang bán</span>
                        @else
                            <span class="badge text-bg-danger">Tạm dừng</span>
                        @endif
                        @if ((float) $sanPham->gia_nhap === 0.0)
                            <span class="badge text-bg-warning" title="Giá nhập = 0: chưa nhập kho hoặc cần kiểm tra">Chưa có giá nhập</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('sanpham.sua', $sanPham) }}">Sửa</a>
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('sanpham.tem', $sanPham) }}">Tem</a>
                        <form class="d-inline" method="POST" action="{{ route('sanpham.xoa', $sanPham) }}" onsubmit="return confirm('Xóa sản phẩm này?')" data-can-pin="m2">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td class="text-center text-muted py-4" colspan="9">Chưa có sản phẩm.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection