<h1>Báo cáo xuất nhập tồn</h1>
<p>Kỳ: {{ $tuNgay }} đến {{ $denNgay }}</p>
<table border="1" cellpadding="4" cellspacing="0"><thead><tr><th>SKU</th><th>Sản phẩm</th><th>Tồn đầu</th><th>Nhập</th><th>Xuất</th><th>Điều chỉnh</th><th>Tồn cuối</th></tr></thead><tbody>
@foreach ($rows as $row)<tr>@foreach ($row as $value)<td>{{ $value }}</td>@endforeach</tr>@endforeach
</tbody></table>
