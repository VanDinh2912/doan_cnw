@extends('layouts.master')

@section('title', 'Chi tiết giao dịch đối soát')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Chi tiết giao dịch đối soát</h1>
    <a class="btn btn-outline-secondary" href="{{ route('doisoat.index') }}">Về danh sách</a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">Mã giao dịch ngoài</dt>
            <dd class="col-sm-9"><code>{{ $webhook->ma_giao_dich_ngoai }}</code></dd>

            <dt class="col-sm-3">Nhà cung cấp</dt>
            <dd class="col-sm-9">{{ $webhook->nha_cung_cap }}</dd>

            <dt class="col-sm-3">Hóa đơn</dt>
            <dd class="col-sm-9">{{ $webhook->ma_hoadon ?? $webhook->donHang?->ma_hoadon ?? 'Không xác định' }}</dd>

            <dt class="col-sm-3">Số tiền</dt>
            <dd class="col-sm-9">{{ number_format($webhook->so_tien, 0, ',', '.') }} đ</dd>

            <dt class="col-sm-3">Lý do</dt>
            <dd class="col-sm-9">{{ \App\Models\PaymentWebhook::LY_DO[$webhook->ly_do] ?? $webhook->ly_do }}</dd>

            <dt class="col-sm-3">Nội dung chuyển khoản</dt>
            <dd class="col-sm-9">{{ $webhook->noi_dung ?? '—' }}</dd>

            <dt class="col-sm-3">Thời gian nhận</dt>
            <dd class="col-sm-9">{{ $webhook->created_at?->format('d/m/Y H:i:s') }}</dd>

            <dt class="col-sm-3">Trạng thái xử lý</dt>
            <dd class="col-sm-9">
                @if ($webhook->trang_thai_xu_ly === 'da_xu_ly')
                    <span class="badge text-bg-success">Đã xử lý</span>
                    — {{ $webhook->nguoiXuLy?->name ?? 'Hệ thống' }},
                    {{ $webhook->thoi_gian_xu_ly?->format('d/m/Y H:i:s') }}
                @else
                    <span class="badge text-bg-warning">Chưa xử lý</span>
                @endif
            </dd>

            @if ($webhook->ghi_chu_xu_ly)
                <dt class="col-sm-3">Ghi chú xử lý</dt>
                <dd class="col-sm-9">{{ $webhook->ghi_chu_xu_ly }}</dd>
            @endif
        </dl>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Payload giao dịch</div>
    <div class="card-body">
        <pre class="mb-0 small">{{ json_encode($webhook->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
    </div>
</div>

@if ($webhook->trang_thai_xu_ly === 'chua_xu_ly')
    <div class="card">
        <div class="card-header">Đánh dấu đã xử lý</div>
        <div class="card-body">
            <p class="text-muted small">
                Bắt buộc ghi rõ cách đã xử lý (đã hoàn tiền tay, đã liên hệ khách, đã bỏ qua…).
                Thao tác này chỉ cập nhật hàng chờ đối soát và ghi nhật ký, không tác động hóa đơn hay tồn kho.
            </p>
            <form method="POST" action="{{ route('doisoat.xuly', $webhook) }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="ghi_chu">Ghi chú xử lý <span class="text-danger">*</span></label>
                    <textarea class="form-control @error('ghi_chu') is-invalid @enderror" id="ghi_chu"
                              name="ghi_chu" rows="3" required minlength="3" maxlength="1000">{{ old('ghi_chu') }}</textarea>
                    @error('ghi_chu')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <button class="btn btn-primary">Đánh dấu đã xử lý</button>
            </form>
        </div>
    </div>
@endif
@endsection
