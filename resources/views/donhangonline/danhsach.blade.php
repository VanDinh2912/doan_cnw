@extends('layouts.master')

@section('title', 'Đơn online')

@section('content')
<h1 class="h3 mb-3">Đơn online</h1>

@if ($thieuMigration)
    <div class="alert alert-warning">Cần áp migration đơn online (cột <code>kenh</code>/<code>hinh_thuc_nhan</code>) trước khi xem danh sách.</div>
@else
<form class="card card-body mb-3" method="get" action="{{ route('donhangonline.danhsach') }}">
    <div class="row g-2 align-items-end">
        <div class="col-md-2">
            <label class="form-label">Hình thức</label>
            <select class="form-select" name="hinh_thuc_nhan">
                <option value="">Tất cả</option>
                <option value="tai_cua_hang" @selected(($filters['hinh_thuc_nhan'] ?? '') === 'tai_cua_hang')>Nhận tại cửa hàng</option>
                <option value="giao_tan_noi" @selected(($filters['hinh_thuc_nhan'] ?? '') === 'giao_tan_noi')>Giao tận nơi</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Thanh toán</label>
            <select class="form-select" name="trang_thai">
                <option value="">Tất cả</option>
                <option value="cho_thanh_toan" @selected(($filters['trang_thai'] ?? '') === 'cho_thanh_toan')>Chờ thanh toán</option>
                <option value="da_thanh_toan" @selected(($filters['trang_thai'] ?? '') === 'da_thanh_toan')>Đã thanh toán</option>
                <option value="da_huy" @selected(($filters['trang_thai'] ?? '') === 'da_huy')>Đã hủy</option>
            </select>
        </div>
        @if ($coCotGiao)
        <div class="col-md-2">
            <label class="form-label">Trạng thái giao</label>
            <select class="form-select" name="trang_thai_giao">
                <option value="">Tất cả</option>
                @foreach (['cho_xac_nhan' => 'Chờ xác nhận', 'da_xac_nhan' => 'Đã xác nhận', 'dang_giao' => 'Đang giao', 'da_giao' => 'Đã giao', 'da_huy_giao' => 'Đã hủy giao', 'da_nhan' => 'Đã nhận hàng (pickup)'] as $gt => $nhan)
                    <option value="{{ $gt }}" @selected(($filters['trang_thai_giao'] ?? '') === $gt)>{{ $nhan }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="col-md-2">
            <label class="form-label">Từ ngày</label>
            <input class="form-control" type="date" name="tu_ngay" value="{{ $filters['tu_ngay'] ?? '' }}">
        </div>
        <div class="col-md-2">
            <label class="form-label">Đến ngày</label>
            <input class="form-control" type="date" name="den_ngay" value="{{ $filters['den_ngay'] ?? '' }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">Tìm kiếm</label>
            <input class="form-control" type="text" name="tim" value="{{ $filters['tim'] ?? '' }}" placeholder="Mã đơn, mã tra cứu, tên, SĐT">
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-primary" type="submit">Lọc</button>
            <a class="btn btn-outline-secondary" href="{{ route('donhangonline.danhsach') }}">Xóa lọc</a>
        </div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead><tr>
                <th>Mã đơn</th><th>Mã tra cứu</th><th>Khách</th><th>SĐT</th><th>Hình thức</th>
                <th>Thanh toán</th><th>Giao</th><th>Ngày tạo</th><th>Tổng</th><th></th>
            </tr></thead>
            <tbody>
            @forelse ($donHangs as $don)
                <tr>
                    <td>{{ $don->ma_hoadon }}</td>
                    <td><code>{{ $don->ma_tra_cuu }}</code></td>
                    <td>{{ $don->ten_khach_dat }}
                        @if (isset($sdtKhoaSet[\App\Support\SoDienThoai::chuanHoa((string) $don->so_dienthoai_dat)]))
                            <span class="badge bg-danger" title="SĐT trùng hồ sơ khách đang bị khóa">SĐT trùng KH khóa</span>
                        @endif
                    </td>
                    <td>{{ \App\Support\CheThongTin::soDienThoai($don->so_dienthoai_dat) }}</td>
                    <td>{{ $don->hinh_thuc_nhan_tieng_viet }}</td>
                    <td>
                        @if ($don->trang_thai === 'da_thanh_toan')<span class="badge bg-success">Đã thanh toán</span>
                        @elseif ($don->trang_thai === 'cho_thanh_toan')<span class="badge bg-warning text-dark">Chờ thanh toán</span>
                        @else<span class="badge bg-secondary">Đã hủy</span>@endif
                    </td>
                    <td>{{ $don->trang_thai_giao_tieng_viet }}</td>
                    <td>{{ $don->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ number_format((float) $don->tong_thanh_toan, 0, ',', '.') }} đ</td>
                    <td><a class="btn btn-sm btn-outline-primary" href="{{ route('donhangonline.chitiet', $don) }}">Xử lý</a></td>
                </tr>
            @empty
                <tr><td class="text-center" colspan="10">Chưa có đơn online nào.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($donHangs->hasPages())
        <div class="p-3">{{ $donHangs->links() }}</div>
    @endif
</div>
@endif
@endsection
