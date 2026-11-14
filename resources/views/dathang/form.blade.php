@extends('storefront.layouts.app')

@section('title', 'Đặt hàng — PhoneShop')

@section('content')
    <div class="sf-container" style="padding-top:20px;">
        <div class="sf-section-head">
            <h1 class="sf-section-title">Thông tin đặt hàng</h1>
        </div>

        <form method="POST" action="{{ route('dat-hang.dat') }}"
              style="background:var(--sf-surface);border:1px solid var(--sf-border);border-radius:12px;padding:20px;">
            @csrf
            <input type="hidden" name="checkout_token" value="{{ $token }}">

            <div style="margin-bottom:12px;">
                <label style="display:block;margin-bottom:4px;">Hình thức nhận</label>
                <div style="display:flex;gap:12px;margin-bottom:8px;">
                    <label style="display:flex;align-items:center;gap:4px;cursor:pointer;">
                        <input type="radio" name="hinh_thuc_nhan" value="tai_cua_hang" {{ old('hinh_thuc_nhan', 'tai_cua_hang') === 'tai_cua_hang' ? 'checked' : '' }}>
                        Nhận tại cửa hàng
                    </label>
                    <label style="display:flex;align-items:center;gap:4px;cursor:pointer;">
                        <input type="radio" name="hinh_thuc_nhan" value="giao_tan_noi" {{ old('hinh_thuc_nhan') === 'giao_tan_noi' ? 'checked' : '' }}>
                        Giao tận nơi
                    </label>
                </div>
                @error('hinh_thuc_nhan')<div style="color:var(--sf-stock-out);font-size:var(--sf-text-sm);">{{ $message }}</div>@enderror
            </div>

            {{-- Honeypot D12: ẩn với người thật, bot điền vào sẽ bị từ chối (không hiện lỗi riêng). --}}
            <div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden;">
                <label for="trang_web">Website</label>
                <input id="trang_web" name="trang_web" type="text" value="" tabindex="-1" autocomplete="off">
            </div>

            <div style="margin-bottom:12px;">
                <label for="ten" style="display:block;margin-bottom:4px;">Họ tên</label>
                <input id="ten" name="ten" type="text" value="{{ old('ten') }}" required maxlength="100"
                       style="width:100%;height:40px;border:1px solid var(--sf-border-strong);border-radius:8px;padding:0 10px;">
                @error('ten')<div style="color:var(--sf-stock-out);font-size:var(--sf-text-sm);">{{ $message }}</div>@enderror
            </div>

            <div style="margin-bottom:12px;">
                <label for="so_dienthoai" style="display:block;margin-bottom:4px;">Số điện thoại</label>
                <input id="so_dienthoai" name="so_dienthoai" type="text" value="{{ old('so_dienthoai') }}" required
                       style="width:100%;height:40px;border:1px solid var(--sf-border-strong);border-radius:8px;padding:0 10px;">
                @error('so_dienthoai')<div style="color:var(--sf-stock-out);font-size:var(--sf-text-sm);">{{ $message }}</div>@enderror
            </div>

            <div id="thong_tin_nguoi_nhan" style="display:none;margin-bottom:12px;">
                <label for="ten_nguoi_nhan" style="display:block;margin-bottom:4px;">Tên người nhận</label>
                <input id="ten_nguoi_nhan" name="ten_nguoi_nhan" type="text" value="{{ old('ten_nguoi_nhan') }}" maxlength="100"
                       style="width:100%;height:40px;border:1px solid var(--sf-border-strong);border-radius:8px;padding:0 10px;">
                @error('ten_nguoi_nhan')<div style="color:var(--sf-stock-out);font-size:var(--sf-text-sm);">{{ $message }}</div>@enderror

                <label for="sdt_nguoi_nhan" style="display:block;margin-top:12px;margin-bottom:4px;">SĐT người nhận</label>
                <input id="sdt_nguoi_nhan" name="sdt_nguoi_nhan" type="text" value="{{ old('sdt_nguoi_nhan') }}" maxlength="15"
                       style="width:100%;height:40px;border:1px solid var(--sf-border-strong);border-radius:8px;padding:0 10px;">
                @error('sdt_nguoi_nhan')<div style="color:var(--sf-stock-out);font-size:var(--sf-text-sm);">{{ $message }}</div>@enderror

                <label for="dia_chi_giao" style="display:block;margin-top:12px;margin-bottom:4px;">Địa chỉ giao</label>
                <textarea id="dia_chi_giao" name="dia_chi_giao" rows="3" maxlength="255"
                          style="width:100%;border:1px solid var(--sf-border-strong);border-radius:8px;padding:8px 10px;">{{ old('dia_chi_giao') }}</textarea>
                @error('dia_chi_giao')<div style="color:var(--sf-stock-out);font-size:var(--sf-text-sm);">{{ $message }}</div>@enderror
            </div>

            <div style="margin-bottom:12px;">
                <label style="display:block;margin-bottom:4px;">Phương thức thanh toán</label>
                <div style="display:flex;gap:12px;margin-bottom:8px;">
                    <label style="display:flex;align-items:center;gap:4px;cursor:pointer;">
                        <input type="radio" name="phuong_thuc" value="qr" {{ old('phuong_thuc', 'pickup') === 'qr' ? 'checked' : '' }}>
                        QR trả trước
                    </label>
                    <label id="option_trata_quay" style="display:flex;align-items:center;gap:4px;cursor:pointer;">
                        <input type="radio" name="phuong_thuc" value="pickup" {{ old('phuong_thuc', 'pickup') === 'pickup' ? 'checked' : '' }}>
                        Trả tại quầy
                    </label>
                </div>
                @error('phuong_thuc')<div style="color:var(--sf-stock-out);font-size:var(--sf-text-sm);">{{ $message }}</div>@enderror
            </div>

            <div style="margin-bottom:12px;">
                <label for="ghi_chu" style="display:block;margin-bottom:4px;">Ghi chú (không bắt buộc)</label>
                <textarea id="ghi_chu" name="ghi_chu" rows="3" maxlength="500"
                          style="width:100%;border:1px solid var(--sf-border-strong);border-radius:8px;padding:8px 10px;">{{ old('ghi_chu') }}</textarea>
                @error('ghi_chu')<div style="color:var(--sf-stock-out);font-size:var(--sf-text-sm);">{{ $message }}</div>@enderror
            </div>

            <p id="thong_tin_nhan_hang" style="color:var(--sf-fg-muted);font-size:var(--sf-text-sm);">
                Nhận hàng tại quầy trong {{ $pickupGio }} giờ, thanh toán tiền mặt khi nhận. Quá hạn giữ hàng đơn sẽ tự hủy và hoàn kho.
            </p>

            <p id="thong_tin_giao_hang" style="display:none;color:var(--sf-fg-muted);font-size:var(--sf-text-sm);">
                Giao tận nơi, thanh toán QR trả trước. Phí giao hàng: {{ number_format($phiGiaoHang, 0, ',', '.') }} ₫{{ $mienPhiGiaoTu > 0 ? ' (miễn phí khi tiền hàng ≥ '.number_format($mienPhiGiaoTu, 0, ',', '.').' ₫)' : '' }}.
            </p>

            <div style="margin:12px 0;font-weight:700;">Tạm tính: {{ number_format($tong, 0, ',', '.') }} ₫</div>

            <button type="submit" class="sf-icon-btn is-primary" style="height:44px;padding:0 24px;">
                <i class="fa-solid fa-bag-shopping"></i> Đặt hàng
            </button>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const hinhThucNhanRadios = document.querySelectorAll('input[name="hinh_thuc_nhan"]');
            const phuongThucRadios = document.querySelectorAll('input[name="phuong_thuc"]');
            const thongTinNguoiNhan = document.getElementById('thong_tin_nguoi_nhan');
            const thongTinNhanHang = document.getElementById('thong_tin_nhan_hang');
            const thongTinGiaoHang = document.getElementById('thong_tin_giao_hang');
            const optionTraTaiQuay = document.getElementById('option_trata_quay');

            function capNhatGiaoDien() {
                const hinhThucNhan = document.querySelector('input[name="hinh_thuc_nhan"]:checked')?.value;
                const laGiaoTanNoi = hinhThucNhan === 'giao_tan_noi';

                if (laGiaoTanNoi) {
                    thongTinNguoiNhan.style.display = 'block';
                    thongTinNhanHang.style.display = 'none';
                    thongTinGiaoHang.style.display = 'block';
                    optionTraTaiQuay.style.display = 'none';

                    // Bắt buộc QR
                    const qrRadio = document.querySelector('input[name="phuong_thuc"][value="qr"]');
                    if (qrRadio) qrRadio.checked = true;
                } else {
                    thongTinNguoiNhan.style.display = 'none';
                    thongTinNhanHang.style.display = 'block';
                    thongTinGiaoHang.style.display = 'none';
                    optionTraTaiQuay.style.display = 'flex';
                }
            }

            hinhThucNhanRadios.forEach(radio => {
                radio.addEventListener('change', capNhatGiaoDien);
            });

            capNhatGiaoDien();
        });
    </script>
@endsection