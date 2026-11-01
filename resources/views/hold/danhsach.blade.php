@extends('layouts.master')
@section('title', 'Giỏ tạm')
@section('content')
<h1 class="h3 mb-3">Giỏ hàng tạm</h1>
<div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Tên giỏ</th><th>Người giữ</th><th>Thời gian</th><th></th></tr></thead><tbody>
@forelse ($gioTams as $gioTam)<tr><td>{{ $gioTam->ten_goi }}</td><td>{{ $gioTam->user->name }}</td><td>{{ $gioTam->created_at->format('d/m/Y H:i') }}</td><td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="{{ route('hold.khoiphuc', $gioTam) }}">Khôi phục</a><form class="d-inline" method="POST" action="{{ route('hold.xoa', $gioTam) }}">@csrf<button class="btn btn-sm btn-outline-danger">Xóa</button></form></td></tr>@empty<tr><td colspan="4" class="text-center">Chưa có giỏ tạm.</td></tr>@endforelse
</tbody></table></div></div><div class="mt-3">{{ $gioTams->links() }}</div>
@endsection
