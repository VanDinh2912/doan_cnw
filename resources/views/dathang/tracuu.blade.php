@extends('storefront.layouts.app')

@section('title', 'Tra cứu đơn hàng — PhoneShop')

@section('content')
    <div class="sf-container" style="padding-top:20px;">
        <div class="sf-section-head">
            <h1 class="sf-section-title">Tra cứu đơn hàng</h1>
        </div>

        <form method="POST" action="{{ route('don-hang.posttracuu') }}"
              style="background:var(--sf-surface);border:1px solid var(--sf-border);border-radius:12px;padding:20px;">
            @csrf
            <p style="margin-top:0;color:var(--sf-fg-muted);font-size:var(--sf-text-sm);">
                Nhập mã tra cứu và số điện thoại đã dùng khi đặt hàng. Số điện thoại chỉ gửi trong biểu mẫu, không hiển thị trên địa chỉ trang.
            </p>

            <div style="margin-bottom:12px;">
                <label for="ma_tra_cuu" style="display:block;margin-bottom:4px;">Mã tra cứu</label>
                <input id="ma_tra_cuu" name="ma_tra_cuu" type="text" value="{{ old('ma_tra_cuu') }}" required maxlength="20"
                       style="width:100%;height:40px;border:1px solid var(--sf-border-strong);border-radius:8px;padding:0 10px;">
                @error('ma_tra_cuu')<div style="color:var(--sf-stock-out);font-size:var(--sf-text-sm);">{{ $message }}</div>@enderror
            </div>

            <div style="margin-bottom:12px;">
                <label for="so_dienthoai" style="display:block;margin-bottom:4px;">Số điện thoại đặt hàng</label>
                <input id="so_dienthoai" name="so_dienthoai" type="text" value="{{ old('so_dienthoai') }}" required
                       style="width:100%;height:40px;border:1px solid var(--sf-border-strong);border-radius:8px;padding:0 10px;">
                @error('so_dienthoai')<div style="color:var(--sf-stock-out);font-size:var(--sf-text-sm);">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="sf-icon-btn is-primary" style="height:44px;padding:0 24px;">
                <i class="fa-solid fa-magnifying-glass"></i> Tra cứu
            </button>
        </form>
    </div>
@endsection