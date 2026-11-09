<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng nhập - Web POS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="auth-shell">
    <div class="auth-card">
        <div class="auth-brand">
            <i class="fa-solid fa-cash-register"></i>
            <span>Hệ thống bán hàng</span>
        </div>
        <h1 class="auth-title">Đăng nhập Web POS</h1>
        <p class="auth-subtitle">Hệ thống bán hàng & quản lý kho</p>

        @if (session('success'))
            <div class="alert alert-success" role="alert">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('auth.postdangnhap') }}">
            @csrf

            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input id="email" name="email" type="email"
                       class="form-control @error('email') is-invalid @enderror"
                       value="{{ old('email') }}" required autofocus autocomplete="username">
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="password">Mật khẩu</label>
                <input id="password" name="password" type="password"
                       class="form-control @error('password') is-invalid @enderror"
                       required autocomplete="current-password">
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="form-check mb-4">
                <input class="form-check-input" id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))>
                <label class="form-check-label" for="remember">Ghi nhớ đăng nhập</label>
            </div>

            <button class="btn btn-primary w-100" type="submit">
                <i class="fa-solid fa-right-to-bracket"></i>
                <span>Đăng nhập</span>
            </button>
        </form>

        <p class="text-center text-muted mt-3 mb-0" style="font-size:var(--text-sm);">
            Chưa có tài khoản?
            <a href="{{ route('auth.dangky') }}">Đăng ký khách hàng</a>
        </p>
    </div>
</main>
</body>
</html>