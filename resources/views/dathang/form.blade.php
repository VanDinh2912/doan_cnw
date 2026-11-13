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
            <input type="hidden" name="phuong_thuc" value="pickup">

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

            <div style="margin-bottom:12px;">
                <label for="ghi_chu" style="display:block;margin-bottom:4px;">Ghi chú (không bắt buộc)</label>
                <textarea id="ghi_chu" name="ghi_chu" rows="3" maxlength="500"
                          style="width:100%;border:1px solid var(--sf-border-strong);border-radius:8px;padding:8px 10px;">{{ old('ghi_chu') }}</textarea>
                @error('ghi_chu')<div style="color:var(--sf-stock-out);font-size:var(--sf-text-sm);">{{ $message }}</div>@enderror
            </div>

            <p style="color:var(--sf-fg-muted);font-size:var(--sf-text-sm);">
                Nhận hàng tại quầy trong {{ $pickupGio }} giờ, thanh toán tiền mặt khi nhận. Quá hạn giữ hàng đơn sẽ tự hủy và hoàn kho.
            </p>

            <div style="margin:12px 0;font-weight:700;">Tạm tính: {{ number_format($tong, 0, ',', '.') }} ₫</div>

            <button type="submit" class="sf-icon-btn is-primary" style="height:44px;padding:0 24px;">
                <i class="fa-solid fa-bag-shopping"></i> Đặt hàng (pickup)
            </button>
        </form>
    </div>
@endsection