@extends('layouts.master')

@section('title', 'Hãng sản xuất')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Hãng sản xuất</h1>
    <div class="d-flex gap-2">
        @if (in_array(auth()->user()->role, ['admin', 'thukho'], true))
            <a class="btn btn-outline-success" href="{{ route('hangsxuat.xuatexcel') }}">
                <i class="fa-solid fa-file-excel"></i> Xuất Excel
            </a>
        @endif
        @if ($laAdmin)
            <a class="btn btn-primary" href="{{ route('hangsxuat.them') }}">Thêm hãng</a>
        @endif
    </div>
</div>
@if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
<div class="card">
    <div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead><tr><th>Logo</th><th>Tên hãng</th><th>Slug</th><th>Số SP</th><th>Trạng thái</th>@if ($laAdmin)<th>Thao tác</th>@endif</tr></thead>
            <tbody>
            @forelse ($hangs as $hang)
                <tr>
                    <td style="width:60px;">
                        @if ($hang->logo)
                            <img src="{{ asset('storage/'.$hang->logo) }}" alt="" style="width:40px;height:40px;object-fit:contain;">
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>{{ $hang->ten }}</td>
                    <td><code>{{ $hang->slug }}</code></td>
                    <td>{{ $hang->san_phams_count }}</td>
                    <td>
                        @if ($hang->kich_hoat)
                            <span class="badge bg-success">Đang bán</span>
                        @else
                            <span class="badge bg-secondary">Đã tắt</span>
                        @endif
                    </td>
                    @if ($laAdmin)
                        <td>
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('hangsxuat.sua', $hang) }}">Sửa</a>
                            @if ($hang->san_phams_count === 0)
                                <form class="d-inline" method="POST" action="{{ route('hangsxuat.xoa', $hang) }}" onsubmit="return confirm('Xóa hãng này?')" data-can-pin="m2">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
                                </form>
                            @endif
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td class="text-center" colspan="6">Chưa có hãng nào.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
