<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Xác nhận đơn hàng {{ $donHang->ma_hoadon }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">
    <h2 style="color: #1a56db;">Xác nhận đặt hàng thành công</h2>

    <p>Xin chào <strong>{{ $donHang->ten_khach_dat }}</strong>,</p>
    <p>Cửa hàng đã ghi nhận đơn hàng của bạn. Dưới đây là thông tin chi tiết:</p>

    <table style="border-collapse: collapse; width: 100%; margin: 16px 0;">
        <tr>
            <td style="padding: 6px 12px; border: 1px solid #ddd; background: #f8fafc; width: 40%;"><strong>Mã đơn hàng</strong></td>
            <td style="padding: 6px 12px; border: 1px solid #ddd;">{{ $donHang->ma_hoadon }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 12px; border: 1px solid #ddd; background: #f8fafc;"><strong>Mã tra cứu</strong></td>
            <td style="padding: 6px 12px; border: 1px solid #ddd;"><strong>{{ $donHang->ma_tra_cuu }}</strong></td>
        </tr>
        <tr>
            <td style="padding: 6px 12px; border: 1px solid #ddd; background: #f8fafc;"><strong>Hình thức nhận</strong></td>
            <td style="padding: 6px 12px; border: 1px solid #ddd;">{{ $donHang->hinh_thuc_nhan === 'giao_tan_noi' ? 'Giao tận nơi' : 'Nhận tại cửa hàng' }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 12px; border: 1px solid #ddd; background: #f8fafc;"><strong>Phương thức thanh toán</strong></td>
            <td style="padding: 6px 12px; border: 1px solid #ddd;">{{ $donHang->phuong_thuc === 'chuyenkhoan' ? 'Chuyển khoản (QR)' : 'Tiền mặt khi nhận hàng' }}</td>
        </tr>
        @if ($donHang->han_giu)
        <tr>
            <td style="padding: 6px 12px; border: 1px solid #ddd; background: #f8fafc;"><strong>Hạn giữ hàng</strong></td>
            <td style="padding: 6px 12px; border: 1px solid #ddd;">{{ $donHang->han_giu->format('H:i d/m/Y') }}</td>
        </tr>
        @endif
        @if ($donHang->hinh_thuc_nhan === 'giao_tan_noi' && $donHang->dia_chi_giao)
        <tr>
            <td style="padding: 6px 12px; border: 1px solid #ddd; background: #f8fafc;"><strong>Địa chỉ giao</strong></td>
            <td style="padding: 6px 12px; border: 1px solid #ddd;">{{ $donHang->dia_chi_giao }}</td>
        </tr>
        @endif
    </table>

    <h3>Sản phẩm đã đặt</h3>
    <table style="border-collapse: collapse; width: 100%; margin: 16px 0;">
        <thead>
            <tr style="background: #f1f5f9;">
                <th style="padding: 8px 12px; border: 1px solid #ddd; text-align: left;">Sản phẩm</th>
                <th style="padding: 8px 12px; border: 1px solid #ddd;">SL</th>
                <th style="padding: 8px 12px; border: 1px solid #ddd;">Đơn giá</th>
                <th style="padding: 8px 12px; border: 1px solid #ddd;">Thành tiền</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($donHang->chiTietDonHangs as $ct)
            <tr>
                <td style="padding: 8px 12px; border: 1px solid #ddd;">{{ $ct->sanPham?->ten_sanpham ?? 'Sản phẩm #'.$ct->product_id }}</td>
                <td style="padding: 8px 12px; border: 1px solid #ddd; text-align: center;">{{ $ct->soluong }}</td>
                <td style="padding: 8px 12px; border: 1px solid #ddd; text-align: right;">{{ number_format((float) $ct->don_gia, 0, ',', '.') }} đ</td>
                <td style="padding: 8px 12px; border: 1px solid #ddd; text-align: right;">{{ number_format((float) $ct->thanh_tien, 0, ',', '.') }} đ</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="padding: 8px 12px; border: 1px solid #ddd; text-align: right;"><strong>Tổng thanh toán</strong></td>
                <td style="padding: 8px 12px; border: 1px solid #ddd; text-align: right;"><strong>{{ number_format((float) $donHang->tong_thanh_toan, 0, ',', '.') }} đ</strong></td>
            </tr>
        </tfoot>
    </table>

    @if ($donHang->phuong_thuc === 'chuyenkhoan')
    <p><strong>Hướng dẫn thanh toán:</strong> Quét mã QR trên trang xác nhận đơn hoặc chuyển khoản đúng số tiền
        <strong>{{ number_format((float) $donHang->tong_thanh_toan, 0, ',', '.') }} đ</strong>
        với nội dung <strong>{{ $donHang->ma_hoadon }}</strong>. Đơn sẽ tự xác nhận khi ngân hàng báo về.</p>
    @else
    <p><strong>Hướng dẫn nhận hàng:</strong> Mang mã tra cứu <strong>{{ $donHang->ma_tra_cuu }}</strong> đến cửa hàng
        trước {{ $donHang->han_giu?->format('H:i d/m/Y') }} để nhận hàng và thanh toán tiền mặt.</p>
    @endif

    <p><strong>Tra cứu đơn hàng:</strong> Truy cập
        <a href="{{ route('don-hang.tracuu') }}">{{ route('don-hang.tracuu') }}</a>
        và nhập mã tra cứu <strong>{{ $donHang->ma_tra_cuu }}</strong> cùng số điện thoại
        <strong>{{ $donHang->so_dienthoai_dat }}</strong>.</p>

    <p>Nếu bạn không thực hiện đặt hàng này, vui lòng bỏ qua email hoặc liên hệ cửa hàng.</p>

    <p style="color: #888; font-size: 12px; margin-top: 24px;">
        Trân trọng,<br>
        {{ config('app.name', 'PhoneShop') }}
    </p>
</body>
</html>
