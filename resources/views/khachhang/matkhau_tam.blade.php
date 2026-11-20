@extends('layouts.master')

@section('title', 'Mật khẩu tạm — '.$khachHang->ho_ten)

@section('content')
<div class="col-lg-7 mx-auto">
    <div class="card border-warning">
        <div class="card-header bg-warning-subtle fw-bold">Đặt lại mật khẩu thành công</div>
        <div class="card-body">
            <div class="alert alert-warning">
                <strong>Mật khẩu tạm chỉ hiển thị ĐÚNG MỘT LẦN.</strong>
                Tải lại trang sẽ tạo mật khẩu MỚI và mật khẩu này mất hiệu lực.
                Hãy ghi lại ngay và chuyển cho khách hàng qua kênh an toàn.
            </div>

            <table class="table table-sm">
                <tr><th style="width:35%">Khách hàng</th><td>{{ $khachHang->ho_ten }} ({{ $khachHang->so_dienthoai }})</td></tr>
                <tr><th>Tài khoản</th><td>{{ $user->name }} ({{ $user->email }})</td></tr>
                <tr>
                    <th>Mật khẩu tạm</th>
                    <td>
                        <code class="fs-6 user-select-all" id="mk-tam">{{ $matKhauTam }}</code>
                        <button type="button" class="btn btn-sm btn-outline-secondary ms-2"
                                onclick="navigator.clipboard.writeText(document.getElementById('mk-tam').textContent)">
                            Sao chép
                        </button>
                    </td>
                </tr>
            </table>

            <p class="text-muted mb-0"><small>
                Khách đăng nhập bằng mật khẩu tạm sẽ bị yêu cầu đổi mật khẩu ngay.
                Mọi phiên đăng nhập cũ và cookie "ghi nhớ đăng nhập" đã bị hủy.
            </small></p>
        </div>
        <div class="card-footer d-flex gap-2">
            <a class="btn btn-primary" href="{{ route('khachhang.chitiet', $khachHang) }}">Về hồ sơ khách hàng</a>
            <a class="btn btn-outline-secondary" href="{{ route('khachhang.danhsach') }}">Danh sách khách hàng</a>
        </div>
    </div>
</div>
@endsection
