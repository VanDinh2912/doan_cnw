@extends('layouts.master')

@section('title', 'Xác nhận PIN')

@section('content')
<div class="col-lg-5 mx-auto">
    <div class="card">
        <div class="card-header fw-bold">Xác nhận PIN</div>
        <div class="card-body">
            @if(session('loi_pin'))
                <div class="alert alert-danger">{{ session('loi_pin') }}</div>
            @endif
            <p class="text-muted small mb-3">
                Nhập PIN 6 số của bạn để xác nhận thao tác
                @if($muc === 'nghiemtrong') <strong>nhạy cảm</strong>@else thông thường@endif.
                Mã xác nhận chỉ có hiệu lực một lần / trong 5 phút.
            </p>
            <form method="POST" action="{{ route('pin.xacnhan') }}">
                @csrf
                <input type="hidden" name="muc" value="{{ $muc }}">
                <input type="hidden" name="return" value="{{ $returnUrl ?? '' }}">
                <div class="mb-3">
                    <label class="form-label" for="pin">PIN (6 số)</label>
                    <input type="password" name="pin" id="pin" class="form-control text-center"
                           inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="off"
                           required autofocus>
                </div>
                <button type="submit" class="btn btn-primary w-100">Xác nhận</button>
                @if($returnUrl)
                    <a href="{{ $returnUrl }}" class="btn btn-link w-100">Hủy, quay lại</a>
                @endif
            </form>
            <hr>
            <p class="small text-muted mb-0">Quên PIN? <a href="{{ route('pin.xincap') }}">Gửi yêu cầu cấp/đặt lại PIN</a> cho quản trị viên.</p>
        </div>
    </div>
</div>
@endsection
