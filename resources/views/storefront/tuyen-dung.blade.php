@extends('storefront.layouts.app')

@section('title', 'Tuyển dụng — PhoneShop')

@section('content')
    @php
        $cfg = config('trang_tinh.tuyen_dung');
        $inputStyle = 'width:100%;height:40px;border:1px solid var(--sf-border-strong);border-radius:8px;padding:0 10px;height:44px;';
    @endphp
    <div class="sf-container" style="padding-top:20px;max-width:860px;">
        <nav aria-label="breadcrumb" style="font-size:var(--sf-text-sm);color:var(--sf-fg-muted);margin-bottom:12px;">
            <a href="{{ route('storefront.home') }}" style="color:var(--sf-primary);text-decoration:none;">Trang chủ</a>
            <span style="margin:0 6px;">/</span>
            <span>Tuyển dụng</span>
        </nav>

        <div class="sf-section-head">
            <h1 class="sf-section-title">Tuyển dụng</h1>
        </div>

        <p style="color:var(--sf-fg-muted);font-size:var(--sf-text-md);margin:0 0 20px;">{{ $cfg['mo_ta'] ?? '' }}</p>

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;margin-bottom:24px;">
            @foreach ($viTriOptions as $slug => $ten)
                <div style="background:var(--sf-surface);border:1px solid var(--sf-border);border-radius:12px;padding:20px;display:flex;flex-direction:column;gap:12px;">
                    <div style="font-weight:700;font-size:var(--sf-text-md);color:var(--sf-fg);">
                        <i class="fa-solid fa-briefcase" style="color:var(--sf-primary);margin-right:8px;"></i>{{ $ten }}
                    </div>
                    <a href="{{ route('lien-he.form', ['loai' => 'ung_tuyen', 'vitri' => $slug]) }}"
                       class="sf-icon-btn is-primary" style="height:40px;padding:0 16px;align-self:flex-start;">
                        <i class="fa-solid fa-paper-plane"></i> Ứng tuyển
                    </a>
                </div>
            @endforeach
        </div>

        @if (! empty($cfg['yeu_cau_chung']))
            <div style="background:var(--sf-surface);border:1px solid var(--sf-border);border-radius:12px;padding:20px;">
                <h2 style="font-size:var(--sf-text-lg);margin:0 0 12px;">Yêu cầu chung</h2>
                <ul style="margin:0;padding-left:20px;color:var(--sf-fg-muted);">
                    @foreach ($cfg['yeu_cau_chung'] as $y)
                        <li style="margin-bottom:6px;">{{ $y }}</li>
                    @endforeach
                </ul>
                <p style="color:var(--sf-fg-muted);font-size:var(--sf-text-sm);margin:12px 0 0;">
                    Hồ sơ của bạn chỉ được sử dụng cho mục đích tuyển dụng. Liên hệ trực tiếp qua form ứng tuyển —
                    không cần đính kèm CV trong giai đoạn này, vui lòng ghi kinh nghiệm vào nội dung.
                </p>
            </div>
        @endif
    </div>
@endsection
