@extends('storefront.layouts.app')

@section('title', 'Thông tin cá nhân — PhoneShop')

@section('content')
    <div class="sf-container" style="padding-top:20px;">
        <div class="sf-section-head">
            <h1 class="sf-section-title">Tài khoản của tôi</h1>
        </div>

        <!-- Header thông tin người dùng -->
        <div style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);border-radius:12px;padding:24px;color:white;margin-bottom:20px;">
            <div style="display:flex;align-items:center;gap:16px;">
                <div style="width:64px;height:64px;background:rgba(255,255,255,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:24px;">
                    <i class="fa-solid fa-user"></i>
                </div>
                <div style="flex:1;">
                    <h2 style="margin:0 0 4px 0;font-size:20px;">{{ auth()->user()->name }}</h2>
                    <p style="margin:0;font-size:14px;opacity:0.9;">
                        {{ $khachHang ? substr($khachHang->so_dienthoai, 0, 3) . '****' . substr($khachHang->so_dienthoai, -2) : 'Chưa cập nhật' }}
                    </p>
                </div>
            </div>
            <div style="display:flex;gap:24px;margin-top:20px;flex-wrap:wrap;">
                <div style="flex:1;min-width:120px;">
                    <div style="font-size:24px;font-weight:700;">{{ $khachHang ? number_format($khachHang->diem_tichluy, 0, ',', '.') : '0' }}</div>
                    <div style="font-size:12px;opacity:0.8;">Điểm tích lũy</div>
                </div>
                <div style="flex:1;min-width:120px;">
                    <div style="font-size:24px;font-weight:700;">{{ $khachHang ? $khachHang->donHangs()->count() : '0' }}</div>
                    <div style="font-size:12px;opacity:0.8;">Tổng đơn hàng</div>
                </div>
                <div style="flex:1;min-width:120px;">
                    <div style="font-size:24px;font-weight:700;">{{ $khachHang ? number_format($khachHang->donHangs()->where('trang_thai', 'da_thanh_toan')->sum('tong_thanh_toan'), 0, ',', '.') : '0' }}₫</div>
                    <div style="font-size:12px;opacity:0.8;">Tổng chi tiêu</div>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <div style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap;">
            <a href="{{ route('profile.show') }}" class="sf-icon-btn {{ request()->is('thong-tin-ca-nhan') ? 'is-primary' : '' }}" style="height:40px;padding:0 16px;">
                <i class="fa-solid fa-user"></i> Thông tin cá nhân
            </a>
            <a href="{{ route('profile.orders') }}" class="sf-icon-btn {{ request()->is('thong-tin-ca-nhan/don-hang') ? 'is-primary' : '' }}" style="height:40px;padding:0 16px;">
                <i class="fa-solid fa-list"></i> Lịch sử đơn hàng
            </a>
        </div>

        <!-- Main content -->
        <div style="background:var(--sf-surface);border:1px solid var(--sf-border);border-radius:12px;padding:20px;">
            @if (session('success'))
                <div style="background:var(--sf-bg);border:1px solid var(--sf-accent);border-radius:8px;padding:12px;margin-bottom:16px;color:var(--sf-accent);">
                    {{ session('success') }}
                </div>
            @endif

            <h3 style="margin-top:0;margin-bottom:16px;">Cập nhật thông tin</h3>

            <form method="POST" action="{{ route('profile.update') }}">
                @csrf

                <div style="margin-bottom:12px;">
                    <label for="ho_ten" style="display:block;margin-bottom:4px;">Họ tên</label>
                    <input id="ho_ten" name="ho_ten" type="text" value="{{ old('ho_ten', $khachHang?->ho_ten) }}" required maxlength="100"
                           style="width:100%;height:40px;border:1px solid var(--sf-border-strong);border-radius:8px;padding:0 10px;">
                    @error('ho_ten')<div style="color:var(--sf-stock-out);font-size:var(--sf-text-sm);">{{ $message }}</div>@enderror
                </div>

                <div style="margin-bottom:12px;">
                    <label for="so_dienthoai" style="display:block;margin-bottom:4px;">Số điện thoại</label>
                    <input id="so_dienthoai" name="so_dienthoai" type="text" value="{{ old('so_dienthoai', $khachHang?->so_dienthoai) }}" required
                           style="width:100%;height:40px;border:1px solid var(--sf-border-strong);border-radius:8px;padding:0 10px;">
                    @error('so_dienthoai')<div style="color:var(--sf-stock-out);font-size:var(--sf-text-sm);">{{ $message }}</div>@enderror
                </div>

                <div style="margin-bottom:12px;">
                    <label for="dia_chi" style="display:block;margin-bottom:4px;">Địa chỉ (không bắt buộc)</label>
                    <textarea id="dia_chi" name="dia_chi" rows="3" maxlength="255"
                              style="width:100%;border:1px solid var(--sf-border-strong);border-radius:8px;padding:8px 10px;">{{ old('dia_chi', $khachHang?->dia_chi) }}</textarea>
                    @error('dia_chi')<div style="color:var(--sf-stock-out);font-size:var(--sf-text-sm);">{{ $message }}</div>@enderror
                </div>

                <button type="submit" class="sf-icon-btn is-primary" style="height:44px;padding:0 24px;">
                    <i class="fa-solid fa-save"></i> Lưu thông tin
                </button>
            </form>
        </div>
    </div>
@endsection
