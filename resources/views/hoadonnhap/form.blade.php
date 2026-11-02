@extends('layouts.master')
@section('title', $hoaDon->exists ? 'Sửa hóa đơn nhập' : 'Thêm hóa đơn nhập')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">{{ $hoaDon->exists ? 'Sửa hóa đơn nhập: ' . $hoaDon->ma_phieu : 'Thêm hóa đơn nhập mới' }}</h1>
    <a href="{{ route('hoadonnhap.danhsach') }}" class="btn btn-outline-secondary btn-sm">
        <i class="fa fa-arrow-left"></i> Quay lại danh sách
    </a>
</div>

{{-- Khung bóc tách PDF cục bộ (Chỉ hiện khi thêm mới hoặc sửa hóa đơn nháp) --}}
<div class="card border-primary mb-4 shadow-sm">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <span><i class="fa fa-file-pdf me-2"></i><strong>Bóc tách hóa đơn PDF tự động (100% Offline / Không dùng API)</strong></span>
        <span class="badge bg-light text-primary">Pure PHP Parser</span>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Tải lên file hóa đơn điện tử PDF của nhà cung cấp. Hệ thống sẽ trích xuất số hóa đơn, ngày lập, nhà cung cấp và toàn bộ danh sách mặt hàng tự động trực tiếp trên máy chủ mà không cần kết nối mạng.
        </p>
        <div class="row g-2 align-items-center">
            <div class="col-md-7">
                <input type="file" id="pdf_input" class="form-control" accept="application/pdf">
            </div>
            <div class="col-md-5">
                <button type="button" id="btn_parse_pdf" class="btn btn-success me-2">
                    <i class="fa fa-bolt me-1"></i> Bóc tách tự động
                </button>
                <span id="parse_spinner" class="spinner-border spinner-border-sm text-primary d-none" role="status"></span>
                <span id="parse_status_text" class="text-muted small ms-2"></span>
            </div>
        </div>

        {{-- Khung thông báo kết quả bóc tách --}}
        <div id="parse_alert" class="alert d-none mt-3 mb-0 py-2 small"></div>
    </div>
</div>

<form id="form_hoadon" method="POST" enctype="multipart/form-data" action="{{ $hoaDon->exists ? route('hoadonnhap.postsua', $hoaDon) : route('hoadonnhap.postthem') }}">
    @csrf

    {{-- Thông tin chung hóa đơn --}}
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0 fs-6">1. Thông tin chung hóa đơn</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Nhà cung cấp <span class="text-danger">*</span></label>
                    <select class="form-select" name="supplier_id" id="supplier_id" required>
                        <option value="">-- Chọn nhà cung cấp --</option>
                        @foreach($nhaCungCaps as $ncc)
                            <option value="{{ $ncc->id }}" data-mst="{{ $ncc->ma_so_thue }}" @selected(old('supplier_id', $hoaDon->supplier_id) == $ncc->id)>
                                {{ $ncc->ten_nhacungcap }} (MST: {{ $ncc->ma_so_thue }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Số hóa đơn <span class="text-danger">*</span></label>
                    <input class="form-control" name="so_hoadon" id="so_hoadon" value="{{ old('so_hoadon', $hoaDon->so_hoadon) }}" required placeholder="VD: 0000123">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Ký hiệu <span class="text-danger">*</span></label>
                    <input class="form-control" name="ky_hieu" id="ky_hieu" value="{{ old('ky_hieu', $hoaDon->ky_hieu) }}" required placeholder="VD: 1C26TMM">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Mẫu số</label>
                    <input class="form-control" name="mau_so" id="mau_so" value="{{ old('mau_so', $hoaDon->mau_so) }}" placeholder="VD: 1">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Ngày hóa đơn <span class="text-danger">*</span></label>
                    <input class="form-control" type="date" name="ngay_hoadon" id="ngay_hoadon" value="{{ old('ngay_hoadon', optional($hoaDon->ngay_hoadon)->format('Y-m-d')) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Thuế suất GTGT</label>
                    <select class="form-select" name="thue_suat" id="thue_suat">
                        @foreach([0, 5, 8, 10] as $tax)
                            <option value="{{ $tax }}" @selected((int)old('thue_suat', $hoaDon->thue_suat) == $tax)>{{ $tax }}%</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Hình thức thanh toán</label>
                    <select class="form-select" name="hinh_thuc_thanh_toan" id="hinh_thuc_thanh_toan">
                        <option value="chuyenkhoan" @selected(old('hinh_thuc_thanh_toan', $hoaDon->hinh_thuc_thanh_toan) == 'chuyenkhoan')>Chuyển khoản</option>
                        <option value="tienmat" @selected(old('hinh_thuc_thanh_toan', $hoaDon->hinh_thuc_thanh_toan) == 'tienmat')>Tiền mặt</option>
                        <option value="congno" @selected(old('hinh_thuc_thanh_toan', $hoaDon->hinh_thuc_thanh_toan) == 'congno')>Công nợ</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">File PDF đính kèm (Lưu trữ chứng từ gốc)</label>
                    <input class="form-control" type="file" name="file_goc" id="file_goc" accept="application/pdf">
                    <small class="text-muted">Định dạng PDF, tối đa 10 MB.</small>
                    @if($hoaDon->file_goc)
                        <div class="mt-1">
                            <a href="{{ Storage::disk('public')->url($hoaDon->file_goc) }}" target="_blank" class="text-decoration-none small text-primary">
                                <i class="fa fa-paperclip"></i> Đã có file đính kèm: {{ basename($hoaDon->file_goc) }}
                            </a>
                        </div>
                    @endif
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ghi chú hóa đơn</label>
                    <input class="form-control" name="ghi_chu" id="ghi_chu" value="{{ old('ghi_chu', $hoaDon->ghi_chu) }}" placeholder="Ghi chú nội bộ...">
                </div>
            </div>
        </div>
    </div>

    {{-- Bảng chi tiết mặt hàng --}}
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fs-6">2. Danh sách mặt hàng nhập kho</h5>
            <div>
                <span id="badge_total_items" class="badge bg-secondary me-2">0 mặt hàng</span>
                <button type="button" id="btn_add_row" class="btn btn-outline-primary btn-sm">
                    <i class="fa fa-plus me-1"></i> Thêm dòng
                </button>
            </div>
        </div>
        <div class="table-responsive" style="max-height: 520px; overflow-y: auto;">
            <table class="table table-bordered table-hover align-middle mb-0" id="table_items">
                <thead class="table-light sticky-top">
                    <tr>
                        <th style="width: 45px;" class="text-center">STT</th>
                        <th style="min-width: 250px;">Sản phẩm trong kho <span class="text-danger">*</span></th>
                        <th style="min-width: 150px;">Hoặc loại mới</th>
                        <th style="min-width: 250px;">Tên hàng trên hóa đơn <span class="text-danger">*</span></th>
                        <th style="width: 80px;">ĐVT</th>
                        <th style="width: 90px;">SL <span class="text-danger">*</span></th>
                        <th style="width: 120px;">Đơn giá nhập <span class="text-danger">*</span></th>
                        <th style="width: 130px;">Thành tiền</th>
                        <th style="width: 50px;" class="text-center">Xóa</th>
                    </tr>
                </thead>
                <tbody id="items_body">
                    {{-- Dữ liệu hiển thị (khi sửa hoặc nạp từ PDF/thủ công) --}}
                    @php($itemsList = $hoaDon->exists ? $hoaDon->chiTiets : [null])
                    @foreach($itemsList as $idx => $item)
                        <tr class="item-row" data-index="{{ $idx }}">
                            <td class="text-center row-stt fw-bold text-muted">{{ $idx + 1 }}</td>
                            <td>
                                <select class="form-select form-select-sm select-product" name="items[{{ $idx }}][product_id]">
                                    <option value="">-- Chọn sản phẩm có sẵn --</option>
                                    @foreach($sanPhams as $sp)
                                        <option value="{{ $sp->id }}" data-unit="{{ $sp->don_vi }}" data-price="{{ (int)$sp->gia_nhap }}" @selected(optional($item)->product_id == $sp->id)>
                                            {{ $sp->sku }} - {{ Str::limit($sp->ten_sanpham, 50) }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select class="form-select form-select-sm select-category" name="items[{{ $idx }}][category_id]">
                                    <option value="">-- Chọn loại (nếu tạo mới) --</option>
                                    @foreach($loais as $loai)
                                        <option value="{{ $loai->id }}" @selected(optional($item)->category_id == $loai->id)>
                                            {{ $loai->ten_loai }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input class="form-control form-control-sm input-tenhang" name="items[{{ $idx }}][ten_hang]" value="{{ optional($item)->ten_hang }}" required placeholder="Tên hàng...">
                            </td>
                            <td>
                                <input class="form-control form-control-sm input-donvi text-center" name="items[{{ $idx }}][don_vi]" value="{{ optional($item)->don_vi ?: 'Cái' }}" required>
                            </td>
                            <td>
                                <input class="form-control form-control-sm input-soluong text-end" type="number" min="1" name="items[{{ $idx }}][soluong]" value="{{ optional($item)->soluong ?: 1 }}" required>
                            </td>
                            <td>
                                <input class="form-control form-control-sm input-dongia text-end" type="number" min="0" name="items[{{ $idx }}][don_gia]" value="{{ optional($item)->don_gia ?: 0 }}" required>
                            </td>
                            <td class="text-end fw-bold row-thanhtien text-primary">
                                {{ number_format((optional($item)->soluong ?: 1) * (optional($item)->don_gia ?: 0), 0, ',', '.') }} đ
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 btn-remove-row" title="Xóa dòng">
                                    <i class="fa fa-times"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-light">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <small class="text-muted">
                        <i class="fa fa-info-circle me-1"></i> Các dòng có viền vàng là mặt hàng chưa khớp được sản phẩm trong kho. Bạn có thể chọn sản phẩm có sẵn hoặc chọn Loại sản phẩm để hệ thống tự tạo mã mới khi xác nhận nhập kho.
                    </small>
                </div>
                <div class="col-md-6 text-end">
                    <div class="d-inline-block text-start me-4">
                        <span class="text-muted d-block small">Tổng tiền hàng:</span>
                        <strong id="display_tien_hang" class="fs-6">0 đ</strong>
                    </div>
                    <div class="d-inline-block text-start me-4">
                        <span class="text-muted d-block small">Tiền thuế GTGT:</span>
                        <strong id="display_tien_thue" class="fs-6 text-secondary">0 đ</strong>
                    </div>
                    <div class="d-inline-block text-start">
                        <span class="text-muted d-block small">Tổng thanh toán:</span>
                        <strong id="display_tong_thanh_toan" class="fs-5 text-success">0 đ</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <button type="submit" class="btn btn-primary px-4 py-2">
            <i class="fa fa-save me-1"></i> {{ $hoaDon->exists ? 'Cập nhật hóa đơn' : 'Lưu nháp hóa đơn nhập' }}
        </button>
        <span class="text-muted small">Sau khi lưu nháp, bạn vẫn có thể kiểm tra lại trước khi nhấn Xác nhận nhập kho chính thức.</span>
    </div>
</form>

{{-- Template ẩn cho việc thêm dòng mới --}}
<template id="row_template">
    <tr class="item-row" data-index="__INDEX__">
        <td class="text-center row-stt fw-bold text-muted">__STT__</td>
        <td>
            <select class="form-select form-select-sm select-product" name="items[__INDEX__][product_id]">
                <option value="">-- Chọn sản phẩm có sẵn --</option>
                @foreach($sanPhams as $sp)
                    <option value="{{ $sp->id }}" data-unit="{{ $sp->don_vi }}" data-price="{{ (int)$sp->gia_nhap }}">
                        {{ $sp->sku }} - {{ Str::limit($sp->ten_sanpham, 50) }}
                    </option>
                @endforeach
            </select>
        </td>
        <td>
            <select class="form-select form-select-sm select-category" name="items[__INDEX__][category_id]">
                <option value="">-- Chọn loại (nếu tạo mới) --</option>
                @foreach($loais as $loai)
                    <option value="{{ $loai->id }}">{{ $loai->ten_loai }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <input class="form-control form-control-sm input-tenhang" name="items[__INDEX__][ten_hang]" required placeholder="Tên hàng...">
        </td>
        <td>
            <input class="form-control form-control-sm input-donvi text-center" name="items[__INDEX__][don_vi]" value="Cái" required>
        </td>
        <td>
            <input class="form-control form-control-sm input-soluong text-end" type="number" min="1" name="items[__INDEX__][soluong]" value="1" required>
        </td>
        <td>
            <input class="form-control form-control-sm input-dongia text-end" type="number" min="0" name="items[__INDEX__][don_gia]" value="0" required>
        </td>
        <td class="text-end fw-bold row-thanhtien text-primary">0 đ</td>
        <td class="text-center">
            <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 btn-remove-row" title="Xóa dòng">
                <i class="fa fa-times"></i>
            </button>
        </td>
    </tr>
</template>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tableBody = document.getElementById('items_body');
    const rowTemplate = document.getElementById('row_template').innerHTML;
    const btnAddRow = document.getElementById('btn_add_row');
    const badgeTotalItems = document.getElementById('badge_total_items');
    const selectTax = document.getElementById('thue_suat');
    const displayTienHang = document.getElementById('display_tien_hang');
    const displayTienThue = document.getElementById('display_tien_thue');
    const displayTongThanhToan = document.getElementById('display_tong_thanh_toan');

    let rowIndexCounter = document.querySelectorAll('.item-row').length;

    // Định dạng tiền tệ VNĐ
    function formatMoney(amount) {
        return new Intl.NumberFormat('vi-VN').format(Math.round(amount)) + ' đ';
    }

    // Cập nhật lại số thứ tự STT
    function updateStt() {
        const rows = document.querySelectorAll('.item-row');
        rows.forEach((row, idx) => {
            const sttCell = row.querySelector('.row-stt');
            if (sttCell) sttCell.textContent = idx + 1;
        });
        badgeTotalItems.textContent = rows.length + ' mặt hàng';
    }

    // Tính toán tổng tiền
    function calculateTotals() {
        let totalTienHang = 0;
        const rows = document.querySelectorAll('.item-row');

        rows.forEach(row => {
            const sl = parseFloat(row.querySelector('.input-soluong')?.value) || 0;
            const gia = parseFloat(row.querySelector('.input-dongia')?.value) || 0;
            const thanhTien = Math.round(sl * gia);
            const thanhTienEl = row.querySelector('.row-thanhtien');
            if (thanhTienEl) {
                thanhTienEl.textContent = formatMoney(thanhTien);
            }
            totalTienHang += thanhTien;
        });

        const taxRate = parseFloat(selectTax.value) || 0;
        const tienThue = Math.round(totalTienHang * (taxRate / 100));
        const tongThanhToan = totalTienHang + tienThue;

        displayTienHang.textContent = formatMoney(totalTienHang);
        displayTienThue.textContent = formatMoney(tienThue);
        displayTongThanhToan.textContent = formatMoney(tongThanhToan);
    }

    // Thêm dòng mới
    btnAddRow.addEventListener('click', function () {
        const newHtml = rowTemplate
            .replace(/__INDEX__/g, rowIndexCounter)
            .replace(/__STT__/g, document.querySelectorAll('.item-row').length + 1);

        tableBody.insertAdjacentHTML('beforeend', newHtml);
        rowIndexCounter++;
        updateStt();
        calculateTotals();
    });

    // Xóa dòng hoặc thay đổi giá trị
    tableBody.addEventListener('click', function (e) {
        if (e.target.closest('.btn-remove-row')) {
            const row = e.target.closest('.item-row');
            if (document.querySelectorAll('.item-row').length > 1) {
                row.remove();
                updateStt();
                calculateTotals();
            } else {
                alert('Hóa đơn cần ít nhất 1 dòng sản phẩm.');
            }
        }
    });

    tableBody.addEventListener('input', function (e) {
        if (e.target.classList.contains('input-soluong') || e.target.classList.contains('input-dongia')) {
            calculateTotals();
        }
    });

    // Khi chọn sản phẩm có sẵn, tự động điền ĐVT và tên hàng nếu chưa có
    tableBody.addEventListener('change', function (e) {
        if (e.target.classList.contains('select-product')) {
            const select = e.target;
            const row = select.closest('.item-row');
            const selectedOpt = select.options[select.selectedIndex];
            if (select.value && selectedOpt) {
                const unit = selectedOpt.getAttribute('data-unit');
                const price = selectedOpt.getAttribute('data-price');
                const tenHangInput = row.querySelector('.input-tenhang');
                const donViInput = row.querySelector('.input-donvi');
                const donGiaInput = row.querySelector('.input-dongia');

                if (unit && donViInput) donViInput.value = unit;
                if (price && donGiaInput && (!donGiaInput.value || donGiaInput.value === '0')) {
                    donGiaInput.value = price;
                }
                if (!tenHangInput.value) {
                    tenHangInput.value = selectedOpt.textContent.trim().split(' - ')[1] || selectedOpt.textContent.trim();
                }
                row.classList.remove('table-warning');
            }
            calculateTotals();
        }
    });

    selectTax.addEventListener('change', calculateTotals);

    // Xử lý nút bóc tách PDF
    const btnParsePdf = document.getElementById('btn_parse_pdf');
    const pdfInput = document.getElementById('pdf_input');
    const parseSpinner = document.getElementById('parse_spinner');
    const parseStatusText = document.getElementById('parse_status_text');
    const parseAlert = document.getElementById('parse_alert');

    btnParsePdf.addEventListener('click', function () {
        if (!pdfInput.files || pdfInput.files.length === 0) {
            alert('Vui lòng chọn một file PDF hóa đơn trước khi bấm bóc tách.');
            return;
        }

        const file = pdfInput.files[0];
        const formData = new FormData();
        formData.append('file_pdf', file);
        formData.append('_token', '{{ csrf_token() }}');

        btnParsePdf.disabled = true;
        parseSpinner.classList.remove('d-none');
        parseStatusText.textContent = 'Đang phân tích PDF trực tiếp trên máy chủ...';
        parseAlert.classList.add('d-none');

        // Đồng bộ file sang input file_goc của form chính để khi lưu hóa đơn sẽ đính kèm luôn
        const fileGocInput = document.getElementById('file_goc');
        if (fileGocInput && window.DataTransfer) {
            const dt = new DataTransfer();
            dt.items.add(file);
            fileGocInput.files = dt.files;
        }

        fetch('{{ route("hoadonnhap.boctachpdf") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => { throw new Error(err.message || 'Lỗi bóc tách file PDF'); });
            }
            return response.json();
        })
        .then(data => {
            btnParsePdf.disabled = false;
            parseSpinner.classList.add('d-none');
            parseStatusText.textContent = '';

            if (!data.success) {
                parseAlert.className = 'alert alert-danger mt-3 mb-0 py-2 small';
                parseAlert.textContent = data.message || 'Không thể bóc tách file PDF này.';
                parseAlert.classList.remove('d-none');
                return;
            }

            // 1. Điền thông tin header
            const h = data.header;
            if (h.so_hoadon) document.getElementById('so_hoadon').value = h.so_hoadon;
            if (h.ky_hieu) document.getElementById('ky_hieu').value = h.ky_hieu;
            if (h.mau_so) document.getElementById('mau_so').value = h.mau_so;
            if (h.ngay_hoadon) document.getElementById('ngay_hoadon').value = h.ngay_hoadon;
            if (h.thue_suat !== null && h.thue_suat !== undefined) {
                const taxVal = Math.round(h.thue_suat);
                const taxOpt = selectTax.querySelector(`option[value="${taxVal}"]`);
                if (taxOpt) selectTax.value = taxVal;
            }
            if (h.hinh_thuc_thanh_toan) {
                const paySel = document.getElementById('hinh_thuc_thanh_toan');
                const rawPay = h.hinh_thuc_thanh_toan.toLowerCase();
                if (rawPay.includes('chuyển khoản')) paySel.value = 'chuyenkhoan';
                else if (rawPay.includes('tiền mặt')) paySel.value = 'tienmat';
                else if (rawPay.includes('công nợ')) paySel.value = 'congno';
            }

            // Chọn nhà cung cấp tự động theo supplier_id hoặc MST
            if (h.supplier_id) {
                document.getElementById('supplier_id').value = h.supplier_id;
            } else if (h.ma_so_thue) {
                const matchedOption = Array.from(document.getElementById('supplier_id').options)
                    .find(opt => (opt.getAttribute('data-mst') || '').trim() === h.ma_so_thue.trim());
                if (matchedOption) document.getElementById('supplier_id').value = matchedOption.value;
            }

            // 2. Điền danh sách sản phẩm
            if (data.items && data.items.length > 0) {
                tableBody.innerHTML = '';
                rowIndexCounter = 0;

                data.items.forEach((item, index) => {
                    const rowHtml = rowTemplate
                        .replace(/__INDEX__/g, index)
                        .replace(/__STT__/g, item.stt || index + 1);

                    tableBody.insertAdjacentHTML('beforeend', rowHtml);
                    const newRow = tableBody.lastElementChild;

                    // Gán các giá trị
                    const selectProd = newRow.querySelector('.select-product');
                    const inputTenHang = newRow.querySelector('.input-tenhang');
                    const inputDonVi = newRow.querySelector('.input-donvi');
                    const inputSoLuong = newRow.querySelector('.input-soluong');
                    const inputDonGia = newRow.querySelector('.input-dongia');

                    if (inputTenHang) inputTenHang.value = item.ten_hang;
                    if (inputDonVi) inputDonVi.value = item.don_vi || 'Cái';
                    if (inputSoLuong) inputSoLuong.value = item.soluong || 1;
                    if (inputDonGia) inputDonGia.value = item.don_gia || 0;

                    if (item.product_id && selectProd) {
                        selectProd.value = item.product_id;
                        newRow.classList.remove('table-warning');
                    } else {
                        // Highlight cảnh báo vàng cho các dòng chưa khớp
                        newRow.classList.add('table-warning');
                    }

                    rowIndexCounter++;
                });

                updateStt();
                calculateTotals();
            }

            // 3. Hiển thị thông báo kết quả
            parseAlert.className = 'alert alert-success mt-3 mb-0 py-2 small';
            let alertMsg = `✓ Bóc tách thành công ${data.summary.total_items} mặt hàng (Tổng SL: ${data.summary.total_quantity}). `;
            if (data.warnings && data.warnings.length > 0) {
                parseAlert.className = 'alert alert-warning mt-3 mb-0 py-2 small';
                alertMsg += '<br><strong>Lưu ý:</strong> ' + data.warnings.join(' | ');
            }
            parseAlert.innerHTML = alertMsg;
            parseAlert.classList.remove('d-none');
        })
        .catch(err => {
            btnParsePdf.disabled = false;
            parseSpinner.classList.add('d-none');
            parseStatusText.textContent = '';
            parseAlert.className = 'alert alert-danger mt-3 mb-0 py-2 small';
            parseAlert.textContent = 'Lỗi bóc tách: ' + err.message;
            parseAlert.classList.remove('d-none');
        });
    });

    // Tính toán ban đầu khi mở trang
    calculateTotals();
    updateStt();
});
</script>
@endpush
