@extends('storefront.layouts.app')

@section('title', 'Điểm tích lũy — ' . config('storefront.ten_cua_hang'))

@section('content')
    <div class="sf-container" style="padding-top:20px;">
        <div class="sf-section-head">
            <h1 class="sf-section-title">Điểm tích lũy</h1>
        </div>

        <!-- Navigation -->
        <div style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap;">
            <a href="{{ route('profile.show') }}" class="sf-icon-btn" style="height:40px;padding:0 16px;">
                <i class="fa-solid fa-user"></i> Thông tin cá nhân
            </a>
            <a href="{{ route('profile.orders') }}" class="sf-icon-btn" style="height:40px;padding:0 16px;">
                <i class="fa-solid fa-list"></i> Lịch sử đơn hàng
            </a>
            <a href="{{ route('profile.diem') }}" class="sf-icon-btn is-primary" style="height:40px;padding:0 16px;">
                <i class="fa-solid fa-star"></i> Điểm tích lũy
            </a>
        </div>

        @if ($khachHang === null)
            <div style="background:var(--sf-surface);border:1px solid var(--sf-border);border-radius:12px;padding:40px;text-align:center;color:var(--sf-fg-muted);">
                <p>Bạn chưa có hồ sơ khách hàng nên chưa có điểm tích lũy.</p>
                <a href="{{ route('profile.show') }}" class="sf-icon-btn is-primary" style="height:40px;padding:0 16px;">
                    Cập nhật hồ sơ
                </a>
            </div>
        @else
            <div style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);border-radius:12px;padding:24px;color:white;margin-bottom:20px;">
                <div style="font-size:14px;opacity:0.9;">Số dư điểm</div>
                <div style="font-size:36px;font-weight:700;">{{ number_format($khachHang->diem_tichluy) }} điểm</div>
                <div style="font-size:13px;opacity:0.85;">
                    Tương đương khoảng {{ number_format($khachHang->diem_tichluy * (int) config('pos.gia_tri_diem'), 0, ',', '.') }} đ khi thanh toán
                </div>
            </div>

            <div style="background:var(--sf-surface);border:1px solid var(--sf-border);border-radius:12px;padding:20px;">
                <h3 style="margin-top:0;margin-bottom:16px;">Lịch sử biến động điểm</h3>

                @if ($soGiaoDich === null)
                    <p style="color:var(--sf-fg-muted);margin:0;">Sổ biến động điểm chưa được kích hoạt — chỉ hiển thị số dư hiện tại.</p>
                @elseif ($soGiaoDich->count() > 0)
                    <div style="overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;">
                            <thead>
                                <tr style="border-bottom:1px solid var(--sf-border);text-align:left;">
                                    <th style="padding:8px 0;">Thời gian</th>
                                    <th style="padding:8px 0;">Loại</th>
                                    <th style="padding:8px 0;text-align:right;">Điểm</th>
                                    <th style="padding:8px 0;text-align:right;">Số dư sau</th>
                                    <th style="padding:8px 0;">Mã hóa đơn</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($soGiaoDich as $gd)
                                    <tr style="border-bottom:1px solid var(--sf-border);">
                                        <td style="padding:8px 0;">{{ $gd->created_at?->format('H:i d/m/Y') }}</td>
                                        <td style="padding:8px 0;">{{ \App\Services\DiemService::nhanLoai($gd->loai) }}</td>
                                        <td style="padding:8px 0;text-align:right;{{ $gd->diem >= 0 ? ' color:#1a7f37;' : ' color:#cf222e;' }}">
                                            {{ $gd->diem >= 0 ? '+' : '' }}{{ $gd->diem }}
                                        </td>
                                        <td style="padding:8px 0;text-align:right;">{{ number_format($gd->so_du_sau) }}</td>
                                        <td style="padding:8px 0;">{{ $gd->donHang?->ma_hoadon ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top:16px;">{{ $soGiaoDich->links() }}</div>
                @else
                    <p style="color:var(--sf-fg-muted);margin:0;">Chưa có biến động điểm nào.</p>
                @endif
            </div>
        @endif
    </div>
@endsection
