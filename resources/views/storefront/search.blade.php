@extends('storefront.layouts.app')

@section('title', 'Tìm kiếm — PhoneShop')

@section('content')
    <div class="sf-container" style="padding-top:20px;">
        <div class="sf-section-head">
            <h1 class="sf-section-title">
                @if ($q !== '')
                    Kết quả cho "{{ $q }}"
                @else
                    Tất cả sản phẩm
                @endif
            </h1>
            <span style="font-size:var(--sf-text-sm);color:var(--sf-fg-muted);">
                {{ $products->total() }} sản phẩm
            </span>
        </div>

        @if ($hangs->isNotEmpty())
            <form method="GET" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-bottom:20px;">
                <input type="hidden" name="q" value="{{ $q }}">
                <select name="brand_id" onchange="this.form.submit()"
                        style="height:36px;padding:0 12px;border:1px solid var(--sf-border-strong);border-radius:8px;font-size:var(--sf-text-sm);background:var(--sf-surface);">
                    <option value="">Tất cả hãng</option>
                    @foreach ($hangs as $hang)
                        <option value="{{ $hang->id }}" @selected((string) request('brand_id') === (string) $hang->id)>{{ $hang->ten }}</option>
                    @endforeach
                </select>
            </form>
        @endif

        @if ($products->isEmpty())
            <div style="text-align:center;padding:60px 16px;color:var(--sf-fg-muted);">
                <i class="fa-solid fa-magnifying-glass" style="font-size:56px;opacity:0.4;display:block;margin-bottom:12px;"></i>
                <p style="margin:0;font-size:var(--sf-text-md);">Không tìm thấy sản phẩm phù hợp.</p>
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