@extends('layouts.master')
@section('title', 'Chi tiết kiểm kê')
@section('content')
<div class="d-flex justify-content-between mb-3"><h1 class="h3">{{ $phieu->ma_phieu }}</h1><span>{{ str_replace('_', ' ', $phieu->trang_thai) }}</span></div>
@if ($phieu->trang_thai === 'dang_kiem')
<form method="POST" action="{{ route('kiemke.submit', $phieu) }}">@csrf
@endif
<div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Sản phẩm</th><th>Tồn lúc bắt đầu</th><th>Thực tế</th><th>Chênh lệch</th></tr></thead><tbody>
@foreach ($phieu->chiTiets as $item)<tr><td>{{ $item->sanPham->ten_sanpham }}</td><td>{{ $item->soluong_he_thong }}</td><td>@if ($phieu->trang_thai === 'dang_kiem')<input class="form-control" type="number" min="0" name="soluong_thuc[{{ $item->id }}]" value="{{ $item->soluong_he_thong }}">@else{{ $item->soluong_thuc }}@endif</td><td>{{ $item->chenh_lech ?? '-' }}</td></tr>@endforeach
</tbody></table></div></div>
@if ($phieu->trang_thai === 'dang_kiem')<button class="btn btn-primary mt-3">Gửi duyệt</button></form>@endif
@if ($phieu->trang_thai === 'cho_duyet' && auth()->user()->role === 'admin')<form method="POST" action="{{ route('kiemke.approve', $phieu) }}">@csrf<button class="btn btn-success mt-3">Duyệt điều chỉnh</button></form>@endif
<a class="btn btn-link px-0" href="{{ route('kiemke.index') }}">Quay lại</a>
@endsection
