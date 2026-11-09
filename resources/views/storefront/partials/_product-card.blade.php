@props(['sanPham'])

@php
    $giaBan = (float) $sanPham->gia_ban;
    $hinhAnh = $sanPham->hinh_anh ?: 'img/no-image.svg';
    $stockStatus = $sanPham->soluong <= 0
        ? 'is-out'
        : ($sanPham->soluong <= $sanPham->soluong_toithieu ? 'is-low' : 'is-in');
    $stockLabel = $sanPham->soluong <= 0
        ? 'Hết hàng'
        : ($sanPham->soluong <= $sanPham->soluong_toithieu ? 'Sắp hết' : 'Còn hàng');

    // Short display name: product type + brand + model. Full name stays in title/quick view.
    $fullName = $sanPham->ten_sanpham;
    $head = trim(preg_replace('/\s*\(.*?\)/u', '', \Illuminate\Support\Str::before($fullName, ' Chất liệu')));
    preg_match('/(?:Nhãn hiệu|Hiệu)\s*:?\s*([^.]+)\./u', $fullName, $mBrand);
    preg_match('/Model\s*:?\s*([^.]+)\./u', $fullName, $mModel);
    $shortName = (isset($mBrand[1]) || isset($mModel[1]))
        ? trim($head . ' ' . trim($mBrand[1] ?? '') . ' ' . trim($mModel[1] ?? ''))
        : $fullName;
@endphp

<article class="sf-product-card"
         data-id="{{ $sanPham->id }}"
         data-name="{{ $sanPham->ten_sanpham }}"
         data-price="{{ number_format($giaBan, 0, ',', '.') }} ₫"
         data-sku="{{ $sanPham->sku }}"
         data-image="{{ asset($hinhAnh) }}"
         data-category="{{ $sanPham->loaiSanPham?->ten_loai ?? 'Phụ kiện' }}"
         data-stock-label="{{ $stockLabel }}"
         data-stock-class="{{ $stockStatus }}"
         data-qty="{{ $sanPham->soluong }}">
    <div class="sf-product-media">
        <img src="{{ asset($hinhAnh) }}"
             alt="{{ $shortName }}"
             loading="lazy"
             width="300" height="300">

        <button type="button"
                class="sf-product-quickview js-quick-view"
                aria-label="Xem nhanh {{ $shortName }}"
                title="Xem nhanh thông số">
            <i class="fa-solid fa-eye" aria-hidden="true"></i>
        </button>

        <button type="button"
                class="sf-product-fav js-fav-toggle"
                data-id="{{ $sanPham->id }}"
                aria-label="Thêm {{ $shortName }} vào yêu thích"
                title="Thêm vào yêu thích">
            <i class="fa-regular fa-heart" aria-hidden="true"></i>
        </button>
    </div>

    <div class="sf-product-body">
        <h3 class="sf-product-name js-quick-view" role="button" tabindex="0" title="{{ $fullName }}">
            {{ $shortName }}
        </h3>

        <div class="sf-product-price">
            {{ number_format($giaBan, 0, ',', '.') }} ₫
        </div>

        <span class="sf-stock-badge {{ $stockStatus }}">{{ $stockLabel }}</span>

        @if ($sanPham->soluong > 0)
            <a class="sf-product-add" href="{{ route('storefront.cart.add', $sanPham) }}">
                <i class="fa-solid fa-cart-plus" aria-hidden="true"></i> Thêm vào giỏ
            </a>
        @endif
    </div>
</article>