@extends('storefront.layouts.app')

@section('title', 'Trạng thái đơn hàng — PhoneShop')

@section('content')
    <div class="sf-container" style="padding-top:20px;">
        <div class="sf-section-head">
            <h1 class="sf-section-title">Đơn hàng {{ $don->ma_hoadon }}</h1>
        </div>

        <div style="background:var(--sf-surface);border:1px solid var(--sf-border);border-radius:12px;padding:20px;">
            <p style="margin-top:0;">Trạng thái: <b id="trang-thai-don">{{ $don->trang_thai_tieng_viet }}</b></p>
            <p>Hạn giữ hàng: {{ $don->han_giu?->format('d/m/Y H:i') ?? '—' }}</p>
            <p>Tổng thanh toán: <b style="color:var(--sf-accent);">{{ number_format((float) $don->tong_thanh_toan, 0, ',', '.') }} ₫</b></p>

            <ul style="padding-left:18px;">
                @foreach ($don->chiTietDonHangs as $ct)
                    <li>{{ $ct->sanPham?->ten_sanpham ?? 'Sản phẩm' }} × {{ $ct->soluong }}</li>
                @endforeach
            </ul>

            @if ($don->trang_thai === 'cho_thanh_toan')
                <form method="POST" action="{{ route('don-hang.huy', $don->ma_tra_cuu) }}"
                      onsubmit="return confirm('Hủy đơn này và hoàn kho?');" style="margin-top:12px;">
                    @csrf
                    <button type="submit" class="sf-icon-btn" style="height:44px;padding:0 20px;">
                        <i class="fa-solid fa-xmark"></i> Hủy đơn
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if ($don->trang_thai === 'cho_thanh_toan')
        <script>
            (function () {
                var url = @json(route('don-hang.poll', $don->ma_tra_cuu));
                var token = document.querySelector('meta[name="csrf-token"]').content;
                var trangThaiMap = {
                    'cho_thanh_toan': 'Chờ thanh toán',
                    'da_thanh_toan': 'Đã thanh toán',
                    'da_huy': 'Đã hủy'
                };
                setInterval(function () {
                    fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' } })
                        .then(function (r) { return r.json(); })
                        .then(function (d) {
                            var el = document.getElementById('trang-thai-don');
                            if (el && d.trang_thai) {
                                el.textContent = trangThaiMap[d.trang_thai] || d.trang_thai.replace(/_/g, ' ');
                            }
                            if (d.trang_thai && d.trang_thai !== 'cho_thanh_toan') { location.reload(); }
                        })
                        .catch(function () {});
                }, 10000);
            })();
        </script>
    @endif
@endsection