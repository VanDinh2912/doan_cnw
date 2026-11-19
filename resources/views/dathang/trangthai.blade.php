@extends('storefront.layouts.app')

@section('title', 'Trạng thái đơn hàng — ' . config('storefront.ten_cua_hang'))

@section('content')
    <div class="sf-container" style="padding-top:20px;">
        <div class="sf-section-head">
            <h1 class="sf-section-title">Đơn hàng {{ $don->ma_hoadon }}</h1>
        </div>

        <div style="background:var(--sf-surface);border:1px solid var(--sf-border);border-radius:12px;padding:20px;">
            <p style="margin-top:0;">Trạng thái: <b id="trang-thai-don">{{ $don->trang_thai_tieng_viet }}</b></p>
            <p>Hạn giữ hàng: {{ $don->han_giu?->format('d/m/Y H:i') ?? '—' }}</p>
            <p>Tổng thanh toán: <b style="color:var(--sf-accent);">{{ number_format((float) $don->tong_thanh_toan, 0, ',', '.') }} ₫</b></p>

            @if ($don->hinh_thuc_nhan)
                <p>Hình thức nhận: {{ $don->hinh_thuc_nhan_tieng_viet }}</p>
            @endif
            @if ($don->hinh_thuc_nhan === 'giao_tan_noi')
                <p>Trạng thái giao: <b id="trang-thai-giao">{{ $don->trang_thai_giao_tieng_viet }}</b></p>
                <div style="margin-top:8px;padding:12px;background:var(--sf-bg);border-radius:8px;">
                    <div style="color:var(--sf-fg-muted);font-size:var(--sf-text-sm);">Thông tin giao hàng</div>
                    <div>Người nhận: {{ $don->ten_nguoi_nhan }}</div>
                    <div>SĐT người nhận: {{ \App\Support\CheThongTin::soDienThoai($don->sdt_nguoi_nhan) }}</div>
                    <div>Địa chỉ: {{ $don->dia_chi_giao }}</div>
                </div>
            @endif

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
                var trangThaiGiaoMap = {
                    'cho_xac_nhan': 'Chờ xác nhận',
                    'da_xac_nhan': 'Đã xác nhận',
                    'dang_giao': 'Đang giao',
                    'da_giao': 'Đã giao',
                    'da_huy_giao': 'Đã hủy giao'
                };
                setInterval(function () {
                    fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' } })
                        .then(function (r) { return r.json(); })
                        .then(function (d) {
                            var el = document.getElementById('trang-thai-don');
                            if (el && d.trang_thai) {
                                el.textContent = trangThaiMap[d.trang_thai] || d.trang_thai.replace(/_/g, ' ');
                            }
                            var elGiao = document.getElementById('trang-thai-giao');
                            if (elGiao && d.trang_thai_giao) {
                                elGiao.textContent = trangThaiGiaoMap[d.trang_thai_giao] || d.trang_thai_giao.replace(/_/g, ' ');
                            }
                            if (d.trang_thai && d.trang_thai !== 'cho_thanh_toan') { location.reload(); }
                        })
                        .catch(function () {});
                }, 10000);
            })();
        </script>
    @endif
@endsection