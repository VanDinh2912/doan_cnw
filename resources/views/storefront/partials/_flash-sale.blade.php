@if ($flashSale->isNotEmpty())
<section class="sf-container sf-section" aria-labelledby="sf-flash-title">
    <div class="sf-flash">
        <div class="sf-flash-head">
            <h2 id="sf-flash-title" class="sf-flash-title">
                <i class="fa-solid fa-bolt" aria-hidden="true"></i>
                Flash Sale
            </h2>
            <div class="sf-flash-meta">
                <div class="sf-flash-countdown" role="timer" aria-label="Thời gian còn lại">
                    Kết thúc sau
                    <span data-unit="h">00</span>:
                    <span data-unit="m">00</span>:
                    <span data-unit="s">00</span>
                </div>
                <a class="sf-flash-more" href="{{ route('storefront.search') }}">
                    Xem thêm <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
        </div>

        <div class="sf-flash-grid">
            @foreach ($flashSale as $sanPham)
                @include('storefront.partials._product-card', ['sanPham' => $sanPham])
            @endforeach
        </div>
    </div>
</section>
@endif