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

<div class="mt-3 d-flex gap-2">
    <a class="btn btn-outline-secondary" href="{{ route('donhangonline.danhsach') }}">← Danh sách</a>
    @if ($don->trang_thai === 'da_thanh_toan')
        <a class="btn btn-outline-primary" href="{{ route('donhang.inhoadon', $don) }}">Xem/in hóa đơn</a>
    @endif
</div>
@endsection
