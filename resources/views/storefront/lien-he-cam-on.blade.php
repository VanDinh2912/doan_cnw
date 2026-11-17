@extends('storefront.layouts.app')

@section('title', 'Đã gửi liên hệ — PhoneShop')

@section('content')
    <div class="sf-container" style="padding-top:40px;max-width:640px;text-align:center;">
        <i class="fa-solid fa-circle-check" style="font-size:64px;color:var(--sf-primary);display:block;margin-bottom:16px;"></i>
        <h1 class="sf-section-title">Đã gửi thành công</h1>
        <p style="color:var(--sf-fg-muted);font-size:var(--sf-text-md);margin:12px 0 24px;">
            Cảm ơn bạn đã liên hệ. Chúng tôi sẽ phản hồi qua số điện thoại hoặc email bạn cung cấp.
            Nếu là khiếu nại, vui lòng giữ lại thông tin đơn hàng để quá trình xử lý nhanh hơn.
        </p>
        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
            <a href="{{ route('storefront.home') }}" class="sf-icon-btn is-primary" style="height:44px;padding:0 24px;">
                <i class="fa-solid fa-arrow-left"></i> Về trang chủ
            </a>
            @if (config('pos.dat_hang_online_bat'))
                <a href="{{ route('don-hang.tracuu') }}" class="sf-icon-btn" style="height:44px;padding:0 24px;">
                    <i class="fa-solid fa-magnifying-glass"></i> Tra cứu đơn hàng
                </a>
            @endif
        </div>
    </div>
@endsection
