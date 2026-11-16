@extends('layouts.master')

@section('title', $baiViet->exists ? 'Sửa bài viết' : 'Thêm bài viết')

@section('content')
<h1 class="h3 mb-3">{{ $baiViet->exists ? 'Sửa bài viết' : 'Thêm bài viết' }}</h1>
<div class="card">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data"
              action="{{ $baiViet->exists ? route('baiviet.postsua', $baiViet) : route('baiviet.postthem') }}">
            @csrf
            @if ($baiViet->exists)
                @method('PUT')
            @endif
            <div class="mb-3">
                <label class="form-label" for="topic_id">Chủ đề</label>
                <select class="form-select" id="topic_id" name="topic_id" required>
                    <option value="">-- Chọn chủ đề --</option>
                    @foreach ($chuDes as $chuDe)
                        <option value="{{ $chuDe->id }}" @selected(old('topic_id', $baiViet->topic_id) == $chuDe->id)>{{ $chuDe->ten_chude }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="tieu_de">Tiêu đề</label>
                <input class="form-control" id="tieu_de" name="tieu_de" type="text" value="{{ old('tieu_de', $baiViet->tieu_de) }}" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="tom_tat">Tóm tắt</label>
                <textarea class="form-control" id="tom_tat" name="tom_tat" rows="3">{{ old('tom_tat', $baiViet->tom_tat) }}</textarea>
            </div>
            <div class="mb-3">
                <label class="form-label" for="noi_dung">Nội dung</label>
                <textarea class="form-control" id="noi_dung" name="noi_dung" rows="12">{{ old('noi_dung', $baiViet->noi_dung) }}</textarea>
                <div class="form-text">HTML được lọc tự động trước khi lưu (loại script, iframe, sự kiện...).</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="hinh_anh">Ảnh đại diện (jpg, jpeg, png, webp — tối đa 2MB)</label>
                <input class="form-control" id="hinh_anh" name="hinh_anh" type="file" accept=".jpg,.jpeg,.png,.webp">
                @if ($baiViet->anh_url)
                    <div class="mt-2 d-flex align-items-center gap-2">
                        <img src="{{ $baiViet->anh_url }}" alt="Ảnh hiện tại" style="max-height:80px;border-radius:6px;">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="xoa_anh" name="xoa_anh" value="1">
                            <label class="form-check-label" for="xoa_anh">Xóa ảnh hiện tại</label>
                        </div>
                    </div>
                @endif
            </div>
            <div class="mb-3 d-flex gap-4">
                <div class="form-check">
                    <input type="hidden" name="kiem_duyet" value="0">
                    <input class="form-check-input" type="checkbox" id="kiem_duyet" name="kiem_duyet" value="1" @checked(old('kiem_duyet', $baiViet->kiem_duyet))>
                    <label class="form-check-label" for="kiem_duyet">Đã duyệt (cho phép hiển thị)</label>
                </div>
                <div class="form-check">
                    <input type="hidden" name="kich_hoat" value="0">
                    <input class="form-check-input" type="checkbox" id="kich_hoat" name="kich_hoat" value="1" @checked(old('kich_hoat', $baiViet->kich_hoat))>
                    <label class="form-check-label" for="kich_hoat">Kích hoạt (đang hiện)</label>
                </div>
            </div>
            <a class="btn btn-secondary" href="{{ route('baiviet.danhsach') }}">Hủy</a>
            <button class="btn btn-primary" type="submit">Lưu</button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('ckeditor5/ckeditor.js') }}"></script>
<script>
    ClassicEditor
        .create(document.querySelector('#noi_dung'))
        .catch(function (error) { console.error(error); });
</script>
@endpush