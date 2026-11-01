@extends('layouts.master')

@section('title', 'Loại sản phẩm')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Loại sản phẩm</h1>
    <a class="btn btn-primary" href="{{ route('loaisanpham.them') }}">Thêm loại</a>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead>
            <tr><th>#</th><th>Tên loại</th><th>Mô tả</th><th>Số sản phẩm</th><th class="text-end">Thao tác</th></tr>
            </thead>
            <tbody>
            @forelse ($loais as $loai)
                <tr>
                    <td>{{ $loai->id }}</td>
                    <td>{{ $loai->ten_loai }}</td>
                    <td>{{ $loai->mo_ta ?: '—' }}</td>
                    <td>{{ $loai->san_phams_count }}</td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('loaisanpham.sua', $loai) }}">Sửa</a>
                        <form class="d-inline" method="POST" action="{{ route('loaisanpham.xoa', $loai) }}" onsubmit="return confirm('Xóa loại sản phẩm này?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td class="text-center" colspan="5">Chưa có loại sản phẩm.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
