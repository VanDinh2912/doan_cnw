@extends('layouts.master')

@section('title', 'Đơn online '.$don->ma_hoadon)

@section('content')
<h1 class="h3 mb-3">Đơn online {{ $don->ma_hoadon }}</h1>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header fw-semibold">Thông tin đơn</div>
            <table class="table mb-0">
                <tr><td class="text-muted">Mã tra cứu</td><td><code>{{ $don->ma_tra_cuu }}</code></td></tr>
                <tr><td class="text-muted">Kênh / hình thức</td><td>{{ $don->hinh_thuc_nhan_tieng_viet }}</td></tr>
                <tr><td class="text-muted">Phương thức</td><td>{{ $don->phuong_thuc === 'chuyenkhoan' ? 'Chuyển khoản QR' : 'Tiền mặt tại quầy' }}</td></tr>
                <tr><td class="text-muted">Trạng thái thanh toán</td><td>{{ $don->trang_thai_tieng_viet }}</td></tr>
                @if ($don->hinh_thuc_nhan === 'giao_tan_noi')
                    <tr><td class="text-muted">Trạng thái giao</td><td><b>{{ $don->trang_thai_giao_tieng_viet }}</b></td></tr>
                    <tr><td class="text-muted">Người nhận</td><td>{{ $don->ten_nguoi_nhan }} — {{ $don->sdt_nguoi_nhan }}</td></tr>
                    <tr><td class="text-muted">Địa chỉ giao</td><td>{{ $don->dia_chi_giao }}</td></tr>
                    <tr><td class="text-muted">Phí giao hàng</td><td>{{ number_format((float) $don->phi_giao_hang, 0, ',', '.') }} đ</td></tr>
                @endif
                <tr><td class="text-muted">Hạn giữ</td><td>{{ $don->han_giu?->format('d/m/Y H:i') ?? '—' }}</td></tr>
                <tr><td class="text-muted">Ngày tạo</td><td>{{ $don->created_at->format('d/m/Y H:i') }}</td></tr>
                @if ($don->thoi_gian_thanh_toan)<tr><td class="text-muted">Thanh toán lúc</td><td>{{ $don->thoi_gian_thanh_toan->format('d/m/Y H:i') }}</td></tr>@endif
                @if ($don->ghi_chu_khach)<tr><td class="text-muted">Ghi chú khách</td><td>{{ $don->ghi_chu_khach }}</td></tr>@endif
            </table>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header fw-semibold">Khách hàng</div>
            <table class="table mb-0">
                <tr><td class="text-muted">Họ tên</td><td>{{ $don->ten_khach_dat }}</td></tr>
                <tr><td class="text-muted">SĐT đặt</td><td>{{ $don->so_dienthoai_dat }}</td></tr>
                <tr><td class="text-muted">Hồ sơ khách</td><td>{{ $don->khachHang?->ho_ten ?? '—' }}</td></tr>
            </table>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header fw-semibold">Sản phẩm</div>
    <table class="table mb-0">
        <thead><tr><th>Sản phẩm</th><th>SL</th><th>Đơn giá</th><th>Thành tiền</th></tr></thead>
        <tbody>
        @foreach ($don->chiTietDonHangs as $ct)
            <tr>
                <td>{{ $ct->sanPham?->ten_sanpham ?? 'Sản phẩm #'.$ct->product_id }}</td>
                <td>{{ $ct->soluong }}</td>
                <td>{{ number_format((float) $ct->don_gia, 0, ',', '.') }} đ</td>
                <td>{{ number_format((float) $ct->thanh_tien, 0, ',', '.') }} đ</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="3" class="text-end">Tiền hàng</td><td>{{ number_format((float) $don->tong_tien, 0, ',', '.') }} đ</td></tr>
            @if ((float) $don->phi_giao_hang > 0)
                <tr><td colspan="3" class="text-end">Phí giao hàng</td><td>{{ number_format((float) $don->phi_giao_hang, 0, ',', '.') }} đ</td></tr>
            @endif
            <tr class="fw-bold"><td colspan="3" class="text-end">Tổng thanh toán</td><td>{{ number_format((float) $don->tong_thanh_toan, 0, ',', '.') }} đ</td></tr>
        </tfoot>
    </table>
</div>

@if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="mt-3 d-flex gap-2">
    <a class="btn btn-outline-secondary" href="{{ route('donhangonline.danhsach') }}">← Danh sách</a>
    @if ($don->trang_thai === 'da_thanh_toan')
        <a class="btn btn-outline-primary" href="{{ route('donhang.inhoadon', $don) }}">Xem/in hóa đơn</a>
    @endif
    @if ($don->hinh_thuc_nhan === 'giao_tan_noi')
        <a class="btn btn-outline-dark" target="_blank" href="{{ route('donhangonline.phieugiaohang', $don) }}">In phiếu giao hàng</a>
    @endif
</div>

@if ($don->hinh_thuc_nhan === 'giao_tan_noi' && $don->trang_thai === 'da_thanh_toan' && $trangThaiKe)
<div class="card mt-3">
    <div class="card-header fw-semibold">Chuyển trạng thái giao</div>
    <div class="card-body d-flex align-items-center gap-3">
        <span>Hiện tại: <b>{{ $don->trang_thai_giao_tieng_viet }}</b></span>
        <form method="POST" action="{{ route('donhangonline.trangthai', $don) }}" class="d-flex gap-2">
            @csrf
            <input type="hidden" name="trang_thai_giao_moi" value="{{ $trangThaiKe }}">
            <button class="btn btn-primary" type="submit"
                onclick="return confirm('Chuyển sang trạng thái: {{ $don->trang_thai_giao_tieng_viet }}? Kiểm tra lại đơn trước khi bấm.');">
                → {{ ['da_xac_nhan' => 'Đã xác nhận', 'dang_giao' => 'Đang giao', 'da_giao' => 'Đã giao'][$trangThaiKe] ?? $trangThaiKe }}
            </button>
        </form>
    </div>
</div>
@endif

@if ($don->trang_thai === 'cho_thanh_toan'
    && in_array(auth()->user()->role, ['admin', 'thungan'], true))
<div class="card mt-3" id="the-pickup">
    <div class="card-header fw-semibold">Xử lý đơn nhận tại cửa hàng</div>
    <div class="card-body">
        <p class="text-muted mb-2">Hạn giữ: <b>{{ $don->han_giu?->format('d/m/Y H:i') ?? '—' }}</b></p>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-success" id="btn-xacnhan">Xác nhận đã nhận tiền/giao hàng</button>
            <button class="btn btn-warning" id="btn-giahan">Gia hạn</button>
            <button class="btn btn-danger" id="btn-huycho">Hủy đơn chờ</button>
        </div>
        <div id="pickup-msg" class="mt-2"></div>
    </div>
</div>
@endif

@if ($don->trang_thai === 'da_thanh_toan' && auth()->user()->role === 'admin')
<div class="card mt-3 border-danger">
    <div class="card-header fw-semibold text-danger">Hủy đơn đã thanh toán (chỉ admin)</div>
    <div class="card-body">
        <p class="text-muted">Hủy sẽ hoàn kho, thu hồi điểm đã cộng và đánh dấu <b>CẦN HOÀN TIỀN THỦ CÔNG</b>. Tiền KHÔNG tự hoàn — xử lý thủ công sau.</p>
        <form method="POST" action="{{ route('donhangonline.huydathanhtoan', $don) }}" class="d-flex gap-2">
            @csrf
            <input type="text" name="ly_do" class="form-control" placeholder="Lý do hủy (bắt buộc)" required minlength="5" maxlength="255">
            <button class="btn btn-danger text-nowrap" type="submit"
                onclick="return confirm('Hủy đơn đã thanh toán {{ $don->ma_hoadon }}? Hành động này hoàn kho và thu hồi điểm.');">
                Hủy đơn &amp; hoàn tiền thủ công
            </button>
        </form>
    </div>
</div>
@endif

@if ($lichSu->isNotEmpty())
<div class="card mt-3">
    <div class="card-header fw-semibold">Lịch sử trạng thái giao</div>
    <ul class="list-group list-group-flush">
        @foreach ($lichSu as $log)
            <li class="list-group-item d-flex justify-content-between">
                <span>{{ $log->mo_ta }}</span>
                <small class="text-muted">{{ $log->created_at?->format('d/m/Y H:i') }} — {{ $log->user?->ho_ten ?? 'hệ thống' }}</small>
            </li>
        @endforeach
    </ul>
</div>
@endif
@endsection

@if ($don->trang_thai === 'cho_thanh_toan'
    && in_array(auth()->user()->role, ['admin', 'thungan'], true))
@push('scripts')
<script>
(function () {
    var token = document.querySelector('meta[name="csrf-token"]')?.content
        || '{{ csrf_token() }}';
    function goi(url) {
        return fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); });
    }
    function bao(loi, msg) {
        var el = document.getElementById('pickup-msg');
        el.innerHTML = '<div class="alert alert-' + (loi ? 'danger' : 'success') + ' mb-0">' + msg + '</div>';
        if (!loi) { setTimeout(function () { location.reload(); }, 1200); }
    }
    document.getElementById('btn-xacnhan').addEventListener('click', function () {
        if (!confirm('Xác nhận đã nhận tiền và giao hàng cho đơn này?')) { return; }
        goi(@json(route('donhang.xacnhan', $don))).then(function (r) {
            bao(!r.ok, r.ok ? 'Đã xác nhận thanh toán.' : (r.d.error || 'Có lỗi xảy ra.'));
        });
    });
    document.getElementById('btn-giahan').addEventListener('click', function () {
        goi(@json(route('donhang.giahan', $don))).then(function (r) {
            bao(!r.ok, r.ok ? 'Đã gia hạn hạn giữ hàng.' : (r.d.error || 'Có lỗi xảy ra.'));
        });
    });
    document.getElementById('btn-huycho').addEventListener('click', function () {
        if (!confirm('Hủy đơn chờ thanh toán? Hàng sẽ được hoàn kho.')) { return; }
        goi(@json(route('donhang.huycho', $don))).then(function (r) {
            bao(!r.ok, r.ok ? 'Đã hủy đơn và hoàn kho.' : (r.d.error || 'Có lỗi xảy ra.'));
        });
    });
})();
</script>
@endpush
@endif
