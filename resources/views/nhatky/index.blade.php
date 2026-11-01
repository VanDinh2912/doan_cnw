@extends('layouts.master')

@section('title', 'Nhật ký hoạt động')

@section('content')
<h1 class="h3 mb-3">Nhật ký hoạt động</h1>
<form class="row g-2 mb-3"><div class="col-md-4"><input class="form-control" name="hanh_dong" value="{{ request('hanh_dong') }}" placeholder="Lọc hành động"></div><div class="col-auto"><button class="btn btn-outline-secondary">Lọc</button></div></form>
<div class="card"><div class="table-responsive"><table class="table table-striped mb-0"><thead><tr><th>Thời gian</th><th>Người dùng</th><th>Hành động</th><th>Đối tượng</th><th>Mô tả</th><th>IP</th></tr></thead><tbody>
@forelse ($nhatKys as $nhatKy)<tr><td>{{ $nhatKy->created_at->format('d/m/Y H:i:s') }}</td><td>{{ $nhatKy->user?->name ?? 'Hệ thống' }}</td><td>{{ $nhatKy->hanh_dong }}</td><td>{{ $nhatKy->doi_tuong }}#{{ $nhatKy->doi_tuong_id }}</td><td>{{ $nhatKy->mo_ta }}</td><td>{{ $nhatKy->ip ?: '-' }}</td></tr>@empty<tr><td colspan="6" class="text-center">Chưa có nhật ký.</td></tr>@endforelse
</tbody></table></div></div><div class="mt-3">{{ $nhatKys->links() }}</div>
@endsection
