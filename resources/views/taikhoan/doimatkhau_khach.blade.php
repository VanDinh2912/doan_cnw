@extends('storefront.layouts.app')

@section('title', 'Đổi mật khẩu — PhoneShop')

@section('content')
<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
<div class="sf-container" style="padding-top:20px;">
    <div class="sf-section-head">
        <h1 class="sf-section-title">Đổi mật khẩu</h1>
    </div>

    @if (session('warning'))
        <div style="background:var(--sf-bg);border:1px solid #f59e0b;border-radius:8px;padding:12px;margin-bottom:16px;color:#f59e0b;">
            <i class="fa-solid fa-triangle-exclamation"></i> {{ session('warning') }}
        </div>
    @endif

    <div style="background:var(--sf-surface);border:1px solid var(--sf-border);border-radius:12px;padding:20px;">
        <form method="POST" action="{{ route('doi-mat-khau.post') }}">
            @csrf

            <div style="margin-bottom:12px;">
                <label for="mat_khau_hien_tai" style="display:block;margin-bottom:4px;">Mật khẩu hiện tại</label>
                <input id="mat_khau_hien_tai" name="mat_khau_hien_tai" type="password" required autocomplete="current-password"
                       style="width:100%;height:40px;border:1px solid var(--sf-border-strong);border-radius:8px;padding:0 10px;">
                @error('mat_khau_hien_tai')<div style="color:var(--sf-stock-out);font-size:var(--sf-text-sm);">{{ $message }}</div>@enderror
            </div>

            <div style="margin-bottom:12px;">
                <label for="mat_khau_moi" style="display:block;margin-bottom:4px;">Mật khẩu mới</label>
                <input id="mat_khau_moi" name="mat_khau_moi" type="password" required autocomplete="new-password"
                       style="width:100%;height:40px;border:1px solid var(--sf-border-strong);border-radius:8px;padding:0 10px;">
                @error('mat_khau_moi')<div style="color:var(--sf-stock-out);font-size:var(--sf-text-sm);">{{ $message }}</div>@enderror
                <small style="color:var(--sf-text-muted,#6b7280);font-size:12px;">Mật khẩu phải có ít nhất 8 ký tự, bao gồm chữ, số và ký tự đặc biệt.</small>
            </div>

            <div style="margin-bottom:12px;">
                <label for="mat_khau_xac_nhan" style="display:block;margin-bottom:4px;">Xác nhận mật khẩu mới</label>
                <input id="mat_khau_xac_nhan" name="mat_khau_xac_nhan" type="password" required autocomplete="new-password"
                       style="width:100%;height:40px;border:1px solid var(--sf-border-strong);border-radius:8px;padding:0 10px;">
                @error('mat_khau_xac_nhan')<div style="color:var(--sf-stock-out);font-size:var(--sf-text-sm);">{{ $message }}</div>@enderror
            </div>

            <div style="display:flex;gap:12px;">
                <a href="{{ route('storefront.home') }}" class="sf-icon-btn" style="height:44px;padding:0 24px;">Hủy</a>
                <button type="submit" class="sf-icon-btn is-primary" style="height:44px;padding:0 24px;">
                    <i class="fa-solid fa-key"></i> Đổi mật khẩu
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
