@extends('storefront.layouts.app')

@section('title', ($don->trang_thai === 'da_huy' ? 'Đơn hàng đã hủy' : 'Đặt hàng thành công') . ' — ' . config('storefront.ten_cua_hang'))

@section('content')
    <div class="sf-container" style="padding-top:20px;">
        <div class="sf-section-head">
            @if ($don->trang_thai === 'da_huy')
                <h1 class="sf-section-title">Đơn hàng đã bị hủy</h1>
            @else
                <h1 class="sf-section-title">Đặt hàng thành công</h1>
            @endif
        </div>

        <div style="background:var(--sf-surface);border:1px solid var(--sf-border);border-radius:12px;padding:20px;">
            @if ($don->trang_thai === 'da_huy')
                <p style="margin-top:0;color:var(--sf-stock-out);">
                    Đơn hàng đã hết hạn thanh toán và đã bị hủy, tồn kho đã được hoàn trả.
                    Vui lòng đặt lại đơn mới nếu bạn vẫn muốn mua.
                </p>
            @else
                <p style="margin-top:0;">Cảm ơn bạn đã đặt hàng. Vui lòng giữ <b>mã tra cứu</b> để theo dõi đơn và nhận hàng tại quầy.</p>
            @endif

            <table style="width:100%;border-collapse:collapse;">
                <tr><td style="padding:6px 0;color:var(--sf-fg-muted);">Mã tra cứu</td><td style="padding:6px 0;font-weight:700;">{{ $don->ma_tra_cuu }}</td></tr>
                <tr><td style="padding:6px 0;color:var(--sf-fg-muted);">Mã đơn</td><td style="padding:6px 0;">{{ $don->ma_hoadon }}</td></tr>
                <tr><td style="padding:6px 0;color:var(--sf-fg-muted);">Trạng thái</td><td style="padding:6px 0;">{{ $don->trang_thai_tieng_viet }}</td></tr>
                <tr><td style="padding:6px 0;color:var(--sf-fg-muted);">Hạn giữ hàng</td><td style="padding:6px 0;">{{ $don->han_giu?->format('d/m/Y H:i') ?? '—' }}</td></tr>
                <tr><td style="padding:6px 0;color:var(--sf-fg-muted);">Tổng thanh toán</td><td style="padding:6px 0;font-weight:700;color:var(--sf-accent);">{{ number_format((float) $don->tong_thanh_toan, 0, ',', '.') }} ₫</td></tr>
            </table>

            @if ($don->ghi_chu_khach)
                <div style="margin-top:12px;padding:12px;background:var(--sf-bg);border-radius:8px;">
                    <div style="color:var(--sf-fg-muted);font-size:var(--sf-text-sm);">Ghi chú của bạn</div>
                    <div>{{ $don->ghi_chu_khach }}</div>
                </div>
            @endif

            <div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap;">
                <a href="{{ route('don-hang.trangthai', $don->ma_tra_cuu) }}" class="sf-icon-btn is-primary" style="height:44px;padding:0 20px;">Theo dõi đơn</a>
                <a href="{{ route('storefront.home') }}" class="sf-icon-btn" style="height:44px;padding:0 20px;">Tiếp tục mua sắm</a>
            </div>
        </div>
    </div>
@endsection