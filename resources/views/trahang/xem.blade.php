@extends('layouts.master')
@section('title', 'Chi tiết phiếu trả')
@section('content')
<h1 class="h3 mb-3">{{ $phieuTra->ma_phieu }}</h1>
<p>Hóa đơn: {{ $phieuTra->donHang->ma_hoadon }} · Hoàn: <strong>{{ number_format($phieuTra->tong_hoan, 0, ',', '.') }} đ</strong> · Trạng thái: {{ str_replace('_', ' ', $phieuTra->trang_thai) }}</p>
<div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Sản phẩm</th><th>Số lượng</th><th>Tình trạng</th><th>Tiền hoàn</th></tr></thead><tbody>@foreach ($phieuTra->chiTiets as $item)<tr><td>{{ $item->sanPham->ten_sanpham }}</td><td>{{ $item->soluong }}</td><td>{{ $item->tinh_trang === 'con_tot' ? 'Còn tốt' : 'Lỗi/hỏng' }}</td><td>{{ number_format($item->thanh_tien, 0, ',', '.') }}</td></tr>@endforeach</tbody></table></div></div>
@if ($phieuTra->trang_thai === 'cho_duyet')<form class="mt-3" method="POST" action="{{ route('trahang.duyet', $phieuTra) }}">@csrf<button class="btn btn-primary">Duyệt phiếu</button></form>@endif
<a class="btn btn-link px-0" href="{{ route('trahang.danhsach') }}">Quay lại</a>
@endsection
