<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng nhập - Web POS</title>
    <style>
        body { align-items: center; background: #f1f5f9; display: flex; font-family: Arial, sans-serif; justify-content: center; min-height: 100vh; }
        .card { background: white; border-radius: 12px; box-shadow: 0 8px 30px rgb(15 23 42 / 12%); padding: 2rem; width: min(100% - 2rem, 420px); }
        h1 { margin-top: 0; }
        label { display: block; font-weight: 600; margin: 1rem 0 .4rem; }
        input { border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; padding: .7rem; width: 100%; }
        button { background: #2563eb; border: 0; border-radius: 6px; color: white; cursor: pointer; margin-top: 1.25rem; padding: .75rem; width: 100%; }
        .error, .success { border-radius: 6px; margin-bottom: 1rem; padding: .7rem; }
        .error { background: #fee2e2; color: #991b1b; }
        .success { background: #dcfce7; color: #166534; }
        .invalid { color: #b91c1c; font-size: .9rem; margin-top: .3rem; }
    </style>
</head>
<body>
<main class="card">
    <h1>Đăng nhập Web POS</h1>
    @if (session('success'))
        <div class="success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="error">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif
    <form method="POST" action="{{ route('auth.postdangnhap') }}">
        @csrf
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>
        @error('email') <div class="invalid">{{ $message }}</div> @enderror

        <label for="password">Mật khẩu</label>
        <input id="password" name="password" type="password" required>
        @error('password') <div class="invalid">{{ $message }}</div> @enderror

        <label>
            <input name="remember" type="checkbox" value="1" style="width:auto" @checked(old('remember'))>
            Ghi nhớ đăng nhập
        </label>
        <button type="submit">Đăng nhập</button>
    </form>
</main>
</body>
</html>
