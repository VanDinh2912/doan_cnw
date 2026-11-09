@extends('storefront.layouts.app')

@section('title', 'Giỏ hàng — PhoneShop')

@section('content')
    <div class="sf-container" style="padding-top:20px;">
        <div class="sf-section-head">
            <h1 class="sf-section-title">Giỏ hàng</h1>
            @if (! empty($cart))
                <span style="font-size:var(--sf-text-sm);color:var(--sf-fg-muted);">{{ count($cart) }} sản phẩm</span>
            @endif
        </div>

        @if (empty($cart))
            <div style="text-align:center;padding:60px 16px;color:var(--sf-fg-muted);">
                <i class="fa-solid fa-cart-shopping" style="font-size:56px;opacity:0.4;display:block;margin-bottom:12px;"></i>
                <p style="margin:0;font-size:var(--sf-text-md);">Giỏ hàng của bạn đang trống.</p>
                <a href="{{ route('storefront.home') }}" class="sf-icon-btn is-primary" style="margin-top:16px;">
                    <i class="fa-solid fa-arrow-left"></i> Mua sắm ngay
                </a>
            </div>
        @else
            <form method="POST" action="{{ route('storefront.cart.update') }}">
                @csrf
                @method('PUT')

                <div style="background:var(--sf-surface);border:1px solid var(--sf-border);border-radius:12px;overflow:hidden;margin-bottom:20px;">
                    @foreach ($cart as $id => $item)
                        <div style="display:grid;grid-template-columns:80px 1fr 120px 120px 48px;gap:12px;align-items:center;padding:16px;border-bottom:1px solid var(--sf-border);" class="sf-cart-row">
                            <img src="{{ asset($item['hinh_anh'] ?: 'img/no-image.svg') }}"
                                 alt="{{ $item['ten_sanpham'] }}"
                                 style="width:80px;height:80px;object-fit:cover;border-radius:8px;border:1px solid var(--sf-border);">

                            <div>
                                <a href="{{ route('storefront.search', ['q' => $item['sku']]) }}"
                                   style="font-size:var(--sf-text-base);font-weight:500;color:var(--sf-fg);text-decoration:none;display:block;margin-bottom:4px;">
                                    {{ $item['ten_sanpham'] }}
                                </a>
                                <div style="font-size:var(--sf-text-xs);color:var(--sf-fg-subtle);">SKU: {{ $item['sku'] }}</div>
                            </div>

                            <input type="number"
                                   name="soluong[{{ $id }}]"
                                   value="{{ $item['soluong'] }}"
                                   min="1" max="{{ $item['ton_kho'] }}"
                                   style="height:40px;border:1px solid var(--sf-border-strong);border-radius:8px;padding:0 10px;width:100%;font-variant-numeric:tabular-nums;">

                            <div style="text-align:right;font-family:var(--sf-font-heading);font-weight:700;color:var(--sf-accent);font-variant-numeric:tabular-nums;">
                                {{ number_format($item['gia_ban'] * $item['soluong'], 0, ',', '.') }} ₫
                            </div>

                            <a href="{{ route('storefront.cart.remove', $id) }}"
                               style="display:grid;place-items:center;height:36px;width:36px;border-radius:8px;color:var(--sf-stock-out);text-decoration:none;"
                               aria-label="Xóa {{ $item['ten_sanpham'] }}"
                               onclick="return confirm('Xóa sản phẩm khỏi giỏ hàng?');">
                                <i class="fa-solid fa-trash"></i>
                            </a>
                        </div>
                    @endforeach
                </div>

                <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;padding:20px;background:var(--sf-surface);border:1px solid var(--sf-border);border-radius:12px;">
                    <div>
                        <div style="font-size:var(--sf-text-sm);color:var(--sf-fg-muted);">Tổng cộng</div>
                        <div style="font-family:var(--sf-font-heading);font-size:var(--sf-text-2xl);font-weight:800;color:var(--sf-accent);">
                            {{ number_format($total, 0, ',', '.') }} ₫
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <button type="submit" class="sf-icon-btn" style="height:44px;">
                            <i class="fa-solid fa-rotate"></i> Cập nhật
                        </button>
                        <button type="button" class="sf-icon-btn is-primary" style="height:44px;padding:0 24px;" disabled
                                title="Thanh toán — sắp ra mắt">
                            <i class="fa-solid fa-credit-card"></i> Thanh toán
                        </button>
                    </div>
                </div>
            </form>
        @endif
    </div>

    @push('styles')
    <style>
        @media (max-width: 768px) {
            .sf-cart-row { grid-template-columns: 60px 1fr 48px !important; }
            .sf-cart-row > :nth-child(3),
            .sf-cart-row > :nth-child(4) { grid-column: 2 / -1; text-align: left; }
        }
    </style>
    @endpush
@endsection