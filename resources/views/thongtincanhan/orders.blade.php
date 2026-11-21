@extends('storefront.layouts.app')

@section('title', 'Lịch sử đơn hàng — ' . config('storefront.ten_cua_hang'))

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
                    <h2 style="margin:0 0 4px 0;font-size:20px;">
                        @if (\App\Support\HoSoXacThuc::conHieuLuc(request()))
                            {{ auth()->user()->name }}
                        @else
                            {{ \App\Support\ChePII::hoTen(auth()->user()->name) }}
                        @endif
                    </h2>
                    <p style="margin:0;font-size:14px;opacity:0.9;">
                        @if (\App\Support\HoSoXacThuc::conHieuLuc(request()))
                            {{ $khachHang ? $khachHang->so_dienthoai : 'Chưa cập nhật' }}
                        @else
                            {{ $khachHang ? \App\Support\ChePII::soDienThoai($khachHang->so_dienthoai) : 'Chưa cập nhật' }}
                        @endif
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
            <a href="{{ route('profile.diem') }}" class="sf-icon-btn {{ request()->is('thong-tin-ca-nhan/diem') ? 'is-primary' : '' }}" style="height:40px;padding:0 16px;">
                <i class="fa-solid fa-star"></i> Điểm thưởng
            </a>
        </div>

        <!-- Main content -->
        <div style="background:var(--sf-surface);border:1px solid var(--sf-border);border-radius:12px;padding:20px;">
            <h3 style="margin-top:0;margin-bottom:16px;">Lịch sử đơn hàng</h3>

            @if ($donHangs->count() > 0)
                <div style="display:flex;flex-direction:column;gap:12px;">
                    @foreach ($donHangs as $don)
                        <div style="border:1px solid var(--sf-border);border-radius:8px;padding:16px;">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
                                <div>
                                    <div style="font-weight:700;">{{ $don->ma_hoadon }}</div>
                                    <div style="font-size:var(--sf-text-sm);color:var(--sf-fg-muted);">{{ $don->created_at->format('d/m/Y H:i') }}</div>
                                </div>
                                <div style="text-align:right;">
                                    <div style="font-weight:700;color:var(--sf-accent);">{{ number_format((float) $don->tong_thanh_toan, 0, ',', '.') }}₫</div>
                                    <div style="font-size:var(--sf-text-sm);">{{ $don->trang_thai_tieng_viet }}</div>
                                </div>
                            </div>
                            <div style="font-size:var(--sf-text-sm);color:var(--sf-fg-muted);">
                                @foreach ($don->chiTietDonHangs->take(3) as $ct)
                                    {{ $ct->sanPham?->ten_sanpham ?? 'Sản phẩm' }} × {{ $ct->soluong }}@if (!$loop->last), @endif
                                @endforeach
                                @if ($don->chiTietDonHangs->count() > 3)
                                    , ...
                                @endif
                            </div>
                            <div style="margin-top:8px;">
                                <a href="{{ route('profile.orders.show', $don) }}" class="sf-icon-btn" style="height:32px;padding:0 12px;font-size:var(--sf-text-sm);">
                                    <i class="fa-solid fa-eye"></i> Xem chi tiết
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div style="margin-top:16px;">{{ $donHangs->links() }}</div>
            @else
                <div style="text-align:center;padding:40px;color:var(--sf-fg-muted);">
                    <i class="fa-solid fa-inbox" style="font-size:48px;margin-bottom:12px;"></i>
                    <p>Chưa có đơn hàng nào</p>
                    <a href="{{ route('storefront.home') }}" class="sf-icon-btn is-primary" style="height:40px;padding:0 16px;margin-top:12px;">
                        <i class="fa-solid fa-shopping-bag"></i> Mua sắm ngay
                    </a>
                </div>
            @endif
        </div>
    </div>
@endsection
