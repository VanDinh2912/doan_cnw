@extends('storefront.layouts.app')

@section('title', ($page['tieu_de'] ?? 'Trang') . ' — PhoneShop')

@section('content')
    <div class="sf-container" style="padding-top:20px;max-width:760px;">
        <nav aria-label="breadcrumb" style="font-size:var(--sf-text-sm);color:var(--sf-fg-muted);margin-bottom:12px;">
            <a href="{{ route('storefront.home') }}" style="color:var(--sf-primary);text-decoration:none;">Trang chủ</a>
            <span style="margin:0 6px;">/</span>
            <span>{{ $page['tieu_de'] ?? '' }}</span>
        </nav>

        <div class="sf-section-head">
            <h1 class="sf-section-title">{{ $page['tieu_de'] ?? '' }}</h1>
        </div>

        @if (! empty($page['mo_ta']))
            <p style="color:var(--sf-fg-muted);font-size:var(--sf-text-md);margin:0 0 20px;">{{ $page['mo_ta'] }}</p>
        @endif

        @foreach (($page['noi_dung'] ?? []) as $muc)
            <div style="background:var(--sf-surface);border:1px solid var(--sf-border);border-radius:12px;padding:20px;margin-bottom:16px;">
                @if (! empty($muc['tieu_de']))
                    <h2 style="font-size:var(--sf-text-lg);margin:0 0 12px;">{{ $muc['tieu_de'] }}</h2>
                @endif
                @foreach (($muc['cac_doan'] ?? $muc['noi_dung'] ?? []) as $doan)
                    @if (is_string($doan))
                        <p style="color:var(--sf-fg);font-size:var(--sf-text-md);line-height:1.6;margin:0 0 10px;white-space:pre-line;">{{ $doan }}</p>
                    @else
                        <p style="color:var(--sf-fg);font-size:var(--sf-text-md);line-height:1.6;margin:0 0 10px;white-space:pre-line;">{{ $doan['text'] ?? '' }}</p>
                    @endif
                @endforeach
            </div>
        @endforeach

        <p style="color:var(--sf-fg-muted);font-size:var(--sf-text-sm);text-align:center;margin-top:24px;">
            Cần hỗ trợ thêm? Gọi hotline <strong>{{ config('storefront.hotline') }}</strong> (8:00 – 22:00)
            hoặc <a href="{{ route('lien-he.form') }}" style="color:var(--sf-primary);">gửi liên hệ</a>.
        </p>
    </div>
@endsection
