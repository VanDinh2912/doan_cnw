<footer class="sf-footer">
    <div class="sf-container">
        <div class="sf-footer-grid">
            <div>
                <div class="sf-logo" style="margin-bottom:16px;">
                    <i class="fa-solid fa-mobile-screen"></i>
                    <span>PhoneShop</span>
                </div>
                <p style="color:var(--sf-fg-muted);font-size:var(--sf-text-sm);margin:0 0 12px;max-width:320px;">
                    Cửa hàng điện thoại chính hãng — bảo hành 12 tháng, đổi trả trong 30 ngày.
                </p>
                <div class="sf-footer-hotline">
                    <i class="fa-solid fa-phone"></i> {{ config('storefront.hotline') }}
                </div>
                <p style="font-size:var(--sf-text-xs);color:var(--sf-fg-subtle);margin:0;">
                    Hỗ trợ 8:00 – 22:00 hằng ngày
                </p>
            </div>

            <div>
                <h4>Về chúng tôi</h4>
                <ul>
                    <li><a href="{{ route('trang-tinh', 'gioi-thieu') }}">Giới thiệu</a></li>
                    <li><a href="{{ route('trang-tinh', 'he-thong-cua-hang') }}">Hệ thống cửa hàng</a></li>
                    <li><a href="{{ route('tuyen-dung') }}">Tuyển dụng</a></li>
                    <li><a href="{{ route('lien-he.form') }}">Liên hệ</a></li>
                </ul>
            </div>

            <div>
                <h4>Chính sách</h4>
                <ul>
                    <li><a href="{{ route('trang-tinh', 'chinh-sach-bao-hanh') }}">Chính sách bảo hành</a></li>
                    <li><a href="{{ route('trang-tinh', 'chinh-sach-doi-tra') }}">Chính sách đổi trả</a></li>
                    <li><a href="{{ route('trang-tinh', 'chinh-sach-van-chuyen') }}">Chính sách vận chuyển</a></li>
                    <li><a href="{{ route('trang-tinh', 'bao-mat-thong-tin') }}">Bảo mật thông tin</a></li>
                </ul>
            </div>

            <div>
                <h4>Hỗ trợ</h4>
                <ul>
                    <li><a href="{{ route('trang-tinh', 'huong-dan-mua-hang') }}">Hướng dẫn mua hàng</a></li>
                    <li><a href="{{ route('tintuc.danhsach') }}">Tin tức</a></li>
                    @if (config('pos.dat_hang_online_bat'))
                        <li><a href="{{ route('don-hang.tracuu') }}">Tra cứu đơn hàng</a></li>
                    @endif
                    <li><a href="{{ route('trang-tinh', 'cau-hoi-thuong-gap') }}">Câu hỏi thường gặp</a></li>
                    <li><a href="{{ route('lien-he.form', ['loai' => 'gop_y']) }}">Góp ý, khiếu nại</a></li>
                </ul>
            </div>
        </div>

        <div class="sf-footer-bottom">
            <span>© {{ date('Y') }} PhoneShop — Đồ án Laravel.</span>
            <span>Thanh toán: Tiền mặt · Chuyển khoản · VietQR</span>
        </div>
    </div>
</footer>
