@extends('layouts.master')

@section('title', 'Đổi trả hàng')

@section('content')
<h1 class="h3 mb-3">Đổi trả hóa đơn {{ $donHang->ma_hoadon }}</h1>
<form method="POST" action="{{ route('trahang.luu') }}" data-can-pin="thuong">@csrf
<input type="hidden" name="invoice_id" value="{{ $donHang->id }}">
<div class="card mb-3"><div class="table-responsive"><table class="table mb-0">
<thead><tr><th>Sản phẩm</th><th>Đã bán</th><th>Số lượng trả</th><th>Tình trạng</th></tr></thead>
<tbody>@foreach ($donHang->chiTietDonHangs as $index => $chiTiet)
<tr><td>{{ $chiTiet->sanPham->ten_sanpham }}</td><td>{{ $chiTiet->soluong }}</td>
<td><input class="form-control" type="number" min="0" max="{{ $chiTiet->soluong }}" name="items[{{ $index }}][soluong]" value="0">
<input type="hidden" name="items[{{ $index }}][invoice_detail_id]" value="{{ $chiTiet->id }}"></td>
<td><select class="form-select" name="items[{{ $index }}][tinh_trang]"><option value="con_tot">Còn tốt</option><option value="loi_hong">Lỗi/hỏng</option></select></td></tr>
@endforeach</tbody></table></div></div>
@php
    $fmtTien = fn ($v) => number_format((float) $v, 0, ',', '.');
@endphp
<div class="card mb-3"><div class="card-body py-2">
    <div class="row text-center">
        <div class="col"><small class="text-muted d-block">Tiền hàng (sau giảm giá)</small><strong>{{ $fmtTien($tienHangDaThanhToan) }} đ</strong></div>
        <div class="col"><small class="text-muted d-block">Phí giao hàng</small><strong>{{ $fmtTien($phiGiaoHang) }} đ</strong></div>
        <div class="col"><small class="text-muted d-block">Tổng đã thanh toán</small><strong>{{ $fmtTien($donHang->tong_thanh_toan) }} đ</strong></div>
    </div>
    <p class="small text-muted mb-0 mt-2">Tiền hoàn tính theo tỷ lệ trên <strong>tiền hàng</strong>, không gồm phí giao.
        @if ($phiGiaoHang > 0 && ! $daHoanPhiGiao)
            Phí giao chỉ được hoàn khi quản trị viên tick "Hoàn cả phí giao" (một lần/đơn, bắt buộc lý do).
        @elseif ($daHoanPhiGiao)
            Đơn này đã hoàn phí giao hàng.
        @endif
    </p>
    @if ($laAdmin && $phiGiaoHang > 0 && ! $daHoanPhiGiao)
    <div class="form-check mt-1">
        <input class="form-check-input" type="checkbox" name="hoan_phi_giao" value="1" id="hoan_phi_giao">
        <label class="form-check-label" for="hoan_phi_giao">Hoàn cả phí giao hàng (chỉ admin — bắt buộc nhập lý do)</label>
    </div>
    @endif
</div></div>
<div class="row g-3"><div class="col-md-4"><label class="form-label">Hình thức hoàn</label><select class="form-select" name="hinh_thuc_hoan"><option value="tienmat">Tiền mặt</option><option value="chuyenkhoan">Chuyển khoản</option><option value="doi_hang">Đổi hàng</option></select></div><div class="col-md-8"><label class="form-label">Lý do</label><input class="form-control" name="ly_do"></div></div>
<a class="btn btn-secondary mt-3" href="{{ route('donhang.danhsach') }}">Hủy</a> <button class="btn btn-primary mt-3">Tạo phiếu trả</button>
</form>
@endsection
