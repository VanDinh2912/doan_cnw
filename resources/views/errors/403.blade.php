<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 - Không có quyền</title>
</head>
<body style="font-family: Arial, sans-serif; margin: 4rem auto; max-width: 640px; padding: 1rem; text-align: center;">
    <h1>403</h1>
    <h2>Không có quyền truy cập</h2>
    <p>{{ $exception->getMessage() ?: 'Bạn không được phép truy cập trang này.' }}</p>
    <a href="{{ url()->previous() }}">Quay lại</a>
</body>
</html>
