@php
    $cartCount = count(session('storefront_cart', []));
    $user = auth()->user();
    $isStaff = $user && in_array($user->role, ['admin', 'thungan', 'kho'], true);
@endphp
<header class="sf-header">
    <div class="sf-container sf-header-inner">
        <a class="sf-logo" href="{{ route('storefront.home') }}">
            <i class="fa-solid fa-mobile-screen"></i>
            <span>{{ config('storefront.ten_cua_hang') }}</span>
        </a>

        <form class="sf-search" action="{{ route('storefront.search') }}" method="GET" role="search">
            <label for="sf-search-input" class="visually-hidden">Tìm kiếm sản phẩm</label>
            <input id="sf-search-input" type="search" name="q"
                   value="{{ request('q') }}"
                   placeholder="Bạn tìm phụ kiện gì hôm nay?"
                   autocomplete="off">
            <button type="submit" aria-label="Tìm kiếm">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>
        </form>

        <div class="sf-header-actions">
            <a class="sf-icon-btn" href="{{ route('tintuc.danhsach') }}" title="Tin tức">
                <i class="fa-solid fa-newspaper" aria-hidden="true"></i>
                <span class="d-none d-md-inline">Tin tức</span>
            </a>
            <a class="sf-icon-btn" href="{{ route('storefront.cart.index') }}" aria-label="Giỏ hàng{{ $cartCount > 0 ? ' (' . $cartCount . ' sản phẩm)' : '' }}">
                <i class="fa-solid fa-cart-shopping" aria-hidden="true"></i>
                <span class="d-none d-md-inline">Giỏ hàng</span>
                @if ($cartCount > 0)
                    <b class="sf-cart-badge">{{ $cartCount }}</b>
                @endif
            </a>
            @auth
                @if ($isStaff)
                    <a class="sf-icon-btn" href="{{ route('tongquan') }}" title="Trang quản trị">
                        <i class="fa-solid fa-gauge-high"></i>
                        <span class="d-none d-md-inline">Trang quản trị</span>
                    </a>
                @endif
                <a class="sf-icon-btn" href="{{ route('profile.show') }}" title="Thông tin cá nhân">
                    <i class="fa-solid fa-user"></i>
                    <span class="d-none d-md-inline">{{ $user->name }}</span>
                </a>
                <a class="sf-icon-btn" href="{{ route('auth.dangxuat') }}" title="Đăng xuất">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span class="d-none d-md-inline">Đăng xuất</span>
                </a>
            @else
                <a class="sf-icon-btn" href="{{ route('auth.dangnhap') }}">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    <span class="d-none d-md-inline">Đăng nhập</span>
                </a>
                <a class="sf-icon-btn is-primary" href="{{ route('auth.dangky') }}">
                    <i class="fa-solid fa-user-plus"></i>
                    <span class="d-none d-md-inline">Đăng ký</span>
                </a>
            @endauth
        </div>
    </div>
</header>
