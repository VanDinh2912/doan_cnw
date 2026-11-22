@extends('layouts.master')
@section('title', 'Yêu cầu duyệt')
@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Yêu cầu duyệt thao tác</h4>
    <button class="btn btn-sm btn-outline-secondary" onclick="location.reload()"><i class="fa-solid fa-rotate"></i> Làm mới</button>
</div>

<div class="card mb-3">
    <div class="card-body py-2 small text-muted">
        Nhân viên gửi yêu cầu cho thao tác mức M2 (tiền/mất dữ liệu). Kiểm tra kỹ <strong>người yêu cầu, thao tác, đối tượng và tham số ràng buộc</strong> trước khi cấp mã. Mã hiện đúng một lần — đọc/ghi lại cho nhân viên.
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th>#</th><th>Nhân viên</th><th>Thao tác</th><th>Đối tượng</th>
                <th>Tham số ràng buộc</th><th>Trạng thái</th><th>Thời gian</th><th></th>
            </tr></thead>
            <tbody>
            @forelse ($ds as $yc)
                <tr>
                    <td>{{ $yc->id }}</td>
                    <td>
                        <div class="fw-semibold">{{ $yc->nhanVien->name ?? '#'.$yc->nhan_vien_id }}</div>
                        <div class="small text-muted">{{ $yc->nhanVien->email ?? '' }}</div>
                    </td>
                    <td>{{ $yc->mo_ta_thao_tac }}</td>
                    <td class="small">
                        @if ($yc->doi_tuong_loai) {{ $yc->doi_tuong_loai }} #{{ $yc->doi_tuong_id }} @else — @endif
                    </td>
                    <td class="small">
                        @if ($yc->tham_so_duyet)
                            @foreach ($yc->tham_so_duyet as $k => $v)
                                <div>{{ $k }}: <code>{{ $v }}</code></div>
                            @endforeach
                        @else — @endif
                    </td>
                    <td>
                        @switch($yc->trang_thai)
                            @case('cho_cap')<span class="badge bg-warning text-dark">Chờ cấp</span>@break
                            @case('da_cap')<span class="badge bg-info text-dark">Đã cấp</span>@break
                            @case('da_dung')<span class="badge bg-success">Đã dùng</span>@break
                            @case('het_han')<span class="badge bg-secondary">Hết hạn</span>@break
                            @case('tu_choi')<span class="badge bg-danger">Từ chối</span>@break
                        @endswitch
                        @if ($yc->trang_thai === 'tu_choi' && $yc->ly_do_tu_choi)
                            <div class="small text-muted">{{ $yc->ly_do_tu_choi }}</div>
                        @endif
                    </td>
                    <td class="small">{{ $yc->created_at?->format('d/m H:i') }}</td>
                    <td class="text-nowrap">
                        @if ($yc->trang_thai === 'cho_cap')
                            <button class="btn btn-sm btn-primary js-cap-ma" data-id="{{ $yc->id }}">Cấp mã</button>
                            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#mdTuChoi{{ $yc->id }}">Từ chối</button>
                        @endif
                    </td>
                </tr>
                @if ($yc->trang_thai === 'cho_cap')
                <div class="modal fade" id="mdTuChoi{{ $yc->id }}" tabindex="-1">
                    <div class="modal-dialog"><div class="modal-content">
                        <form method="POST" action="{{ route('duyet.tuchoi', $yc) }}" data-can-pin="a">
                            @csrf
                            <div class="modal-header"><h5 class="modal-title">Từ chối yêu cầu #{{ $yc->id }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <div class="modal-body">
                                <p class="small text-muted">{{ $yc->mo_ta_thao_tac }} — {{ $yc->nhanVien->name ?? '' }}</p>
                                <label class="form-label small">Lý do từ chối</label>
                                <input type="text" name="ly_do" class="form-control" maxlength="255" required>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                <button class="btn btn-danger">Từ chối</button>
                            </div>
                        </form>
                    </div></div>
                </div>
                @endif
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">Không có yêu cầu nào.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($ds->hasPages())<div class="card-footer">{{ $ds->links() }}</div>@endif
</div>

{{-- Modal hiện mã một lần --}}
<div class="modal fade" id="mdMa" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content text-center">
        <div class="modal-header"><h5 class="modal-title">Mã duyệt (chỉ hiện một lần)</h5></div>
        <div class="modal-body">
            <div id="mdMaSo" style="font-size:2.6rem;font-weight:700;letter-spacing:.4rem;font-family:monospace;">&mdash;&mdash;&mdash;&mdash;</div>
            <div class="small text-muted mt-2">Hết hạn: <span id="mdMaHan"></span> — đọc/ghi lại cho nhân viên.</div>
            <button class="btn btn-outline-secondary btn-sm mt-3" id="mdMaCopy"><i class="fa-regular fa-copy"></i> Sao chép</button>
        </div>
        <div class="modal-footer"><button class="btn btn-primary" data-bs-dismiss="modal">Đã chuyển mã</button></div>
    </div></div>
</div>

<div class="modal fade" id="mdMaErr" tabindex="-1"><div class="modal-dialog modal-sm"><div class="modal-content">
    <div class="modal-body text-danger" id="mdMaErrText"></div>
    <div class="modal-footer"><button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Đóng</button></div>
</div></div></div>

@push('scripts')
<script>
document.querySelectorAll('.js-cap-ma').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-id');
        btn.disabled = true;
        fetch('/yeu-cau-duyet/' + id + '/cap-ma', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: '{}'
        }).then(function (r) { return r.json().then(function (j) { return { s: r.status, j: j }; }); })
          .then(function (res) {
              btn.disabled = false;
              if (res.j && res.j.ma === 'OK' && res.j.ma_hien) {
                  document.getElementById('mdMaSo').textContent = res.j.ma_hien;
                  document.getElementById('mdMaHan').textContent = res.j.het_han_luc || '';
                  document.getElementById('mdMaCopy').onclick = function () {
                      navigator.clipboard && navigator.clipboard.writeText(res.j.ma_hien);
                  };
                  new bootstrap.Modal(document.getElementById('mdMa')).show();
                  setTimeout(function(){ location.reload(); }, 8000);
              } else {
                  document.getElementById('mdMaErrText').textContent = (res.j && res.j.message) || 'Không cấp được mã.';
                  new bootstrap.Modal(document.getElementById('mdMaErr')).show();
              }
          })
          .catch(function () { btn.disabled = false; });
    });
});
// Polling badge nhẹ
setInterval(function () {
    fetch('/yeu-cau-duyet/so-luong', { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
            document.querySelectorAll('.js-badge-duyet').forEach(function (b) {
                if (d && d.so_luong > 0) { b.style.display = ''; b.textContent = d.so_luong; }
                else { b.style.display = 'none'; }
            });
        }).catch(function () {});
}, 30000);
</script>
@endpush
@endsection
