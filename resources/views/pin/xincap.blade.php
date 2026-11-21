@extends('layouts.master')

@section('title', 'Xin cấp PIN')

@section('content')
<div class="col-lg-6 mx-auto">
    <div class="card">
        <div class="card-header fw-bold">Xin cấp / đặt lại PIN</div>
        <div class="card-body">
            @if(session('thanh_cong'))
                <div class="alert alert-success">{{ session('thanh_cong') }}</div>
            @endif
            @if(session('loi_pin'))
                <div class="alert alert-danger">{{ session('loi_pin') }}</div>
            @endif
            @error('ly_do')
                <div class="alert alert-danger">{{ $message }}</div>
            @enderror

            <p class="text-muted small">
                Dùng khi bạn <strong>chưa có PIN</strong> (lần đầu) hoặc <strong>quên PIN</strong>.
                Yêu cầu sẽ được ghi vào nhật ký kèm lý do và báo cho quản trị viên.
                Sau khi được cấp, PIN tạm hiện tại trang
                <a href="{{ route('pin.tam') }}">PIN tạm của tôi</a> — chỉ xem được <strong>một lần</strong>.
            </p>

            <form method="POST" action="{{ route('pin.xincap.post') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="ly_do">Lý do xin cấp PIN <span class="text-danger">*</span></label>
                    <textarea name="ly_do" id="ly_do" class="form-control" rows="3" maxlength="255"
                              placeholder="VD: Lần đầu sử dụng; Quên PIN; Mất điện thoại…" required>{{ old('ly_do') }}</textarea>
                    <div class="form-text">Bắt buộc — quản trị viên sẽ thấy lý do này khi duyệt.</div>
                </div>
                <button type="submit" class="btn btn-primary w-100">Gửi yêu cầu</button>
            </form>
        </div>
    </div>
</div>
@endsection
