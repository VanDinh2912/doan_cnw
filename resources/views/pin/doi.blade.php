@extends('layouts.master')

@section('title', 'Đổi PIN')

@section('content')
<div class="col-lg-5 mx-auto">
    <div class="card">
        <div class="card-header fw-bold">Đổi PIN xác nhận</div>
        <div class="card-body">
            @if(session('loi_pin'))
                <div class="alert alert-danger">{{ session('loi_pin') }}</div>
            @endif
            @if(session('thanh_cong'))
                <div class="alert alert-success">{{ session('thanh_cong') }}</div>
            @endif
            <form method="POST" action="{{ route('pin.doi.post') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="pin_cu">PIN hiện tại</label>
                    <input type="password" name="pin_cu" id="pin_cu" class="form-control text-center"
                           inputmode="numeric" maxlength="6" autocomplete="off" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="pin_moi">PIN mới (6 số)</label>
                    <input type="password" name="pin_moi" id="pin_moi" class="form-control text-center"
                           inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="new-password" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="pin_moi_confirmation">Nhập lại PIN mới</label>
                    <input type="password" name="pin_moi_confirmation" id="pin_moi_confirmation" class="form-control text-center"
                           inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="new-password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Đổi PIN</button>
            </form>
            <hr>
            <p class="small text-muted mb-0">
                Quên PIN? <a href="{{ route('pin.xincap') }}">Gửi yêu cầu cấp/đặt lại PIN</a>.
                @if(\Illuminate\Support\Facades\Cache::has(\App\Http\Controllers\PinController::KHOA_PIN_TAM().auth()->id()))
                    · <a href="{{ route('pin.tam') }}">Xem PIN tạm vừa được cấp</a>
                @endif
            </p>
        </div>
    </div>
</div>
@endsection
