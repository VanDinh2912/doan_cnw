@extends('layouts.master')

@section('title', 'Quản lý bài viết')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Bài viết</h1>
    <a class="btn btn-primary" href="{{ route('baiviet.them') }}">Thêm bài viết</a>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-auto">
        <input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="Tìm theo tiêu đề...">
    </div>
    <div class="col-auto">
        <select class="form-select" name="kiem_duyet">
            <option value="">Tất cả trạng thái</option>
            <option value="1" @selected(request('kiem_duyet') === '1')>Đã duyệt</option>
            <option value="0" @selected(request('kiem_duyet') === '0')>Chưa duyệt</option>
        </select>
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-secondary" type="submit">Lọc</button>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-striped mb-0 align-middle">
            <thead>
            <tr><th>#</th><th>Tiêu đề</th><th>Chủ đề</th><th>Tác giả</th><th>Lượt xem</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr>
            </thead>
            <tbody>
            @forelse ($baiViets as $baiViet)
                <tr>
                    <td>{{ $baiViet->id }}</td>
                    <td>
                        <a href="{{ route('tintuc.chitiet', [$baiViet->chuDe->slug ?? '', $baiViet->slug]) }}" target="_blank" rel="noopener">{{ $baiViet->tieu_de }}</a>
                    </td>
                    <td>{{ $baiViet->chuDe->ten_chude ?? '—' }}</td>
                    <td>{{ $baiViet->tacGia->name ?? '—' }}</td>
                    <td>{{ number_format($baiViet->luot_xem) }}</td>
                    <td>
                        @if ($baiViet->kiem_duyet)
                            <span class="badge bg-success">Đã duyệt</span>
                        @else
                            <span class="badge bg-warning text-dark">Chưa duyệt</span>
                        @endif
                        @if ($baiViet->kich_hoat)
                            <span class="badge bg-info text-dark">Đang hiện</span>
                        @else
                            <span class="badge bg-secondary">Đang ẩn</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('baiviet.sua', $baiViet) }}">Sửa</a>
                        <form class="d-inline" method="POST" action="{{ route('baiviet.duyet', $baiViet) }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-success" type="submit">{{ $baiViet->kiem_duyet ? 'Bỏ duyệt' : 'Duyệt' }}</button>
                        </form>
                        <form class="d-inline" method="POST" action="{{ route('baiviet.kichhoat', $baiViet) }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-secondary" type="submit">{{ $baiViet->kich_hoat ? 'Ẩn' : 'Hiện' }}</button>
                        </form>
                        <form class="d-inline" method="POST" action="{{ route('baiviet.xoa', $baiViet) }}" onsubmit="return confirm('Xóa bài viết này? Toàn bộ bình luận sẽ bị xóa theo.')" data-can-pin="a">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td class="text-center" colspan="7">Chưa có bài viết.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">
    {{ $baiViets->links('pagination::bootstrap-5') }}
</div>
@endsection