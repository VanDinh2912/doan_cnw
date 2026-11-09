@extends('storefront.layouts.app')

@section('title', $category->ten_loai . ' — PhoneShop')

@section('content')
    <div class="sf-container" style="padding-top:20px;">
        <nav aria-label="breadcrumb" style="font-size:var(--sf-text-sm);color:var(--sf-fg-muted);margin-bottom:12px;">
            <a href="{{ route('storefront.home') }}" style="color:var(--sf-primary);text-decoration:none;">Trang chủ</a>
            <span style="margin:0 6px;">/</span>
            <span>{{ $category->ten_loai }}</span>
        </nav>

        <div class="sf-section-head">
            <h1 class="sf-section-title">{{ $category->ten_loai }}</h1>
            <span style="font-size:var(--sf-text-sm);color:var(--sf-fg-muted);">
                {{ $products->total() }} sản phẩm
            </span>
        </div>

        <form method="GET" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-bottom:20px;">
            <label style="display:flex;align-items:center;gap:6px;font-size:var(--sf-text-sm);color:var(--sf-fg-muted);">
                <input type="checkbox" name="con_hang" value="1" @checked(request()->boolean('con_hang'))>
                Chỉ hiện còn hàng
            </label>

            <select name="sap_xep" onchange="this.form.submit()"
                    style="height:36px;padding:0 12px;border:1px solid var(--sf-border-strong);border-radius:8px;font-size:var(--sf-text-sm);background:var(--sf-surface);">
                <option value="moi" @selected(request('sap_xep', 'moi') === 'moi')>Mới nhất</option>
                <option value="gia_tang" @selected(request('sap_xep') === 'gia_tang')>Giá thấp đến cao</option>
                <option value="gia_giam" @selected(request('sap_xep') === 'gia_giam')>Giá cao đến thấp</option>
            </select>

            <button type="submit" class="sf-icon-btn is-primary" style="height:36px;">
                <i class="fa-solid fa-filter"></i> Áp dụng
            </button>
        </form>

        @if ($products->isEmpty())
            <div style="text-align:center;padding:60px 16px;color:var(--sf-fg-muted);">
                <i class="fa-solid fa-box-open" style="font-size:56px;opacity:0.4;display:block;margin-bottom:12px;"></i>
                <p style="margin:0;font-size:var(--sf-text-md);">Không có sản phẩm trong danh mục này.</p>
                <a href="{{ route('storefront.home') }}" class="sf-icon-btn is-primary" style="margin-top:16px;">
                    <i class="fa-solid fa-arrow-left"></i> Về trang chủ
                </a>
            </div>
        @else
            <div class="sf-product-grid">
                @foreach ($products as $sanPham)
                    @include('storefront.partials._product-card', ['sanPham' => $sanPham])
                @endforeach
            </div>

            <div style="margin-top:24px;">
                {{ $products->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
@endsection