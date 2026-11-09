<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Cửa hàng điện thoại — Mua sắm chính hãng')</title>
    @vite(['resources/css/storefront.css', 'resources/js/storefront.js'])
    @stack('styles')
</head>
<body class="sf-body">

@include('storefront.partials._header')

<main>
    @if (session('success'))
        <div class="sf-container">
            <div class="sf-alert is-success" role="alert">{{ session('success') }}</div>
        </div>
    @endif
    @if (session('error'))
        <div class="sf-container">
            <div class="sf-alert is-error" role="alert">{{ session('error') }}</div>
        </div>
    @endif

    @yield('content')
</main>

@include('storefront.partials._footer')
@include('storefront.partials._mobile-tab-bar')

</body>
</html>