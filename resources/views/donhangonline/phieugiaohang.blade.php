<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Phiếu giao hàng {{ $don->ma_hoadon }}</title>
<style>
    body { font-family: 'Segoe UI', Arial, sans-serif; max-width: 720px; margin: 24px auto; color: #111; }
    h1 { font-size: 20px; margin-bottom: 4px; }
    .meta { color: #555; font-size: 13px; margin-bottom: 16px; }
    table { width: 100%; border-collapse: collapse; margin: 12px 0; }
    th, td { border: 1px solid #ccc; padding: 6px 8px; font-size: 14px; text-align: left; }
    th { background: #f3f3f3; }
    .box { border: 1px solid #999; border-radius: 6px; padding: 10px 14px; margin: 10px 0; }
    .paid { display: inline-block; border: 2px solid #0a7; color: #0a7; font-weight: 700;
            padding: 4px 14px; border-radius: 6px; letter-spacing: 1px; }
    .totals td { border: none; text-align: right; }
    .totals .grand { font-weight: 700; font-size: 16px; }
    @media print { .noprint { display: none; } body { margin: 0; } }
</style>
</head>
<body>
<button class="noprint" onclick="window.print()" style="margin-bottom:12px;">In phiếu</button>
<a class="noprint" href="{{ route('donhangonline.chitiet', $don) }}" style="margin-left:8px;">← Quay lại đơn</a>

<h1>PHIẾU GIAO HÀNG</h1>
<div class="meta">
    Mã đơn: <b>{{ $don->ma_hoadon }}</b> &nbsp;|&nbsp;
    Ngày: {{ now()->format('H:i d/m/Y') }}
</div>

<div class="box">
    <b>Người nhận:</b> {{ $don->ten_nguoi_nhan }}<br>
    <b>Điện thoại:</b> {{ $don->sdt_nguoi_nhan }}<br>
    <b>Địa chỉ:</b> {{ $don->dia_chi_giao }}
</div>

@if ($don->ghi_chu_khach)
<div class="box"><b>Ghi chú của khách:</b> {{ $don->ghi_chu_khach }}</div>
@endif

<table>
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
</table>

<table class="totals">
    <tr><td>Tiền hàng</td><td>{{ number_format((float) $don->tong_tien, 0, ',', '.') }} đ</td></tr>
    <tr><td>Phí giao hàng</td><td>{{ number_format((float) $don->phi_giao_hang, 0, ',', '.') }} đ</td></tr>
    <tr class="grand"><td>TỔNG CỘNG</td><td>{{ number_format((float) $don->tong_thanh_toan, 0, ',', '.') }} đ</td></tr>
</table>

<p><span class="paid">ĐÃ THANH TOÁN — KHÔNG THU TIỀN KHI GIAO</span></p>
<p class="meta">Người giao: ____________________ &nbsp;&nbsp; Chữ ký người nhận: ____________________</p>
</body>
</html>
