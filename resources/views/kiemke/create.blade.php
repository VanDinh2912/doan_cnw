@extends('layouts.master')
@section('title', 'Bắt đầu kiểm kê')
@section('content')
<h1 class="h3 mb-3">Bắt đầu kiểm kê kho</h1>
<p>Hệ thống sẽ chụp tồn hiện tại của toàn bộ sản phẩm đang kinh doanh. Bạn vẫn có thể bán hàng trong lúc kiểm kê.</p>
<form method="POST" action="{{ route('kiemke.store') }}">@csrf
<textarea class="form-control" name="ghi_chu" placeholder="Ghi chú"></textarea>
<button class="btn btn-primary mt-3">Bắt đầu</button>
</form>
@endsection
