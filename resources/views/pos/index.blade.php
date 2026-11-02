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
                            <div class="col-md-4" id="box-tien-khach-dua">
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
                        <button class="btn btn-success w-100 mt-3" id="btn-submit-tienmat" type="submit">Thanh toán</button>
                        <button class="btn btn-primary w-100 mt-3 d-none" id="btn-tao-qr" type="button">
                            <i class="fa fa-qrcode me-1"></i> Tạo mã QR
                        </button>
                    </form>
                @else
                    <p class="text-muted mb-0">Giỏ hàng đang trống.</p>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-vietqr" tabindex="-1" aria-labelledby="modalVietQrLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalVietQrLabel">
                    <i class="fa fa-qrcode me-2"></i>Thanh toán Chuyển khoản VietQR
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body text-center">
                <div class="mb-2">
                    <span class="text-muted">Mã hóa đơn:</span> <strong id="qr-modal-ma-hoadon" class="fs-5 text-primary"></strong>
                </div>
                <div class="mb-3">
                    <span class="text-muted">Số tiền cần thanh toán:</span>
                    <div class="fs-4 fw-bold text-danger" id="qr-modal-tong-tien">0 đ</div>
                </div>

                <div class="position-relative d-inline-block p-2 border rounded bg-white shadow-sm mb-3 text-center">
                    <img id="qr-modal-image" src="" alt="Mã VietQR" class="img-fluid" style="width: 240px; height: 240px; object-fit: contain;">
                </div>

                <div id="qr-modal-error" class="alert alert-warning d-none text-start small mb-3">
                    <i class="fa fa-triangle-exclamation me-1"></i>
                    <strong>Không thể tải ảnh QR.</strong><br>
                    Vui lòng đối chiếu tài khoản ngân hàng thủ công. Thu ngân có thể bấm <em>"Xác nhận đã nhận tiền"</em> sau khi kiểm tra giao dịch hoặc bấm <em>"Hủy chờ"</em> để chuyển sang thanh toán tiền mặt.
                </div>

                <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light mb-2">
                    <span class="small text-muted">Thời gian chờ thanh toán:</span>
                    <span id="qr-modal-countdown" class="badge bg-warning text-dark fs-6 font-monospace">15:00</span>
                </div>

                <div class="text-primary small" id="qr-modal-status">
                    <i class="fa fa-circle-notch fa-spin me-1"></i> Đang chờ nhận diện chuyển khoản từ SePay...
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-outline-danger" id="btn-modal-huy-cho">
                    <i class="fa fa-times me-1"></i> Hủy chờ
                </button>
                <button type="button" class="btn btn-success" id="btn-modal-xac-nhan">
                    <i class="fa fa-check me-1"></i> Xác nhận đã nhận tiền
                </button>
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
const boxTienKhachDua = document.getElementById('box-tien-khach-dua');
const btnSubmitTienmat = document.getElementById('btn-submit-tienmat');
const btnTaoQr = document.getElementById('btn-tao-qr');
const modalVietQrEl = document.getElementById('modal-vietqr');
const qrModalImage = document.getElementById('qr-modal-image');
const qrModalError = document.getElementById('qr-modal-error');
const qrModalMaHoaDon = document.getElementById('qr-modal-ma-hoadon');
const qrModalTongTien = document.getElementById('qr-modal-tong-tien');
const qrModalCountdown = document.getElementById('qr-modal-countdown');
const btnModalXacNhan = document.getElementById('btn-modal-xac-nhan');
const btnModalHuyCho = document.getElementById('btn-modal-huy-cho');

let currentInvoiceId = null;
let pollingTimer = null;
let countdownTimer = null;
let bsModalInstance = null;

function stopPollingAndTimer() {
    if (pollingTimer) {
        clearInterval(pollingTimer);
        pollingTimer = null;
    }
    if (countdownTimer) {
        clearInterval(countdownTimer);
        countdownTimer = null;
    }
}
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
function doiPhuongThuc() {
    const isChuyenKhoan = phuongThuc.value === 'chuyenkhoan';
    if (isChuyenKhoan) {
        btnSubmitTienmat.classList.add('d-none');
        btnTaoQr.classList.remove('d-none');
        tienKhachDua.required = false;
    } else {
        btnSubmitTienmat.classList.remove('d-none');
        btnTaoQr.classList.add('d-none');
        tienKhachDua.required = true;
    }
}
phuongThuc.addEventListener('change', doiPhuongThuc);
doiPhuongThuc();

btnTaoQr?.addEventListener('click', async () => {
    btnTaoQr.disabled = true;
    btnTaoQr.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Đang tạo mã QR...';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const giamGia = document.getElementById('giam_gia_phantram')?.value || 0;
    const cusId = customerId?.value || null;
    const diem = pointsInput?.value || 0;

    try {
        const response = await fetch("{{ route('donhang.taoqr') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                giam_gia_phantram: giamGia,
                customer_id: cusId,
                diem_su_dung: diem
            })
        });

        const data = await response.json();
        if (!response.ok) {
            alert(data.error || 'Không thể tạo mã QR thanh toán.');
            btnTaoQr.disabled = false;
            btnTaoQr.innerHTML = '<i class="fa fa-qrcode me-1"></i> Tạo mã QR';
            return;
        }

        currentInvoiceId = data.id;
        qrModalMaHoaDon.textContent = data.ma_hoadon;
        qrModalTongTien.textContent = Number(data.tong_thanh_toan).toLocaleString('vi-VN') + ' đ';

        qrModalError.classList.add('d-none');
        qrModalImage.classList.remove('d-none');
        qrModalImage.onerror = () => {
            qrModalImage.classList.add('d-none');
            qrModalError.classList.remove('d-none');
        };
        qrModalImage.src = data.url_qr;

        if (!bsModalInstance) {
            bsModalInstance = new bootstrap.Modal(modalVietQrEl);
        }
        bsModalInstance.show();

        // Đồng hồ đếm ngược
        const hetHanLuc = new Date(data.het_han_luc).getTime();
        stopPollingAndTimer();

        countdownTimer = setInterval(async () => {
            const conLai = hetHanLuc - Date.now();
            if (conLai <= 0) {
                qrModalCountdown.textContent = '00:00';
                stopPollingAndTimer();
                // Hết hạn -> gọi trạng thái để kích hoạt hủy lười
                try {
                    const stRes = await fetch(`/donhang/trangthai/${currentInvoiceId}`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    const stData = await stRes.json();
                    if (stData.trang_thai === 'da_huy') {
                        currentInvoiceId = null;
                        bsModalInstance.hide();
                        alert('Hóa đơn đã hết thời gian chờ và tự động hủy.');
                        window.location.reload();
                    }
                } catch (e) {}
                return;
            }
            const phut = Math.floor(conLai / 60000);
            const giay = Math.floor((conLai % 60000) / 1000);
            qrModalCountdown.textContent = `${String(phut).padStart(2, '0')}:${String(giay).padStart(2, '0')}`;
        }, 1000);

        // Polling trạng thái mỗi 3 giây
        pollingTimer = setInterval(async () => {
            try {
                const res = await fetch(`/donhang/trangthai/${currentInvoiceId}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const resData = await res.json();
                if (resData.trang_thai === 'da_thanh_toan') {
                    stopPollingAndTimer();
                    currentInvoiceId = null;
                    bsModalInstance.hide();
                    alert('Thanh toán thành công qua chuyển khoản!');
                    window.location.href = resData.url_in;
                } else if (resData.trang_thai === 'da_huy') {
                    stopPollingAndTimer();
                    currentInvoiceId = null;
                    bsModalInstance.hide();
                    alert('Hóa đơn đã bị hủy hoặc hết hạn chờ.');
                    window.location.reload();
                }
            } catch (err) {
                console.error('Polling error:', err);
            }
        }, 3000);

    } catch (e) {
        alert('Lỗi tạo mã QR: ' + e.message);
    } finally {
        btnTaoQr.disabled = false;
        btnTaoQr.innerHTML = '<i class="fa fa-qrcode me-1"></i> Tạo mã QR';
    }
});

btnModalXacNhan?.addEventListener('click', async () => {
    if (!currentInvoiceId) return;
    if (!confirm('Xác nhận đã nhận đủ tiền chuyển khoản cho hóa đơn này?')) return;

    btnModalXacNhan.disabled = true;
    btnModalXacNhan.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Đang xác nhận...';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    try {
        const res = await fetch(`/donhang/xacnhan/${currentInvoiceId}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        });
        const data = await res.json();
        if (res.ok && data.success) {
            stopPollingAndTimer();
            currentInvoiceId = null;
            bsModalInstance.hide();
            window.location.href = data.url_in;
        } else {
            alert(data.error || 'Không thể xác nhận thanh toán.');
            btnModalXacNhan.disabled = false;
            btnModalXacNhan.innerHTML = '<i class="fa fa-check me-1"></i> Xác nhận đã nhận tiền';
        }
    } catch (e) {
        alert('Lỗi kết nối khi xác nhận: ' + e.message);
        btnModalXacNhan.disabled = false;
        btnModalXacNhan.innerHTML = '<i class="fa fa-check me-1"></i> Xác nhận đã nhận tiền';
    }
});

btnModalHuyCho?.addEventListener('click', async () => {
    if (!currentInvoiceId) return;
    if (!confirm('Bạn có chắc chắn muốn hủy chờ thanh toán? Tồn kho và điểm sẽ được hoàn trả.')) return;

    btnModalHuyCho.disabled = true;
    btnModalHuyCho.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Đang hủy...';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    try {
        const res = await fetch(`/donhang/huycho/${currentInvoiceId}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        });
        const data = await res.json();
        if (res.ok && data.success) {
            stopPollingAndTimer();
            currentInvoiceId = null;
            bsModalInstance.hide();
            alert('Đã hủy chờ thanh toán thành công.');
            window.location.reload();
        } else {
            alert(data.error || 'Không thể hủy chờ thanh toán.');
            btnModalHuyCho.disabled = false;
            btnModalHuyCho.innerHTML = '<i class="fa fa-times me-1"></i> Hủy chờ';
        }
    } catch (e) {
        alert('Lỗi kết nối khi hủy chờ: ' + e.message);
        btnModalHuyCho.disabled = false;
        btnModalHuyCho.innerHTML = '<i class="fa fa-times me-1"></i> Hủy chờ';
    }
});

modalVietQrEl?.addEventListener('hidden.bs.modal', () => {
    stopPollingAndTimer();
    if (currentInvoiceId) {
        window.location.reload();
    }
});
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
