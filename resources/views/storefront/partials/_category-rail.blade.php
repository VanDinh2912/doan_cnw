@if ($categories->isNotEmpty())
@php
    // Keyword -> Font Awesome icon, matched against the lower-cased category name (first match wins).
    $iconMap = [
        'tai nghe' => 'fa-headphones',
        'loa' => 'fa-volume-high',
        'pin' => 'fa-battery-full',
        'cáp' => 'fa-link',
        'sạc' => 'fa-plug',
        'kính' => 'fa-shield-halved',
        'ốp' => 'fa-mobile-screen-button',
        'giá đỡ' => 'fa-hand-holding',
        'thẻ nhớ' => 'fa-sd-card',
        'chuột' => 'fa-computer-mouse',
        'bàn phím' => 'fa-keyboard',
        'camera' => 'fa-camera',
        'đồng hồ' => 'fa-clock',
    ];
    $iconFor = function (string $name) use ($iconMap): string {
        $name = mb_strtolower($name);
        foreach ($iconMap as $keyword => $icon) {
            if (str_contains($name, $keyword)) {
                return $icon;
            }
        }
        return 'fa-box-open';
    };
@endphp
<section class="sf-container sf-section" id="danh-muc" aria-labelledby="sf-cat-title">
    <div class="sf-section-head">
        <div>
            <h2 id="sf-cat-title" class="sf-section-title">Danh mục nổi bật</h2>
            <p class="sf-section-sub">Chọn nhanh nhóm phụ kiện bạn cần</p>
        </div>
    </div>

    <ul class="sf-cat-grid">
        @foreach ($categories as $category)
            <li>
                <a class="sf-cat-card" href="{{ route('storefront.category', $category) }}"
                   title="{{ $category->ten_loai }}">
                    <span class="sf-cat-icon">
                        <i class="fa-solid {{ $iconFor($category->ten_loai) }}" aria-hidden="true"></i>
                    </span>
                    <span class="sf-cat-body">
                        <span class="sf-cat-name">{{ $category->ten_loai }}</span>
                        <span class="sf-cat-count">{{ $category->san_phams_count }} sản phẩm</span>
                    </span>
                </a>
            </li>
        @endforeach
    </ul>
</section>
@endif