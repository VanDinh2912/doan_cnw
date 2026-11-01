@extends('layouts.master')

@section('title', 'Đổi trả hàng')

@section('content')
<h1 class="h3 mb-3">Đổi trả hóa đơn {{ $donHang->ma_hoadon }}</h1>
<form method="POST" action="{{ route('trahang.luu') }}">@csrf
<input type="hidden" name="invoice_id" value="{{ $donHang->id }}">
<div class="card mb-3"><div class="table-responsive"><table class="table mb-0">
<thead><tr><th>Sản phẩm</th><th>Đã bán</th><th>Số lượng trả</th><th>Tình trạng</th></tr></thead>
<tbody>@foreach ($donHang->chiTietDonHangs as $index => $chiTiet)
<tr><td>{{ $chiTiet->sanPham->ten_sanpham }}</td><td>{{ $chiTiet->soluong }}</td>
<td><input class="form-control" type="number" min="0" max="{{ $chiTiet->soluong }}" name="items[{{ $index }}][soluong]" value="0">
<input type="hidden" name="items[{{ $index }}][invoice_detail_id]" value="{{ $chiTiet->id }}"></td>
<td><select class="form-select" name="items[{{ $index }}][tinh_trang]"><option value="con_tot">Còn tốt</option><option value="loi_hong">Lỗi/hỏng</option></select></td></tr>
@endforeach</tbody></table></div></div>
<div class="row g-3"><div class="col-md-4"><label class="form-label">Hình thức hoàn</label><select class="form-select" name="hinh_thuc_hoan"><option value="tienmat">Tiền mặt</option><option value="chuyenkhoan">Chuyển khoản</option><option value="doi_hang">Đổi hàng</option></select></div><div class="col-md-8"><label class="form-label">Lý do</label><input class="form-control" name="ly_do"></div></div>
<a class="btn btn-secondary mt-3" href="{{ route('donhang.danhsach') }}">Hủy</a> <button class="btn btn-primary mt-3">Tạo phiếu trả</button>
</form>
@endsection
