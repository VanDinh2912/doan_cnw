@extends('layouts.master')

@section('title', $sanPham->exists ? 'Sửa sản phẩm' : 'Thêm sản phẩm')

@section('content')
<h1 class="h3 mb-3">{{ $sanPham->exists ? 'Sửa sản phẩm' : 'Thêm sản phẩm' }}</h1>
<div class="card">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" action="{{ $sanPham->exists ? route('sanpham.postsua', $sanPham) : route('sanpham.postthem') }}" data-can-pin="{{ $sanPham->exists ? 'nghiemtrong' : 'thuong' }}">
            @csrf
            @if ($sanPham->exists) @method('PUT') @endif
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="ten_sanpham">Tên sản phẩm</label>
                    <input class="form-control" id="ten_sanpham" name="ten_sanpham" value="{{ old('ten_sanpham', $sanPham->ten_sanpham) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="sku">SKU</label>
                    <input class="form-control" id="sku" name="sku" value="{{ old('sku', $sanPham->sku) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="barcode">Barcode</label>
                    <input class="form-control" id="barcode" name="barcode" value="{{ old('barcode', $sanPham->barcode) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="category_id">Loại sản phẩm</label>
                    <select class="form-select" id="category_id" name="category_id" required>
                        <option value="">-- Chọn loại --</option>
                        @foreach ($loais as $loai)
                            <option value="{{ $loai->id }}" @selected(old('category_id', $sanPham->category_id) == $loai->id)>{{ $loai->ten_loai }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="brand_id">Hãng sản xuất</label>
                    <select class="form-select" id="brand_id" name="brand_id">
                        <option value="">-- Chọn hãng --</option>
                        @foreach ($hangs as $hang)
                            <option value="{{ $hang->id }}" @selected((int) old('brand_id', $sanPham->brand_id) === $hang->id)>{{ $hang->ten }}@unless ($hang->kich_hoat) (đã tắt)@endunless</option>
                        @endforeach
                    </select>
                    @error('brand_id')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="don_vi">Đơn vị</label>
                    <input class="form-control" id="don_vi" name="don_vi" value="{{ old('don_vi', $sanPham->don_vi ?: 'Cái') }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="hinh_anh">Hình ảnh (tải tệp)</label>
                    <input class="form-control" id="hinh_anh" name="hinh_anh" type="file" accept="image/*">
                    @error('hinh_anh')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="hinh_anh_url">Hoặc dán URL ảnh (https)</label>
                    <input class="form-control" id="hinh_anh_url" name="hinh_anh_url" type="url"
                           value="{{ old('hinh_anh_url', $sanPham->exists && str_starts_with((string) $sanPham->hinh_anh, 'https://') ? $sanPham->hinh_anh : '') }}"
                           placeholder="https://cdn.example.com/anh.jpg">
                    @error('hinh_anh_url')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Gửi cả tệp lẫn URL thì tệp tải lên được ưu tiên. URL chỉ nhận https, tối đa {{ $gioiHanUrlAnh ?? 150 }} ký tự.</div>
                    <div id="trang-thai-url" class="form-text" aria-live="polite"></div>
                    <div id="hop-le-url-canh-bao" class="form-check mt-1 d-none">
                        <input class="form-check-input" id="luu-url-du-canh-bao" type="checkbox" value="1">
                        <label class="form-check-label small" for="luu-url-du-canh-bao">Vẫn lưu liên kết này (máy chủ sẽ kiểm tra lại định dạng)</label>
                    </div>
                </div>
                <div class="col-12 d-flex align-items-center gap-3">
                    <img id="xem-truoc-anh"
                         src="{{ $sanPham->exists ? $sanPham->anh_url : asset('img/no-image.svg') }}"
                         data-mac-dinh="{{ $sanPham->exists ? $sanPham->anh_url : asset('img/no-image.svg') }}"
                         alt="Xem trước ảnh sản phẩm"
                         loading="lazy" referrerpolicy="no-referrer"
                         style="width:72px;height:72px;object-fit:cover;border-radius:8px;border:1px solid #dee2e6;">
                    @if ($sanPham->exists)
                        <div class="form-check">
                            <input class="form-check-input" id="xoa_anh" name="xoa_anh" type="checkbox" value="1">
                            <label class="form-check-label" for="xoa_anh">Xóa ảnh hiện tại</label>
                        </div>
                    @endif
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="gia_nhap">Giá nhập</label>
                    <input class="form-control" id="gia_nhap" name="gia_nhap" type="number" min="0" value="{{ old('gia_nhap', $sanPham->gia_nhap ?: 0) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="gia_ban">Giá bán</label>
                    <input class="form-control" id="gia_ban" name="gia_ban" type="number" min="0" value="{{ old('gia_ban', $sanPham->gia_ban ?: 0) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="soluong">Số lượng</label>
                    <input class="form-control" id="soluong" name="soluong" type="number" min="0" value="{{ old('soluong', $sanPham->soluong ?: 0) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="soluong_toithieu">Tồn tối thiểu</label>
                    <input class="form-control" id="soluong_toithieu" name="soluong_toithieu" type="number" min="0" value="{{ old('soluong_toithieu', $sanPham->soluong_toithieu ?: 5) }}" required>
                </div>
                <div class="col-12 form-check ms-2">
                    <input class="form-check-input" id="trang_thai" name="trang_thai" type="checkbox" value="1" @checked(old('trang_thai', $sanPham->trang_thai))>
                    <label class="form-check-label" for="trang_thai">Đang kinh doanh</label>
                </div>
            </div>
            <div class="mt-4">
                <a class="btn btn-secondary" href="{{ route('sanpham.danhsach') }}">Hủy</a>
                <button class="btn btn-primary" id="nut-luu" type="submit">Lưu</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const tep = document.getElementById('hinh_anh');
    const url = document.getElementById('hinh_anh_url');
    const xoaAnh = document.getElementById('xoa_anh');
    const xemTruoc = document.getElementById('xem-truoc-anh');
    const nutLuu = document.getElementById('nut-luu');
    const trangThai = document.getElementById('trang-thai-url');
    const hopLeUrlCanhBao = document.getElementById('hop-le-url-canh-bao');
    const luuUrlDuCanhBao = document.getElementById('luu-url-du-canh-bao');
    if (!xemTruoc || !url || !nutLuu || !trangThai) return;

    const anhMacDinh = xemTruoc.dataset.macDinh;
    const BAO_LOI = 'Liên kết này không phải ảnh hoặc không tải được. Hãy chọn \'Sao chép địa chỉ hình ảnh\' rồi dán lại.';
    const CHO_KIEM_TRA = 500;
    const TIMEOUT_ANH = 8000;

    let henGio = null;
    let henTimeout = null;
    let anhDangKiemTra = null;
    let giaTriDaKiemTra = null;
    let giaTriCanhBaoGanNhat = null;
    let muc = 'trong';

    function capNhatNutLuu() {
        const coTep = !!(tep && tep.files && tep.files.length > 0);
        const coUrl = url.value.trim() !== '';
        const boQuaCanhBao = !!(luuUrlDuCanhBao && luuUrlDuCanhBao.checked);
        nutLuu.disabled = coUrl && !coTep && !(xoaAnh && xoaAnh.checked)
            && (muc === 'dang_kiem_tra' || (muc === 'hong' && !boQuaCanhBao));
    }

    function datMuc(moi, loiNhan, lop) {
        muc = moi;
        trangThai.textContent = loiNhan;
        trangThai.className = lop;
        if (hopLeUrlCanhBao && luuUrlDuCanhBao) {
            const hienCanhBao = moi === 'hong';
            hopLeUrlCanhBao.classList.toggle('d-none', !hienCanhBao);
            if (hienCanhBao) {
                // URL mới bị cảnh báo thì phải tích lại lựa chọn "Vẫn lưu".
                const giaTriHienTai = url.value.trim();
                if (giaTriHienTai !== giaTriCanhBaoGanNhat) {
                    luuUrlDuCanhBao.checked = false;
                    giaTriCanhBaoGanNhat = giaTriHienTai;
                }
            }
        }
        capNhatNutLuu();
    }

    function huyKiemTra() {
        if (henTimeout !== null) { clearTimeout(henTimeout); henTimeout = null; }
        if (anhDangKiemTra !== null) {
            anhDangKiemTra.onload = null;
            anhDangKiemTra.onerror = null;
            anhDangKiemTra = null;
        }
    }

    function capNhatXemTruoc() {
        if (xoaAnh && xoaAnh.checked) { xemTruoc.src = anhMacDinh; return; }
        if (tep && tep.files && tep.files[0]) { xemTruoc.src = URL.createObjectURL(tep.files[0]); return; }
        const giaTri = url.value.trim();
        xemTruoc.src = giaTri.startsWith('https://') ? giaTri : anhMacDinh;
    }

    function ketThucKiemTra(anh, thanhCong) {
        if (anhDangKiemTra !== anh) return;
        huyKiemTra();
        if (thanhCong) {
            datMuc('hop_le', 'Liên kết ảnh tải được.', 'form-text text-success');
            capNhatXemTruoc();
        } else {
            datMuc('hong', BAO_LOI, 'form-text text-danger');
            if (!(xoaAnh && xoaAnh.checked) && !(tep && tep.files && tep.files[0])) {
                xemTruoc.src = anhMacDinh;
            }
        }
    }

    function kiemTraUrl() {
        if (henGio !== null) { clearTimeout(henGio); henGio = null; }
        huyKiemTra();
        const giaTri = url.value.trim();
        giaTriDaKiemTra = giaTri;
        if (giaTri === '') {
            datMuc('trong', '', 'form-text');
            capNhatXemTruoc();
            return;
        }
        if (!giaTri.startsWith('https://')) {
            datMuc('hong', BAO_LOI, 'form-text text-danger');
            capNhatXemTruoc();
            return;
        }
        datMuc('dang_kiem_tra', 'Đang kiểm tra liên kết ảnh…', 'form-text');
        const anh = new Image();
        anhDangKiemTra = anh;
        anh.referrerPolicy = 'no-referrer';
        anh.onload = function () { ketThucKiemTra(anh, true); };
        anh.onerror = function () { ketThucKiemTra(anh, false); };
        henTimeout = setTimeout(function () { ketThucKiemTra(anh, false); }, TIMEOUT_ANH);
        anh.src = giaTri;
    }

    url.addEventListener('input', function () {
        capNhatXemTruoc();
        if (henGio !== null) clearTimeout(henGio);
        if (url.value.trim() === '') {
            huyKiemTra();
            giaTriDaKiemTra = '';
            datMuc('trong', '', 'form-text');
            return;
        }
        datMuc('dang_kiem_tra', 'Đang kiểm tra liên kết ảnh…', 'form-text');
        henGio = setTimeout(kiemTraUrl, CHO_KIEM_TRA);
    });
    url.addEventListener('blur', function () {
        const giaTri = url.value.trim();
        if (giaTri === giaTriDaKiemTra && (muc === 'hop_le' || muc === 'hong')) return;
        kiemTraUrl();
    });
    if (tep) tep.addEventListener('change', function () { capNhatXemTruoc(); capNhatNutLuu(); });
    if (xoaAnh) xoaAnh.addEventListener('change', function () { capNhatXemTruoc(); capNhatNutLuu(); });
    if (luuUrlDuCanhBao) luuUrlDuCanhBao.addEventListener('change', capNhatNutLuu);
    capNhatNutLuu();
})();
</script>
@endpush
