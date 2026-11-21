<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Web POS')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body data-img-fallback="{{ asset('img/no-image.svg') }}">
<header class="app-topbar">
    <a class="app-brand" href="{{ route('tongquan') }}">
        <i class="fa-solid fa-cash-register"></i>
        <span>Web POS</span>
    </a>
    <div class="ms-auto d-flex align-items-center gap-3">
        <div class="app-account">
            <button type="button" class="app-account-toggle" id="appAccountToggle" aria-haspopup="true" aria-expanded="false" aria-controls="appAccountMenu">
                <i class="fa-solid fa-circle-user"></i>
                <span class="d-none d-md-inline">{{ auth()->user()->name }}</span>
                <span class="app-user-role">{{ auth()->user()->role }}</span>
                <i class="fa-solid fa-chevron-down app-account-caret"></i>
            </button>
            <div class="app-account-menu" id="appAccountMenu" hidden>
                <a href="{{ route('profile.show') }}"><i class="fa-solid fa-id-card"></i> Thông tin cá nhân</a>
                <a href="{{ route('doi-mat-khau') }}"><i class="fa-solid fa-key"></i> Đổi mật khẩu</a>
                <a href="{{ route('pin.doi') }}"><i class="fa-solid fa-shield-halved"></i> Đổi PIN</a>
                <hr>
                <a href="{{ route('auth.dangxuat') }}"><i class="fa-solid fa-right-from-bracket"></i> Đăng xuất</a>
            </div>
        </div>
    </div>
</header>

<div class="app-shell">
    <aside class="app-sidebar">
        <nav class="app-nav">
            <a class="app-nav-link {{ request()->routeIs('tongquan') ? 'active' : '' }}" href="{{ route('tongquan') }}">
                <i class="fa-solid fa-gauge-high"></i><span>Tổng quan</span>
            </a>

            @if (in_array(auth()->user()->role, ['admin', 'thungan'], true))
                <details class="app-nav-group" data-nav-group="ban-hang">
                    <summary class="app-nav-group-toggle"><span>Bán hàng</span><i class="fa-solid fa-chevron-down app-nav-group-caret"></i></summary>
                    <div class="app-nav-group-body">
                        <a class="app-nav-link {{ request()->routeIs('pos.*') ? 'active' : '' }}" href="{{ route('pos.index') }}">
                            <i class="fa-solid fa-cart-shopping"></i><span>Bán hàng POS</span>
                        </a>
                        <a class="app-nav-link {{ request()->routeIs('hold.*') ? 'active' : '' }}" href="{{ route('hold.danhsach') }}">
                            <i class="fa-solid fa-pause"></i><span>Giỏ tạm</span>
                        </a>
                        <a class="app-nav-link {{ request()->routeIs('donhang.*') ? 'active' : '' }}" href="{{ route('donhang.danhsach') }}">
                            <i class="fa-solid fa-receipt"></i><span>Hóa đơn</span>
                        </a>
                        <a class="app-nav-link {{ request()->routeIs('donhangonline.*') ? 'active' : '' }}" href="{{ route('donhangonline.danhsach') }}">
                            <i class="fa-solid fa-truck-fast"></i><span>Đơn online</span>
                            <span class="badge bg-danger ms-1 js-badge-don-online" style="display:none;">0</span>
                        </a>
                        @if (auth()->user()->role === 'admin')
                            <a class="app-nav-link {{ request()->routeIs('donhangonline.canhoantien') ? 'active' : '' }}" href="{{ route('donhangonline.canhoantien') }}">
                                <i class="fa-solid fa-money-bill-wave"></i><span>Đơn cần hoàn tiền</span>
                            </a>
                        @endif
                        <a class="app-nav-link {{ request()->routeIs('khachhang.*') ? 'active' : '' }}" href="{{ route('khachhang.danhsach') }}">
                            <i class="fa-solid fa-user-group"></i><span>Khách hàng</span>
                        </a>
                        <a class="app-nav-link {{ request()->routeIs('trahang.*') ? 'active' : '' }}" href="{{ route('trahang.danhsach') }}">
                            <i class="fa-solid fa-rotate-left"></i><span>Đổi trả hàng</span>
                        </a>
                        @if (auth()->user()->role === 'thungan')
                            <a class="app-nav-link {{ request()->routeIs('hangsxuat.*') ? 'active' : '' }}" href="{{ route('hangsxuat.danhsach') }}">
                                <i class="fa-solid fa-copyright"></i><span>Hãng sản xuất</span>
                            </a>
                        @endif
                    </div>
                </details>
            @endif

            @if (in_array(auth()->user()->role, ['admin', 'thukho'], true))
                <details class="app-nav-group" data-nav-group="kho">
                    <summary class="app-nav-group-toggle"><span>Kho</span><i class="fa-solid fa-chevron-down app-nav-group-caret"></i></summary>
                    <div class="app-nav-group-body">
                        <a class="app-nav-link {{ request()->routeIs('sanpham.*') ? 'active' : '' }}" href="{{ route('sanpham.danhsach') }}">
                            <i class="fa-solid fa-box"></i><span>Sản phẩm</span>
                        </a>
                        <a class="app-nav-link {{ request()->routeIs('loaisanpham.*') ? 'active' : '' }}" href="{{ route('loaisanpham.danhsach') }}">
                            <i class="fa-solid fa-tags"></i><span>Loại sản phẩm</span>
                        </a>
                        <a class="app-nav-link {{ request()->routeIs('hangsxuat.*') ? 'active' : '' }}" href="{{ route('hangsxuat.danhsach') }}">
                            <i class="fa-solid fa-copyright"></i><span>Hãng sản xuất</span>
                        </a>
                        <a class="app-nav-link {{ request()->routeIs('kho.*') ? 'active' : '' }}" href="{{ route('kho.danhsach') }}">
                            <i class="fa-solid fa-warehouse"></i><span>Kho</span>
                        </a>
                        @if (auth()->user()->role === 'thukho')
                            <a class="app-nav-link {{ request()->routeIs('donhangonline.*') ? 'active' : '' }}" href="{{ route('donhangonline.danhsach') }}">
                                <i class="fa-solid fa-truck-fast"></i><span>Đơn online</span>
                                <span class="badge bg-danger ms-1 js-badge-don-online" style="display:none;">0</span>
                            </a>
                        @endif
                        <a class="app-nav-link {{ request()->routeIs('kiemke.*') ? 'active' : '' }}" href="{{ route('kiemke.index') }}">
                            <i class="fa-solid fa-clipboard-check"></i><span>Kiểm kê</span>
                        </a>
                        <a class="app-nav-link {{ request()->routeIs('nhaphang.nhacungcap*') ? 'active' : '' }}" href="{{ route('nhaphang.nhacungcap') }}">
                            <i class="fa-solid fa-truck"></i><span>Nhà cung cấp</span>
                        </a>
                        <a class="app-nav-link {{ request()->routeIs('nhaphang.donnhap*') ? 'active' : '' }}" href="{{ route('nhaphang.donnhap') }}">
                            <i class="fa-solid fa-file-import"></i><span>Đơn nhập hàng</span>
                        </a>
                        <a class="app-nav-link {{ request()->routeIs('hoadonnhap.*') ? 'active' : '' }}" href="{{ route('hoadonnhap.danhsach') }}">
                            <i class="fa-solid fa-file-invoice"></i><span>Hóa đơn nhập</span>
                        </a>
                    </div>
                </details>
            @endif

            @if (in_array(auth()->user()->role, ['admin', 'thungan'], true))
                <details class="app-nav-group" data-nav-group="noi-dung">
                    <summary class="app-nav-group-toggle"><span>Nội dung</span><i class="fa-solid fa-chevron-down app-nav-group-caret"></i></summary>
                    <div class="app-nav-group-body">
                        @if (in_array(auth()->user()->role, ['admin', 'thungan'], true))
                            <a class="app-nav-link {{ request()->routeIs('lienhe.*') ? 'active' : '' }}" href="{{ route('lienhe.danhsach') }}">
                                <i class="fa-solid fa-envelope"></i><span>Liên hệ</span>
                            </a>
                        @endif
                        @if (auth()->user()->role === 'admin')
                            <a class="app-nav-link {{ request()->routeIs('baiviet.*') ? 'active' : '' }}" href="{{ route('baiviet.danhsach') }}">
                                <i class="fa-solid fa-newspaper"></i><span>Bài viết</span>
                            </a>
                            <a class="app-nav-link {{ request()->routeIs('chude.*') ? 'active' : '' }}" href="{{ route('chude.danhsach') }}">
                                <i class="fa-solid fa-folder-open"></i><span>Chủ đề</span>
                            </a>
                            <a class="app-nav-link {{ request()->routeIs('binhluan.*') ? 'active' : '' }}" href="{{ route('binhluan.danhsach') }}">
                                <i class="fa-solid fa-comments"></i><span>Bình luận</span>
                            </a>
                        @endif
                    </div>
                </details>
            @endif

            @if (auth()->user()->role === 'admin')
                <details class="app-nav-group" data-nav-group="he-thong">
                    <summary class="app-nav-group-toggle"><span>Hệ thống</span><i class="fa-solid fa-chevron-down app-nav-group-caret"></i></summary>
                    <div class="app-nav-group-body">
                        <a class="app-nav-link {{ request()->routeIs('thongke.*') ? 'active' : '' }}" href="{{ route('thongke.index') }}">
                            <i class="fa-solid fa-chart-line"></i><span>Thống kê</span>
                        </a>
                        <a class="app-nav-link {{ request()->routeIs('nhatky.*') ? 'active' : '' }}" href="{{ route('nhatky.index') }}">
                            <i class="fa-solid fa-clock-rotate-left"></i><span>Nhật ký hoạt động</span>
                        </a>
                        <a class="app-nav-link {{ request()->routeIs('taikhoan.*') ? 'active' : '' }}" href="{{ route('taikhoan.danhsach') }}">
                            <i class="fa-solid fa-users-gear"></i><span>Tài khoản</span>
                        </a>
                        <a class="app-nav-link {{ request()->routeIs('doisoat.*') ? 'active' : '' }}" href="{{ route('doisoat.index') }}">
                            <i class="fa-solid fa-scale-balanced"></i><span>Đối soát thanh toán</span>
                        </a>
                    </div>
                </details>
            @endif
        </nav>
    </aside>

    <main class="app-main">
        @if (session('success'))
            <div class="alert alert-success d-flex align-items-center gap-2" role="alert">
                <i class="fa-solid fa-circle-check"></i><div>{{ session('success') }}</div>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
                <i class="fa-solid fa-circle-exclamation"></i><div>{{ session('error') }}</div>
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <div class="d-flex align-items-center gap-2 fw-semibold mb-1">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span>Vui lòng kiểm tra lại thông tin:</span>
                </div>
                <ul class="mb-0 ps-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</div>

<script src="{{ asset('js/pin.js') }}" defer></script>
<script>
// Khoi phuc vi tri cuon sidebar sau dieu huong (sessionStorage, bo qua neu loi)
(function () {
    try {
        var KEY = 'app_sidebar_scroll';
        var sb = document.querySelector('.app-sidebar');
        if (!sb) { return; }
        document.querySelectorAll('.app-nav a').forEach(function (a) {
            a.addEventListener('click', function () {
                try { sessionStorage.setItem(KEY, String(sb.scrollTop)); } catch (e) {}
            });
        });
        var saved = null;
        try { saved = sessionStorage.getItem(KEY); } catch (e) {}
        if (saved !== null) {
            sb.scrollTop = parseInt(saved, 10) || 0;
            try { sessionStorage.removeItem(KEY); } catch (e) {}
        }
        var active = sb.querySelector('.app-nav-link.active');
        if (active) {
            var ag = active.closest('.app-nav-group');
            if (ag) { ag.open = true; }
        }
        if (active && (active.offsetTop < sb.scrollTop || active.offsetTop > sb.scrollTop + sb.clientHeight)) {
            active.scrollIntoView({ block: 'nearest' });
        }
    } catch (e) { /* bo qua */ }
})();
</script>
<script>
// Nhom menu thu gon: mo nhom chua trang active, ghi nho trang thai, mobile hien tat ca
(function () {
    try {
        var KEY = 'app_nav_groups';
        var groups = document.querySelectorAll('.app-nav-group');
        if (!groups.length) { return; }
        var mobile = window.matchMedia('(max-width: 991.98px)').matches;
        if (mobile) {
            groups.forEach(function (g) { g.open = true; });
            return;
        }
        var saved = {};
        try { saved = JSON.parse(sessionStorage.getItem(KEY) || '{}'); } catch (e) {}
        groups.forEach(function (g) {
            var ten = g.getAttribute('data-nav-group');
            if (saved[ten] === true) { g.open = true; }
            g.addEventListener('toggle', function () {
                try {
                    var s = {};
                    try { s = JSON.parse(sessionStorage.getItem(KEY) || '{}'); } catch (e2) {}
                    s[ten] = g.open;
                    sessionStorage.setItem(KEY, JSON.stringify(s));
                } catch (e3) {}
            });
        });
        var activeGroup = document.querySelector('.app-nav-group .app-nav-link.active');
        if (activeGroup) {
            var g = activeGroup.closest('.app-nav-group');
            if (g) { g.open = true; }
        }
    } catch (e) { /* bo qua */ }
})();
// Dropdown tai khoan: click, Esc, click ra ngoai
(function () {
    try {
        var btn = document.getElementById('appAccountToggle');
        var menu = document.getElementById('appAccountMenu');
        if (!btn || !menu) { return; }
        function dong() {
            menu.hidden = true;
            btn.setAttribute('aria-expanded', 'false');
        }
        btn.addEventListener('click', function (ev) {
            ev.stopPropagation();
            var mo = menu.hidden;
            menu.hidden = !mo;
            btn.setAttribute('aria-expanded', String(mo));
        });
        document.addEventListener('click', function (ev) {
            if (!menu.hidden && !menu.contains(ev.target) && ev.target !== btn && !btn.contains(ev.target)) { dong(); }
        });
        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape') { dong(); btn.focus(); }
        });
    } catch (e) { /* bo qua */ }
})();
</script>
@stack('scripts')
@if (in_array(auth()->user()->role ?? '', ['admin', 'thungan', 'thukho'], true))
<script>
(function () {
    var badges = document.querySelectorAll('.js-badge-don-online');
    if (!badges.length) { return; }
    var url = @json(route('donhangonline.soluong'));
    function capNhat() {
        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) {
                if (!d) { return; }
                badges.forEach(function (b) {
                    if (d.so_luong > 0) { b.style.display = ''; b.textContent = d.so_luong; }
                    else { b.style.display = 'none'; }
                });
            })
            .catch(function () {
                badges.forEach(function (b) { b.textContent = '!'; b.style.display = ''; b.title = 'Lỗi mạng khi cập nhật số đơn online'; });
            });
    }
    capNhat();
    setInterval(capNhat, 30000);
})();
</script>
@endif
</body>
</html>