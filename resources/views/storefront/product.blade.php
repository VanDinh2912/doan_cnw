@extends('storefront.layouts.app')

@section('title', $sanPham->ten_sanpham . ' — PhoneShop')

@section('content')
    @php
        $hinhAnh = $sanPham->hinh_anh ?: 'img/no-image.svg';
        $inStock = $sanPham->soluong > 0;
        $isLow = $inStock && $sanPham->soluong <= $sanPham->soluong_toithieu;
        $badge = ! $inStock ? ['is-out', 'Hết hàng'] : ($isLow ? ['is-low', 'Sắp hết'] : ['is-in', 'Còn hàng']);
    @endphp

    <div class="sf-container" style="padding-top:20px;">
        <nav aria-label="breadcrumb" style="font-size:var(--sf-text-sm);color:var(--sf-fg-muted);margin-bottom:16px;">
            <a href="{{ route('storefront.home') }}" style="color:var(--sf-primary);text-decoration:none;">Trang chủ</a>
            @if ($sanPham->loaiSanPham)
                <span style="margin:0 6px;">/</span>
                <a href="{{ route('storefront.category', $sanPham->loaiSanPham) }}" style="color:var(--sf-primary);text-decoration:none;">{{ $sanPham->loaiSanPham->ten_loai }}</a>
            @endif
            <span style="margin:0 6px;">/</span>
            <span>{{ $sanPham->ten_sanpham }}</span>
        </nav>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:32px;align-items:start;" class="sf-product-detail">
            <div style="background:var(--sf-surface);border:1px solid var(--sf-border);border-radius:12px;overflow:hidden;aspect-ratio:1/1;">
                <img src="{{ asset($hinhAnh) }}" alt="{{ $sanPham->ten_sanpham }}"
                     style="width:100%;height:100%;object-fit:contain;padding:16px;box-sizing:border-box;background:#fff;display:block;">
            </div>

            <div>
                <h1 style="font-family:var(--sf-font-heading);font-size:var(--sf-text-2xl);font-weight:700;margin:0 0 8px;line-height:1.25;">
                    {{ $sanPham->ten_sanpham }}
                </h1>

                <div style="display:flex;gap:8px;align-items:center;margin-bottom:16px;">
                    <span class="sf-stock-badge {{ $badge[0] }}">{{ $badge[1] }}</span>
                    <span style="font-size:var(--sf-text-xs);color:var(--sf-fg-subtle);">SKU: {{ $sanPham->sku }}</span>
                </div>

                <div style="padding:20px;background:var(--sf-accent-soft);border:1px solid var(--sf-accent-border);border-radius:12px;margin-bottom:20px;">
                    <div style="font-family:var(--sf-font-heading);font-size:var(--sf-text-3xl);font-weight:800;color:var(--sf-accent);line-height:1;">
                        {{ number_format((float) $sanPham->gia_ban, 0, ',', '.') }} ₫
                    </div>
                    <div style="font-size:var(--sf-text-xs);color:var(--sf-fg-muted);margin-top:6px;">
                        Giá đã bao gồm VAT
                    </div>
                </div>

                <dl style="display:grid;grid-template-columns:120px 1fr;gap:8px 16px;font-size:var(--sf-text-sm);margin:0 0 24px;">
                    <dt style="color:var(--sf-fg-muted);">Danh mục</dt>
                    <dd style="margin:0;">{{ $sanPham->loaiSanPham?->ten_loai ?? '—' }}</dd>
                    <dt style="color:var(--sf-fg-muted);">Đơn vị</dt>
                    <dd style="margin:0;">{{ $sanPham->don_vi }}</dd>
                    <dt style="color:var(--sf-fg-muted);">Tồn kho</dt>
                    <dd style="margin:0;">{{ $sanPham->soluong }} sản phẩm</dd>
                </dl>

                <div style="display:flex;gap:12px;flex-wrap:wrap;">
                    @if ($inStock)
                        <a class="sf-icon-btn is-primary" style="height:48px;padding:0 24px;font-size:var(--sf-text-md);"
                           href="{{ route('storefront.cart.add', $sanPham) }}">
                            <i class="fa-solid fa-cart-plus"></i> Thêm vào giỏ
                        </a>
                    @else
                        <button class="sf-icon-btn" style="height:48px;padding:0 24px;font-size:var(--sf-text-md);" disabled>
                            <i class="fa-solid fa-ban"></i> Tạm hết hàng
                        </button>
                    @endif
                    <a class="sf-icon-btn" style="height:48px;padding:0 24px;font-size:var(--sf-text-md);"
                       href="{{ route('storefront.home') }}">
                        <i class="fa-solid fa-arrow-left"></i> Tiếp tục mua sắm
                    </a>
                </div>

                <div style="margin-top:24px;padding:16px;background:var(--sf-surface);border:1px solid var(--sf-border);border-radius:12px;font-size:var(--sf-text-sm);color:var(--sf-fg-muted);">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                        <i class="fa-solid fa-shield-halved" style="color:var(--sf-primary);"></i>
                        <strong style="color:var(--sf-fg);">Bảo hành 12 tháng chính hãng</strong>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <i class="fa-solid fa-rotate-left" style="color:var(--sf-primary);"></i>
                        <span>Đổi trả trong 30 ngày nếu lỗi nhà sản xuất</span>
                    </div>
                </div>
            </div>
        </div>

        @if ($related->isNotEmpty())
            <section class="sf-section">
                <div class="sf-section-head">
                    <h2 class="sf-section-title">Sản phẩm tương tự</h2>
                </div>
                <div class="sf-product-grid">
                    @foreach ($related as $sp)
                        @include('storefront.partials._product-card', ['sanPham' => $sp])
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    @push('styles')
    <style>
        @media (max-width: 768px) {
            .sf-product-detail { grid-template-columns: 1fr !important; }
        }
    </style>
    @endpush
@endsection