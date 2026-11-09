@extends('storefront.layouts.app')

@section('title', 'PhoneShop — Phụ kiện điện thoại chính hãng')

@section('content')
<div class="sf-home">
    @include('storefront.partials._hero')

    @include('storefront.partials._category-rail')

    @include('storefront.partials._flash-sale')

    @include('storefront.partials._brand-strip')

    <section class="sf-container sf-section" aria-labelledby="sf-featured-title">
        <div class="sf-section-head">
            <div>
                <h2 id="sf-featured-title" class="sf-section-title">Phụ kiện nổi bật</h2>
                <p class="sf-section-sub">Sản phẩm mới cập nhật, sẵn hàng giao ngay</p>
            </div>
            <div class="sf-section-actions">
                <span class="sf-chip">
                    <i class="fa-solid fa-box-open" aria-hidden="true"></i> {{ $featured->count() }} sản phẩm sẵn hàng
                </span>
                <a class="sf-section-link" href="{{ route('storefront.search') }}">
                    Xem tất cả <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
        </div>

        @if ($featured->isEmpty())
            <div class="sf-empty">
                <i class="fa-solid fa-box-open" aria-hidden="true"></i>
                <p>Sản phẩm đang được cập nhật. Vui lòng quay lại sau.</p>
            </div>
        @else
            <div class="sf-product-grid">
                @foreach ($featured as $sanPham)
                    @include('storefront.partials._product-card', ['sanPham' => $sanPham])
                @endforeach
            </div>
        @endif
    </section>

    @include('storefront.partials._trust-bar')

    @include('storefront.partials._newsletter')

    @include('storefront.partials._quick-view-modal')
</div>
@endsection