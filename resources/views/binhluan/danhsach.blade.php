@extends('layouts.master')

@section('title', 'Quản lý bình luận')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Bình luận bài viết</h1>
    @if ($soChoDuyet > 0)
        <span class="badge bg-warning text-dark">{{ $soChoDuyet }} bình luận chờ duyệt</span>
    @endif
</div>

<ul class="nav nav-pills mb-3">
    <li class="nav-item"><a class="nav-link {{ $loc === '' ? 'active' : '' }}" href="{{ route('binhluan.danhsach') }}">Tất cả</a></li>
    <li class="nav-item"><a class="nav-link {{ $loc === 'cho_duyet' ? 'active' : '' }}" href="{{ route('binhluan.danhsach', ['loc' => 'cho_duyet']) }}">Chờ duyệt</a></li>
    <li class="nav-item"><a class="nav-link {{ $loc === 'da_duyet' ? 'active' : '' }}" href="{{ route('binhluan.danhsach', ['loc' => 'da_duyet']) }}">Đã duyệt</a></li>
    <li class="nav-item"><a class="nav-link {{ $loc === 'da_an' ? 'active' : '' }}" href="{{ route('binhluan.danhsach', ['loc' => 'da_an']) }}">Đã ẩn</a></li>
</ul>

<div class="card">
    <div class="table-responsive">
        <table class="table table-striped mb-0 align-middle">
            <thead>
            <tr><th>#</th><th>Nội dung</th><th>Người viết</th><th>Bài viết</th><th>Trạng thái</th><th>Gửi lúc</th><th class="text-end">Thao tác</th></tr>
            </thead>
            <tbody>
            @forelse ($binhLuans as $binhLuan)
                <tr>
                    <td>{{ $binhLuan->id }}</td>
                    <td style="max-width:340px;">{{ $binhLuan->noi_dung }}</td>
                    <td>{{ $binhLuan->nguoiDung->name ?? '—' }}</td>
                    <td>
                        @if ($binhLuan->baiViet)
                            <a href="{{ route('baiviet.sua', $binhLuan->baiViet) }}">{{ \Illuminate\Support\Str::limit($binhLuan->baiViet->tieu_de, 50) }}</a>
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if (! $binhLuan->kiem_duyet)
                            <span class="badge bg-warning text-dark">Chờ duyệt</span>
                        @elseif ($binhLuan->kich_hoat)
                            <span class="badge bg-success">Đang hiện</span>
                        @else
                            <span class="badge bg-secondary">Đã ẩn</span>
                        @endif
                    </td>
                    <td>{{ $binhLuan->created_at->format('d/m/Y H:i') }}</td>
                    <td class="text-end text-nowrap">
                        @if (! $binhLuan->kiem_duyet)
                            <form class="d-inline" method="POST" action="{{ route('binhluan.duyet', $binhLuan) }}" data-can-pin="thuong">
                                @csrf
                                <button class="btn btn-sm btn-outline-success" type="submit">Duyệt</button>
                            </form>
                        @endif
                        <form class="d-inline" method="POST" action="{{ route('binhluan.an', $binhLuan) }}" data-can-pin="thuong">
                            @csrf
                            <button class="btn btn-sm btn-outline-secondary" type="submit">{{ $binhLuan->kich_hoat ? 'Ẩn' : 'Hiện' }}</button>
                        </form>
                        <form class="d-inline" method="POST" action="{{ route('binhluan.xoa', $binhLuan) }}" onsubmit="return confirm('Xóa bình luận này?')" data-can-pin="nghiemtrong">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td class="text-center" colspan="7">Chưa có bình luận.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">
    {{ $binhLuans->links('pagination::bootstrap-5') }}
</div>
@endsection