@extends('layouts.master')

@section('title', 'Liên hệ #' . $lienHe->id)

@section('content')
@php $inputStyle = 'width:100%;'; @endphp
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Liên hệ #{{ $lienHe->id }}</h1>
    <a class="btn btn-outline-secondary" href="{{ route('lienhe.danhsach') }}">← Danh sách</a>
</div>
@if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="row">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header"><strong>Nội dung khách gửi</strong></div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th style="width:140px;">Loại</th><td><span class="badge bg-secondary">{{ $lienHe->nhanLoai() }}</span></td></tr>
                    <tr><th>Vị trí ứng tuyển</th><td>{{ $lienHe->nhanViTri() ?: '—' }}</td></tr>
                    <tr><th>Họ tên</th><td>{{ $lienHe->ho_ten }}</td></tr>
                    <tr><th>Số điện thoại</th><td>{{ $lienHe->so_dien_thoai ?: '—' }}</td></tr>
                    <tr><th>Email</th><td>{{ $lienHe->email ?: '—' }}</td></tr>
                    <tr><th>Tài khoản</th><td>{{ $lienHe->user ? $lienHe->user->email.' ('.$lienHe->user->role.')' : 'Khách vãng lai' }}</td></tr>
                    <tr><th>Thời gian</th><td>{{ $lienHe->created_at->format('H:i d/m/Y') }}</td></tr>
                </table>
                <hr>
                <div style="font-weight:600;margin-bottom:6px;">{{ $lienHe->chu_de }}</div>
                <div style="white-space:pre-line;background:var(--bs-tertiary-bg,#f8f9fa);border-radius:8px;padding:12px;">{{ $lienHe->noi_dung }}</div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header"><strong>Xử lý</strong></div>
            <div class="card-body">
                <form method="POST" action="{{ route('lienhe.capnhat', $lienHe) }}" data-can-pin="thuong">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Trạng thái</label>
                        <select name="trang_thai" class="form-select">
                            @foreach (\App\Models\LienHe::TRANG_THAI as $gt)
                                <option value="{{ $gt }}" {{ old('trang_thai', $lienHe->trang_thai) === $gt ? 'selected' : '' }}>{{ ['moi'=>'Mới','dang_xu_ly'=>'Đang xử lý','da_xu_ly'=>'Đã xử lý'][$gt] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ghi chú nội bộ <span class="text-muted">(không hiển thị cho khách)</span></label>
                        <textarea name="ghi_chu_noibo" rows="5" maxlength="2000" class="form-control">{{ old('ghi_chu_noibo', $lienHe->ghi_chu_noibo) }}</textarea>
                    </div>
                    <button class="btn btn-primary" type="submit">Lưu</button>
                </form>
            </div>
        </div>
        @if ($lienHe->ghi_chu_noibo)
            <div class="card">
                <div class="card-header"><strong>Ghi chú nội bộ hiện tại</strong></div>
                <div class="card-body"><div style="white-space:pre-line;">{{ $lienHe->ghi_chu_noibo }}</div></div>
            </div>
        @endif
    </div>
</div>
@endsection
