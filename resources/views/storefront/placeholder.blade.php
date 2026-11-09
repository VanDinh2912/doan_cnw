<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Storefront Placeholder - Web POS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="auth-shell">
    <div class="auth-card" style="width:min(100%,560px);">
        <div class="auth-brand">
            <i class="fa-solid fa-store"></i>
            <span>Storefront</span>
        </div>
        <h1 class="auth-title">Storefront Placeholder</h1>
        <p class="auth-subtitle">Trang chạy thử Luồng Auth</p>

        @if (session('success'))
            <div class="alert alert-success" role="alert">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-warning" role="alert">{{ session('error') }}</div>
        @endif

        @auth
            <div class="alert alert-info" role="status">
                Xin chào, <strong>{{ auth()->user()->name }}</strong>
                <span class="app-user-role">{{ auth()->user()->role }}</span>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-3">
                <a class="btn btn-outline-secondary" href="{{ route('tongquan') }}">
                    <i class="fa-solid fa-gauge-high"></i>
                    <span>Thử truy cập trang Quản trị (/tongquan)</span>
                </a>
                <a class="btn btn-outline-danger" href="{{ route('auth.dangxuat') }}">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Đăng xuất</span>
                </a>
            </div>
        @else
            <p class="text-muted mb-3">
                Bạn đang truy cập với tư cách khách. Đăng nhập hoặc đăng ký để tiếp tục.
            </p>
            <div class="d-grid gap-2">
                <a class="btn btn-primary" href="{{ route('auth.dangnhap') }}">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    <span>Đăng nhập</span>
                </a>
                <a class="btn btn-outline-primary" href="{{ route('auth.dangky') }}">
                    <i class="fa-solid fa-user-plus"></i>
                    <span>Đăng ký tài khoản khách hàng</span>
                </a>
            </div>
        @endauth
    </div>
</main>
</body>
</html>