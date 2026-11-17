<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Đã nhận thanh toán {{ $donHang->ma_hoadon }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">
    <h2 style="color: #16a34a;">Đã nhận thanh toán</h2>

    <p>Cửa hàng đã xác nhận thanh toán cho đơn hàng của bạn:</p>

    <table style="border-collapse: collapse; width: 100%; margin: 16px 0;">
        <tr>
            <td style="padding: 6px 12px; border: 1px solid #ddd; background: #f8fafc; width: 40%;"><strong>Mã đơn hàng</strong></td>
            <td style="padding: 6px 12px; border: 1px solid #ddd;">{{ $donHang->ma_hoadon }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 12px; border: 1px solid #ddd; background: #f8fafc;"><strong>Mã tra cứu</strong></td>
            <td style="padding: 6px 12px; border: 1px solid #ddd;">{{ $donHang->ma_tra_cuu }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 12px; border: 1px solid #ddd; background: #f8fafc;"><strong>Hình thức nhận</strong></td>
            <td style="padding: 6px 12px; border: 1px solid #ddd;">{{ $donHang->hinh_thuc_nhan === 'giao_tan_noi' ? 'Giao tận nơi' : 'Nhận tại cửa hàng' }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 12px; border: 1px solid #ddd; background: #f8fafc;"><strong>Tổng thanh toán</strong></td>
            <td style="padding: 6px 12px; border: 1px solid #ddd;">{{ number_format((float) $donHang->tong_thanh_toan, 0, ',', '.') }} đ</td>
        </tr>
    </table>

    <h3>Sản phẩm</h3>
    <ul style="margin: 8px 0 16px; padding-left: 20px;">
        @foreach ($donHang->chiTietDonHangs as $ct)
        <li>{{ $ct->sanPham?->ten_sanpham ?? 'Sản phẩm #'.$ct->product_id }} — SL {{ $ct->soluong }}</li>
        @endforeach
    </ul>

    <p><strong>Bước tiếp theo:</strong>
        @if ($donHang->hinh_thuc_nhan === 'giao_tan_noi')
            đơn hàng sẽ được đóng gói và giao đến địa chỉ bạn đã cung cấp.
        @else
            mang mã tra cứu <strong>{{ $donHang->ma_tra_cuu }}</strong> đến cửa hàng để nhận hàng.
        @endif
    </p>

    <p><strong>Tra cứu đơn hàng:</strong> Truy cập
        <a href="{{ route('don-hang.tracuu') }}">{{ route('don-hang.tracuu') }}</a>
        và nhập mã tra cứu <strong>{{ $donHang->ma_tra_cuu }}</strong> cùng số điện thoại
        <strong>{{ $donHang->so_dienthoai_dat }}</strong>.</p>

    <p style="color: #888; font-size: 12px; margin-top: 24px;">
        Trân trọng,<br>
        {{ config('app.name', 'PhoneShop') }}
    </p>
</body>
</html>
