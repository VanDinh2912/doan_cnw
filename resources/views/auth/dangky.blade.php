<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng ký tài khoản - Web POS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="auth-shell">
    <div class="auth-card">
        <div class="auth-brand">
            <i class="fa-solid fa-user-plus"></i>
            <span>Tạo tài khoản khách hàng</span>
        </div>
        <h1 class="auth-title">Đăng ký</h1>
        <p class="auth-subtitle">Dành cho khách hàng mua sắm tại cửa hàng</p>

        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('auth.postdangky') }}">
            @csrf

            <div class="mb-3">
                <label class="form-label" for="name">Họ tên</label>
                <input id="name" name="name" type="text"
                       class="form-control @error('name') is-invalid @enderror"
                       value="{{ old('name') }}" required autofocus autocomplete="name">
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input id="email" name="email" type="email"
                       class="form-control @error('email') is-invalid @enderror"
                       value="{{ old('email') }}" required autocomplete="email">
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="so_dienthoai">Số điện thoại</label>
                <input id="so_dienthoai" name="so_dienthoai" type="tel"
                       class="form-control @error('so_dienthoai') is-invalid @enderror"
                       value="{{ old('so_dienthoai') }}" required autocomplete="tel"
                       placeholder="Ví dụ: 0912345678">
                @error('so_dienthoai') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="password">Mật khẩu</label>
                <input id="password" name="password" type="password"
                       class="form-control @error('password') is-invalid @enderror"
                       required autocomplete="new-password">
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-4">
                <label class="form-label" for="password_confirmation">Xác nhận mật khẩu</label>
                <input id="password_confirmation" name="password_confirmation" type="password"
                       class="form-control" required autocomplete="new-password">
            </div>

            <button class="btn btn-primary w-100" type="submit">
                <i class="fa-solid fa-user-plus"></i>
                <span>Đăng ký</span>
            </button>
        </form>

        <p class="text-center text-muted mt-3 mb-0" style="font-size:var(--text-sm);">
            Đã có tài khoản?
            <a href="{{ route('auth.dangnhap') }}">Đăng nhập</a>
        </p>
    </div>
</main>
</body>
</html>