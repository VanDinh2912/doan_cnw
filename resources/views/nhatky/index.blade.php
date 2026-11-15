@extends('layouts.master')

@section('title', 'Nhật ký hoạt động')

@section('content')
<h1 class="h3 mb-3">Nhật ký hoạt động</h1>
<form class="row g-2 mb-3">
    <div class="col-md-3"><select class="form-select" name="hanh_dong"><option value="">Tất cả hành động</option>@foreach($hanhDongs as $hanhDong)<option value="{{ $hanhDong }}" @selected(request('hanh_dong') === $hanhDong)>{{ $hanhDong }}</option>@endforeach</select></div>
    <div class="col-md-2"><input class="form-control" name="doi_tuong" value="{{ request('doi_tuong') }}" placeholder="Đối tượng"></div>
    <div class="col-md-2"><input class="form-control" name="doi_tuong_id" value="{{ request('doi_tuong_id') }}" placeholder="Mã đối tượng"></div>
    <div class="col-md-2"><input class="form-control" type="date" name="tu_ngay" value="{{ request('tu_ngay') }}"></div>
    <div class="col-md-2"><input class="form-control" type="date" name="den_ngay" value="{{ request('den_ngay') }}"></div>
    <div class="col-auto"><button class="btn btn-outline-secondary">Lọc</button></div>
    <div class="col-auto"><a class="btn btn-outline-primary" href="{{ route('nhatky.export', request()->query()) }}">Xuất CSV</a></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-striped mb-0"><thead><tr><th>Thời gian</th><th>Người dùng</th><th>Hành động</th><th>Đối tượng</th><th>Nguồn</th><th>Mô tả</th><th>IP</th></tr></thead><tbody>
@forelse ($nhatKys as $nhatKy)<tr><td>{{ $nhatKy->created_at?->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i:s') }}</td><td>{{ $nhatKy->user?->name ?? 'Hệ thống' }}</td><td>{{ $nhatKy->hanh_dong }}</td><td>{{ $nhatKy->doi_tuong }}#{{ $nhatKy->doi_tuong_id }}</td><td>{{ $nhatKy->nguon ?? 'web' }}</td><td>{{ $nhatKy->mo_ta }}</td><td>{{ $nhatKy->ip ?: '-' }}</td></tr>@empty<tr><td colspan="7" class="text-center">Chưa có nhật ký.</td>@endforelse
</tbody></table></div></div><div class="mt-3">{{ $nhatKys->links() }}</div>
@endsection
