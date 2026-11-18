@extends('layouts.master')

@section('title', 'Tổng quan')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Tổng quan</h1>
        <p class="page-subtitle">Tình hình hoạt động của cửa hàng</p>
    </div>
</div>

@if (! empty($canhBaoMatKhauMacDinh))
    <div class="alert alert-warning" role="alert">
        <div class="d-flex align-items-center gap-2">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <strong>Cảnh báo bảo mật: tài khoản còn dùng mật khẩu mặc định</strong>
        </div>
        <ul class="mb-1 mt-2">
            @foreach ($canhBaoMatKhauMacDinh as $email)
                <li>{{ $email }}</li>
            @endforeach
        </ul>
        <div>
            Đổi ngay bằng <code>php artisan nguoidung:doi-matkhau &lt;email&gt;</code> trước khi đưa hệ thống vào sử dụng thật.
        </div>
    </div>
@endif

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div class="text-muted small">
        Cập nhật lúc <span id="cap-nhat-luc">{{ $chiSo['cap_nhat_luc'] }}</span> (giờ Việt Nam) — tự làm mới mỗi 30 giây khi tab đang mở.
    </div>
    <div id="canh-bao-lam-moi" class="alert alert-warning py-1 px-2 mb-0 small d-none" role="alert">
        <i class="fa-solid fa-triangle-exclamation"></i>
        Không làm mới được số liệu — đang giữ số cũ. Kiểm tra kết nối mạng.
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-label">Số mặt hàng đang kinh doanh</div>
            <div class="stat-value" data-chi-so="so_mat_hang_dang_kinh_doanh">{{ number_format($chiSo['so_mat_hang_dang_kinh_doanh'], 0, ',', '.') }}</div>
            <small class="text-muted">Chủng loại sản phẩm đang bán</small>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-label">Tồn khả dụng (sản phẩm)</div>
            <div class="stat-value text-success" data-chi-so="ton_kha_dung">{{ number_format($chiSo['ton_kha_dung'] ?? $chiSo['tong_ton_tot'], 0, ',', '.') }}</div>
            <small class="text-muted">Sẵn sàng bán (đã trừ hàng giữ)</small>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-label">Đang giữ chờ thanh toán</div>
            <div class="stat-value text-warning" data-chi-so="dang_giu">{{ number_format($chiSo['dang_giu'] ?? 0, 0, ',', '.') }}</div>
            <small class="text-muted">Đơn QR/pickup chưa hoàn tất</small>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-label">Tổng hàng thực tế trong kho</div>
            <div class="stat-value text-primary" data-chi-so="tong_thuc_te">{{ number_format($chiSo['tong_thuc_te'] ?? $chiSo['tong_ton_tot'], 0, ',', '.') }}</div>
            <small class="text-muted">= Khả dụng + Đang giữ</small>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="stat-card">
            <div class="stat-label">Mặt hàng còn hàng</div>
            <div class="stat-value" data-chi-so="so_con_hang">{{ number_format($chiSo['so_con_hang'], 0, ',', '.') }}</div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="stat-card">
            <div class="stat-label">Mặt hàng hết hàng</div>
            <div class="stat-value" data-chi-so="so_het_hang">{{ number_format($chiSo['so_het_hang'], 0, ',', '.') }}</div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="stat-card">
            <div class="stat-label">Tổng hàng hỏng (sản phẩm)</div>
            <div class="stat-value" data-chi-so="tong_ton_hong">{{ number_format($chiSo['tong_ton_hong'], 0, ',', '.') }}</div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-6">
        <div class="stat-card">
            <div class="stat-label">Giá trị tồn theo giá nhập (đ)</div>
            <div class="stat-value" data-chi-so="gia_tri_ton">{{ number_format($chiSo['gia_tri_ton'], 0, ',', '.') }}</div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-6">
        <div class="stat-card">
            <div class="stat-label">Hóa đơn</div>
            <div class="stat-value" data-chi-so="so_hoa_don">{{ number_format($chiSo['so_hoa_don'], 0, ',', '.') }}</div>
        </div>
    </div>
    <div class="col-12">
        <div class="stat-card {{ ($chiSo['so_sp_chua_co_gia_nhap'] ?? 0) > 0 ? 'border-warning' : '' }}">
            <div class="stat-label">Đang bán nhưng chưa có giá nhập</div>
            <div class="stat-value {{ ($chiSo['so_sp_chua_co_gia_nhap'] ?? 0) > 0 ? 'text-warning' : '' }}" data-chi-so="so_sp_chua_co_gia_nhap">{{ number_format($chiSo['so_sp_chua_co_gia_nhap'] ?? 0, 0, ',', '.') }}</div>
            <small class="text-muted">
                @if (in_array(auth()->user()->role, ['admin', 'thukho'], true))
                    <a href="{{ route('sanpham.danhsach', ['loc' => 'gia_nhap_0']) }}">Xem sản phẩm chưa có giá nhập</a> — bán ra sẽ tính lợi nhuận = toàn bộ giá bán.
                @else
                    Sản phẩm bán ra sẽ tính lợi nhuận = toàn bộ giá bán — cần nhập giá vốn.
                @endif
            </small>
        </div>
    </div>
</div>

<p class="text-muted small">
    <strong>Chú thích chỉ số kho:</strong>
    <br>• <strong>Số mặt hàng đang kinh doanh</strong>: Số chủng loại sản phẩm đang bật kinh doanh.
    <br>• <strong>Tồn khả dụng</strong>: Tổng số lượng hàng sẵn sàng bán (đã trừ hàng giữ cho đơn QR/pickup chờ thanh toán).
    <br>• <strong>Đang giữ chờ thanh toán</strong>: Số lượng hàng tạm giữ cho các đơn chưa thanh toán (hàng vẫn còn trong kho).
    <br>• <strong>Tổng hàng thực tế trong kho</strong>: Tổng hàng vật lý trong kho (= Tồn khả dụng + Đang giữ). Hủy đơn sẽ hoàn về khả dụng.
</p>

@if ($sanPhamTonThap->isNotEmpty())
    <div class="card border-warning">
        <div class="card-header d-flex align-items-center gap-2 text-warning-emphasis">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span>Cảnh báo tồn kho thấp</span>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Sản phẩm</th>
                        <th class="text-end">Tồn kho</th>
                        <th class="text-end">Ngưỡng tối thiểu</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($sanPhamTonThap as $sanPham)
                    <tr>
                        <td>{{ $sanPham->ten_sanpham }}</td>
                        <td class="text-end">
                            <span class="badge {{ $sanPham->soluong === 0 ? 'text-bg-danger' : 'text-bg-warning' }}">{{ $sanPham->soluong }}</span>
                        </td>
                        <td class="text-end text-muted">{{ $sanPham->soluong_toithieu }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    const endpoint = @json(route('tongquan.solieu'));
    const spanCapNhat = document.getElementById('cap-nhat-luc');
    const canhBao = document.getElementById('canh-bao-lam-moi');
    const cacO = document.querySelectorAll('[data-chi-so]');
    if (!cacO.length) return;

    let dinhKy = null;

    async function lamMoi() {
        try {
            const response = await fetch(endpoint, { headers: { 'Accept': 'application/json' } });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const data = await response.json();
            cacO.forEach(function (el) {
                const key = el.dataset.chiSo;
                if (key in data) el.textContent = Number(data[key]).toLocaleString('vi-VN');
            });
            if (spanCapNhat && data.cap_nhat_luc) spanCapNhat.textContent = data.cap_nhat_luc;
            if (canhBao) canhBao.classList.add('d-none');
        } catch (e) {
            // Giữ số cũ, hiện cảnh báo lỗi mạng rõ ràng thay vì im lặng
            if (canhBao) {
                canhBao.innerHTML = `<i class="fa-solid fa-triangle-exclamation"></i> Lỗi kết nối máy chủ (${e.message || 'Lỗi mạng'}). Số liệu có thể chưa cập nhật.`;
                canhBao.classList.remove('d-none');
            }
        }
    }

    function batDau() {
        if (dinhKy !== null) return;
        lamMoi();
        dinhKy = setInterval(lamMoi, 30000);
    }

    function tamDung() {
        if (dinhKy === null) return;
        clearInterval(dinhKy);
        dinhKy = null;
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') batDau();
        else tamDung();
    });

    if (document.visibilityState === 'visible') batDau();
})();
</script>
@endpush