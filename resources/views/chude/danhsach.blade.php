@extends('layouts.master')

@section('title', 'Chủ đề tin tức')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Chủ đề tin tức</h1>
    <a class="btn btn-primary" href="{{ route('chude.them') }}">Thêm chủ đề</a>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-striped mb-0 js-data-table">
            <thead>
            <tr><th>#</th><th>Tên chủ đề</th><th>Slug</th><th>Mô tả</th><th>Số bài viết</th><th class="text-end">Thao tác</th></tr>
            </thead>
            <tbody>
            @forelse ($chuDes as $chuDe)
                <tr>
                    <td>{{ $chuDe->id }}</td>
                    <td>{{ $chuDe->ten_chude }}</td>
                    <td><code>{{ $chuDe->slug }}</code></td>
                    <td>{{ $chuDe->mo_ta ?: '—' }}</td>
                    <td>{{ $chuDe->bai_viets_count }}</td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('chude.sua', $chuDe) }}">Sửa</a>
                        <form class="d-inline" method="POST" action="{{ route('chude.xoa', $chuDe) }}" onsubmit="return confirm('Xóa chủ đề này?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td class="text-center" colspan="6">Chưa có chủ đề.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection