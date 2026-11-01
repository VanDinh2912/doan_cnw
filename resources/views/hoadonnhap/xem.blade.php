@extends('layouts.master')
@section('title', 'Chi tiết hóa đơn nhập')
@section('content')
<div class="d-flex justify-content-between mb-3"><h1 class="h3">{{ $hoaDon->ma_phieu }}</h1><span>{{ str_replace('_', ' ', $hoaDon->trang_thai) }}</span></div>
<p>Số hóa đơn: <strong>{{ $hoaDon->so_hoadon }}</strong> · Ký hiệu: {{ $hoaDon->ky_hieu }} · Nhà cung cấp: {{ $hoaDon->nhaCungCap->ten_nhacungcap }}</p>
<div class="card"><div class="table-responsive"><table class="table"><thead><tr><th>STT</th><th>Tên hàng</th><th>SL</th><th>Đơn giá</th><th>Thành tiền</th></tr></thead><tbody>@foreach($hoaDon->chiTiets as $item)<tr><td>{{ $item->stt }}</td><td>{{ $item->ten_hang }}</td><td>{{ $item->soluong }}</td><td>{{ number_format($item->don_gia,0,',','.') }} đ</td><td>{{ number_format($item->thanh_tien,0,',','.') }} đ</td></tr>@endforeach</tbody></table></div></div>
<p class="text-end">Tiền hàng: {{ number_format($hoaDon->tong_tien_hang,0,',','.') }} đ · Thuế: {{ number_format($hoaDon->tien_thue,0,',','.') }} đ · <strong>Tổng: {{ number_format($hoaDon->tong_thanh_toan,0,',','.') }} đ</strong></p>
@if($hoaDon->file_goc)<a class="btn btn-outline-secondary" href="{{ asset('storage/'.$hoaDon->file_goc) }}" target="_blank">Mở file gốc PDF</a>@endif
@if($hoaDon->trang_thai === 'nhap_tam')<a class="btn btn-outline-primary" href="{{ route('hoadonnhap.sua', $hoaDon) }}">Sửa</a><form class="d-inline" method="POST" action="{{ route('hoadonnhap.xacnhan', $hoaDon) }}">@csrf<button class="btn btn-success">Xác nhận nhập kho</button></form><form class="d-inline" method="POST" action="{{ route('hoadonnhap.huy', $hoaDon) }}">@csrf<button class="btn btn-outline-danger">Hủy</button></form>@endif
@endsection
