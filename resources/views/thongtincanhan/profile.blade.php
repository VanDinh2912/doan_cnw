@extends('storefront.layouts.app')

@section('title', 'Thông tin cá nhân — ' . config('storefront.ten_cua_hang'))

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
                        {{ $daXacThuc ? auth()->user()->name : \App\Support\ChePII::hoTen($khachHang?->ho_ten ?? auth()->user()->name) }}
                    </h2>
                    <p style="margin:0;font-size:14px;opacity:0.9;">
                        @if ($daXacThuc)
                            {{ $khachHang?->so_dienthoai ?? 'Chưa cập nhật' }}
                        @else
                            {{ $che['so_dienthoai'] }}
                        @endif
                    </p>
                </div>
            </div>
            @if ($daXacThuc)
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
            @endif
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
            @if (session('error'))
                <div style="background:var(--sf-bg);border:1px solid var(--sf-stock-out);border-radius:8px;padding:12px;margin-bottom:16px;color:var(--sf-stock-out);">
                    {{ session('error') }}
                </div>
            @endif
            @error('mat_khau')<div style="color:var(--sf-stock-out);font-size:var(--sf-text-sm);margin-bottom:12px;">{{ $message }}</div>@enderror

            @if (! $daXacThuc)
                {{-- ============ CHẾ ĐỘ CHE: chỉ hiển thị dữ liệu đã che ============ --}}
                <h3 style="margin-top:0;margin-bottom:16px;">Thông tin của bạn</h3>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:20px;">
                    <div style="border:1px solid var(--sf-border);border-radius:8px;padding:12px;">
                        <div style="font-size:12px;color:var(--sf-text-muted,#6b7280);margin-bottom:4px;">Họ tên</div>
                        <div>{{ $che['ho_ten'] }}</div>
                    </div>
                    <div style="border:1px solid var(--sf-border);border-radius:8px;padding:12px;">
                        <div style="font-size:12px;color:var(--sf-text-muted,#6b7280);margin-bottom:4px;">Email</div>
                        <div>{{ $che['email'] }}</div>
                    </div>
                    <div style="border:1px solid var(--sf-border);border-radius:8px;padding:12px;">
                        <div style="font-size:12px;color:var(--sf-text-muted,#6b7280);margin-bottom:4px;">Số điện thoại</div>
                        <div>{{ $che['so_dienthoai'] }}</div>
                    </div>
                    <div style="border:1px solid var(--sf-border);border-radius:8px;padding:12px;">
                        <div style="font-size:12px;color:var(--sf-text-muted,#6b7280);margin-bottom:4px;">Địa chỉ</div>
                        <div>{{ $che['dia_chi'] }}</div>
                    </div>
                </div>

                <div style="border-top:1px solid var(--sf-border);padding-top:16px;">
                    <h3 style="margin-top:0;margin-bottom:8px;">Xác thực để xem và cập nhật</h3>
                    <p style="color:var(--sf-text-muted,#6b7280);font-size:14px;margin-bottom:12px;">
                        Vì lý do bảo mật, thông tin cá nhân chỉ hiển thị đầy đủ trong
                        {{ \App\Support\HoSoXacThuc::cuaSoPhut() }} phút sau khi bạn xác thực lại.
                    </p>

                    <form method="POST" action="{{ route('profile.xacthuc') }}">
                        @csrf

                        <div style="margin-bottom:12px;max-width:360px;">
                            <label for="mat_khau" style="display:block;margin-bottom:4px;">Mật khẩu hiện tại</label>
                            <input id="mat_khau" name="mat_khau" type="password" autocomplete="current-password" required
                                   style="width:100%;height:40px;border:1px solid var(--sf-border-strong);border-radius:8px;padding:0 10px;">
                        </div>

                        @if ($coSoDienThoai)
                            <div style="margin-bottom:12px;max-width:360px;">
                                <label for="so_dien_thoai" style="display:block;margin-bottom:4px;">Số điện thoại hiện tại trong hồ sơ</label>
                                <input id="so_dien_thoai" name="so_dien_thoai" type="tel" inputmode="numeric" autocomplete="tel" maxlength="20" required
                                       style="width:100%;height:40px;border:1px solid var(--sf-border-strong);border-radius:8px;padding:0 10px;">
                                <small style="color:var(--sf-text-muted,#6b7280);font-size:12px;">Nhập đúng số điện thoại đang lưu trong hồ sơ (10 số, có thể nhập +84).</small>
                            </div>
                        @else
                            <p style="color:var(--sf-text-muted,#6b7280);font-size:13px;max-width:480px;">
                                Hồ sơ chưa có số điện thoại — chỉ cần mật khẩu để xác thực;
                                bạn sẽ được nhắc bổ sung SĐT khi cập nhật.
                            </p>
                        @endif

                        <button type="submit" class="sf-icon-btn is-primary" style="height:44px;padding:0 24px;">
                            <i class="fa-solid fa-lock-open"></i> Xác thực
                        </button>
                    </form>
                </div>
            @else
                {{-- ============ CHẾ ĐỘ ĐÃ XÁC THỰC: dữ liệu đầy đủ + form ============ --}}
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
                        <label for="email" style="display:block;margin-bottom:4px;">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required maxlength="150"
                               style="width:100%;height:40px;border:1px solid var(--sf-border-strong);border-radius:8px;padding:0 10px;">
                        @error('email')<div style="color:var(--sf-stock-out);font-size:var(--sf-text-sm);">{{ $message }}</div>@enderror
                    </div>

                    <div style="margin-bottom:12px;">
                        <label for="so_dienthoai" style="display:block;margin-bottom:4px;">Số điện thoại</label>
                        <input id="so_dienthoai" name="so_dienthoai" type="tel" inputmode="numeric" autocomplete="tel" maxlength="20"
                               pattern="[0-9+\-.\s\(\)]{10,20}"
                               value="{{ old('so_dienthoai', $khachHang?->so_dienthoai) }}" required
                               style="width:100%;height:40px;border:1px solid var(--sf-border-strong);border-radius:8px;padding:0 10px;">
                        @error('so_dienthoai')<div style="color:var(--sf-stock-out);font-size:var(--sf-text-sm);">{{ $message }}</div>@enderror
                        <small style="color:var(--sf-text-muted,#6b7280);font-size:12px;">10 chữ số, bắt đầu bằng 0. Có thể nhập +84, khoảng trắng, dấu chấm/gạch ngang.</small>
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
            @endif
        </div>
    </div>

    <script>
        const sdtInput = document.getElementById('so_dienthoai');
        if (sdtInput) {
            sdtInput.addEventListener('input', () => {
                sdtInput.value = sdtInput.value.replace(/[^0-9+\-.\s()]/g, '').slice(0, 20);
            });
        }
        const sdtXacThuc = document.getElementById('so_dien_thoai');
        if (sdtXacThuc) {
            sdtXacThuc.addEventListener('input', () => {
                sdtXacThuc.value = sdtXacThuc.value.replace(/[^0-9+\-.\s()]/g, '').slice(0, 20);
            });
        }
    </script>
@endsection
