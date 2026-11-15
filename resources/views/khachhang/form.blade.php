@extends('layouts.master')

@section('title', $khachHang->exists ? 'Sửa khách hàng' : 'Thêm khách hàng')

@section('content')
<h1 class="h3 mb-3">{{ $khachHang->exists ? 'Sửa khách hàng' : 'Thêm khách hàng' }}</h1>
<form class="card card-body" method="POST" action="{{ $khachHang->exists ? route('khachhang.postsua', $khachHang) : route('khachhang.postthem') }}">
    @csrf
    @if ($khachHang->exists) @method('PUT') @endif
    <div class="mb-3">
        <label class="form-label" for="ho_ten">Họ tên</label>
        <input class="form-control @error('ho_ten') is-invalid @enderror" id="ho_ten" name="ho_ten" value="{{ old('ho_ten', $khachHang->ho_ten) }}" required>
        @error('ho_ten') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="mb-3">
        <label class="form-label" for="so_dienthoai">Số điện thoại</label>
        <input class="form-control @error('so_dienthoai') is-invalid @enderror" id="so_dienthoai" name="so_dienthoai"
               type="tel" inputmode="numeric" autocomplete="tel" maxlength="20"
               pattern="[0-9+\-.\s\(\)]{10,20}"
               value="{{ old('so_dienthoai', $khachHang->so_dienthoai) }}" required>
        @error('so_dienthoai') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <small class="text-muted">10 chữ số, bắt đầu bằng 0. Có thể nhập +84, khoảng trắng, dấu chấm/gạch ngang.</small>
    </div>
    <div class="mb-3">
        <label class="form-label" for="dia_chi">Địa chỉ</label>
        <input class="form-control @error('dia_chi') is-invalid @enderror" id="dia_chi" name="dia_chi" value="{{ old('dia_chi', $khachHang->dia_chi) }}">
        @error('dia_chi') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    @if ($khachHang->exists)
    <div class="mb-3">
        <label class="form-label" for="user_id">Tài khoản khách hàng</label>
        <select class="form-select @error('user_id') is-invalid @enderror" id="user_id" name="user_id">
            <option value="">— Chưa liên kết —</option>
            @foreach (($taiKhoan ?? collect()) as $tk)
                <option value="{{ $tk->id }}" @selected((string) old('user_id', $khachHang->user_id) === (string) $tk->id)>
                    {{ $tk->name }} ({{ $tk->email }})
                </option>
            @endforeach
        </select>
        <small class="text-muted">Chỉ nhân viên được gán liên kết sau khi xác nhận danh tính. Tài khoản đã liên kết hồ sơ khác không xuất hiện ở đây.</small>
        @error('user_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    @endif
    <div>
        <a class="btn btn-secondary" href="{{ route('khachhang.danhsach') }}">Hủy</a>
        <button class="btn btn-primary" type="submit">Lưu</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
    const sdtInput = document.getElementById('so_dienthoai');
    if (sdtInput) {
        sdtInput.addEventListener('input', () => {
            sdtInput.value = sdtInput.value.replace(/[^0-9+\-.\s()]/g, '').slice(0, 20);
        });
    }
</script>
@endpush
