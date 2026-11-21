<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>PIN tạm — {{ $user->email }}</title>
    <style>
        body { font-family: system-ui, sans-serif; background:#f6f7f9; display:flex; min-height:100vh; align-items:center; justify-content:center; margin:0; }
        .card { background:#fff; border:1px solid #ddd; border-radius:12px; padding:2rem; max-width:480px; width:90%; }
        .pin { font-size:2rem; letter-spacing:.4rem; font-weight:700; text-align:center; background:#f0f4ff; border-radius:8px; padding:.8rem; margin:1rem 0; user-select:all; }
        .warn { color:#8a5a00; background:#fff8e6; border:1px solid #f0d48a; border-radius:8px; padding:.6rem .8rem; font-size:.9rem; }
    </style>
</head>
<body>
    <div class="card">
        <h2 style="margin-top:0">PIN tạm đã được tạo</h2>
        <p>Tài khoản <strong>{{ $user->email }}</strong> ({{ $user->role }}) được cấp PIN tạm:</p>
        <div class="pin">{{ $pinTam }}</div>
        <div class="warn">
            <strong>Chỉ hiển thị MỘT LẦN.</strong> Tải lại trang sẽ tạo PIN mới và PIN này mất hiệu lực.
            Nhân viên bắt buộc đổi PIN ở lần sử dụng đầu tiên tại <code>/doi-pin</code>.
        </div>
        <p style="margin-top:1rem"><a href="{{ $urlLai ?? route('taikhoan.danhsach') }}">{{ $nhanLai ?? 'Quay lại danh sách tài khoản' }}</a></p>
    </div>
</body>
</html>
