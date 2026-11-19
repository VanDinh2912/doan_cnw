@extends('storefront.layouts.app')

@section('title', 'Liên hệ — ' . config('storefront.ten_cua_hang'))

@php
    $nhanLoai = ['lien_he' => 'Liên hệ', 'gop_y' => 'Góp ý', 'khieu_nai' => 'Khiếu nại', 'ung_tuyen' => 'Ứng tuyển'];
    $inputStyle = 'width:100%;height:40px;border:1px solid var(--sf-border-strong);border-radius:8px;padding:0 10px;';
    $loiStyle = 'color:var(--sf-stock-out);font-size:var(--sf-text-sm);';
@endphp

@section('content')
    <div class="sf-container" style="padding-top:20px;max-width:720px;">
        <nav aria-label="breadcrumb" style="font-size:var(--sf-text-sm);color:var(--sf-fg-muted);margin-bottom:12px;">
            <a href="{{ route('storefront.home') }}" style="color:var(--sf-primary);text-decoration:none;">Trang chủ</a>
            <span style="margin:0 6px;">/</span>
            <span>Liên hệ</span>
        </nav>

        <div class="sf-section-head">
            <h1 class="sf-section-title">Liên hệ / Góp ý / Khiếu nại</h1>
        </div>

        <p style="color:var(--sf-fg-muted);font-size:var(--sf-text-sm);margin:0 0 16px;">
            Hotline: <strong>{{ config('storefront.hotline') }}</strong> (8:00 – 22:00 hằng ngày).
            Bạn cũng có thể gửi tin qua form bên dưới — chúng tôi sẽ phản hồi sớm nhất có thể.
        </p>

        @if ($errors->any())
            <div class="sf-alert is-error" role="alert" style="margin-bottom:16px;">
                Vui lòng kiểm tra lại thông tin đã nhập.
            </div>
        @endif

        <form method="POST" action="{{ route('lien-he.gui') }}"
              style="background:var(--sf-surface);border:1px solid var(--sf-border);border-radius:12px;padding:20px;">
            @csrf

            {{-- Honeypot: tên trung tính, ẩn với người thật; bot điền vào sẽ bị từ chối. --}}
            <div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden;">
                <label for="truong_phu">Trường phụ</label>
                <input id="truong_phu" name="truong_phu" type="text" value="" tabindex="-1" autocomplete="off">
            </div>

            <div style="margin-bottom:12px;">
                <label for="loai" style="display:block;margin-bottom:4px;">Loại liên hệ <span style="color:var(--sf-stock-out);">*</span></label>
                <select id="loai" name="loai" required style="{{ $inputStyle }}height:44px;">
                    @foreach ($loaiOptions as $giaTri)
                        <option value="{{ $giaTri }}" {{ old('loai', $loaiMacDinh) === $giaTri ? 'selected' : '' }}>
                            {{ $nhanLoai[$giaTri] ?? $giaTri }}
                        </option>
                    @endforeach
                </select>
                @error('loai')<div style="{{ $loiStyle }}">{{ $message }}</div>@enderror
            </div>

            <div id="khung_vi_tri" style="margin-bottom:12px;display:{{ old('loai', $loaiMacDinh) === 'ung_tuyen' ? 'block' : 'none' }};">
                <label for="vi_tri" style="display:block;margin-bottom:4px;">Vị trí ứng tuyển</label>
                <select id="vi_tri" name="vi_tri" style="{{ $inputStyle }}height:44px;">
                    <option value="">— Chọn vị trí —</option>
                    @foreach ($viTriOptions as $slug => $ten)
                        <option value="{{ $slug }}" {{ old('vi_tri', $viTriMacDinh) === $slug ? 'selected' : '' }}>{{ $ten }}</option>
                    @endforeach
                </select>
                <div style="color:var(--sf-fg-muted);font-size:var(--sf-text-xs);margin-top:4px;">Xem danh sách vị trí đang tuyển tại <a href="{{ route('tuyen-dung') }}" style="color:var(--sf-primary);">trang Tuyển dụng</a>.</div>
                @error('vi_tri')<div style="{{ $loiStyle }}">{{ $message }}</div>@enderror
            </div>

            <div style="margin-bottom:12px;">
                <label for="ho_ten" style="display:block;margin-bottom:4px;">Họ tên <span style="color:var(--sf-stock-out);">*</span></label>
                <input id="ho_ten" name="ho_ten" type="text" value="{{ old('ho_ten', $prefill['ho_ten']) }}" required maxlength="100" style="{{ $inputStyle }}">
                @error('ho_ten')<div style="{{ $loiStyle }}">{{ $message }}</div>@enderror
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="sf-lien-he-grid">
                <div style="margin-bottom:12px;">
                    <label for="so_dien_thoai" style="display:block;margin-bottom:4px;">Số điện thoại</label>
                    <input id="so_dien_thoai" name="so_dien_thoai" type="tel" value="{{ old('so_dien_thoai', $prefill['so_dien_thoai']) }}" maxlength="30" placeholder="Ví dụ: 09xxxxxxxx"
                           style="{{ $inputStyle }}">
                    @error('so_dien_thoai')<div style="{{ $loiStyle }}">{{ $message }}</div>@enderror
                </div>
                <div style="margin-bottom:12px;">
                    <label for="email" style="display:block;margin-bottom:4px;">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $prefill['email']) }}" maxlength="150"
                           style="{{ $inputStyle }}">
                    @error('email')<div style="{{ $loiStyle }}">{{ $message }}</div>@enderror
                </div>
            </div>
            @error('lien_he')<div style="{{ $loiStyle }}margin-bottom:12px;">{{ $message }}</div>@enderror
            <p style="color:var(--sf-fg-muted);font-size:var(--sf-text-xs);margin:-4px 0 12px;">Nhập ít nhất một trong hai: số điện thoại hoặc email để chúng tôi phản hồi.</p>

            <div style="margin-bottom:12px;">
                <label for="chu_de" style="display:block;margin-bottom:4px;">Chủ đề <span style="color:var(--sf-stock-out);">*</span></label>
                <input id="chu_de" name="chu_de" type="text" value="{{ old('chu_de') }}" required maxlength="150"
                       placeholder="Tóm tắt vấn đề của bạn"
                       style="{{ $inputStyle }}">
                @error('chu_de')<div style="{{ $loiStyle }}">{{ $message }}</div>@enderror
            </div>

            <div style="margin-bottom:16px;">
                <label for="noi_dung" style="display:block;margin-bottom:4px;">Nội dung <span style="color:var(--sf-stock-out);">*</span></label>
                <textarea id="noi_dung" name="noi_dung" rows="6" required maxlength="2000"
                          placeholder="Mô tả chi tiết..."
                          style="width:100%;border:1px solid var(--sf-border-strong);border-radius:8px;padding:8px 10px;">{{ old('noi_dung') }}</textarea>
                @error('noi_dung')<div style="{{ $loiStyle }}">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="sf-icon-btn is-primary" style="height:44px;padding:0 24px;">
                <i class="fa-solid fa-paper-plane"></i> Gửi
            </button>
        </form>
    </div>

    <style>
        @media (max-width: 640px) {
            .sf-lien-he-grid { grid-template-columns: 1fr !important; }
        }
    </style>

    <script>
        (function () {
            var loai = document.getElementById('loai');
            var khung = document.getElementById('khung_vi_tri');
            function capNhat() {
                khung.style.display = loai.value === 'ung_tuyen' ? 'block' : 'none';
            }
            loai.addEventListener('change', capNhat);
            capNhat();
        })();
    </script>
@endsection
