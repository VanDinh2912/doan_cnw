@extends('layouts.master')

@section('title', 'Tổng quan')

@section('content')
<h1 class="h3 mb-4">Tổng quan</h1>
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted">Sản phẩm</div><div class="fs-3">{{ $soSanPham }}</div></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted">Hóa đơn</div><div class="fs-3">{{ $soHoaDon }}</div></div></div></div>
</div>
@if ($sanPhamTonThap->isNotEmpty())
    <div class="alert alert-warning"><strong>Cảnh báo tồn thấp</strong>
        <ul class="mb-0">@foreach ($sanPhamTonThap as $sanPham)<li>{{ $sanPham->ten_sanpham }}: còn {{ $sanPham->soluong }} (ngưỡng {{ $sanPham->soluong_toithieu }})</li>@endforeach</ul>
    </div>
@endif
@endsection
