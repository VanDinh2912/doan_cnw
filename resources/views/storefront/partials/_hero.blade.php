<section class="sf-container sf-home-hero" aria-label="Khuyến mãi nổi bật">
    <div class="sf-bento">
        <div class="sf-bento-tile is-main">
            <div class="sf-bento-copy">
                <span class="sf-bento-eyebrow">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i> Chính hãng · Bảo hành 12 tháng
                </span>
                <h1 class="sf-bento-title">Phụ kiện điện thoại <span>chính hãng, giá tốt</span></h1>
                <p class="sf-bento-sub">Củ sạc, cáp, tai nghe, pin dự phòng từ Hoco, Borofone, Remax và nhiều thương hiệu khác.</p>
                <div class="sf-bento-actions">
                    <a class="sf-btn is-accent" href="{{ route('storefront.search') }}">
                        Mua ngay <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </a>
                    <a class="sf-btn is-ghost" href="#danh-muc">Xem danh mục</a>
                </div>
                <ul class="sf-bento-points">
                    <li><i class="fa-solid fa-check" aria-hidden="true"></i> Giao 24h nội thành</li>
                    <li><i class="fa-solid fa-check" aria-hidden="true"></i> Kiểm tra hàng trước khi thanh toán</li>
                </ul>
            </div>

            <div class="sf-bento-art" aria-hidden="true">
                <span class="sf-art-orb is-1"><i class="fa-solid fa-plug"></i></span>
                <span class="sf-art-orb is-2"><i class="fa-solid fa-bolt"></i></span>
                <span class="sf-art-orb is-3"><i class="fa-solid fa-headphones"></i></span>
            </div>
        </div>

        <a class="sf-bento-tile is-small is-ship" href="{{ route('storefront.search') }}">
            <span class="sf-tile-icon"><i class="fa-solid fa-truck-fast" aria-hidden="true"></i></span>
            <span class="sf-tile-text">
                <strong>Freeship từ 500.000 ₫</strong>
                <small>Giao trong 24h nội thành</small>
            </span>
            <i class="fa-solid fa-arrow-right sf-tile-arrow" aria-hidden="true"></i>
        </a>

        <a class="sf-bento-tile is-small" href="{{ route('storefront.search', ['q' => 'sạc']) }}">
            <span class="sf-tile-icon"><i class="fa-solid fa-bolt" aria-hidden="true"></i></span>
            <span class="sf-tile-text">
                <strong>Củ sạc & cáp</strong>
                <small>Sạc nhanh, bền bỉ</small>
            </span>
            <i class="fa-solid fa-arrow-right sf-tile-arrow" aria-hidden="true"></i>
        </a>

        <a class="sf-bento-tile is-small" href="{{ route('storefront.search', ['q' => 'tai nghe']) }}">
            <span class="sf-tile-icon"><i class="fa-solid fa-headphones" aria-hidden="true"></i></span>
            <span class="sf-tile-text">
                <strong>Tai nghe</strong>
                <small>Âm thanh rõ, giá tốt</small>
            </span>
            <i class="fa-solid fa-arrow-right sf-tile-arrow" aria-hidden="true"></i>
        </a>

        <a class="sf-bento-tile is-small is-return" href="{{ route('storefront.search') }}">
            <span class="sf-tile-icon"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></span>
            <span class="sf-tile-text">
                <strong>Đổi trả 30 ngày</strong>
                <small>Nếu lỗi nhà sản xuất</small>
            </span>
            <i class="fa-solid fa-arrow-right sf-tile-arrow" aria-hidden="true"></i>
        </a>
    </div>
</section>