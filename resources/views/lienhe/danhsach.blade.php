@extends('layouts.master')

@section('title', 'Liên hệ')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Liên hệ / Góp ý / Khiếu nại</h1>
</div>
@if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('lienhe.danhsach') }}" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label mb-1">Loại</label>
                <select name="loai" class="form-select">
                    <option value="">Tất cả</option>
                    @foreach (\App\Models\LienHe::LOAI as $gt)
                        <option value="{{ $gt }}" {{ $loai === $gt ? 'selected' : '' }}>{{ ['lien_he'=>'Liên hệ','gop_y'=>'Góp ý','khieu_nai'=>'Khiếu nại','ung_tuyen'=>'Ứng tuyển'][$gt] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label mb-1">Trạng thái</label>
                <select name="trang_thai" class="form-select">
                    <option value="">Tất cả</option>
                    @foreach (\App\Models\LienHe::TRANG_THAI as $gt)
                        <option value="{{ $gt }}" {{ $trangThai === $gt ? 'selected' : '' }}>{{ ['moi'=>'Mới','dang_xu_ly'=>'Đang xử lý','da_xu_ly'=>'Đã xử lý'][$gt] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-outline-primary" type="submit">Lọc</button>
                <a class="btn btn-outline-secondary" href="{{ route('lienhe.danhsach') }}">Xóa lọc</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-striped mb-0 js-data-table">
            <thead><tr><th>#</th><th>Thời gian</th><th>Loại</th><th>Họ tên</th><th>Liên hệ</th><th>Chủ đề</th><th>Vị trí</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
            <tbody>
            @forelse ($lienHes as $lh)
                <tr>
                    <td>{{ $lh->id }}</td>
                    <td>{{ $lh->created_at->format('d/m/Y H:i') }}</td>
                    <td><span class="badge bg-secondary">{{ $lh->nhanLoai() }}</span></td>
                    <td>{{ $lh->ho_ten }}</td>
                    <td>
                        @if ($lh->so_dien_thoai)<div>{{ $lh->so_dien_thoai }}</div>@endif
                        @if ($lh->email)<div class="text-muted" style="font-size:.85em;">{{ $lh->email }}</div>@endif
                    </td>
                    <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $lh->chu_de }}</td>
                    <td>{{ $lh->nhanViTri() ?: '—' }}</td>
                    <td>
                        @php $mau = ['moi'=>'bg-danger','dang_xu_ly'=>'bg-warning text-dark','da_xu_ly'=>'bg-success'][$lh->trang_thai] ?? 'bg-secondary'; @endphp
                        <span class="badge {{ $mau }}">{{ $lh->nhanTrangThai() }}</span>
                    </td>
                    <td><a class="btn btn-sm btn-outline-primary" href="{{ route('lienhe.chitiet', $lh) }}">Xem</a></td>
                </tr>
            @empty
                <tr><td class="text-center" colspan="9">Chưa có liên hệ nào.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $lienHes->links() }}</div>
@endsection
