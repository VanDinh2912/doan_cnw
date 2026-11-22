{{--
    Điều hướng chung cho trang lỗi 403/404/419/500.
    Staff thấy nút về khu quản trị + mở cửa hàng tab mới;
    khách/guest thấy về storefront.
--}}
@php
    $laStaff = auth()->check()
        && in_array(auth()->user()->role, ['admin', 'thungan', 'thukho'], true);
@endphp
<div style="margin-top:1.5rem;display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;">
    @if ($laStaff)
        <a href="{{ route('tongquan') }}"
           style="display:inline-block;padding:.6rem 1.2rem;background:#0d6efd;color:#fff;border-radius:8px;text-decoration:none;">
            Về tổng quan
        </a>
        <a href="{{ route('storefront.home') }}" target="_blank" rel="noopener"
           style="display:inline-block;padding:.6rem 1.2rem;border:1px solid #0d6efd;color:#0d6efd;border-radius:8px;text-decoration:none;">
            Xem cửa hàng
        </a>
    @else
        <a href="{{ route('storefront.home') }}"
           style="display:inline-block;padding:.6rem 1.2rem;background:#0d6efd;color:#fff;border-radius:8px;text-decoration:none;">
            Về trang chủ
        </a>
        <a href="{{ route('auth.dangnhap') }}"
           style="display:inline-block;padding:.6rem 1.2rem;border:1px solid #0d6efd;color:#0d6efd;border-radius:8px;text-decoration:none;">
            Đăng nhập
        </a>
    @endif
</div>
