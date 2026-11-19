@extends('storefront.layouts.app')

@section('title', 'Thanh toán QR — ' . config('storefront.ten_cua_hang'))

@section('content')
    <div class="sf-container" style="padding-top:20px;">
        <div class="sf-section-head">
            <h1 class="sf-section-title">Thanh toán QR</h1>
        </div>

        <div style="background:var(--sf-surface);border:1px solid var(--sf-border);border-radius:12px;padding:20px;">
            <table style="width:100%;border-collapse:collapse;">
                <tr><td style="padding:6px 0;color:var(--sf-fg-muted);">Mã tra cứu</td><td style="padding:6px 0;font-weight:700;">{{ $don->ma_tra_cuu }}</td></tr>
                <tr><td style="padding:6px 0;color:var(--sf-fg-muted);">Mã đơn</td><td style="padding:6px 0;">{{ $don->ma_hoadon }}</td></tr>
                <tr><td style="padding:6px 0;color:var(--sf-fg-muted);">Hình thức nhận</td><td style="padding:6px 0;">{{ $don->hinh_thuc_nhan === 'giao_tan_noi' ? 'Giao tận nơi' : 'Nhận tại cửa hàng' }}</td></tr>
                <tr><td style="padding:6px 0;color:var(--sf-fg-muted);">Hạn thanh toán</td><td style="padding:6px 0;">{{ $don->han_giu?->format('d/m/Y H:i') ?? '—' }}</td></tr>
                <tr><td style="padding:6px 0;color:var(--sf-fg-muted);">Tổng thanh toán</td><td style="padding:6px 0;font-weight:700;color:var(--sf-accent);">{{ number_format((float) $don->tong_thanh_toan, 0, ',', '.') }} ₫</td></tr>
            </table>

            <div id="qr-countdown" data-han-giu="{{ $don->han_giu?->timestamp }}"
                 style="margin-top:12px;padding:12px;background:var(--sf-bg);border-radius:8px;text-align:center;">
                <span style="color:var(--sf-fg-muted);">Thời gian còn lại để thanh toán:</span>
                <b id="qr-countdown-text" style="color:var(--sf-accent);">--:--</b>
            </div>

            @if ($don->hinh_thuc_nhan === 'giao_tan_noi')
                <div style="margin-top:12px;padding:12px;background:var(--sf-bg);border-radius:8px;">
                    <div style="color:var(--sf-fg-muted);font-size:var(--sf-text-sm);">Phí giao hàng</div>
                    <div>{{ number_format((float) $don->phi_giao_hang, 0, ',', '.') }} ₫</div>
                </div>
            @endif

            @if ($qrUrl)
                <div style="margin-top:20px;text-align:center;">
                    <p style="color:var(--sf-fg-muted);">Quét mã QR bên dưới để thanh toán:</p>
                    <img src="{{ $qrUrl }}" alt="QR Code" style="max-width:300px;height:auto;border:1px solid var(--sf-border);border-radius:8px;">
                    <div style="margin-top:12px;padding:12px;background:var(--sf-bg);border:1px solid var(--sf-accent);border-radius:8px;max-width:420px;margin-left:auto;margin-right:auto;">
                        <div style="color:var(--sf-fg-muted);font-size:var(--sf-text-sm);">Vui lòng chuyển <b>ĐÚNG số tiền
                            {{ number_format((float) $don->tong_thanh_toan, 0, ',', '.') }} ₫</b> và giữ nguyên
                            <b>nội dung chuyển khoản: {{ $don->ma_hoadon }}</b>.</div>
                    </div>
                    <p style="margin-top:12px;color:var(--sf-fg-muted);font-size:var(--sf-text-sm);">
                        Hệ thống sẽ tự động cập nhật trạng thái sau khi thanh toán thành công.
                    </p>
                </div>
            @else
                <div style="margin-top:20px;padding:12px;background:var(--sf-bg);border-radius:8px;color:var(--sf-stock-out);">
                    Chưa cấu hình thông tin ngân hàng để tạo QR. Vui lòng liên hệ cửa hàng để thanh toán bằng phương thức khác.
                </div>
            @endif

            <div style="margin-top:16px;">
                <a href="{{ route('don-hang.trangthai', $don->ma_tra_cuu) }}" class="sf-icon-btn" style="height:44px;padding:0 20px;">Theo dõi trạng thái</a>
            </div>
        </div>
    </div>

    @if ($don->trang_thai === 'cho_thanh_toan')
        <script>
            (function () {
                var url = @json(route('don-hang.poll', $don->ma_tra_cuu));
                var token = document.querySelector('meta[name="csrf-token"]').content;
                var checkInterval = setInterval(function () {
                    fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' } })
                        .then(function (r) { return r.json(); })
                        .then(function (d) {
                            if (d.trang_thai && d.trang_thai !== 'cho_thanh_toan') {
                                clearInterval(checkInterval);
                                window.location.href = @json(route('dat-hang.thanhcong', $don->ma_tra_cuu));
                            }
                        })
                        .catch(function () {});
                }, 5000);
            })();

            (function () {
                var el = document.getElementById('qr-countdown');
                if (!el) { return; }
                var han = parseInt(el.getAttribute('data-han-giu'), 10);
                if (!han) { return; }
                var text = document.getElementById('qr-countdown-text');
                var thanhcong = @json(route('dat-hang.thanhcong', $don->ma_tra_cuu));
                function tick() {
                    var con = han - Math.floor(Date.now() / 1000);
                    if (con <= 0) {
                        if (text) { text.textContent = '00:00'; }
                        window.location.href = thanhcong;
                        return;
                    }
                    var phut = Math.floor(con / 60);
                    var giay = con % 60;
                    if (text) {
                        text.textContent = (phut < 10 ? '0' : '') + phut + ':' + (giay < 10 ? '0' : '') + giay;
                    }
                }
                tick();
                setInterval(tick, 1000);
            })();
        </script>
    @endif
@endsection
