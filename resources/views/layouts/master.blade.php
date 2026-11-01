<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Web POS')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    @stack('styles')
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="{{ route('tongquan') }}">Web POS</a>
        <div class="d-flex align-items-center gap-3 text-white">
            <span>{{ auth()->user()->name }} ({{ auth()->user()->role }})</span>
            <a class="btn btn-sm btn-light" href="{{ route('auth.dangxuat') }}">Đăng xuất</a>
        </div>
    </div>
</nav>
<div class="container-fluid">
    <div class="row">
        <aside class="col-md-2 bg-white border-end min-vh-100 p-3">
            <div class="nav flex-column gap-1">
                <a class="nav-link" href="{{ route('tongquan') }}">Tổng quan</a>
                @if (in_array(auth()->user()->role, ['admin', 'thungan'], true))
                    <a class="nav-link" href="{{ route('pos.index') }}">Bán hàng POS</a>
                @endif
                @if (in_array(auth()->user()->role, ['admin', 'thukho'], true))
                    <a class="nav-link" href="#">Sản phẩm</a>
                    <a class="nav-link" href="{{ route('loaisanpham.danhsach') }}">Loại sản phẩm</a>
                    <a class="nav-link" href="{{ route('kho.danhsach') }}">Kho</a>
                @endif
                @if (in_array(auth()->user()->role, ['admin', 'thungan'], true))
                    <a class="nav-link" href="{{ route('donhang.danhsach') }}">Hóa đơn</a>
                @endif
                @if (auth()->user()->role === 'admin')
                    <a class="nav-link" href="{{ route('thongke.doanhthu') }}">Thống kê</a>
                @endif
            </div>
        </aside>
        <main class="col-md-10 p-4">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
