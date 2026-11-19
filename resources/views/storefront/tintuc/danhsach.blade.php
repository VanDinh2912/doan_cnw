@extends('storefront.layouts.app')

@section('title', ($chuDeHienTai ? $chuDeHienTai->ten_chude . ' — ' : '') . 'Tin tức — ' . config('storefront.ten_cua_hang'))

@section('content')
    <div class="sf-container" style="padding-top:20px;">
        <nav aria-label="breadcrumb" style="font-size:var(--sf-text-sm);color:var(--sf-fg-muted);margin-bottom:12px;">
            <a href="{{ route('storefront.home') }}" style="color:var(--sf-primary);text-decoration:none;">Trang chủ</a>
            <span style="margin:0 6px;">/</span>
            <a href="{{ route('tintuc.danhsach') }}" style="color:var(--sf-primary);text-decoration:none;">Tin tức</a>
            @if ($chuDeHienTai)
                <span style="margin:0 6px;">/</span>
                <span>{{ $chuDeHienTai->ten_chude }}</span>
            @endif
        </nav>

        <div class="sf-section-head">
            <h1 class="sf-section-title">{{ $chuDeHienTai ? $chuDeHienTai->ten_chude : 'Tin tức' }}</h1>
            <span style="font-size:var(--sf-text-sm);color:var(--sf-fg-muted);">{{ $baiViets->total() }} bài viết</span>
        </div>

        @if ($chuDeHienTai && $chuDeHienTai->mo_ta)
            <p style="color:var(--sf-fg-muted);font-size:var(--sf-text-sm);margin:0 0 16px;">{{ $chuDeHienTai->mo_ta }}</p>
        @endif

        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px;">
            <a href="{{ route('tintuc.danhsach') }}"
               style="padding:6px 14px;border-radius:999px;font-size:var(--sf-text-sm);text-decoration:none;border:1px solid {{ $chuDeHienTai ? 'var(--sf-border-strong)' : 'var(--sf-primary)' }};color:{{ $chuDeHienTai ? 'var(--sf-fg-muted)' : 'var(--sf-primary)' }};">
                Tất cả
            </a>
            @foreach ($chuDes as $chuDe)
                <a href="{{ route('tintuc.chude', $chuDe->slug) }}"
                   style="padding:6px 14px;border-radius:999px;font-size:var(--sf-text-sm);text-decoration:none;border:1px solid {{ $chuDeHienTai && $chuDeHienTai->id === $chuDe->id ? 'var(--sf-primary)' : 'var(--sf-border-strong)' }};color:{{ $chuDeHienTai && $chuDeHienTai->id === $chuDe->id ? 'var(--sf-primary)' : 'var(--sf-fg-muted)' }};">
                    {{ $chuDe->ten_chude }}
                </a>
            @endforeach
        </div>

        @if ($baiViets->isEmpty())
            <div style="text-align:center;padding:60px 16px;color:var(--sf-fg-muted);">
                <i class="fa-solid fa-newspaper" style="font-size:56px;opacity:0.4;display:block;margin-bottom:12px;"></i>
                <p style="margin:0;font-size:var(--sf-text-md);">Chưa có bài viết trong mục này.</p>
            </div>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
                @foreach ($baiViets as $baiViet)
                    <article style="background:var(--sf-surface);border:1px solid var(--sf-border);border-radius:12px;overflow:hidden;display:flex;flex-direction:column;">
                        <a href="{{ route('tintuc.chitiet', [$baiViet->chuDe->slug, $baiViet->slug]) }}" style="display:block;">
                            @if ($baiViet->anh_url)
                                <img src="{{ $baiViet->anh_url }}" alt="{{ $baiViet->tieu_de }}" loading="lazy"
                                     style="width:100%;height:160px;object-fit:cover;display:block;">
                            @else
                                <div style="height:160px;display:flex;align-items:center;justify-content:center;background:var(--sf-bg-muted, #f3f4f6);color:var(--sf-fg-subtle);">
                                    <i class="fa-solid fa-newspaper" style="font-size:40px;opacity:0.5;"></i>
                                </div>
                            @endif
                        </a>
                        <div style="padding:14px 16px 16px;display:flex;flex-direction:column;gap:8px;flex:1;">
                            <span style="font-size:var(--sf-text-xs);color:var(--sf-primary);text-transform:uppercase;letter-spacing:0.04em;">{{ $baiViet->chuDe->ten_chude }}</span>
                            <h2 style="margin:0;font-size:var(--sf-text-md);line-height:1.4;">
                                <a href="{{ route('tintuc.chitiet', [$baiViet->chuDe->slug, $baiViet->slug]) }}" style="color:var(--sf-fg);text-decoration:none;">{{ $baiViet->tieu_de }}</a>
                            </h2>
                            @if ($baiViet->tom_tat)
                                <p style="margin:0;font-size:var(--sf-text-sm);color:var(--sf-fg-muted);display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;">{{ $baiViet->tom_tat }}</p>
                            @endif
                            <div style="margin-top:auto;display:flex;gap:12px;font-size:var(--sf-text-xs);color:var(--sf-fg-subtle);">
                                <span><i class="fa-regular fa-calendar"></i> {{ $baiViet->created_at->format('d/m/Y') }}</span>
                                <span><i class="fa-regular fa-eye"></i> {{ number_format($baiViet->luot_xem) }}</span>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div style="margin-top:24px;">
                {{ $baiViets->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
@endsection