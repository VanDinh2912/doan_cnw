@extends('layouts.master')

@section('title', 'Chi tiết đơn nhập')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">{{ $donNhap->ma_don }}</h1><span class="badge text-bg-secondary">{{ str_replace('_', ' ', $donNhap->trang_thai) }}</span></div>
<p>Nhà cung cấp: <strong>{{ $donNhap->nhaCungCap->ten_nhacungcap }}</strong> · Người tạo: {{ $donNhap->nguoiTao->name }}</p>
<div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Sản phẩm</th><th>Đặt</th><th>Đã nhận</th><th>Giá nhập</th><th>Thành tiền</th></tr></thead><tbody>@foreach ($donNhap->chiTiets as $item)<tr><td>{{ $item->sanPham->ten_sanpham }}</td><td>{{ $item->soluong_dat }}</td><td>{{ $item->soluong_nhan }}</td><td>{{ number_format($item->don_gia_nhap, 0, ',', '.') }}</td><td>{{ number_format($item->thanh_tien, 0, ',', '.') }}</td></tr>@endforeach</tbody></table></div></div>
<div class="mt-3 d-flex gap-2">@foreach (['cho_duyet'=>'Duyệt', 'dang_giao'=>'Đang giao', 'da_nhan'=>'Nhận hàng', 'da_huy'=>'Hủy'] as $status => $label) @php($allowed = ['cho_duyet'=>['cho_giao'], 'cho_giao'=>['dang_giao'], 'dang_giao'=>['da_nhan']][$donNhap->trang_thai] ?? []) @if (in_array($status, $allowed, true) || ($status === 'cho_giao' && $donNhap->trang_thai === 'cho_duyet'))<form method="POST" action="{{ route('nhaphang.donnhap.trangthai', $donNhap) }}">@csrf<input type="hidden" name="trang_thai" value="{{ $status }}"><button class="btn btn-outline-primary">{{ $label }}</button></form>@endif @endforeach</div>
<a class="btn btn-link px-0" href="{{ route('nhaphang.donnhap') }}">Quay lại</a>
@endsection
