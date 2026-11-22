@extends('layouts.master')

@section('title', 'Xem trước import sản phẩm')

@php
    $tenTruong = [
        'ten_sanpham' => 'Tên SP', 'barcode' => 'Barcode', 'category_id' => 'Danh mục',
        'brand_id' => 'Hãng', 'don_vi' => 'Đơn vị', 'gia_ban' => 'Giá bán',
    ];
    $loaiMap = \App\Models\LoaiSanPham::pluck('ten_loai', 'id');
    $hangMap = \App\Models\Brand::pluck('ten', 'id');
    $dungTen = fn ($field, $value) => match ($field) {
        'category_id' => $value !== null ? ($loaiMap[$value] ?? '#'.$value) : '—',
        'brand_id' => $value !== null ? ($hangMap[$value] ?? '#'.$value) : '—',
        'gia_ban' => $value !== null ? number_format((float) $value, 0, ',', '.').' đ' : '—',
        default => $value ?? '—',
    };
    $mauAction = ['create' => 'success', 'update' => 'primary', 'skip' => 'secondary', 'error' => 'danger'];
    $tenAction = ['create' => 'Tạo mới', 'update' => 'Cập nhật', 'skip' => 'Không đổi', 'error' => 'Lỗi'];
@endphp

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Xem trước import: {{ $fileName }}</h1>
        <p class="page-subtitle">
            Tạo mới: <strong>{{ $counts['create'] ?? 0 }}</strong> ·
            Cập nhật: <strong>{{ $counts['update'] ?? 0 }}</strong> ·
            Không đổi: <strong>{{ $counts['skip'] ?? 0 }}</strong> ·
            Lỗi: <strong class="{{ ($counts['error'] ?? 0) > 0 ? 'text-danger' : '' }}">{{ $counts['error'] ?? 0 }}</strong>
        </p>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('sanpham.import') }}">
        <i class="fa-solid fa-arrow-left"></i>
        <span>Import file khác</span>
    </a>
</div>

@if (! empty($loiFile))
    <div class="card border-danger mb-3">
        <div class="card-body">
            <h6 class="text-danger fw-semibold mb-2"><i class="fa-solid fa-circle-exclamation"></i> File có lỗi — KHÔNG thể xác nhận, hãy sửa file rồi tải lên lại:</h6>
            <ul class="mb-0">
                @foreach ($loiFile as $loi)
                    <li>{{ $loi }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

@if (($counts['create'] ?? 0) > 0)
    <div class="alert alert-warning">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <strong>{{ $counts['create'] }}</strong> sản phẩm sẽ được tạo ở trạng thái
        <strong>NGỪNG BÁN</strong>, tồn 0, giá nhập 0 — cần nhập kho rồi bật "Đang bán" ở form sửa sản phẩm.
    </div>
@endif

<div class="card">
    <div class="table-responsive" style="max-height: 70vh; overflow-y: auto;">
        <table class="table table-hover align-middle">
            <thead class="table-light" style="position: sticky; top: 0;">
            <tr>
                <th style="width: 60px;">Dòng</th>
                <th>SKU</th>
                <th>Tên sản phẩm</th>
                <th>Kết quả</th>
                <th>Thay đổi / Ghi chú</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($rows as $row)
                <tr class="{{ $row['action'] === 'error' ? 'table-danger' : '' }}">
                    <td class="text-muted">{{ $row['line'] }}</td>
                    <td><span class="font-monospace">{{ $row['sku'] !== '' ? $row['sku'] : '—' }}</span></td>
                    <td>{{ $row['ten_sanpham'] !== '' ? $row['ten_sanpham'] : '—' }}</td>
                    <td><span class="badge text-bg-{{ $mauAction[$row['action']] ?? 'secondary' }}">{{ $tenAction[$row['action']] ?? $row['action'] }}</span></td>
                    <td class="small">
                        @if ($row['action'] === 'create')
                            <div class="text-muted">
                                Mới: {{ $dungTen('category_id', collect($row['changes'])->firstWhere('field','category_id')['new'] ?? null) }} ·
                                {{ $dungTen('brand_id', collect($row['changes'])->firstWhere('field','brand_id')['new'] ?? null) }} ·
                                {{ $dungTen('don_vi', collect($row['changes'])->firstWhere('field','don_vi')['new'] ?? null) }} ·
                                {{ $dungTen('gia_ban', collect($row['changes'])->firstWhere('field','gia_ban')['new'] ?? null) }}
                                @php $bc = collect($row['changes'])->firstWhere('field','barcode')['new'] ?? null; @endphp
                                · Barcode: {{ $bc ?: '—' }}
                            </div>
                        @elseif ($row['action'] === 'update')
                            <ul class="mb-1">
                                @foreach ($row['changes'] as $change)
                                    <li>
                                        <strong>{{ $tenTruong[$change['field']] ?? $change['field'] }}:</strong>
                                        {{ $dungTen($change['field'], $change['old']) }}
                                        <i class="fa-solid fa-arrow-right text-muted"></i>
                                        {{ $dungTen($change['field'], $change['new']) }}
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @if (! empty($row['note']))
                            <div class="{{ $row['action'] === 'error' ? 'text-danger fw-semibold' : 'text-muted' }}">{{ $row['note'] }}</div>
                        @endif
                        @foreach ($row['warnings'] ?? [] as $canhBao)
                            <div class="text-warning-emphasis"><i class="fa-solid fa-triangle-exclamation"></i> {{ $canhBao }}</div>
                        @endforeach
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

@if ($token !== null && ($counts['error'] ?? 0) === 0)
    <div class="card mt-3 border-primary">
        <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="text-muted small">
                Phiên xem trước có hiệu lực <strong>15 phút</strong> và chỉ tài khoản của bạn xác nhận được.
                Hệ thống sẽ kiểm tra lại dữ liệu trước khi ghi — nếu có thay đổi sẽ yêu cầu tải lên lại.
            </div>
            <form method="POST" action="{{ route('sanpham.import.xacnhan') }}"
                  onsubmit="return confirm('Xác nhận ghi {{ ($counts['create'] ?? 0) + ($counts['update'] ?? 0) }} thay đổi vào database?');" data-can-pin="a">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <button class="btn btn-success" type="submit">
                    <i class="fa-solid fa-check"></i>
                    <span>Xác nhận import ({{ ($counts['create'] ?? 0) + ($counts['update'] ?? 0) }} thay đổi)</span>
                </button>
            </form>
        </div>
    </div>
@endif
@endsection
