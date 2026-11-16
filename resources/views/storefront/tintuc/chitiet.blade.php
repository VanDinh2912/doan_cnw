@extends('storefront.layouts.app')

@section('title', $baiViet->tieu_de . ' — PhoneShop')

@section('content')
    <div class="sf-container" style="padding-top:20px;max-width:860px;">
        <nav aria-label="breadcrumb" style="font-size:var(--sf-text-sm);color:var(--sf-fg-muted);margin-bottom:12px;">
            <a href="{{ route('storefront.home') }}" style="color:var(--sf-primary);text-decoration:none;">Trang chủ</a>
            <span style="margin:0 6px;">/</span>
            <a href="{{ route('tintuc.danhsach') }}" style="color:var(--sf-primary);text-decoration:none;">Tin tức</a>
            <span style="margin:0 6px;">/</span>
            <a href="{{ route('tintuc.chude', $chuDe->slug) }}" style="color:var(--sf-primary);text-decoration:none;">{{ $chuDe->ten_chude }}</a>
        </nav>

        <article>
            <h1 style="font-size:var(--sf-text-2xl, 28px);line-height:1.3;margin:0 0 10px;">{{ $baiViet->tieu_de }}</h1>
            <div style="display:flex;flex-wrap:wrap;gap:14px;font-size:var(--sf-text-sm);color:var(--sf-fg-muted);margin-bottom:18px;">
                <span><i class="fa-regular fa-calendar"></i> {{ $baiViet->created_at->format('d/m/Y H:i') }}</span>
                <span><i class="fa-regular fa-eye"></i> {{ number_format($baiViet->luot_xem) }} lượt xem</span>
                @if ($baiViet->tacGia)
                    <span><i class="fa-regular fa-user"></i> {{ $baiViet->tacGia->name }}</span>
                @endif
            </div>

            @if ($baiViet->anh_url)
                <img src="{{ $baiViet->anh_url }}" alt="{{ $baiViet->tieu_de }}"
                     style="width:100%;max-height:420px;object-fit:cover;border-radius:12px;margin-bottom:18px;">
            @endif

            @if ($baiViet->tom_tat)
                <p style="font-size:var(--sf-text-md);color:var(--sf-fg-muted);font-style:italic;border-left:3px solid var(--sf-primary);padding-left:12px;margin:0 0 18px;">{{ $baiViet->tom_tat }}</p>
            @endif

            <div class="sf-news-content" style="line-height:1.75;font-size:var(--sf-text-md);">
                {{-- Nội dung đã được lọc HTMLPurifier trước khi lưu (App\Support\LocHtml). --}}
                {!! $baiViet->noi_dung !!}
            </div>
        </article>

        @if ($lienQuan->isNotEmpty())
            <section style="margin-top:32px;">
                <h2 class="sf-section-title" style="font-size:var(--sf-text-lg);margin-bottom:12px;">Bài viết liên quan</h2>
                <ul style="list-style:none;padding:0;margin:0;display:grid;gap:8px;">
                    @foreach ($lienQuan as $baiLienQuan)
                        <li>
                            <a href="{{ route('tintuc.chitiet', [$chuDe->slug, $baiLienQuan->slug]) }}" style="color:var(--sf-primary);text-decoration:none;">
                                <i class="fa-solid fa-angle-right"></i> {{ $baiLienQuan->tieu_de }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section style="margin-top:36px;" id="binh-luan">
            <h2 class="sf-section-title" style="font-size:var(--sf-text-lg);margin-bottom:14px;">
                Bình luận ({{ $binhLuans->count() }})
            </h2>

            @auth
                <form method="POST" action="{{ route('tintuc.binhluan', [$chuDe->slug, $baiViet->slug]) }}" style="margin-bottom:22px;">
                    @csrf
                    <label for="noi_dung" class="visually-hidden">Nội dung bình luận</label>
                    <textarea id="noi_dung" name="noi_dung" rows="3" maxlength="1000" required
                              placeholder="Viết bình luận của bạn (tối đa 1000 ký tự)..."
                              style="width:100%;padding:10px 12px;border:1px solid var(--sf-border-strong);border-radius:8px;font-size:var(--sf-text-sm);background:var(--sf-surface);">{{ old('noi_dung') }}</textarea>
                    @error('noi_dung')
                        <div style="color:var(--sf-danger, #dc2626);font-size:var(--sf-text-sm);margin-top:6px;">{{ $message }}</div>
                    @enderror
                    <div style="margin-top:8px;display:flex;align-items:center;gap:12px;">
                        <button type="submit" class="sf-icon-btn is-primary">
                            <i class="fa-solid fa-paper-plane"></i> Gửi bình luận
                        </button>
                        <span style="font-size:var(--sf-text-xs);color:var(--sf-fg-subtle);">Bình luận sẽ hiển thị sau khi được kiểm duyệt.</span>
                    </div>
                </form>
            @else
                <p style="font-size:var(--sf-text-sm);color:var(--sf-fg-muted);background:var(--sf-surface);border:1px solid var(--sf-border);border-radius:8px;padding:12px 14px;margin-bottom:22px;">
                    <a href="{{ route('auth.dangnhap') }}" style="color:var(--sf-primary);">Đăng nhập</a> để gửi bình luận.
                </p>
            @endauth

            @forelse ($binhLuans as $binhLuan)
                <div style="background:var(--sf-surface);border:1px solid var(--sf-border);border-radius:10px;padding:12px 14px;margin-bottom:10px;">
                    <div style="display:flex;gap:10px;align-items:center;font-size:var(--sf-text-sm);margin-bottom:6px;">
                        <strong>{{ $binhLuan->nguoiDung->name ?? 'Người dùng' }}</strong>
                        <span style="color:var(--sf-fg-subtle);font-size:var(--sf-text-xs);">{{ $binhLuan->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                    <p style="margin:0;font-size:var(--sf-text-sm);white-space:pre-line;">{{ $binhLuan->noi_dung }}</p>
                </div>
            @empty
                <p style="color:var(--sf-fg-muted);font-size:var(--sf-text-sm);">Chưa có bình luận nào. Hãy là người đầu tiên!</p>
            @endforelse
        </section>
    </div>
@endsection

@push('styles')
<style>
    .sf-news-content img { max-width: 100%; height: auto; border-radius: 8px; }
    .sf-news-content table { width: 100%; border-collapse: collapse; }
    .sf-news-content th, .sf-news-content td { border: 1px solid var(--sf-border-strong); padding: 6px 10px; }
    .sf-news-content blockquote { border-left: 3px solid var(--sf-border-strong); margin: 12px 0; padding: 4px 14px; color: var(--sf-fg-muted); }
    .sf-news-content pre { background: var(--sf-surface); border: 1px solid var(--sf-border); border-radius: 8px; padding: 12px; overflow-x: auto; }
</style>
@endpush