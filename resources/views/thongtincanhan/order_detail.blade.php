@extends('storefront.layouts.app')

@section('title', 'Đơn hàng ' . $don->ma_hoadon . ' — ' . config('storefront.ten_cua_hang'))

@section('content')
    <div class="sf-container" style="padding-top:20px;">
        <div class="sf-section-head">
            <h1 class="sf-section-title">Đơn hàng {{ $don->ma_hoadon }}</h1>
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
            <a href="{{ route('profile.orders') }}" class="sf-icon-btn {{ request()->is('thong-tin-ca-nhan/don-hang*') ? 'is-primary' : '' }}" style="height:40px;padding:0 16px;">
                <i class="fa-solid fa-list"></i> Lịch sử đơn hàng
            </a>
        </div>

        <!-- Main content -->
        <div style="background:var(--sf-surface);border:1px solid var(--sf-border);border-radius:12px;padding:20px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
                <div>
                    <div style="font-size:var(--sf-text-sm);color:var(--sf-fg-muted);">Ngày đặt: {{ $don->created_at->format('d/m/Y H:i') }}</div>
                    @if ($don->ma_tra_cuu)
                        <div style="font-size:var(--sf-text-sm);color:var(--sf-fg-muted);">Mã tra cứu: {{ $don->ma_tra_cuu }}</div>
                    @endif
                </div>
                <div style="text-align:right;">
                    <div style="font-weight:700;color:var(--sf-accent);font-size:18px;">{{ number_format((float) $don->tong_thanh_toan, 0, ',', '.') }}₫</div>
                    <div>{{ $don->trang_thai_tieng_viet }}</div>
                </div>
            </div>

            <table style="width:100%;border-collapse:collapse;margin-bottom:16px;">
                <thead>
                    <tr style="border-bottom:1px solid var(--sf-border);text-align:left;">
                        <th style="padding:8px 0;">Sản phẩm</th>
                        <th style="padding:8px 0;text-align:center;">SL</th>
                        <th style="padding:8px 0;text-align:right;">Đơn giá</th>
                        <th style="padding:8px 0;text-align:right;">Thành tiền</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($don->chiTietDonHangs as $ct)
                        <tr style="border-bottom:1px solid var(--sf-border);">
                            <td style="padding:8px 0;">{{ $ct->sanPham?->ten_sanpham ?? 'Sản phẩm' }}</td>
                            <td style="padding:8px 0;text-align:center;">{{ $ct->soluong }}</td>
                            <td style="padding:8px 0;text-align:right;">{{ number_format((float) $ct->don_gia, 0, ',', '.') }}₫</td>
                            <td style="padding:8px 0;text-align:right;">{{ number_format((float) $ct->thanh_tien, 0, ',', '.') }}₫</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div style="max-width:320px;margin-left:auto;font-size:var(--sf-text-sm);">
                <div style="display:flex;justify-content:space-between;padding:4px 0;">
                    <span>Tạm tính</span><span>{{ number_format((float) $don->tong_tien, 0, ',', '.') }}₫</span>
                </div>
                @if ((float) ($don->tien_giam ?? 0) > 0 || (float) ($don->tien_giam_diem ?? 0) > 0)
                    <div style="display:flex;justify-content:space-between;padding:4px 0;">
                        <span>Giảm giá</span><span>-{{ number_format((float) ($don->tien_giam ?? 0) + (float) ($don->tien_giam_diem ?? 0), 0, ',', '.') }}₫</span>
                    </div>
                @endif
                @if ((float) ($don->phi_giao_hang ?? 0) > 0)
                    <div style="display:flex;justify-content:space-between;padding:4px 0;">
                        <span>Phí giao hàng</span><span>{{ number_format((float) $don->phi_giao_hang, 0, ',', '.') }}₫</span>
                    </div>
                @endif
                <div style="display:flex;justify-content:space-between;padding:4px 0;font-weight:700;border-top:1px solid var(--sf-border);margin-top:4px;">
                    <span>Tổng thanh toán</span><span>{{ number_format((float) $don->tong_thanh_toan, 0, ',', '.') }}₫</span>
                </div>
                @if ((float) ($don->tien_khach_dua ?? 0) > 0)
                    <div style="display:flex;justify-content:space-between;padding:4px 0;">
                        <span>Khách đưa</span><span>{{ number_format((float) $don->tien_khach_dua, 0, ',', '.') }}₫</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:4px 0;">
                        <span>Tiền thừa</span><span>{{ number_format((float) ($don->tien_thua ?? 0), 0, ',', '.') }}₫</span>
                    </div>
                @endif
            </div>

            <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--sf-border);font-size:var(--sf-text-sm);color:var(--sf-fg-muted);">
                <div>Thanh toán: {{ $don->phuong_thuc === 'chuyenkhoan' ? 'Chuyển khoản' : 'Tiền mặt' }}</div>
                @if ($don->hinh_thuc_nhan === 'giao_tan_noi')
                    <div>Nhận hàng: Giao tận nơi</div>
                    @if ($don->ten_nguoi_nhan)
                        <div>Người nhận:
                            @if (\App\Support\HoSoXacThuc::conHieuLuc(request()))
                                {{ $don->ten_nguoi_nhan }} — {{ $don->sdt_nguoi_nhan }}
                            @else
                                {{ \App\Support\ChePII::hoTen($don->ten_nguoi_nhan) }} — {{ \App\Support\ChePII::soDienThoai($don->sdt_nguoi_nhan) }}
                            @endif
                        </div>
                    @endif
                    @if ($don->dia_chi_giao)
                        <div>Địa chỉ:
                            @if (\App\Support\HoSoXacThuc::conHieuLuc(request()))
                                {{ $don->dia_chi_giao }}
                            @else
                                {{ \App\Support\ChePII::diaChi($don->dia_chi_giao) }}
                            @endif
                        </div>
                    @endif
                @else
                    <div>Nhận hàng: Tại cửa hàng</div>
                @endif
                @if ($don->ghi_chu_khach)
                    <div>Ghi chú: {{ $don->ghi_chu_khach }}</div>
                @endif
            </div>

            <div style="margin-top:16px;">
                <a href="{{ route('profile.orders') }}" class="sf-icon-btn" style="height:40px;padding:0 16px;">
                    <i class="fa-solid fa-arrow-left"></i> Quay lại lịch sử đơn hàng
                </a>
            </div>
        </div>
    </div>
@endsection
