@extends('layouts.master')

@section('title', 'Đối soát thanh toán')

@section('content')
<h1 class="h3 mb-1">Đối soát thanh toán</h1>
<p class="text-muted mb-3">
    Tiền đã về nhưng không hoàn tất được hóa đơn. Màn hình này chỉ dùng để theo dõi và
    đánh dấu đã xử lý — <strong>không</strong> tự hoàn tiền và <strong>không</strong> tự đổi trạng thái đơn.
</p>

<form class="row g-2 mb-3" method="GET">
    <div class="col-md-3">
        <select class="form-select" name="trang_thai_xu_ly">
            <option value="chua_xu_ly" @selected($trangThai === 'chua_xu_ly')>Chưa xử lý</option>
            <option value="da_xu_ly" @selected($trangThai === 'da_xu_ly')>Đã xử lý</option>
            <option value="tat_ca" @selected($trangThai === 'tat_ca')>Tất cả</option>
        </select>
    </div>
    <div class="col-md-3">
        <input class="form-control" type="date" name="tu_ngay" value="{{ request('tu_ngay') }}" placeholder="Từ ngày">
    </div>
    <div class="col-md-3">
        <input class="form-control" type="date" name="den_ngay" value="{{ request('den_ngay') }}" placeholder="Đến ngày">
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-secondary">Lọc</button>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead>
                <tr>
                    <th>Thời gian</th>
                    <th>Nguồn</th>
                    <th>Mã GD ngoài</th>
                    <th>Hóa đơn</th>
                    <th>Số tiền</th>
                    <th>Lý do</th>
                    <th>Trạng thái</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($webhooks as $webhook)
                    <tr>
                        <td>{{ $webhook->created_at?->format('d/m/Y H:i:s') }}</td>
                        <td>
                            @if ($webhook->nha_cung_cap === 'sepay_api')
                                <span class="badge text-bg-info">api_doi_soat</span>
                            @else
                                <span class="badge text-bg-secondary">webhook</span>
                            @endif
                        </td>
                        <td><code>{{ $webhook->ma_giao_dich_ngoai }}</code></td>
                        <td>{{ $webhook->ma_hoadon ?? $webhook->donHang?->ma_hoadon ?? '—' }}</td>
                        <td>{{ number_format($webhook->so_tien, 0, ',', '.') }} đ</td>
                        <td>{{ \App\Models\PaymentWebhook::LY_DO[$webhook->ly_do] ?? $webhook->ly_do }}</td>
                        <td>
                            @if ($webhook->trang_thai_xu_ly === 'da_xu_ly')
                                <span class="badge text-bg-success">Đã xử lý</span>
                            @else
                                <span class="badge text-bg-warning">Chưa xử lý</span>
                            @endif
                        </td>
                        <td>
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('doisoat.show', $webhook) }}">Chi tiết</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center py-4">Không có giao dịch nào cần đối soát.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $webhooks->links() }}</div>
@endsection
