@php
    $items = [
        ['icon' => 'fa-truck-fast', 'title' => 'Miễn phí vận chuyển', 'sub' => 'Đơn hàng từ 500.000 ₫'],
        ['icon' => 'fa-rotate-left', 'title' => 'Đổi trả 30 ngày', 'sub' => 'Lỗi do nhà sản xuất'],
        ['icon' => 'fa-shield-halved', 'title' => 'Bảo hành 12 tháng', 'sub' => 'Chính hãng, toàn quốc'],
        ['icon' => 'fa-headset', 'title' => 'Hỗ trợ 24/7', 'sub' => 'Hotline 1900 0000'],
    ];
@endphp

<section aria-label="Cam kết dịch vụ">
    <div class="sf-container">
        <div class="sf-trust-bar">
            @foreach ($items as $item)
                <div class="sf-trust-item">
                    <i class="fa-solid {{ $item['icon'] }}"></i>
                    <div>
                        <div class="sf-trust-title">{{ $item['title'] }}</div>
                        <div class="sf-trust-sub">{{ $item['sub'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>