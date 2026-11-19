@php
    $giaoTanNoi = (bool) config('pos.giao_tan_noi_bat');
    $mienPhiTu = (int) config('pos.mien_phi_giao_tu', 0);
    $shipItem = $giaoTanNoi
        ? ($mienPhiTu > 0
            ? ['icon' => 'fa-truck-fast', 'title' => 'Miễn phí vận chuyển', 'sub' => 'Đơn từ '.number_format($mienPhiTu, 0, ',', '.').' ₫']
            : ['icon' => 'fa-truck-fast', 'title' => 'Giao tận nơi', 'sub' => 'Giao trong 24h nội thành'])
        : ['icon' => 'fa-store', 'title' => 'Nhận tại cửa hàng', 'sub' => 'Đặt online, ghé lấy hàng'];
    $items = [
        $shipItem,
        ['icon' => 'fa-rotate-left', 'title' => 'Đổi trả 30 ngày', 'sub' => 'Lỗi do nhà sản xuất'],
        ['icon' => 'fa-shield-halved', 'title' => 'Bảo hành 12 tháng', 'sub' => 'Chính hãng, toàn quốc'],
        ['icon' => 'fa-headset', 'title' => 'Hỗ trợ '.config('storefront.gio_lam_viec'), 'sub' => 'Hotline '.config('storefront.hotline').' hằng ngày'],
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
