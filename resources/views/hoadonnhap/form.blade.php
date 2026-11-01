@extends('layouts.master')
@section('title', $hoaDon->exists ? 'Sửa hóa đơn nhập' : 'Thêm hóa đơn nhập')
@section('content')
<h1 class="h3 mb-3">{{ $hoaDon->exists ? 'Sửa hóa đơn nhập' : 'Thêm hóa đơn nhập' }}</h1>
<form method="POST" enctype="multipart/form-data" action="{{ $hoaDon->exists ? route('hoadonnhap.postsua', $hoaDon) : route('hoadonnhap.postthem') }}">
@csrf
<div class="row g-3">
<div class="col-md-6"><label class="form-label">Nhà cung cấp</label><select class="form-select" name="supplier_id" required>@foreach($nhaCungCaps as $nhaCungCap)<option value="{{ $nhaCungCap->id }}" @selected(old('supplier_id', $hoaDon->supplier_id) == $nhaCungCap->id)>{{ $nhaCungCap->ten_nhacungcap }} - MST {{ $nhaCungCap->ma_so_thue }}</option>@endforeach</select></div>
<div class="col-md-3"><label class="form-label">Số hóa đơn</label><input class="form-control" name="so_hoadon" value="{{ old('so_hoadon', $hoaDon->so_hoadon) }}" required></div>
<div class="col-md-3"><label class="form-label">Ký hiệu</label><input class="form-control" name="ky_hieu" value="{{ old('ky_hieu', $hoaDon->ky_hieu) }}" required></div>
<div class="col-md-3"><label class="form-label">Mẫu số</label><input class="form-control" name="mau_so" value="{{ old('mau_so', $hoaDon->mau_so) }}"></div>
<div class="col-md-3"><label class="form-label">Ngày hóa đơn</label><input class="form-control" type="date" name="ngay_hoadon" value="{{ old('ngay_hoadon', optional($hoaDon->ngay_hoadon)->format('Y-m-d')) }}" required></div>
<div class="col-md-3"><label class="form-label">Thuế suất</label><select class="form-select" name="thue_suat">@foreach([0,5,8,10] as $tax)<option value="{{ $tax }}" @selected(old('thue_suat', $hoaDon->thue_suat) == $tax)>{{ $tax }}%</option>@endforeach</select></div>
<div class="col-md-3"><label class="form-label">Thanh toán</label><select class="form-select" name="hinh_thuc_thanh_toan"><option value="chuyenkhoan">Chuyển khoản</option><option value="tienmat">Tiền mặt</option><option value="congno">Công nợ</option></select></div>
<div class="col-md-6"><label class="form-label">PDF hóa đơn</label><input class="form-control" type="file" name="file_goc" accept="application/pdf"><small class="text-muted">Tối đa 10 MB.</small></div>
</div>
<hr><p class="text-muted">Nhập tối thiểu một dòng. Có thể chọn sản phẩm hiện có hoặc loại để tạo sản phẩm mới.</p>
@php($item = $hoaDon->chiTiets->first())
<div class="row g-2">
<div class="col-md-4"><select class="form-select" name="items[0][product_id]"><option value="">-- Sản phẩm --</option>@foreach($sanPhams as $sanPham)<option value="{{ $sanPham->id }}" @selected(optional($item)->product_id == $sanPham->id)>{{ $sanPham->sku }} - {{ Str::limit($sanPham->ten_sanpham, 60) }}</option>@endforeach</select></div>
<div class="col-md-3"><select class="form-select" name="items[0][category_id]"><option value="">-- Hoặc loại mới --</option>@foreach($loais as $loai)<option value="{{ $loai->id }}" @selected(optional($item)->category_id == $loai->id)>{{ $loai->ten_loai }}</option>@endforeach</select></div>
<div class="col-md-2"><input class="form-control" name="items[0][ten_hang]" placeholder="Tên hàng" value="{{ optional($item)->ten_hang }}"></div>
<div class="col-md-1"><input class="form-control" name="items[0][don_vi]" placeholder="ĐVT" value="{{ optional($item)->don_vi ?: 'Cái' }}"></div>
<div class="col-md-1"><input class="form-control" type="number" min="1" name="items[0][soluong]" placeholder="SL" value="{{ optional($item)->soluong ?: 1 }}"></div>
<div class="col-md-1"><input class="form-control" type="number" min="0" name="items[0][don_gia]" placeholder="Giá" value="{{ optional($item)->don_gia ?: 0 }}"></div>
</div>
<textarea class="form-control mt-3" name="ghi_chu" placeholder="Ghi chú">{{ old('ghi_chu', $hoaDon->ghi_chu) }}</textarea>
<button class="btn btn-primary mt-3">Lưu nháp</button>
</form>
@endsection
