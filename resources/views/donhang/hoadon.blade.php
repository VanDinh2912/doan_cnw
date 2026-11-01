<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hóa đơn {{ $donHang->ma_hoadon }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0 auto; width: 80mm; }
        .center { text-align: center; }
        table { border-collapse: collapse; width: 100%; }
        th, td { padding: 3px 0; text-align: left; vertical-align: top; }
        .right { text-align: right; }
        .total { border-top: 1px dashed #000; font-weight: bold; }
        .actions { margin: 1rem 0; text-align: center; }
        @media print {
            .actions { display: none; }
            @page { margin: 3mm; size: 80mm auto; }
        }
    </style>
</head>
<body>
<div class="actions">
    <button type="button" onclick="window.print()">In hóa đơn</button>
    <a href="{{ route('pos.index') }}">Quay lại POS</a>
</div>
<div class="center">
    <h3>WEB POS</h3>
    <div>HÓA ĐƠN BÁN HÀNG</div>
    <div>{{ $donHang->ma_hoadon }}</div>
    <div>{{ $donHang->created_at->format('d/m/Y H:i') }}</div>
</div>
<p>Thu ngân: {{ $donHang->user->name }}</p>
<table>
    <thead><tr><th>Sản phẩm</th><th>SL</th><th class="right">T.Tiền</th></tr></thead>
    <tbody>
    @foreach ($donHang->chiTietDonHangs as $chiTiet)
        <tr>
            <td>{{ $chiTiet->sanPham->ten_sanpham }}<br>{{ number_format($chiTiet->don_gia, 0, ',', '.') }} đ</td>
            <td>{{ $chiTiet->soluong }}</td>
            <td class="right">{{ number_format($chiTiet->thanh_tien, 0, ',', '.') }}</td>
        </tr>
    @endforeach
    </tbody>
    <tfoot>
    <tr><td colspan="2">Tạm tính</td><td class="right">{{ number_format($donHang->tong_tien, 0, ',', '.') }}</td></tr>
    <tr><td colspan="2">Giảm giá</td><td class="right">-{{ number_format($donHang->tien_giam, 0, ',', '.') }}</td></tr>
    <tr class="total"><td colspan="2">Thanh toán</td><td class="right">{{ number_format($donHang->tong_thanh_toan, 0, ',', '.') }}</td></tr>
    <tr><td colspan="2">Khách đưa</td><td class="right">{{ number_format($donHang->tien_khach_dua, 0, ',', '.') }}</td></tr>
    <tr><td colspan="2">Tiền thừa</td><td class="right">{{ number_format($donHang->tien_thua, 0, ',', '.') }}</td></tr>
    </tfoot>
</table>
<p class="center">Cảm ơn quý khách!</p>
</body>
</html>
