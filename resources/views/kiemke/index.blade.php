@extends('layouts.master')
@section('title', 'Kiểm kê kho')
@section('content')
<div class="d-flex justify-content-between mb-3"><h1 class="h3">Phiếu kiểm kê</h1><a class="btn btn-primary" href="{{ route('kiemke.create') }}">Bắt đầu kiểm kê</a></div>
<div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Mã phiếu</th><th>Người tạo</th><th>Trạng thái</th><th></th></tr></thead><tbody>
@forelse ($phieuKies as $phieu)<tr><td>{{ $phieu->ma_phieu }}</td><td>{{ $phieu->user->name }}</td><td>{{ str_replace('_', ' ', $phieu->trang_thai) }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('kiemke.show', $phieu) }}">Chi tiết</a></td></tr>@empty<tr><td colspan="4" class="text-center">Chưa có phiếu.</td></tr>@endforelse
</tbody></table></div></div><div class="mt-3">{{ $phieuKies->links() }}</div>
@endsection
