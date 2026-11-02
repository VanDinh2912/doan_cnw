@extends('layouts.master')

@section('title', 'Bán hàng POS')

@section('content')
<h1 class="h3 mb-3">Bán hàng POS</h1>
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-body">
                <label class="form-label" for="tim-kiem">Tìm theo tên, SKU hoặc barcode</label>
                <div class="row g-2">
                    <div class="col-md-8">
                        <input class="form-control" id="tim-kiem" type="search" autocomplete="off" placeholder="Nhập từ khóa...">
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" id="loc-loai">
                            <option value="">Tất cả loại</option>
                            @foreach ($loais as $loai)
                                <option value="{{ $loai->id }}">{{ $loai->ten_loai }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row g-2 mt-2" id="ket-qua"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Giỏ hàng</strong>
                <div class="d-flex gap-2">
                @if (count(session('gio_hang', [])))
                    <form method="POST" action="{{ route('hold.giu') }}" class="d-flex gap-1">
                        @csrf
                        <input class="form-control form-control-sm" name="ten_goi" placeholder="Tên giỏ" style="width:130px">
                        <button class="btn btn-sm btn-outline-warning" type="submit">Giữ giỏ</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('giohang.lammoi') }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger" type="submit">Làm mới</button>
                </form>
                </div>
            </div>
            <div class="card-body">
                @php $gioHang = session('gio_hang', []); $tongTien = collect($gioHang)->sum(fn ($item) => $item['gia_ban'] * $item['soluong']); @endphp
                @if (count($gioHang))
                    <form method="POST" action="{{ route('giohang.capnhat') }}">
                        @csrf
                        @method('PUT')
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead><tr><th>Sản phẩm</th><th>Đơn giá</th><th style="width:120px">SL</th><th>Thành tiền</th><th></th></tr></thead>
                                <tbody>
                                @foreach ($gioHang as $item)
                                    <tr>
                                        <td>{{ $item['ten_sanpham'] }}<small class="d-block text-muted">{{ $item['sku'] }}</small></td>
                                        <td>{{ number_format($item['gia_ban'], 0, ',', '.') }} đ</td>
                                        <td><input class="form-control" name="soluong[{{ $item['id'] }}]" type="number" min="1" max="{{ $item['ton_kho'] }}" value="{{ $item['soluong'] }}"></td>
                                        <td>{{ number_format($item['gia_ban'] * $item['soluong'], 0, ',', '.') }} đ</td>
                                        <td><a class="btn btn-sm btn-outline-danger" href="{{ route('giohang.xoa', $item['id']) }}">Xóa</a></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <button class="btn btn-outline-primary" type="submit">Cập nhật giỏ</button>
                    </form>
                    <hr>
                    <div class="text-end fs-5"><strong>Tổng: {{ number_format($tongTien, 0, ',', '.') }} đ</strong></div>
                    <form class="border-top mt-3 pt-3" method="POST" action="{{ route('donhang.thanhtoan') }}">
                        @csrf
                        <div class="row g-2 mb-2">
                            <div class="col-md-8">
                                <label class="form-label" for="so_dienthoai_khach">Số điện thoại khách</label>
                                <input class="form-control" id="so_dienthoai_khach" type="search" autocomplete="off" placeholder="Nhập để tìm khách hàng">
                                <input id="customer_id" name="customer_id" type="hidden">
                                <div class="list-group mt-1" id="khach-hang-results"></div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="diem_su_dung">Dùng điểm</label>
                                <input class="form-control" id="diem_su_dung" name="diem_su_dung" type="number" min="0" value="{{ old('diem_su_dung', 0) }}">
                                <small class="text-muted" id="diem-hien-co">Chưa chọn khách</small>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label" for="giam_gia_phantram">Giảm giá (%)</label>
                                <input class="form-control" id="giam_gia_phantram" name="giam_gia_phantram" type="number" min="0" max="100" step="0.01" value="{{ old('giam_gia_phantram', 0) }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="tien_khach_dua">Tiền khách đưa</label>
                                <input class="form-control" id="tien_khach_dua" name="tien_khach_dua" type="number" min="0" value="{{ old('tien_khach_dua', 0) }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="phuong_thuc">Thanh toán</label>
                                <select class="form-select" id="phuong_thuc" name="phuong_thuc">
                                    <option value="tienmat">Tiền mặt</option>
                                    <option value="chuyenkhoan">Chuyển khoản</option>
                                </select>
                            </div>
                        </div>
                        <div class="alert alert-info mt-3 d-none" id="vietqr-box">
                            <strong>VietQR chuyển khoản</strong>
                            <img class="d-block mt-2" id="vietqr-image" width="220" height="220" alt="Mã QR thanh toán">
                            <small class="d-block mt-2">Thu ngân kiểm tra giao dịch và bấm Thanh toán để xác nhận.</small>
                        </div>
                        <button class="btn btn-success w-100 mt-3" type="submit">Thanh toán</button>
                    </form>
                @else
                    <p class="text-muted mb-0">Giỏ hàng đang trống.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script>
const input = document.getElementById('tim-kiem');
const result = document.getElementById('ket-qua');
const category = document.getElementById('loc-loai');
const phuongThuc = document.getElementById('phuong_thuc');
const tienKhachDua = document.getElementById('tien_khach_dua');
const vietQrBox = document.getElementById('vietqr-box');
const vietQrImage = document.getElementById('vietqr-image');
const customerPhone = document.getElementById('so_dienthoai_khach');
const customerId = document.getElementById('customer_id');
const customerResults = document.getElementById('khach-hang-results');
const pointsInput = document.getElementById('diem_su_dung');
const pointsHint = document.getElementById('diem-hien-co');
let timer;
let scannerTimer;
let scannerBuffer = '';
let scannerLastKeyAt = 0;
function hienThiKetQua(products) {
    result.innerHTML = '';
    products.forEach(product => {
        const col = document.createElement('div');
        col.className = 'col-md-6';
        const link = document.createElement('a');
        link.className = 'card h-100 text-decoration-none text-dark';
        link.href = `{{ url('/giohang/them') }}/${product.id}`;
        if (product.hinh_anh) {
            const image = document.createElement('img');
            image.src = product.hinh_anh.startsWith('upload/')
                ? `{{ asset('') }}${product.hinh_anh}`
                : `{{ asset('storage') }}/${product.hinh_anh}`;
            image.className = 'card-img-top';
            image.style = 'height:120px;object-fit:cover';
            image.alt = product.ten_sanpham;
            link.appendChild(image);
        } else {
            const image = document.createElement('img');
            image.src = `{{ asset('img/no-image.svg') }}`;
            image.className = 'card-img-top';
            image.style = 'height:120px;object-fit:cover';
            image.alt = 'Chưa có ảnh';
            link.appendChild(image);
        }
        const body = document.createElement('div');
        body.className = 'card-body p-2';
        const name = document.createElement('strong');
        name.textContent = product.ten_sanpham;
        const details = document.createElement('small');
        details.className = 'd-block text-muted';
        details.textContent = `${product.sku} · Tồn: ${product.soluong}`;
        const price = document.createElement('span');
        price.textContent = `${Number(product.gia_ban).toLocaleString('vi-VN')} đ`;
        body.append(name, details, price);
        link.appendChild(body);
        col.appendChild(link);
        result.appendChild(col);
    });
    if (!products.length) result.innerHTML = '<div class="text-muted p-2">Không tìm thấy sản phẩm còn hàng.</div>';
}
async function timKiem(query, chinhXac = false) {
    const categoryQuery = category.value ? `&category_id=${category.value}` : '';
    const response = await fetch(`{{ route('sanpham.timkiem') }}?q=${encodeURIComponent(query)}${chinhXac ? '&chinhxac=1' : ''}${categoryQuery}`);
    hienThiKetQua(await response.json());
}
async function themTuBarcode(barcode) {
    const categoryQuery = category.value ? `&category_id=${category.value}` : '';
    const response = await fetch(`{{ route('sanpham.timkiem') }}?q=${encodeURIComponent(barcode)}&chinhxac=1${categoryQuery}`);
    const products = await response.json();
    if (products.length !== 1) {
        result.innerHTML = '<div class="alert alert-warning">Không tìm thấy barcode hoặc sản phẩm đã hết hàng.</div>';
        return;
    }
    window.location.href = `{{ url('/giohang/them') }}/${products[0].id}`;
}
document.addEventListener('keydown', (event) => {
    if (document.activeElement !== input) return;
    const now = performance.now();
    if (event.key === 'Enter') {
        if (scannerBuffer.length >= 6) {
            event.preventDefault();
            themTuBarcode(scannerBuffer);
        }
        scannerBuffer = '';
        scannerLastKeyAt = 0;
        return;
    }
    if (event.key.length !== 1) return;
    scannerBuffer = now - scannerLastKeyAt < 50 ? scannerBuffer + event.key : event.key;
    scannerLastKeyAt = now;
});
input.addEventListener('input', () => {
    clearTimeout(timer);
    const query = input.value.trim();
    result.innerHTML = '';
    if (!query) return;
    timer = setTimeout(async () => {
        timKiem(query);
    }, 250);
});
async function capNhatQr() {
    const hienThi = phuongThuc.value === 'chuyenkhoan';
    vietQrBox.classList.toggle('d-none', !hienThi);
    tienKhachDua.readOnly = hienThi;
    if (!hienThi) return;
    const giam = Number(document.getElementById('giam_gia_phantram').value || 0);
    try {
        const res = await fetch(`{{ route('pos.vietqr') }}?giam_gia_phantram=${giam}`);
        const data = await res.json();
        if (!data.enabled || !data.url) {
            vietQrImage.classList.add('d-none');
            vietQrImage.removeAttribute('src');
            vietQrBox.querySelector('.text-danger')?.remove();
            vietQrBox.insertAdjacentHTML('beforeend', '<small class="text-danger d-block mt-2">Chưa cấu hình POS_BANK_CODE và POS_BANK_ACCOUNT trong .env.</small>');
            return;
        }
        vietQrBox.querySelector('.text-danger')?.remove();
        tienKhachDua.value = data.amount;
        vietQrImage.src = data.url;
        vietQrImage.classList.remove('d-none');
    } catch (e) {
        console.error('VietQR error:', e);
    }
}
phuongThuc.addEventListener('change', capNhatQr);
document.getElementById('giam_gia_phantram').addEventListener('input', capNhatQr);
category.addEventListener('change', () => {
    if (input.value.trim()) timKiem(input.value.trim());
});
customerPhone.addEventListener('input', async () => {
    customerId.value = '';
    pointsHint.textContent = 'Chưa chọn khách';
    const phone = customerPhone.value.trim();
    customerResults.innerHTML = '';
    if (!phone) return;
    const response = await fetch(`{{ route('khachhang.timkiem') }}?sdt=${encodeURIComponent(phone)}`);
    const customers = await response.json();
    customers.forEach(customer => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'list-group-item list-group-item-action';
        button.textContent = `${customer.ho_ten} - ${customer.so_dienthoai} (Điểm: ${customer.diem_tichluy})`;
        button.addEventListener('click', () => {
            customerId.value = customer.id;
            customerPhone.value = customer.so_dienthoai;
            pointsHint.textContent = `Điểm hiện có: ${customer.diem_tichluy}`;
            customerResults.innerHTML = '';
        });
        customerResults.appendChild(button);
    });
});
</script>
@endpush
@endsection
