@extends('layouts.master')

@section('title', 'Xác nhận thủ công — Đối soát')

@section('content')
<h1 class="h3 mb-1">Xác nhận thanh toán thủ công</h1>
<p class="text-muted mb-3">
    Các lần thu ngân xác nhận đã nhận tiền <strong>không qua webhook</strong> (bấm nút xác nhận tay).
    Dùng màn hình này để đối chiếu với sao kê ngân hàng — mặc định hiển thị <strong>chưa đối chiếu</strong>.
</p>

<div class="d-flex gap-2 mb-3">
    <a class="btn btn-sm {{ $trangThai === 'chua' ? 'btn-primary' : 'btn-outline-secondary' }}"
       href="{{ route('doisoat.thucu', array_merge(request()->except('trang_thai_soat', 'page'), ['trang_thai_soat' => 'chua'])) }}">Chưa đối chiếu</a>
    <a class="btn btn-sm {{ $trangThai === 'daxong' ? 'btn-primary' : 'btn-outline-secondary' }}"
       href="{{ route('doisoat.thucu', array_merge(request()->except('trang_thai_soat', 'page'), ['trang_thai_soat' => 'daxong'])) }}">Đã đối chiếu</a>
    <a class="btn btn-sm {{ $trangThai === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}"
       href="{{ route('doisoat.thucu', array_merge(request()->except('trang_thai_soat', 'page'), ['trang_thai_soat' => 'all'])) }}">Tất cả</a>
</div>

<form class="row g-2 mb-3" method="GET">
    <input type="hidden" name="trang_thai_soat" value="{{ $trangThai }}">
    <div class="col-md-3">
        <input class="form-control" type="date" name="tu_ngay" value="{{ $tuNgay }}" placeholder="Từ ngày">
    </div>
    <div class="col-md-3">
        <input class="form-control" type="date" name="den_ngay" value="{{ $denNgay }}" placeholder="Đến ngày">
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-secondary">Lọc</button>
    </div>
</form>

<div class="alert alert-info py-2">
    <i class="fa fa-circle-info me-1"></i>
    {{ $soDongNgay }} mục — tổng <strong>{{ number_format($tongNgay, 0, ',', '.') }} đ</strong> (theo bộ lọc hiện tại).
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead>
                <tr>
                    <th>Xác nhận lúc</th>
                    <th>Hóa đơn</th>
                    <th>Số tiền</th>
                    <th>Người xác nhận</th>
                    <th>Admin duyệt mã</th>
                    <th>Bằng chứng</th>
                    <th>Trạng thái soát</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($ds as $xn)
                    <tr>
                        <td>{{ $xn->xac_nhan_luc?->format('d/m/Y H:i') }}</td>
                        <td>
                            <code>{{ $xn->donHang?->ma_hoadon ?? '—' }}</code>
                            @if ($xn->donHang?->kenh === 'online')
                                <span class="badge text-bg-secondary">online</span>
                            @endif
                        </td>
                        <td>{{ number_format($xn->so_tien, 0, ',', '.') }} đ</td>
                        <td>{{ $xn->nguoiXacNhan?->name ?? '—' }}</td>
                        <td>{{ $xn->adminDuyet?->name ?? '—' }}</td>
                        <td><span class="text-break">{{ $xn->bang_chung }}</span></td>
                        <td>
                            @if ($xn->soat_luc)
                                <span class="badge text-bg-success">Đã soát</span>
                                <div class="small text-muted">
                                    {{ $xn->soatBoi?->name ?? '—' }} · {{ $xn->soat_luc->format('d/m H:i') }}
                                    @if ($xn->ghi_chu_soat) — {{ $xn->ghi_chu_soat }} @endif
                                </div>
                            @else
                                <span class="badge text-bg-warning text-dark">Chưa soát</span>
                            @endif
                        </td>
                        <td class="text-nowrap">
                            @unless ($xn->soat_luc)
                                <button class="btn btn-sm btn-outline-success js-soat-xong"
                                        data-id="{{ $xn->id }}" data-ma="{{ $xn->donHang?->ma_hoadon }}">Đã đối chiếu sao kê</button>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td class="text-center" colspan="8">Không có xác nhận thủ công nào.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $ds->links() }}</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var token = document.querySelector('meta[name="csrf-token"]').content;
    document.querySelectorAll('.js-soat-xong').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.dataset.id;
            var ma = this.dataset.ma || '';
            var ghiChu = prompt('Ghi chú đối chiếu (để trống nếu không có):') || '';
            if (!confirm('Đánh dấu hóa đơn ' + ma + ' đã đối chiếu sao kê?')) return;
            fetch('/doisoat/thu-cong/' + id + '/soat', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ ghi_chu_soat: ghiChu })
            })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success || d.message) { location.reload(); }
                else { alert(d.error || 'Lỗi không xác định'); }
            })
            .catch(function() { alert('Lỗi kết nối'); });
        });
    });
});
</script>
@endpush
