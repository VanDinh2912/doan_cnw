@extends('layouts.master')

@section('title', 'Lịch sử kho')

@section('content')
<h1 class="h3 mb-3">Lịch sử biến động kho</h1>
<div class="card"><div class="table-responsive"><table class="table table-striped mb-0">
    <thead><tr><th>Thời gian</th><th>Sản phẩm</th><th>Loại</th><th>Số lượng</th><th>Người thực hiện</th><th>Ghi chú</th></tr></thead>
    <tbody>@forelse ($bienDongs as $bienDong)
        <tr><td>{{ $bienDong->created_at->format('d/m/Y H:i') }}</td><td>{{ $bienDong->sanPham->ten_sanpham }}</td><td>{{ $bienDong->loai }}</td><td>{{ $bienDong->soluong }}</td><td>{{ $bienDong->user->name }}</td><td>{{ $bienDong->ghi_chu ?: '—' }}</td></tr>
    @empty<tr><td class="text-center" colspan="6">Chưa có biến động.</td></tr>@endforelse</tbody>
</table></div></div>
<div class="mt-3">{{ $bienDongs->links() }}</div>
@endsection
