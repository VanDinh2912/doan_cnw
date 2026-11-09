@extends('layouts.master')

@section('title', 'Tổng quan')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Tổng quan</h1>
        <p class="page-subtitle">Tình hình hoạt động của cửa hàng</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-4">
        <div class="stat-card">
            <div class="stat-label">Sản phẩm</div>
            <div class="stat-value">{{ number_format($soSanPham, 0, ',', '.') }}</div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="stat-card">
            <div class="stat-label">Hóa đơn</div>
            <div class="stat-value">{{ number_format($soHoaDon, 0, ',', '.') }}</div>
        </div>
    </div>
</div>

@if ($sanPhamTonThap->isNotEmpty())
    <div class="card border-warning">
        <div class="card-header d-flex align-items-center gap-2 text-warning-emphasis">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span>Cảnh báo tồn kho thấp</span>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Sản phẩm</th>
                        <th class="text-end">Tồn kho</th>
                        <th class="text-end">Ngưỡng tối thiểu</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($sanPhamTonThap as $sanPham)
                    <tr>
                        <td>{{ $sanPham->ten_sanpham }}</td>
                        <td class="text-end">
                            <span class="badge {{ $sanPham->soluong === 0 ? 'text-bg-danger' : 'text-bg-warning' }}">{{ $sanPham->soluong }}</span>
                        </td>
                        <td class="text-end text-muted">{{ $sanPham->soluong_toithieu }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection