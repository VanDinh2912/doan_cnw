<nav class="sf-tabbar" aria-label="Điều hướng nhanh">
    <div class="sf-tabbar-inner">
        <a class="sf-tab is-active" href="{{ route('storefront.home') }}">
            <i class="fa-solid fa-house"></i>
            <span>Trang chủ</span>
        </a>
        <a class="sf-tab" href="#" aria-disabled="true" title="Danh mục — sắp ra mắt">
            <i class="fa-solid fa-list"></i>
            <span>Danh mục</span>
        </a>
        <a class="sf-tab" href="#" aria-disabled="true" title="Giỏ hàng — sắp ra mắt">
            <i class="fa-solid fa-cart-shopping"></i>
            <span>Giỏ hàng</span>
        </a>
        @auth
            <a class="sf-tab" href="{{ route('auth.dangxuat') }}">
                <i class="fa-solid fa-user"></i>
                <span>Tôi</span>
            </a>
        @else
            <a class="sf-tab" href="{{ route('auth.dangnhap') }}">
                <i class="fa-solid fa-user"></i>
                <span>Tôi</span>
            </a>
        @endauth
    </div>
</nav>