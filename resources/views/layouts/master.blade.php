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
<body>
<header class="app-topbar">
    <a class="app-brand" href="{{ route('tongquan') }}">
        <i class="fa-solid fa-cash-register"></i>
        <span>Web POS</span>
    </a>
    <div class="ms-auto d-flex align-items-center gap-3">
        <span class="app-user">
            {{ auth()->user()->name }}
            <span class="app-user-role">{{ auth()->user()->role }}</span>
        </span>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('auth.dangxuat') }}">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span class="d-none d-sm-inline">Đăng xuất</span>
        </a>
    </div>
</header>

<div class="app-shell">
    <aside class="app-sidebar">
        <nav class="app-nav">
            <a class="app-nav-link {{ request()->routeIs('tongquan') ? 'active' : '' }}" href="{{ route('tongquan') }}">
                <i class="fa-solid fa-gauge-high"></i><span>Tổng quan</span>
            </a>

            @if (in_array(auth()->user()->role, ['admin', 'thungan'], true))
                <div class="app-nav-label">Bán hàng</div>
                <a class="app-nav-link {{ request()->routeIs('pos.*') ? 'active' : '' }}" href="{{ route('pos.index') }}">
                    <i class="fa-solid fa-cart-shopping"></i><span>Bán hàng POS</span>
                </a>
                <a class="app-nav-link {{ request()->routeIs('hold.*') ? 'active' : '' }}" href="{{ route('hold.danhsach') }}">
                    <i class="fa-solid fa-pause"></i><span>Giỏ tạm</span>
                </a>
                <a class="app-nav-link {{ request()->routeIs('donhang.*') ? 'active' : '' }}" href="{{ route('donhang.danhsach') }}">
                    <i class="fa-solid fa-receipt"></i><span>Hóa đơn</span>
                </a>
                <a class="app-nav-link {{ request()->routeIs('khachhang.*') ? 'active' : '' }}" href="{{ route('khachhang.danhsach') }}">
                    <i class="fa-solid fa-user-group"></i><span>Khách hàng</span>
                </a>
                <a class="app-nav-link {{ request()->routeIs('trahang.*') ? 'active' : '' }}" href="{{ route('trahang.danhsach') }}">
                    <i class="fa-solid fa-rotate-left"></i><span>Đổi trả hàng</span>
                </a>
            @endif

            @if (in_array(auth()->user()->role, ['admin', 'thukho'], true))
                <div class="app-nav-label">Kho & hàng hóa</div>
                <a class="app-nav-link {{ request()->routeIs('sanpham.*') ? 'active' : '' }}" href="{{ route('sanpham.danhsach') }}">
                    <i class="fa-solid fa-box"></i><span>Sản phẩm</span>
                </a>
                <a class="app-nav-link {{ request()->routeIs('loaisanpham.*') ? 'active' : '' }}" href="{{ route('loaisanpham.danhsach') }}">
                    <i class="fa-solid fa-tags"></i><span>Loại sản phẩm</span>
                </a>
                <a class="app-nav-link {{ request()->routeIs('kho.*') ? 'active' : '' }}" href="{{ route('kho.danhsach') }}">
                    <i class="fa-solid fa-warehouse"></i><span>Kho</span>
                </a>
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
            @endif

            @if (auth()->user()->role === 'admin')
                <div class="app-nav-label">Quản trị</div>
                <a class="app-nav-link {{ request()->routeIs('thongke.*') ? 'active' : '' }}" href="{{ route('thongke.index') }}">
                    <i class="fa-solid fa-chart-line"></i><span>Thống kê</span>
                </a>
                <a class="app-nav-link {{ request()->routeIs('nhatky.*') ? 'active' : '' }}" href="{{ route('nhatky.index') }}">
                    <i class="fa-solid fa-clock-rotate-left"></i><span>Nhật ký hoạt động</span>
                </a>
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

@stack('scripts')
</body>
</html>